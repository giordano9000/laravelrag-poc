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

class SyncSourceConnection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;

    public function __construct(
        public SourceConnection $sourceConnection,
        public bool $fullSync = false,
    ) {}

    public function handle(SourceSyncService $syncService): void
    {
        $syncType = $this->fullSync ? 'full_sync' : 'sync';
        $syncTypeLabel = $this->fullSync ? 'full sync' : 'sync';

        // Create sync log
        $syncLog = SyncLog::create([
            'source_connection_id' => $this->sourceConnection->id,
            'type' => $syncType,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => [
                'full_sync' => $this->fullSync,
                'folder_path' => $this->sourceConnection->folder_path,
                'auto_triggered' => $this->sourceConnection->auto_sync,
            ],
        ]);

        Log::info("Starting {$syncTypeLabel} for source connection", [
            'connection_id' => $this->sourceConnection->id,
            'provider' => $this->sourceConnection->provider,
            'full_sync' => $this->fullSync,
            'sync_log_id' => $syncLog->id,
        ]);

        try {
            $result = $this->fullSync
                ? $syncService->fullSyncConnection($this->sourceConnection, $syncLog)
                : $syncService->syncConnection($this->sourceConnection, $syncLog);

            $syncLog->markAsCompleted();

            Log::info("Sync completed for source connection", [
                'connection_id' => $this->sourceConnection->id,
                'sync_type' => $syncTypeLabel,
                'result_count' => count($result),
                'sync_log_id' => $syncLog->id,
            ]);
        } catch (\Throwable $e) {
            $syncLog->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("Sync source connection job failed", [
            'connection_id' => $this->sourceConnection->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
