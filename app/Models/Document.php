<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'title',
        'original_filename',
        'mime_type',
        'file_path',
        'file_size',
        'status',
        'content_preview',
        'content_hash',
        'source_type',
        'source_file_id',
        'source_connection_id',
        'source_modified_at',
    ];

    protected $casts = [
        'source_modified_at' => 'datetime',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function sourceConnection(): BelongsTo
    {
        return $this->belongsTo(SourceConnection::class);
    }
}
