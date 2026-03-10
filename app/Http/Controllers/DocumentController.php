<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::latest()->get();

        return view('dashboard', compact('documents'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200', // 50MB
                'mimes:pdf,txt,xls,xlsx,csv,jpg,jpeg,doc,docx',
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        ProcessDocument::dispatch($document);

        return response()->json([
            'message' => 'Documento caricato con successo. Elaborazione in corso...',
            'document' => $document,
        ]);
    }

    public function show(Document $document)
    {
        $document->load('chunks');

        return response()->json($document);
    }

    public function destroy(Document $document)
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Documento eliminato.']);
    }
}
