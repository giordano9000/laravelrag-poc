<?php

namespace App\Services\Sources;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\SourceConnection;
use App\Services\Sources\Contracts\SourceProviderInterface;
use App\Services\Sources\DTOs\DownloadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SourceSyncService
{
    public function ensureValidToken(SourceConnection $connection, SourceProviderInterface $provider): void
    {
        if (!$connection->isTokenExpired()) {
            return;
        }

        try {
            $tokens = $provider->refreshToken();

            $connection->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
                'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'status' => 'connected',
            ]);
        } catch (\Throwable $e) {
            $connection->update(['status' => 'expired']);
            throw $e;
        }
    }

    /**
     * Import specific files from a source connection.
     *
     * @param SourceConnection $connection
     * @param string[] $fileIds
     * @return Document[]
     */
    public function importFiles(SourceConnection $connection, array $fileIds): array
    {
        $provider = SourceProviderFactory::make($connection);
        $this->ensureValidToken($connection, $provider);

        // Reload connection after potential token refresh
        $connection->refresh();

        $imported = [];

        foreach ($fileIds as $fileId) {
            try {
                $document = $this->importSingleFile($connection, $provider, $fileId);
                if ($document) {
                    $imported[] = $document;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to import file from source", [
                    'connection_id' => $connection->id,
                    'file_id' => $fileId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $connection->update(['last_synced_at' => now()]);

        return $imported;
    }

    /**
     * Sync all previously imported files from a connection (only updates modified files).
     */
    public function syncConnection(SourceConnection $connection): array
    {
        $provider = SourceProviderFactory::make($connection);
        $this->ensureValidToken($connection, $provider);
        $connection->refresh();

        $documents = Document::where('source_connection_id', $connection->id)->get();
        $updated = [];

        foreach ($documents as $document) {
            try {
                if (!$document->source_file_id) {
                    continue;
                }

                $metadata = $provider->getFileMetadata($document->source_file_id);

                // Skip if not modified
                if ($document->source_modified_at && $metadata->modifiedAt) {
                    if ($document->source_modified_at->toIso8601String() === $metadata->modifiedAt) {
                        continue;
                    }
                }

                // Re-download and re-process
                $downloaded = $provider->downloadFile($document->source_file_id);

                // Check if content actually changed
                if ($document->content_hash && $document->content_hash === $downloaded->contentHash) {
                    $this->cleanupDownload($downloaded);
                    continue;
                }

                // Move file to documents storage
                $storedPath = $this->moveToDocuments($downloaded);

                // Delete old file
                if ($document->file_path) {
                    Storage::disk('local')->delete($document->file_path);
                }

                // Delete old chunks
                $document->chunks()->delete();

                // Update document
                $document->update([
                    'file_path' => $storedPath,
                    'file_size' => $downloaded->size,
                    'content_hash' => $downloaded->contentHash,
                    'source_modified_at' => $metadata->modifiedAt,
                    'status' => 'pending',
                ]);

                ProcessDocument::dispatch($document);
                $updated[] = $document;

                $this->cleanupDownload($downloaded);
            } catch (\Throwable $e) {
                Log::error("Failed to sync document", [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $connection->update(['last_synced_at' => now()]);

        return $updated;
    }

    /**
     * Full sync: updates existing files AND imports new files from root folder.
     */
    public function fullSyncConnection(SourceConnection $connection): array
    {
        $provider = SourceProviderFactory::make($connection);
        $this->ensureValidToken($connection, $provider);
        $connection->refresh();

        // Step 1: Update existing files
        $updated = $this->syncConnection($connection);

        // Step 2: Import new files from root folder
        $newFiles = [];

        try {
            $items = $provider->listItems(''); // root folder

            // Get already imported file IDs
            $importedFileIds = Document::where('source_connection_id', $connection->id)
                ->whereNotNull('source_file_id')
                ->pluck('source_file_id')
                ->toArray();

            // Filter only files (not folders) that haven't been imported yet
            $newFileIds = [];
            foreach ($items as $item) {
                if ($item->type === 'file' && !in_array($item->id, $importedFileIds)) {
                    $newFileIds[] = $item->id;
                }
            }

            // Import new files
            if (!empty($newFileIds)) {
                Log::info("Full sync found new files", [
                    'connection_id' => $connection->id,
                    'new_files_count' => count($newFileIds),
                ]);

                $newFiles = $this->importFiles($connection, $newFileIds);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to scan for new files during full sync", [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }

        return array_merge($updated, $newFiles);
    }

    protected function importSingleFile(
        SourceConnection $connection,
        SourceProviderInterface $provider,
        string $fileId,
    ): ?Document {
        // Check if already imported from this connection with same file_id
        $existing = Document::where('source_connection_id', $connection->id)
            ->where('source_file_id', $fileId)
            ->first();

        $metadata = $provider->getFileMetadata($fileId);

        // If exists and not modified, skip
        if ($existing && $existing->source_modified_at && $metadata->modifiedAt) {
            if ($existing->source_modified_at->toIso8601String() === $metadata->modifiedAt) {
                Log::info("Skipping unchanged file", ['file_id' => $fileId, 'name' => $metadata->name]);
                return null;
            }
        }

        // Download file
        $downloaded = $provider->downloadFile($fileId);

        // Cross-source dedup: check if same content already exists
        if (!$existing && $downloaded->contentHash) {
            $duplicate = Document::where('content_hash', $downloaded->contentHash)->first();
            if ($duplicate) {
                Log::info("Skipping duplicate content", [
                    'file_id' => $fileId,
                    'name' => $metadata->name,
                    'duplicate_of' => $duplicate->id,
                ]);
                $this->cleanupDownload($downloaded);
                return null;
            }
        }

        // Move to permanent storage
        $storedPath = $this->moveToDocuments($downloaded);

        if ($existing) {
            // Update existing document
            Storage::disk('local')->delete($existing->file_path);
            $existing->chunks()->delete();

            $existing->update([
                'file_path' => $storedPath,
                'file_size' => $downloaded->size,
                'mime_type' => $downloaded->mimeType,
                'content_hash' => $downloaded->contentHash,
                'source_modified_at' => $metadata->modifiedAt,
                'status' => 'pending',
            ]);

            ProcessDocument::dispatch($existing);
            $this->cleanupDownload($downloaded);
            return $existing;
        }

        // Create new document
        $document = Document::create([
            'title' => pathinfo($downloaded->originalFilename, PATHINFO_FILENAME),
            'original_filename' => $downloaded->originalFilename,
            'mime_type' => $downloaded->mimeType,
            'file_path' => $storedPath,
            'file_size' => $downloaded->size,
            'status' => 'pending',
            'content_hash' => $downloaded->contentHash,
            'source_type' => $connection->provider,
            'source_file_id' => $fileId,
            'source_connection_id' => $connection->id,
            'source_modified_at' => $metadata->modifiedAt,
        ]);

        ProcessDocument::dispatch($document);
        $this->cleanupDownload($downloaded);

        return $document;
    }

    protected function moveToDocuments(DownloadedFile $downloaded): string
    {
        $newPath = 'documents/' . uniqid() . '_' . $downloaded->originalFilename;
        Storage::disk('local')->move($downloaded->localPath, $newPath);
        return $newPath;
    }

    protected function cleanupDownload(DownloadedFile $downloaded): void
    {
        if (Storage::disk('local')->exists($downloaded->localPath)) {
            Storage::disk('local')->delete($downloaded->localPath);
        }
    }
}
