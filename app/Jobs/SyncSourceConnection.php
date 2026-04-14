<?php

namespace App\Jobs;

use App\Models\SourceConnection;
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
        $syncType = $this->fullSync ? 'full sync' : 'sync';

        Log::info("Starting {$syncType} for source connection", [
            'connection_id' => $this->sourceConnection->id,
            'provider' => $this->sourceConnection->provider,
            'full_sync' => $this->fullSync,
        ]);

        $result = $this->fullSync
            ? $syncService->fullSyncConnection($this->sourceConnection)
            : $syncService->syncConnection($this->sourceConnection);

        Log::info("Sync completed for source connection", [
            'connection_id' => $this->sourceConnection->id,
            'sync_type' => $syncType,
            'result_count' => count($result),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("Sync source connection job failed", [
            'connection_id' => $this->sourceConnection->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
