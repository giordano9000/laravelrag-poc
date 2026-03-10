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

class ImportFromSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public SourceConnection $connection,
        public array $fileIds,
    ) {}

    public function handle(SourceSyncService $syncService): void
    {
        Log::info("Starting import from source", [
            'connection_id' => $this->connection->id,
            'provider' => $this->connection->provider,
            'file_count' => count($this->fileIds),
        ]);

        $imported = $syncService->importFiles($this->connection, $this->fileIds);

        Log::info("Import from source completed", [
            'connection_id' => $this->connection->id,
            'imported_count' => count($imported),
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("Import from source job failed", [
            'connection_id' => $this->connection->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
