<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLogItem extends Model
{
    protected $fillable = [
        'sync_log_id',
        'document_id',
        'file_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'status',
        'skip_reason',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(SyncLog::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function getFormattedSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $bytes = $this->file_size;
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / 1048576, 1) . ' MB';
        }
    }

    public function getSkipReasonLabelAttribute(): ?string
    {
        if (!$this->skip_reason) {
            return null;
        }

        return match ($this->skip_reason) {
            'unsupported_format' => 'Formato non supportato',
            'already_synced' => 'Già sincronizzato',
            'no_changes' => 'Nessuna modifica',
            'too_large' => 'File troppo grande',
            'permission_denied' => 'Permesso negato',
            'corrupted' => 'File corrotto',
            default => ucfirst(str_replace('_', ' ', $this->skip_reason)),
        };
    }
}
