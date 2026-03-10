<?php

namespace App\Services\Sources\Providers;

use App\Models\SourceConnection;
use App\Services\Sources\Contracts\SourceProviderInterface;
use App\Services\Sources\DTOs\DownloadedFile;
use App\Services\Sources\DTOs\SourceItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DropboxProvider implements SourceProviderInterface
{
    protected SourceConnection $connection;

    protected string $authorizeUrl = 'https://www.dropbox.com/oauth2/authorize';
    protected string $tokenUrl = 'https://api.dropboxapi.com/oauth2/token';
    protected string $apiBaseUrl = 'https://api.dropboxapi.com/2';
    protected string $contentBaseUrl = 'https://content.dropboxapi.com/2';

    public function setConnection(SourceConnection $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    public function getAuthorizationUrl(string $state, string $redirectUri): string
    {
        $params = http_build_query([
            'client_id' => $this->connection->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            'token_access_type' => 'offline',
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
        ]);

        $data = $response->json();

        if (!$response->successful() || !isset($data['access_token'])) {
            throw new \RuntimeException('Dropbox OAuth token exchange failed: ' . ($data['error_description'] ?? json_encode($data)));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 14400,
        ];
    }

    public function refreshToken(): array
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'client_id' => $this->connection->client_id,
            'client_secret' => $this->connection->client_secret,
            'refresh_token' => $this->connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        $data = $response->json();

        if (!$response->successful() || !isset($data['access_token'])) {
            throw new \RuntimeException('Dropbox token refresh failed: ' . ($data['error_description'] ?? json_encode($data)));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $this->connection->refresh_token,
            'expires_in' => $data['expires_in'] ?? 14400,
        ];
    }

    public function listItems(string $folderId = ''): array
    {
        $path = $folderId ?: '';

        $response = Http::withToken($this->connection->access_token)
            ->post("{$this->apiBaseUrl}/files/list_folder", [
                'path' => $path,
                'limit' => 200,
                'include_mounted_folders' => true,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to list Dropbox folder: ' . $response->body());
        }

        $items = [];
        foreach ($response->json('entries', []) as $entry) {
            $isFolder = $entry['.tag'] === 'folder';

            $items[] = new SourceItem(
                id: $entry['path_lower'] ?? $entry['id'],
                name: $entry['name'],
                type: $isFolder ? 'folder' : 'file',
                mimeType: null, // Dropbox doesn't return MIME in list
                size: $entry['size'] ?? null,
                modifiedAt: $entry['server_modified'] ?? null,
            );
        }

        return $items;
    }

    public function downloadFile(string $fileId): DownloadedFile
    {
        $metadata = $this->getFileMetadata($fileId);

        $response = Http::withToken($this->connection->access_token)
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode(['path' => $fileId]),
            ])
            ->post("{$this->contentBaseUrl}/files/download");

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download file from Dropbox: ' . $response->body());
        }

        $tempPath = 'source_downloads/' . uniqid() . '_' . $metadata->name;
        Storage::disk('local')->put($tempPath, $response->body());
        $localPath = Storage::disk('local')->path($tempPath);

        $hash = hash_file('sha256', $localPath);

        return new DownloadedFile(
            localPath: $tempPath,
            originalFilename: $metadata->name,
            mimeType: mime_content_type($localPath) ?: null,
            size: filesize($localPath),
            contentHash: $hash,
        );
    }

    public function getFileMetadata(string $fileId): SourceItem
    {
        $response = Http::withToken($this->connection->access_token)
            ->post("{$this->apiBaseUrl}/files/get_metadata", [
                'path' => $fileId,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get file metadata from Dropbox: ' . $response->body());
        }

        $entry = $response->json();
        $isFolder = ($entry['.tag'] ?? '') === 'folder';

        return new SourceItem(
            id: $entry['path_lower'] ?? $entry['id'],
            name: $entry['name'],
            type: $isFolder ? 'folder' : 'file',
            mimeType: null,
            size: $entry['size'] ?? null,
            modifiedAt: $entry['server_modified'] ?? null,
        );
    }
}
