<?php

namespace App\Services\Sources\Providers;

use App\Models\SourceConnection;
use App\Services\Sources\Contracts\SourceProviderInterface;
use App\Services\Sources\DTOs\DownloadedFile;
use App\Services\Sources\DTOs\SourceItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

abstract class AbstractMicrosoftProvider implements SourceProviderInterface
{
    protected SourceConnection $connection;

    protected string $authorizeUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    protected string $tokenUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    protected string $graphBaseUrl = 'https://graph.microsoft.com/v1.0';

    public function setConnection(SourceConnection $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    abstract protected function getScopes(): string;

    abstract protected function getDriveItemsUrl(string $folderId): string;

    abstract protected function getDriveItemUrl(string $fileId): string;

    abstract protected function getDriveItemContentUrl(string $fileId): string;

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $params = http_build_query([
            'client_id' => $this->connection->client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => $this->getScopes(),
            'state' => $state,
            'response_mode' => 'query',
        ]);

        return "{$this->authorizeUrl}?{$params}";
    }

    public function handleCallback(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => $this->connection->client_id,
            'client_secret' => $this->connection->client_secret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => $this->getScopes(),
        ]);

        $data = $response->json();

        if (!$response->successful() || !isset($data['access_token'])) {
            throw new \RuntimeException('Microsoft OAuth token exchange failed: ' . ($data['error_description'] ?? 'Unknown error'));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    public function refreshToken(): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => $this->connection->client_id,
            'client_secret' => $this->connection->client_secret,
            'refresh_token' => $this->connection->refresh_token,
            'grant_type' => 'refresh_token',
            'scope' => $this->getScopes(),
        ]);

        $data = $response->json();

        if (!$response->successful() || !isset($data['access_token'])) {
            throw new \RuntimeException('Microsoft token refresh failed: ' . ($data['error_description'] ?? 'Unknown error'));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $this->connection->refresh_token,
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    public function listItems(string $folderId = ''): array
    {
        $url = $this->getDriveItemsUrl($folderId);

        $response = Http::withToken($this->connection->access_token)
            ->get($url, [
                '$select' => 'id,name,file,folder,size,lastModifiedDateTime',
                '$top' => 200,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to list items from Microsoft Graph: ' . $response->body());
        }

        $items = [];
        foreach ($response->json('value', []) as $item) {
            $items[] = new SourceItem(
                id: $item['id'],
                name: $item['name'],
                type: isset($item['folder']) ? 'folder' : 'file',
                mimeType: $item['file']['mimeType'] ?? null,
                size: $item['size'] ?? null,
                modifiedAt: $item['lastModifiedDateTime'] ?? null,
            );
        }

        return $items;
    }

    public function downloadFile(string $fileId): DownloadedFile
    {
        $metadata = $this->getFileMetadata($fileId);
        $url = $this->getDriveItemContentUrl($fileId);

        $response = Http::withToken($this->connection->access_token)
            ->withOptions(['sink' => null])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download file from Microsoft Graph: ' . $response->body());
        }

        $tempPath = 'source_downloads/' . uniqid() . '_' . $metadata->name;
        Storage::disk('local')->put($tempPath, $response->body());
        $localPath = Storage::disk('local')->path($tempPath);

        $hash = hash_file('sha256', $localPath);

        return new DownloadedFile(
            localPath: $tempPath,
            originalFilename: $metadata->name,
            mimeType: $metadata->mimeType,
            size: $metadata->size,
            contentHash: $hash,
        );
    }

    public function getFileMetadata(string $fileId): SourceItem
    {
        $url = $this->getDriveItemUrl($fileId);

        $response = Http::withToken($this->connection->access_token)
            ->get($url, [
                '$select' => 'id,name,file,folder,size,lastModifiedDateTime',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get file metadata from Microsoft Graph: ' . $response->body());
        }

        $item = $response->json();

        return new SourceItem(
            id: $item['id'],
            name: $item['name'],
            type: isset($item['folder']) ? 'folder' : 'file',
            mimeType: $item['file']['mimeType'] ?? null,
            size: $item['size'] ?? null,
            modifiedAt: $item['lastModifiedDateTime'] ?? null,
        );
    }
}
