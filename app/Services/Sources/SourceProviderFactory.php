<?php

namespace App\Services\Sources;

use App\Models\SourceConnection;
use App\Services\Sources\Contracts\SourceProviderInterface;
use App\Services\Sources\Providers\DropboxProvider;
use App\Services\Sources\Providers\GoogleDriveProvider;
use App\Services\Sources\Providers\OneDriveProvider;
use App\Services\Sources\Providers\SharePointProvider;

class SourceProviderFactory
{
    public static function make(SourceConnection $connection): SourceProviderInterface
    {
        $provider = match ($connection->provider) {
            'onedrive' => new OneDriveProvider(),
            'sharepoint' => new SharePointProvider(),
            'google_drive' => new GoogleDriveProvider(),
            'dropbox' => new DropboxProvider(),
            default => throw new \InvalidArgumentException("Unknown provider: {$connection->provider}"),
        };

        return $provider->setConnection($connection);
    }

    public static function makeFromProvider(string $provider): SourceProviderInterface
    {
        return match ($provider) {
            'onedrive' => new OneDriveProvider(),
            'sharepoint' => new SharePointProvider(),
            'google_drive' => new GoogleDriveProvider(),
            'dropbox' => new DropboxProvider(),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
}
