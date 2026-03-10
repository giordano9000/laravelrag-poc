<?php

namespace App\Services\Sources\DTOs;

class SourceItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type, // 'file' or 'folder'
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?string $modifiedAt = null,
    ) {}

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'modifiedAt' => $this->modifiedAt,
        ];
    }
}
