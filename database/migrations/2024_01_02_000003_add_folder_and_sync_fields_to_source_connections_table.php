<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_connections', function (Blueprint $table) {
            $table->json('folder_path')->nullable()->after('metadata');
            $table->boolean('auto_sync')->default(false)->after('last_synced_at');
            $table->enum('sync_frequency', ['hourly', 'every_3_hours', 'every_6_hours', 'daily', 'twice_daily'])->nullable()->after('auto_sync');
            $table->timestamp('next_sync_at')->nullable()->after('sync_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('source_connections', function (Blueprint $table) {
            $table->dropColumn(['folder_path', 'auto_sync', 'sync_frequency', 'next_sync_at']);
        });
    }
};
