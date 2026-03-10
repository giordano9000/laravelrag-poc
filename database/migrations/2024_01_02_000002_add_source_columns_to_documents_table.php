<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->index();
            $table->string('source_type')->default('upload');
            $table->string('source_file_id')->nullable();
            $table->foreignId('source_connection_id')->nullable()->constrained('source_connections')->nullOnDelete();
            $table->timestamp('source_modified_at')->nullable();

            $table->index(['source_connection_id', 'source_file_id']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['source_connection_id', 'source_file_id']);
            $table->dropForeign(['source_connection_id']);
            $table->dropColumn([
                'content_hash',
                'source_type',
                'source_file_id',
                'source_connection_id',
                'source_modified_at',
            ]);
        });
    }
};
