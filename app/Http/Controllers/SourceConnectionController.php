<?php

namespace App\Http\Controllers;

use App\Jobs\ImportFromSource;
use App\Jobs\SyncSourceConnection;
use App\Models\SourceConnection;
use App\Services\Sources\SourceProviderFactory;
use App\Services\Sources\SourceSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SourceConnectionController extends Controller
{
    public function index()
    {
        $connections = SourceConnection::latest()->get();

        return view('sources.index', compact('connections'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|in:onedrive,sharepoint,google_drive,dropbox',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'metadata' => 'nullable|array',
            'metadata.tenant_id' => 'nullable|string',
            'metadata.site_id' => 'nullable|string',
        ]);

        $connection = SourceConnection::create([
            'name' => $request->name,
            'provider' => $request->provider,
            'client_id' => $request->client_id,
            'client_secret' => $request->client_secret,
            'metadata' => $request->metadata,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Connessione creata. Procedi con l\'autenticazione OAuth.',
            'connection' => $connection,
        ]);
    }

    public function destroy(SourceConnection $connection)
    {
        $connection->delete();

        return response()->json(['message' => 'Connessione eliminata.']);
    }

    public function redirectToAuth(SourceConnection $connection)
    {
        $provider = SourceProviderFactory::make($connection);

        $state = encrypt($connection->id);
        $redirectUri = route('sources.callback', ['provider' => $connection->provider]);

        $url = $provider->getAuthorizationUrl($state, $redirectUri);

        return response()->json(['url' => $url]);
    }

    public function handleCallback(Request $request, string $provider)
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        try {
            $connectionId = decrypt($request->state);
        } catch (\Throwable) {
            return redirect()->route('sources.index')->with('error', 'Stato OAuth non valido.');
        }

        $connection = SourceConnection::findOrFail($connectionId);

        try {
            $sourceProvider = SourceProviderFactory::make($connection);
            $redirectUri = route('sources.callback', ['provider' => $provider]);

            $tokens = $sourceProvider->handleCallback($request->code, $redirectUri);

            $connection->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                'status' => 'connected',
            ]);

            return redirect()->route('sources.index')->with('success', 'Connessione stabilita con successo!');
        } catch (\Throwable $e) {
            Log::error("OAuth callback failed", [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('sources.index')->with('error', 'Autenticazione fallita: ' . $e->getMessage());
        }
    }

    public function browse(Request $request, SourceConnection $connection)
    {
        if (!$connection->isConnected()) {
            return response()->json(['message' => 'Connessione non attiva.'], 422);
        }

        try {
            $provider = SourceProviderFactory::make($connection);

            $syncService = app(SourceSyncService::class);
            $syncService->ensureValidToken($connection, $provider);
            $connection->refresh();

            $folderId = $request->query('folder_id', '');
            $items = $provider->listItems($folderId);

            return response()->json([
                'items' => array_map(fn ($item) => $item->toArray(), $items),
            ]);
        } catch (\Throwable $e) {
            Log::error("Browse failed", [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Errore nel caricamento dei file: ' . $e->getMessage()], 500);
        }
    }

    public function import(Request $request, SourceConnection $connection)
    {
        $request->validate([
            'file_ids' => 'required|array|min:1',
            'file_ids.*' => 'required|string',
        ]);

        if (!$connection->isConnected()) {
            return response()->json(['message' => 'Connessione non attiva.'], 422);
        }

        ImportFromSource::dispatch($connection, $request->file_ids);

        return response()->json([
            'message' => count($request->file_ids) . ' file in coda per l\'importazione.',
        ]);
    }

    public function sync(SourceConnection $connection)
    {
        if (!$connection->isConnected()) {
            return response()->json(['message' => 'Connessione non attiva.'], 422);
        }

        SyncSourceConnection::dispatch($connection);

        return response()->json([
            'message' => 'Sincronizzazione avviata.',
        ]);
    }
}
