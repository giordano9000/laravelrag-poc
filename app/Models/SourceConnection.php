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
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
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

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}
