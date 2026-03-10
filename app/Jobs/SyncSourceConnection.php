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
        public SourceConnection $connection,
    ) {}

    public function handle(SourceSyncService $syncService): void
    {
        Log::info("Starting sync for source connection", [
            'connection_id' => $this->connection->id,
            'provider' => $this->connection->provider,
        ]);

        $updated = $syncService->syncConnection($this->connection);

        Log::info("Sync completed for source connection", [
            'connection_id' => $this->connection->id,
            'updated_count' => count($updated),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("Sync source connection job failed", [
            'connection_id' => $this->connection->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
