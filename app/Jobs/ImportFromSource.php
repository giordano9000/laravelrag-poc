<?php

namespace App\Jobs;

use App\Models\SourceConnection;
use App\Models\SyncLog;
use App\Services\Sources\SourceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportFromSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public SourceConnection $sourceConnection,
        public array $fileIds,
    ) {}

    public function handle(SourceSyncService $syncService): void
    {
        // Create sync log
        $syncLog = SyncLog::create([
            'source_connection_id' => $this->sourceConnection->id,
            'type' => 'import',
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'file_ids' => $this->fileIds,
                'user_triggered' => true,
            ],
        ]);

        Log::info("Starting import from source", [
            'connection_id' => $this->sourceConnection->id,
            'provider' => $this->sourceConnection->provider,
            'file_count' => count($this->fileIds),
            'sync_log_id' => $syncLog->id,
        ]);

        try {
            $imported = $syncService->importFiles($this->sourceConnection, $this->fileIds, $syncLog);

            $syncLog->markAsCompleted();

            Log::info("Import from source completed", [
                'connection_id' => $this->sourceConnection->id,
                'imported_count' => count($imported),
                'sync_log_id' => $syncLog->id,
            ]);
        } catch (\Throwable $e) {
            $syncLog->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("Import from source job failed", [
            'connection_id' => $this->sourceConnection->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
