<?php

namespace App\Console\Commands;

use App\Jobs\SyncSourceConnection;
use App\Models\SourceConnection;
use Illuminate\Console\Command;

class SyncSourceConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sources:sync
                          {--connection-id= : Sync only a specific connection by ID}
                          {--full : Perform a full sync instead of incremental}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync source connections that are due for automatic synchronization';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fullSync = $this->option('full');
        $connectionId = $this->option('connection-id');

        if ($connectionId) {
            // Sync specific connection
            $connection = SourceConnection::find($connectionId);

            if (!$connection) {
                $this->error("Connection with ID {$connectionId} not found.");
                return self::FAILURE;
            }

            if (!$connection->isConnected()) {
                $this->error("Connection '{$connection->name}' is not connected.");
                return self::FAILURE;
            }

            $this->info("Syncing connection '{$connection->name}'...");
            SyncSourceConnection::dispatch($connection, $fullSync);

            // Update next sync time if auto sync is enabled
            if ($connection->auto_sync && $connection->sync_frequency) {
                $connection->next_sync_at = $connection->calculateNextSyncAt();
                $connection->save();
            }

            $this->info("Sync job dispatched for connection '{$connection->name}'.");
            return self::SUCCESS;
        }

        // Sync all connections that need syncing
        $connections = SourceConnection::query()
            ->where('auto_sync', true)
            ->where('status', 'connected')
            ->whereNotNull('next_sync_at')
            ->where('next_sync_at', '<=', now())
            ->get();

        if ($connections->isEmpty()) {
            $this->info('No connections need syncing at this time.');
            return self::SUCCESS;
        }

        $this->info("Found {$connections->count()} connection(s) to sync.");

        foreach ($connections as $connection) {
            $this->info("Dispatching sync for '{$connection->name}'...");
            SyncSourceConnection::dispatch($connection, $fullSync);

            // Update next sync time
            $connection->next_sync_at = $connection->calculateNextSyncAt();
            $connection->save();
        }

        $this->info("All sync jobs dispatched successfully.");
        return self::SUCCESS;
    }
}
