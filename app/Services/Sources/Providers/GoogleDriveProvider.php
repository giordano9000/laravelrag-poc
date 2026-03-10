<?php

namespace App\Services\Sources\Providers;

use App\Models\SourceConnection;
use App\Services\Sources\Contracts\SourceProviderInterface;
use App\Services\Sources\DTOs\DownloadedFile;
use App\Services\Sources\DTOs\SourceItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GoogleDriveProvider implements SourceProviderInterface
{
    protected SourceConnection $connection;

    protected string $authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected string $tokenUrl = 'https://oauth2.googleapis.com/token';
    protected string $apiBaseUrl = 'https://www.googleapis.com/drive/v3';

    protected array $exportMimeMap = [
        'application/vnd.google-apps.document' => [
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'extension' => 'docx',
        ],
        'application/vnd.google-apps.spreadsheet' => [
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'extension' => 'xlsx',
        ],
        'application/vnd.google-apps.presentation' => [
            'mimeType' => 'application/pdf',
            'extension' => 'pdf',
        ],
    ];

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
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
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
            throw new \RuntimeException('Google OAuth token exchange failed: ' . ($data['error_description'] ?? json_encode($data)));
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
        ]);

        $data = $response->json();

        if (!$response->successful() || !isset($data['access_token'])) {
            throw new \RuntimeException('Google token refresh failed: ' . ($data['error_description'] ?? json_encode($data)));
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $this->connection->refresh_token,
            'expires_in' => $data['expires_in'] ?? 3600,
        ];
    }

    public function listItems(string $folderId = ''): array
    {
        $folderId = $folderId ?: 'root';

        $response = Http::withToken($this->connection->access_token)
            ->get("{$this->apiBaseUrl}/files", [
                'q' => "'{$folderId}' in parents and trashed = false",
                'fields' => 'files(id,name,mimeType,size,modifiedTime)',
                'pageSize' => 200,
                'orderBy' => 'folder,name',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to list Google Drive files: ' . $response->body());
        }

        $items = [];
        foreach ($response->json('files', []) as $file) {
            $isFolder = $file['mimeType'] === 'application/vnd.google-apps.folder';

            $items[] = new SourceItem(
                id: $file['id'],
                name: $file['name'],
                type: $isFolder ? 'folder' : 'file',
                mimeType: $file['mimeType'] ?? null,
                size: isset($file['size']) ? (int) $file['size'] : null,
                modifiedAt: $file['modifiedTime'] ?? null,
            );
        }

        return $items;
    }

    public function downloadFile(string $fileId): DownloadedFile
    {
        $metadata = $this->getFileMetadata($fileId);

        // Google Docs native formats need to be exported
        if (isset($this->exportMimeMap[$metadata->mimeType])) {
            return $this->exportGoogleDoc($fileId, $metadata);
        }

        $response = Http::withToken($this->connection->access_token)
            ->get("{$this->apiBaseUrl}/files/{$fileId}", [
                'alt' => 'media',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download file from Google Drive: ' . $response->body());
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

    protected function exportGoogleDoc(string $fileId, SourceItem $metadata): DownloadedFile
    {
        $export = $this->exportMimeMap[$metadata->mimeType];

        $response = Http::withToken($this->connection->access_token)
            ->get("{$this->apiBaseUrl}/files/{$fileId}/export", [
                'mimeType' => $export['mimeType'],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to export Google Doc: ' . $response->body());
        }

        $filename = pathinfo($metadata->name, PATHINFO_FILENAME) . '.' . $export['extension'];
        $tempPath = 'source_downloads/' . uniqid() . '_' . $filename;
        Storage::disk('local')->put($tempPath, $response->body());
        $localPath = Storage::disk('local')->path($tempPath);

        $hash = hash_file('sha256', $localPath);

        return new DownloadedFile(
            localPath: $tempPath,
            originalFilename: $filename,
            mimeType: $export['mimeType'],
            size: filesize($localPath),
            contentHash: $hash,
        );
    }

    public function getFileMetadata(string $fileId): SourceItem
    {
        $response = Http::withToken($this->connection->access_token)
            ->get("{$this->apiBaseUrl}/files/{$fileId}", [
                'fields' => 'id,name,mimeType,size,modifiedTime',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get file metadata from Google Drive: ' . $response->body());
        }

        $file = $response->json();
        $isFolder = ($file['mimeType'] ?? '') === 'application/vnd.google-apps.folder';

        return new SourceItem(
            id: $file['id'],
            name: $file['name'],
            type: $isFolder ? 'folder' : 'file',
            mimeType: $file['mimeType'] ?? null,
            size: isset($file['size']) ? (int) $file['size'] : null,
            modifiedAt: $file['modifiedTime'] ?? null,
        );
    }
}
