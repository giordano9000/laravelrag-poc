<?php

namespace App\Services\Sources\DTOs;

class DownloadedFile
{
    public function __construct(
        public readonly string $localPath,
        public readonly string $originalFilename,
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?string $contentHash = null,
    ) {}

    public function toArray(): array
    {
        return [
            'localPath' => $this->localPath,
            'originalFilename' => $this->originalFilename,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'contentHash' => $this->contentHash,
        ];
    }
}
