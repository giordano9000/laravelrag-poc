@extends('layouts.app')

@section('content')
<div class="h-[calc(100vh-4rem)] overflow-y-auto" x-data="documentsManager()">
    {{-- Page Header --}}
    <div class="bg-white/80 backdrop-blur-xl border-b border-gray-200/50 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Gestione Documenti</h1>
                <p class="text-sm text-gray-500 mt-1">Visualizza e gestisci tutti i documenti importati</p>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="bg-gray-50/80 border-b border-gray-200/50 mb-8">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="text-sm font-semibold text-gray-700">Filtri e Ricerca:</span>

                {{-- Search --}}
                <div class="relative flex-1 max-w-md">
                    <input
                        type="text"
                        x-model="searchQuery"
                        @input="filterDocuments"
                        placeholder="Cerca documenti..."
                        class="w-full pl-10 pr-4 py-2 bg-white rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                    >
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                {{-- Filter by status --}}
                <select
                    x-model="statusFilter"
                    @change="filterDocuments"
                    class="px-4 py-2 bg-white rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                >
                    <option value="">Tutti gli stati</option>
                    <option value="completed">Completati</option>
                    <option value="pending">In elaborazione</option>
                    <option value="failed">Errore</option>
                </select>

                {{-- Filter by source --}}
                <select
                    x-model="sourceFilter"
                    @change="filterDocuments"
                    class="px-4 py-2 bg-white rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm"
                >
                    <option value="">Tutte le fonti</option>
                    <option value="upload">Upload</option>
                    <option value="google_drive">Google Drive</option>
                    <option value="onedrive">OneDrive</option>
                    <option value="sharepoint">SharePoint</option>
                </select>
            </div>
        </div>
    </div>

    <div class="px-6">
    {{-- Stats Cards --}}
    <div class="max-w-7xl mx-auto mb-6 grid grid-cols-4 gap-4">
        <div class="glass rounded-2xl p-4 border border-gray-200/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Totale</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="allDocuments.length"></p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-4 border border-gray-200/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Completati</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="stats.completed"></p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-4 border border-gray-200/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">In elaborazione</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="stats.pending"></p>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-4 border border-gray-200/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Errori</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="stats.failed"></p>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk Actions Bar --}}
    <div x-show="selectedDocuments.length > 0" x-cloak class="max-w-7xl mx-auto mb-4">
        <div class="glass rounded-2xl border border-gray-200/50 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900">
                        <span x-text="selectedDocuments.length"></span>
                        <span x-text="selectedDocuments.length === 1 ? 'documento selezionato' : 'documenti selezionati'"></span>
                    </p>
                    <p class="text-xs text-gray-500">Seleziona azioni da eseguire</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="downloadSelected"
                    class="flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-xl hover:bg-blue-100 transition-all"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download
                </button>
                <button
                    @click="deleteSelected"
                    class="flex items-center gap-2 px-4 py-2 bg-red-50 text-red-700 rounded-xl hover:bg-red-100 transition-all"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Elimina
                </button>
                <button
                    @click="clearSelection"
                    class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-all"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Annulla
                </button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="max-w-7xl mx-auto">
        <div class="glass rounded-2xl border border-gray-200/50 overflow-hidden">
            <div class="overflow-hidden">
                <table class="w-full table-fixed">
                    <colgroup>
                        <col style="width: 5%;">
                        <col style="width: 22%;">
                        <col style="width: 8%;">
                        <col style="width: 10%;">
                        <col style="width: 12%;">
                        <col style="width: 8%;">
                        <col style="width: 12%;">
                        <col style="width: 11%;">
                        <col style="width: 12%;">
                    </colgroup>
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-4 text-center">
                                <input
                                    type="checkbox"
                                    @change="toggleSelectAll"
                                    :checked="selectedDocuments.length === filteredDocuments.length && filteredDocuments.length > 0"
                                    class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                >
                            </th>
                            <th class="px-4 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2 cursor-pointer" @click="sortBy('title')">
                                    Documento
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </div>
                            </th>
                            <th class="px-3 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th class="px-3 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2 cursor-pointer" @click="sortBy('file_size')">
                                    Dim.
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </div>
                            </th>
                            <th class="px-3 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Stato
                            </th>
                            <th class="px-3 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Chunk
                            </th>
                            <th class="px-3 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Fonte
                            </th>
                            <th class="px-3 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <div class="flex items-center gap-2 cursor-pointer" @click="sortBy('updated_at')">
                                    Modifica
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                    </svg>
                                </div>
                            </th>
                            <th class="px-3 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Azioni
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="doc in filteredDocuments" :key="doc.id">
                            <tr class="hover:bg-gray-50 transition-colors" :class="{ 'bg-indigo-50/30': isDocumentSelected(doc.id) }">
                                {{-- Checkbox --}}
                                <td class="px-4 py-4 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isDocumentSelected(doc.id)"
                                        @change="toggleDocumentSelection(doc)"
                                        class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                    >
                                </td>

                                {{-- Document Name --}}
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                            :class="getFileIconClass(doc.mime_type)"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 truncate" :title="doc.title" x-text="doc.title"></p>
                                            <p class="text-xs text-gray-500 truncate" :title="doc.original_filename" x-text="doc.original_filename"></p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Type --}}
                                <td class="px-3 py-4">
                                    <span class="text-xs text-gray-600 truncate block" x-text="getFileType(doc.mime_type)"></span>
                                </td>

                                {{-- Size --}}
                                <td class="px-3 py-4">
                                    <span class="text-xs text-gray-600 truncate block" x-text="formatSize(doc.file_size)"></span>
                                </td>

                                {{-- Status --}}
                                <td class="px-3 py-4">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap"
                                        :class="getStatusClass(doc.status)"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(doc.status)"></span>
                                        <span x-text="getStatusLabel(doc.status)"></span>
                                    </span>
                                </td>

                                {{-- Chunks --}}
                                <td class="px-3 py-4 text-center">
                                    <span class="text-xs text-gray-600" x-text="doc.chunk_count"></span>
                                </td>

                                {{-- Source --}}
                                <td class="px-3 py-4">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium whitespace-nowrap"
                                        :class="getSourceClass(doc.source_type)"
                                        :title="doc.source_name || getSourceLabel(doc.source_type)"
                                    >
                                        <span x-text="getSourceLabel(doc.source_type)"></span>
                                    </span>
                                </td>

                                {{-- Date --}}
                                <td class="px-3 py-4">
                                    <span class="text-xs text-gray-600 truncate block" x-text="formatDateShort(doc.updated_at)"></span>
                                </td>

                                {{-- Actions --}}
                                <td class="px-3 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="viewDocument(doc)"
                                            class="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                            title="Visualizza dettagli"
                                        >
                                            <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <a
                                            :href="`/documents/${doc.id}/download`"
                                            class="p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                            title="Download"
                                        >
                                            <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </a>
                                        <button
                                            @click="deleteDocument(doc)"
                                            class="p-2 rounded-lg hover:bg-red-50 transition-colors"
                                            title="Elimina"
                                        >
                                            <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty State --}}
                        <tr x-show="filteredDocuments.length === 0">
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-gray-900 font-medium">Nessun documento trovato</p>
                                        <p class="text-sm text-gray-500">Prova a modificare i filtri di ricerca</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Confirm Delete Modal --}}
    <div x-show="confirmModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="confirmModal.cancel()"></div>
        <div
            x-show="confirmModal.show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            class="relative glass rounded-2xl border border-gray-200/50 w-full max-w-md p-6 space-y-6"
        >
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900" x-text="confirmModal.title"></h3>
                    <p class="text-gray-500 mt-2" x-text="confirmModal.message"></p>
                </div>
            </div>
            <div class="flex gap-3">
                <button
                    @click="confirmModal.cancel()"
                    class="flex-1 px-6 py-3 text-sm font-semibold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-all"
                >
                    Annulla
                </button>
                <button
                    @click="confirmModal.confirm()"
                    class="flex-1 px-6 py-3 text-sm font-semibold text-white bg-red-500 rounded-xl hover:bg-red-600 transition-all hover:shadow-lg hover:shadow-red-500/25"
                >
                    Elimina
                </button>
            </div>
        </div>
    </div>

    {{-- Document Details Modal --}}
    <div x-show="showDetails" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showDetails = false"></div>
        <div
            x-show="showDetails"
            x-transition
            class="relative glass rounded-2xl border border-gray-200/50 w-full max-w-2xl max-h-[80vh] overflow-y-auto"
        >
            <div class="sticky top-0 glass border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Dettagli Documento</h3>
                <button @click="showDetails = false" class="p-2 rounded-xl hover:bg-gray-100 transition-all">
                    <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div x-show="selectedDocument" class="p-6 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase">Titolo</label>
                    <p class="mt-1 text-sm text-gray-900" x-text="selectedDocument?.title"></p>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase">Nome File</label>
                    <p class="mt-1 text-sm text-gray-900" x-text="selectedDocument?.original_filename"></p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Tipo</label>
                        <p class="mt-1 text-sm text-gray-900" x-text="getFileType(selectedDocument?.mime_type)"></p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Dimensione</label>
                        <p class="mt-1 text-sm text-gray-900" x-text="formatSize(selectedDocument?.file_size)"></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Stato</label>
                        <p class="mt-1">
                            <span
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
                                :class="getStatusClass(selectedDocument?.status)"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(selectedDocument?.status)"></span>
                                <span x-text="getStatusLabel(selectedDocument?.status)"></span>
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Chunks</label>
                        <p class="mt-1 text-sm text-gray-900" x-text="selectedDocument?.chunk_count"></p>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase">Fonte</label>
                    <p class="mt-1">
                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium"
                            :class="getSourceClass(selectedDocument?.source_type)"
                        >
                            <span x-text="getSourceLabel(selectedDocument?.source_type)"></span>
                        </span>
                        <span x-show="selectedDocument?.source_name" class="ml-2 text-sm text-gray-600" x-text="selectedDocument?.source_name"></span>
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Creato</label>
                        <p class="mt-1 text-sm text-gray-900" x-text="formatDate(selectedDocument?.created_at)"></p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Modificato</label>
                        <p class="mt-1 text-sm text-gray-900" x-text="formatDate(selectedDocument?.updated_at)"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast Notification --}}
    <div
        x-show="toast.show"
        x-transition
        class="fixed bottom-6 right-6 z-50 glass rounded-xl border border-gray-200/50 px-4 py-3 shadow-lg max-w-sm"
    >
        <div class="flex items-center gap-3">
            <div
                class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                :class="toast.type === 'success' ? 'bg-emerald-100' : 'bg-red-100'"
            >
                <svg x-show="toast.type === 'success'" class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg x-show="toast.type === 'error'" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900" x-text="toast.message"></p>
        </div>
    </div>
    </div>
</div>

<script>
function documentsManager() {
    return {
        allDocuments: @json($documents),
        filteredDocuments: [],
        selectedDocuments: [],
        searchQuery: '',
        statusFilter: '',
        sourceFilter: '',
        sortColumn: 'updated_at',
        sortDirection: 'desc',
        showDetails: false,
        selectedDocument: null,
        toast: { show: false, message: '', type: 'success' },
        confirmModal: {
            show: false, title: '', message: '', resolve: null,
            confirm() { this.show = false; if (this.resolve) this.resolve(true); },
            cancel() { this.show = false; if (this.resolve) this.resolve(false); },
        },

        init() {
            this.filterDocuments();
        },

        get stats() {
            return {
                completed: this.allDocuments.filter(d => d.status === 'completed').length,
                pending: this.allDocuments.filter(d => d.status === 'pending').length,
                failed: this.allDocuments.filter(d => d.status === 'failed').length,
            };
        },

        filterDocuments() {
            let docs = this.allDocuments;

            // Search filter
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                docs = docs.filter(d =>
                    d.title.toLowerCase().includes(query) ||
                    d.original_filename.toLowerCase().includes(query)
                );
            }

            // Status filter
            if (this.statusFilter) {
                docs = docs.filter(d => d.status === this.statusFilter);
            }

            // Source filter
            if (this.sourceFilter) {
                docs = docs.filter(d => d.source_type === this.sourceFilter);
            }

            this.filteredDocuments = docs;
            this.sortDocuments();
        },

        sortBy(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.sortDocuments();
        },

        sortDocuments() {
            this.filteredDocuments.sort((a, b) => {
                let aVal = a[this.sortColumn];
                let bVal = b[this.sortColumn];

                if (this.sortColumn === 'updated_at' || this.sortColumn === 'created_at') {
                    aVal = new Date(aVal);
                    bVal = new Date(bVal);
                }

                if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },

        viewDocument(doc) {
            this.selectedDocument = doc;
            this.showDetails = true;
        },

        async deleteDocument(doc) {
            const ok = await this.askConfirm(
                'Elimina documento',
                `Sei sicuro di voler eliminare "${doc.title}"? Questa azione non può essere annullata.`
            );
            if (!ok) return;

            try {
                const res = await fetch(`/documents/${doc.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                if (!res.ok) throw new Error('Errore durante l\'eliminazione');

                this.allDocuments = this.allDocuments.filter(d => d.id !== doc.id);
                this.filterDocuments();
                this.showToast('Documento eliminato con successo', 'success');
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },

        askConfirm(title, message) {
            return new Promise(resolve => {
                this.confirmModal = {
                    show: true, title, message, resolve,
                    confirm() { this.show = false; resolve(true); },
                    cancel() { this.show = false; resolve(false); },
                };
            });
        },

        // Bulk selection methods
        toggleSelectAll() {
            if (this.selectedDocuments.length === this.filteredDocuments.length) {
                this.selectedDocuments = [];
            } else {
                this.selectedDocuments = [...this.filteredDocuments];
            }
        },

        toggleDocumentSelection(doc) {
            const index = this.selectedDocuments.findIndex(d => d.id === doc.id);
            if (index >= 0) {
                this.selectedDocuments.splice(index, 1);
            } else {
                this.selectedDocuments.push(doc);
            }
        },

        isDocumentSelected(docId) {
            return this.selectedDocuments.some(d => d.id === docId);
        },

        clearSelection() {
            this.selectedDocuments = [];
        },

        async downloadSelected() {
            if (this.selectedDocuments.length === 0) return;

            for (const doc of this.selectedDocuments) {
                const link = document.createElement('a');
                link.href = `/documents/${doc.id}/download`;
                link.download = doc.original_filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Small delay between downloads to avoid browser blocking
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            this.showToast(`${this.selectedDocuments.length} ${this.selectedDocuments.length === 1 ? 'documento scaricato' : 'documenti scaricati'}`, 'success');
        },

        async deleteSelected() {
            if (this.selectedDocuments.length === 0) return;

            const count = this.selectedDocuments.length;
            const ok = await this.askConfirm(
                'Elimina documenti',
                `Sei sicuro di voler eliminare ${count} ${count === 1 ? 'documento' : 'documenti'}? Questa azione non può essere annullata.`
            );
            if (!ok) return;

            let deletedCount = 0;
            const errors = [];

            for (const doc of this.selectedDocuments) {
                try {
                    const res = await fetch(`/documents/${doc.id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });

                    if (!res.ok) throw new Error(`Errore eliminazione ${doc.title}`);

                    this.allDocuments = this.allDocuments.filter(d => d.id !== doc.id);
                    deletedCount++;
                } catch (e) {
                    errors.push(doc.title);
                }
            }

            this.selectedDocuments = [];
            this.filterDocuments();

            if (errors.length === 0) {
                this.showToast(`${deletedCount} ${deletedCount === 1 ? 'documento eliminato' : 'documenti eliminati'} con successo`, 'success');
            } else {
                this.showToast(`${deletedCount} eliminati, ${errors.length} errori`, 'error');
            }
        },

        getFileIconClass(mimeType) {
            if (!mimeType) return 'bg-gray-100 text-gray-600';
            if (mimeType.includes('pdf')) return 'bg-red-100 text-red-600';
            if (mimeType.includes('word') || mimeType.includes('document')) return 'bg-blue-100 text-blue-600';
            if (mimeType.includes('sheet') || mimeType.includes('excel') || mimeType.includes('csv')) return 'bg-emerald-100 text-emerald-600';
            if (mimeType.includes('image')) return 'bg-purple-100 text-purple-600';
            return 'bg-gray-100 text-gray-600';
        },

        getFileType(mimeType) {
            if (!mimeType) return 'N/A';
            if (mimeType.includes('pdf')) return 'PDF';
            if (mimeType.includes('word') || mimeType.includes('document')) return 'Word';
            if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'Excel';
            if (mimeType.includes('csv')) return 'CSV';
            if (mimeType.includes('image')) return 'Immagine';
            if (mimeType.includes('text')) return 'Testo';
            return 'Altro';
        },

        getStatusClass(status) {
            const map = {
                completed: 'bg-emerald-100 text-emerald-700',
                pending: 'bg-amber-100 text-amber-700',
                failed: 'bg-red-100 text-red-700',
            };
            return map[status] || 'bg-gray-100 text-gray-700';
        },

        getStatusDotClass(status) {
            const map = {
                completed: 'bg-emerald-500',
                pending: 'bg-amber-500',
                failed: 'bg-red-500',
            };
            return map[status] || 'bg-gray-500';
        },

        getStatusLabel(status) {
            const map = {
                completed: 'Completato',
                pending: 'In elaborazione',
                failed: 'Errore',
            };
            return map[status] || status;
        },

        getSourceClass(source) {
            const map = {
                upload: 'bg-indigo-100 text-indigo-700',
                google_drive: 'bg-blue-100 text-blue-700',
                onedrive: 'bg-sky-100 text-sky-700',
                sharepoint: 'bg-teal-100 text-teal-700',
            };
            return map[source] || 'bg-gray-100 text-gray-700';
        },

        getSourceLabel(source) {
            const map = {
                upload: 'Upload',
                google_drive: 'GDrive',
                onedrive: 'OneDrive',
                sharepoint: 'SharePoint',
            };
            return map[source] || source;
        },

        formatSize(bytes) {
            if (!bytes) return 'N/A';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        formatDateShort(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / 86400000);

            // Se è oggi o ieri
            if (diffDays === 0) return 'Oggi';
            if (diffDays === 1) return 'Ieri';
            if (diffDays < 7) return diffDays + 'g fa';

            // Altrimenti formato compatto
            return date.toLocaleDateString('it-IT', {
                day: '2-digit',
                month: '2-digit',
                year: '2-digit'
            });
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => this.toast.show = false, 4000);
        },
    };
}
</script>
@endsection
