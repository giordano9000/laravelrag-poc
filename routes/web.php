<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SourceConnectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentController::class, 'index'])->name('dashboard');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
Route::post('/chat', ChatController::class)->name('chat');

// Source Connections
Route::get('/sources', [SourceConnectionController::class, 'index'])->name('sources.index');
Route::post('/sources', [SourceConnectionController::class, 'store'])->name('sources.store');
Route::delete('/sources/{connection}', [SourceConnectionController::class, 'destroy'])->name('sources.destroy');
Route::get('/sources/{connection}/auth', [SourceConnectionController::class, 'redirectToAuth'])->name('sources.auth');
Route::get('/sources/callback/{provider}', [SourceConnectionController::class, 'handleCallback'])->name('sources.callback');
Route::get('/sources/{connection}/browse', [SourceConnectionController::class, 'browse'])->name('sources.browse');
Route::post('/sources/{connection}/import', [SourceConnectionController::class, 'import'])->name('sources.import');
Route::post('/sources/{connection}/sync', [SourceConnectionController::class, 'sync'])->name('sources.sync');
