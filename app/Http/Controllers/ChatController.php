<?php

namespace App\Http\Controllers;

use App\Ai\Agents\DocumentAssistant;
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

        // Cerca documenti rilevanti PRIMA di chiamare l'LLM
        $relevantChunks = DocumentChunk::query()
            ->whereVectorSimilarTo('embedding', $message, 0.3)
            ->limit(5)
            ->get();

        Log::info('Chat request', [
            'message' => $message,
            'chunks_found' => $relevantChunks->count()
        ]);

        // Costruisci il contesto dai chunks trovati
        $context = $relevantChunks->map(function ($chunk) {
            $docTitle = $chunk->document->title ?? 'Documento sconosciuto';
            return "--- Da: {$docTitle} ---\n{$chunk->content}";
        })->join("\n\n");

        Log::info('Context built', ['context_length' => strlen($context)]);

        // Crea l'agent con il contesto
        $agent = (new DocumentAssistant())->withContext($context);

        return new StreamedResponse(function () use ($agent, $message) {
            set_time_limit(300);
            while (ob_get_level()) {
                ob_end_flush();
            }

            $stream = $agent->stream($message);

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    echo "data: " . json_encode(['text' => $event->delta]) . "\n\n";
                    flush();
                }
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
