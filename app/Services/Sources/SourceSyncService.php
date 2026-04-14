<?php

namespace App\Services\Sources;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\SourceConnection;
use App\Services\Sources\Contracts\SourceProviderInterface;
use App\Services\Sources\DTOs\DownloadedFile;
use App\Services\Sources\DTOs\FileMetadata;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

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

                // Skip unsupported file types
                if (!$document->mime_type || !\App\Services\DocumentProcessor::isSupportedMimeType($document->mime_type)) {
                    Log::debug("Skipping unsupported document during sync", [
                        'document_id' => $document->id,
                        'mime_type' => $document->mime_type,
                    ]);
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
            // Also filter by supported mime types
            $newFileIds = [];
            $skippedUnsupported = 0;

            foreach ($items as $item) {
                // Skip folders
                if ($item->type === 'folder') {
                    continue;
                }

                // Skip already imported
                if (in_array($item->id, $importedFileIds)) {
                    continue;
                }

                // Check if mime type is supported
                if (!\App\Services\DocumentProcessor::isSupportedMimeType($item->mimeType)) {
                    $skippedUnsupported++;
                    continue;
                }

                $newFileIds[] = $item->id;
            }

            if ($skippedUnsupported > 0) {
                Log::info("Skipped unsupported files during full sync", [
                    'connection_id' => $connection->id,
                    'count' => $skippedUnsupported,
                ]);
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

        // Check if mime type is supported
        if (!\App\Services\DocumentProcessor::isSupportedMimeType($downloaded->mimeType)) {
            Log::info("Skipping unsupported file type during import", [
                'file_id' => $fileId,
                'name' => $metadata->name,
                'mime_type' => $downloaded->mimeType,
            ]);
            $this->cleanupDownload($downloaded);
            return null;
        }

        // Handle ZIP files specially - extract and import contents
        if (in_array($downloaded->mimeType, ['application/zip', 'application/x-zip-compressed'])) {
            $this->cleanupDownload($downloaded);
            return $this->importZipFile($connection, $fileId, $downloaded, $metadata);
        }

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

    protected function importZipFile(
        SourceConnection $connection,
        string $fileId,
        DownloadedFile $downloaded,
        FileMetadata $metadata
    ): ?Document {
        $zip = new ZipArchive;
        $zipPath = Storage::disk('local')->path($downloaded->localPath);

        if ($zip->open($zipPath) !== true) {
            Log::error("Failed to open ZIP file", [
                'file_id' => $fileId,
                'name' => $metadata->name,
            ]);
            return null;
        }

        $tempDir = storage_path('app/temp_zip_' . uniqid());
        mkdir($tempDir, 0755, true);

        $zip->extractTo($tempDir);
        $zip->close();

        $importedDocuments = [];
        $supportedExtensions = ['pdf', 'txt', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'doc', 'docx'];
        $extensionMimeMap = [
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $extractedFile) {
                if (!$extractedFile->isFile()) {
                    continue;
                }

                // Skip macOS resource fork files
                if (str_contains($extractedFile->getPathname(), '__MACOSX')) {
                    continue;
                }

                $extension = strtolower($extractedFile->getExtension());
                if (!in_array($extension, $supportedExtensions)) {
                    continue;
                }

                $originalName = $extractedFile->getFilename();
                $mimeType = $extensionMimeMap[$extension] ?? mime_content_type($extractedFile->getRealPath());
                $storedPath = Storage::disk('local')->putFile('documents', new \Illuminate\Http\File($extractedFile->getRealPath()));
                $contentHash = hash_file('sha256', $extractedFile->getRealPath());

                // Check for duplicates
                $duplicate = Document::where('content_hash', $contentHash)->first();
                if ($duplicate) {
                    Storage::disk('local')->delete($storedPath);
                    Log::info("Skipping duplicate file from ZIP", [
                        'name' => $originalName,
                        'duplicate_of' => $duplicate->id,
                    ]);
                    continue;
                }

                $document = Document::create([
                    'title' => pathinfo($originalName, PATHINFO_FILENAME),
                    'original_filename' => $originalName,
                    'mime_type' => $mimeType,
                    'file_path' => $storedPath,
                    'file_size' => $extractedFile->getSize(),
                    'status' => 'pending',
                    'content_hash' => $contentHash,
                    'source_type' => $connection->provider,
                    'source_connection_id' => $connection->id,
                ]);

                ProcessDocument::dispatch($document);
                $importedDocuments[] = $document;
            }
        } finally {
            // Clean up temp directory
            $this->deleteDirectory($tempDir);
        }

        Log::info("Extracted and imported files from ZIP", [
            'zip_name' => $metadata->name,
            'files_imported' => count($importedDocuments),
        ]);

        // Return the first imported document (or null if none)
        return $importedDocuments[0] ?? null;
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
