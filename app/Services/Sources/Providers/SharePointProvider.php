<?php

namespace App\Services\Sources\Providers;

class SharePointProvider extends AbstractMicrosoftProvider
{
    protected function getTenantId(): string
    {
        return $this->connection->metadata['tenant_id'] ?? 'common';
    }

    protected function getSiteId(): string
    {
        return $this->connection->metadata['site_id'] ?? '';
    }

    protected function getScopes(): string
    {
        return 'Sites.Read.All Files.Read.All offline_access';
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $tenantId = $this->getTenantId();
        $this->authorizeUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize";
        $this->tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        return parent::getAuthorizationUrl($state, $redirectUri);
    }

    public function handleCallback(string $code, string $redirectUri): array
    {
        $tenantId = $this->getTenantId();
        $this->tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        return parent::handleCallback($code, $redirectUri);
    }

    public function refreshToken(): array
    {
        $tenantId = $this->getTenantId();
        $this->tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

        return parent::refreshToken();
    }

    protected function getDriveItemsUrl(string $folderId): string
    {
        $siteId = $this->getSiteId();

        if ($folderId === '' || $folderId === 'root') {
            return "{$this->graphBaseUrl}/sites/{$siteId}/drive/root/children";
        }

        return "{$this->graphBaseUrl}/sites/{$siteId}/drive/items/{$folderId}/children";
    }

    protected function getDriveItemUrl(string $fileId): string
    {
        $siteId = $this->getSiteId();
        return "{$this->graphBaseUrl}/sites/{$siteId}/drive/items/{$fileId}";
    }

    protected function getDriveItemContentUrl(string $fileId): string
    {
        $siteId = $this->getSiteId();
        return "{$this->graphBaseUrl}/sites/{$siteId}/drive/items/{$fileId}/content";
    }
}
