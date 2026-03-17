<?php

namespace App\Http\Controllers;

use App\Ai\Agents\DocumentAssistant;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $message = $request->input('message');

        // 1. Vector similarity search
        $relevantChunks = DocumentChunk::query()
            ->whereVectorSimilarTo('embedding', $message, 0.3)
            ->limit(5)
            ->get();

        // 2. Search by document name — find docs whose title matches words in the query
        $nameMatchedChunks = collect();
        $matchedDocs = Document::where('status', 'ready')
            ->where(function ($query) use ($message) {
                // Split message into words and search for meaningful ones (>= 3 chars)
                $words = array_filter(
                    preg_split('/[\s,.\-\/]+/', $message),
                    fn ($w) => mb_strlen($w) >= 3
                );
                foreach ($words as $word) {
                    $query->orWhere('title', 'ILIKE', '%' . $word . '%')
                          ->orWhere('original_filename', 'ILIKE', '%' . $word . '%');
                }
            })
            ->pluck('id');

        if ($matchedDocs->isNotEmpty()) {
            $existingIds = $relevantChunks->pluck('id');
            $nameMatchedChunks = DocumentChunk::whereIn('document_id', $matchedDocs)
                ->whereNotIn('id', $existingIds)
                ->orderBy('chunk_index')
                ->limit(3)
                ->get();
        }

        // 3. Merge results, vector matches first
        $allChunks = $relevantChunks->concat($nameMatchedChunks)->unique('id');

        Log::info('Chat request', [
            'message' => $message,
            'vector_chunks' => $relevantChunks->count(),
            'name_matched_chunks' => $nameMatchedChunks->count(),
            'total_chunks' => $allChunks->count(),
        ]);

        // Costruisci il contesto dai chunks trovati
        $sourceDocuments = $allChunks->map(function ($chunk) {
            return $chunk->document;
        })->unique('id')->values();

        $context = $allChunks->map(function ($chunk) {
            $docTitle = $chunk->document->title ?? 'Documento sconosciuto';
            return "--- Da: {$docTitle} ---\n{$chunk->content}";
        })->join("\n\n");

        Log::info('Context built', ['context_length' => strlen($context)]);

        // Crea l'agent con il contesto
        $agent = (new DocumentAssistant())->withContext($context);

        return new StreamedResponse(function () use ($agent, $message, $sourceDocuments) {
            set_time_limit(300);
            while (ob_get_level()) {
                ob_end_flush();
            }

            // Send source documents metadata first
            echo "data: " . json_encode([
                'sources' => $sourceDocuments->map(fn ($doc) => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'original_filename' => $doc->original_filename,
                ])->toArray()
            ]) . "\n\n";
            flush();

            try {
                $stream = $agent->stream($message);

                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        echo "data: " . json_encode(['text' => $event->delta]) . "\n\n";
                        flush();
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Chat streaming error', [
                    'error' => $e->getMessage(),
                    'message' => $message,
                ]);

                echo "data: " . json_encode(['error' => 'Si è verificato un errore nella generazione della risposta. Verifica che Ollama sia in esecuzione.']) . "\n\n";
                flush();
            }

            echo "data: [DONE]\n\n";
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
