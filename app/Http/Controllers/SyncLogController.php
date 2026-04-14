<?php

namespace App\Http\Controllers;

use App\Models\SourceConnection;
use App\Models\SyncLog;
use Illuminate\Http\Request;

class SyncLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SyncLog::with(['sourceConnection', 'items'])
            ->latest();

        // Filter by connection
        if ($request->filled('connection_id')) {
            $query->where('source_connection_id', $request->connection_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $syncLogs = $query->paginate(20);
        $connections = SourceConnection::select('id', 'name', 'provider')->get();

        return view('sync-logs.index', compact('syncLogs', 'connections'));
    }

    public function show(SyncLog $syncLog)
    {
        $syncLog->load(['sourceConnection', 'items.document']);

        return view('sync-logs.show', compact('syncLog'));
    }

    public function destroy(SyncLog $syncLog)
    {
        $syncLog->delete();

        return response()->json(['message' => 'Log eliminato con successo.']);
    }
}
