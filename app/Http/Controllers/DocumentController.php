<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentController extends Controller
{
    private const SUPPORTED_EXTENSIONS = ['pdf', 'txt', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'doc', 'docx'];

    private const EXTENSION_MIME_MAP = [
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

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
                'mimes:pdf,txt,xls,xlsx,csv,jpg,jpeg,doc,docx,zip',
            ],
        ]);

        $file = $request->file('file');

        if ($file->getClientOriginalExtension() === 'zip') {
            return $this->handleZipUpload($file);
        }

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

    private function handleZipUpload(\Illuminate\Http\UploadedFile $file)
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            return response()->json(['message' => 'Impossibile aprire il file ZIP.'], 422);
        }

        $tempDir = storage_path('app/temp_zip_' . uniqid());
        mkdir($tempDir, 0755, true);

        $zip->extractTo($tempDir);
        $zip->close();

        $documents = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $extractedFile) {
                if (!$extractedFile->isFile()) {
                    continue;
                }

                // Skip macOS resource fork files
                if (str_contains($extractedFile->getPathname(), '__MACOSX')) {
                    continue;
                }

                $extension = strtolower($extractedFile->getExtension());
                if (!in_array($extension, self::SUPPORTED_EXTENSIONS)) {
                    continue;
                }

                $originalName = $extractedFile->getFilename();
                $mimeType = self::EXTENSION_MIME_MAP[$extension] ?? mime_content_type($extractedFile->getRealPath());
                $storedPath = Storage::disk('local')->putFile('documents', new \Illuminate\Http\File($extractedFile->getRealPath()));

                $document = Document::create([
                    'title' => pathinfo($originalName, PATHINFO_FILENAME),
                    'original_filename' => $originalName,
                    'mime_type' => $mimeType,
                    'file_path' => $storedPath,
                    'file_size' => $extractedFile->getSize(),
                    'status' => 'pending',
                ]);

                ProcessDocument::dispatch($document);
                $documents[] = $document;
            }
        } finally {
            // Clean up temp directory
            $this->deleteDirectory($tempDir);
        }

        if (empty($documents)) {
            return response()->json([
                'message' => 'Nessun file con formato supportato trovato nel ZIP.',
            ], 422);
        }

        return response()->json([
            'message' => count($documents) . ' documenti caricati con successo. Elaborazione in corso...',
            'documents' => $documents,
        ]);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
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
