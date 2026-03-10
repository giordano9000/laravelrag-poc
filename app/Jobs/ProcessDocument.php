<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentProcessor;
use App\Services\TextChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;
    public int $maxExceptions = 2;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(
        public Document $document,
    ) {}

    public function handle(DocumentProcessor $processor, TextChunker $chunker): void
    {
        // Check document still exists (may have been deleted while queued)
        if (!Document::where('id', $this->document->id)->exists()) {
            Log::warning("Document no longer exists, skipping", ['document_id' => $this->document->id]);
            return;
        }

        $this->document->update(['status' => 'processing']);

        try {
            $filePath = Storage::disk('local')->path($this->document->file_path);

            // 1. Extract text
            $text = $processor->extractText($filePath, $this->document->mime_type);

            if (trim($text) === '') {
                throw new \RuntimeException('No text could be extracted from the document.');
            }

            // Save preview
            $this->document->update([
                'content_preview' => mb_substr($text, 0, 500),
            ]);

            // 2. Chunk text
            $chunks = $chunker->chunk($text);

            // Prefix each chunk with document name for better retrieval
            // and filter out chunks that are only whitespace/punctuation after prefixing
            $docPrefix = "[Documento: {$this->document->title}]\n";
            $filtered = [];
            foreach ($chunks as $chunk) {
                $contentOnly = $chunk['content'];
                $stripped = preg_replace('/[\s\p{P}]+/u', '', $contentOnly);
                if (mb_strlen($stripped) < 30) {
                    continue;
                }
                $chunk['content'] = $docPrefix . $contentOnly;
                $filtered[] = $chunk;
            }
            $chunks = array_values($filtered);

            // 3. Generate embeddings in batches to avoid overloading Ollama
            $batchSize = 20;
            $allEmbeddings = [];

            foreach (array_chunk($chunks, $batchSize) as $batch) {
                $texts = array_column($batch, 'content');
                $embeddingsResponse = Embeddings::for($texts)->generate();
                array_push($allEmbeddings, ...$embeddingsResponse->embeddings);
            }

            // 4. Save chunks with embeddings
            foreach ($chunks as $i => $chunk) {
                $this->document->chunks()->create([
                    'content' => $chunk['content'],
                    'chunk_index' => $chunk['index'],
                    'embedding' => $allEmbeddings[$i],
                ]);
            }

            // 5. Mark as ready
            $this->document->update(['status' => 'ready']);

            Log::info("Document processed successfully", [
                'document_id' => $this->document->id,
                'chunks' => count($chunks),
            ]);
        } catch (\Throwable $e) {
            // Only mark as failed on last attempt
            if ($this->attempts() >= $this->tries) {
                $this->document->update(['status' => 'failed']);
            }
            Log::error("Document processing failed", [
                'document_id' => $this->document->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if (Document::where('id', $this->document->id)->exists()) {
            $this->document->update(['status' => 'failed']);
        }
    }
}
