<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceConnection extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'metadata',
        'status',
        'last_synced_at',
        'folder_path',
        'auto_sync',
        'sync_frequency',
        'next_sync_at',
    ];

    protected $hidden = [
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'folder_path' => 'array',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'next_sync_at' => 'datetime',
            'auto_sync' => 'boolean',
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'source_connection_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function needsSync(): bool
    {
        return $this->auto_sync &&
               $this->next_sync_at &&
               $this->next_sync_at->isPast() &&
               $this->isConnected();
    }

    public function calculateNextSyncAt(): ?\Carbon\Carbon
    {
        if (!$this->auto_sync || !$this->sync_frequency) {
            return null;
        }

        return match ($this->sync_frequency) {
            'hourly' => now()->addHour(),
            'every_3_hours' => now()->addHours(3),
            'every_6_hours' => now()->addHours(6),
            'daily' => now()->addDay(),
            'twice_daily' => now()->addHours(12),
            default => null,
        };
    }

    public function getFolderIdAttribute(): ?string
    {
        if (!$this->folder_path) {
            return null;
        }

        // Support both old format (string) and new format (array)
        if (is_string($this->folder_path)) {
            return $this->folder_path;
        }

        return $this->folder_path['id'] ?? null;
    }

    public function getFolderNameAttribute(): ?string
    {
        if (!$this->folder_path) {
            return null;
        }

        // Support both old format (string) and new format (array)
        if (is_string($this->folder_path)) {
            return $this->folder_path; // Fallback to ID if old format
        }

        return $this->folder_path['name'] ?? $this->folder_path['id'] ?? null;
    }
}
