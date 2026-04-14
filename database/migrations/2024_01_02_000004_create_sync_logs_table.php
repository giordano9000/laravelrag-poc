<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_connection_id')->constrained('source_connections')->onDelete('cascade');
            $table->enum('type', ['import', 'sync', 'full_sync']); // Tipo di operazione
            $table->enum('status', ['running', 'completed', 'failed', 'partial'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('total_items')->default(0); // Totale file processati
            $table->integer('successful_items')->default(0); // File importati con successo
            $table->integer('failed_items')->default(0); // File falliti
            $table->integer('skipped_items')->default(0); // File saltati
            $table->text('error_message')->nullable(); // Errore generale se il job fallisce
            $table->json('metadata')->nullable(); // Dati extra (es: folder_id, user_triggered, etc.)
            $table->timestamps();

            $table->index(['source_connection_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('sync_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->constrained('sync_logs')->onDelete('cascade');
            $table->foreignId('document_id')->nullable()->constrained('documents')->onDelete('set null');
            $table->string('file_id'); // ID del file sul provider esterno
            $table->string('file_name');
            $table->string('file_path')->nullable(); // Percorso del file
            $table->bigInteger('file_size')->nullable(); // Dimensione in bytes
            $table->string('mime_type')->nullable();
            $table->enum('status', ['success', 'failed', 'skipped']);
            $table->string('skip_reason')->nullable(); // Motivo dello skip (es: "unsupported_format", "already_synced", "no_changes")
            $table->text('error_message')->nullable(); // Messaggio di errore specifico
            $table->json('metadata')->nullable(); // Dati extra
            $table->timestamps();

            $table->index('sync_log_id');
            $table->index('status');
            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_log_items');
        Schema::dropIfExists('sync_logs');
    }
};
