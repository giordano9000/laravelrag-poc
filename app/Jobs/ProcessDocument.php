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

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public Document $document,
    ) {}

    public function handle(DocumentProcessor $processor, TextChunker $chunker): void
    {
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

            // 3. Generate embeddings for all chunks
            $texts = array_column($chunks, 'content');
            $embeddingsResponse = Embeddings::for($texts)->generate();

            // 4. Save chunks with embeddings
            foreach ($chunks as $i => $chunk) {
                $this->document->chunks()->create([
                    'content' => $chunk['content'],
                    'chunk_index' => $chunk['index'],
                    'embedding' => $embeddingsResponse->embeddings[$i],
                ]);
            }

            // 5. Mark as ready
            $this->document->update(['status' => 'ready']);

            Log::info("Document processed successfully", [
                'document_id' => $this->document->id,
                'chunks' => count($chunks),
            ]);
        } catch (\Throwable $e) {
            $this->document->update(['status' => 'failed']);
            Log::error("Document processing failed", [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
