<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncLog extends Model
{
    protected $fillable = [
        'source_connection_id',
        'type',
        'status',
        'started_at',
        'completed_at',
        'total_items',
        'successful_items',
        'failed_items',
        'skipped_items',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function sourceConnection(): BelongsTo
    {
        return $this->belongsTo(SourceConnection::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SyncLogItem::class);
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return ($this->successful_items / $this->total_items) * 100;
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => $this->failed_items > 0 ? 'partial' : 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $error,
        ]);
    }

    public function incrementCounters(string $status): void
    {
        $field = match ($status) {
            'success' => 'successful_items',
            'failed' => 'failed_items',
            'skipped' => 'skipped_items',
        };

        $this->increment($field);
        $this->increment('total_items');
    }
}
