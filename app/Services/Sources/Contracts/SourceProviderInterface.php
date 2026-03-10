<?php

namespace App\Services\Sources\Contracts;

use App\Models\SourceConnection;
use App\Services\Sources\DTOs\DownloadedFile;
use App\Services\Sources\DTOs\SourceItem;

interface SourceProviderInterface
{
    public function setConnection(SourceConnection $connection): static;

    public function getAuthorizationUrl(string $state, string $redirectUri): string;

    public function handleCallback(string $code, string $redirectUri): array;

    public function refreshToken(): array;

    /** @return SourceItem[] */
    public function listItems(string $folderId = ''): array;

    public function downloadFile(string $fileId): DownloadedFile;

    public function getFileMetadata(string $fileId): SourceItem;
}
