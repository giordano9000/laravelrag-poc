<?php

namespace App\Services\Sources\Providers;

class OneDriveProvider extends AbstractMicrosoftProvider
{
    protected function getScopes(): string
    {
        return 'Files.Read Files.Read.All offline_access';
    }

    protected function getDriveItemsUrl(string $folderId): string
    {
        if ($folderId === '' || $folderId === 'root') {
            return "{$this->graphBaseUrl}/me/drive/root/children";
        }

        return "{$this->graphBaseUrl}/me/drive/items/{$folderId}/children";
    }

    protected function getDriveItemUrl(string $fileId): string
    {
        return "{$this->graphBaseUrl}/me/drive/items/{$fileId}";
    }

    protected function getDriveItemContentUrl(string $fileId): string
    {
        return "{$this->graphBaseUrl}/me/drive/items/{$fileId}/content";
    }
}
