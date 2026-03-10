@extends('layouts.app')

@section('content')
<div x-data="app()" class="h-screen flex overflow-hidden">

    {{-- Toast Notification --}}
    <div
        x-show="toast.show"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-4 rounded-2xl shadow-2xl text-sm font-medium"
        :class="{
            'bg-red-500 text-white': toast.type === 'error',
            'bg-emerald-500 text-white': toast.type === 'success',
        }"
    >
        <template x-if="toast.type === 'success'">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        </template>
        <template x-if="toast.type === 'error'">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </template>
        <span x-text="toast.message"></span>
        <button @click="toast.show = false" class="ml-2 hover:opacity-70 transition-opacity">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    {{-- Confirm Modal --}}
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
            class="relative bg-white rounded-3xl shadow-2xl p-8 w-full max-w-md"
        >
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900" x-text="confirmModal.title"></h3>
                <p class="text-gray-500 mt-2" x-text="confirmModal.message"></p>
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

    {{-- Sidebar --}}
    <aside class="w-96 bg-white/80 backdrop-blur-xl border-r border-gray-200/50 flex flex-col h-full shadow-xl">
        {{-- Header --}}
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl gradient-bg flex items-center justify-center shadow-lg shadow-purple-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">DocuMind AI</h1>
                    <p class="text-sm text-gray-500">Intelligent Document Assistant</p>
                </div>
            </div>
        </div>

        {{-- Upload Area --}}
        <div class="p-4">
            <div
                @dragover.prevent="dragover = true"
                @dragleave="dragover = false"
                @drop.prevent="handleDrop($event)"
                :class="dragover ? 'upload-zone dragover scale-[1.02]' : 'upload-zone'"
                class="rounded-2xl p-6 text-center cursor-pointer transition-all duration-300"
                @click="$refs.fileInput.click()"
            >
                <input
                    type="file"
                    x-ref="fileInput"
                    @change="uploadFile($event.target.files[0])"
                    accept=".pdf,.txt,.xls,.xlsx,.csv,.jpg,.jpeg,.doc,.docx,.zip"
                    class="hidden"
                >
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/30">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-700 mb-1">
                    Trascina i tuoi documenti qui
                </p>
                <p class="text-xs text-gray-500">o clicca per selezionare</p>
                <div class="flex flex-wrap justify-center gap-1 mt-3">
                    <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-500">PDF</span>
                    <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-500">DOCX</span>
                    <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-500">XLS</span>
                    <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-500">TXT</span>
                    <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-500">JPG</span>
                    <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-500">ZIP</span>
                </div>
            </div>

            {{-- Upload Progress --}}
            <div x-show="uploading" x-cloak class="mt-4 p-4 bg-indigo-50 rounded-2xl">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-medium text-indigo-700">Caricamento in corso...</span>
                    <span class="font-bold text-indigo-700" x-text="uploadProgress + '%'"></span>
                </div>
                <div class="w-full bg-indigo-100 rounded-full h-2 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                </div>
            </div>

            {{-- Processing Progress --}}
            <div x-show="processingCount > 0" x-cloak class="mt-4 p-4 bg-emerald-50 rounded-2xl">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-medium text-emerald-700">Elaborazione documenti...</span>
                    <span class="font-bold text-emerald-700" x-text="processedCount + '/' + totalProcessing"></span>
                </div>
                <div class="w-full bg-emerald-100 rounded-full h-2 overflow-hidden">
                    <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-2 rounded-full transition-all duration-300" :style="'width: ' + processingProgress + '%'"></div>
                </div>
            </div>

            {{-- Upload Error --}}
            <div x-show="uploadError" x-cloak class="mt-4 p-4 bg-red-50 rounded-2xl text-sm text-red-600 font-medium" x-text="uploadError"></div>
        </div>

        {{-- Search --}}
        <div class="px-4 pb-3">
            <div class="relative">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    x-model="searchQuery"
                    type="text"
                    placeholder="Cerca documenti..."
                    class="w-full pl-12 pr-4 py-3 bg-gray-50 border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all"
                >
            </div>
        </div>

        {{-- Filters --}}
        <div class="px-4 pb-4 flex gap-2">
            <button
                @click="statusFilter = 'all'"
                :class="statusFilter === 'all' ? 'bg-gray-900 text-white shadow-lg' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
            >
                Tutti <span class="opacity-60" x-text="documents.length"></span>
            </button>
            <button
                @click="statusFilter = 'ready'"
                :class="statusFilter === 'ready' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
            >
                Pronti <span class="opacity-60" x-text="documents.filter(d => d.status === 'ready').length"></span>
            </button>
            <button
                @click="statusFilter = 'pending'"
                :class="statusFilter === 'pending' ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/30' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
            >
                In coda <span class="opacity-60" x-text="documents.filter(d => d.status === 'pending' || d.status === 'processing').length"></span>
            </button>
        </div>

        {{-- Document List --}}
        <div class="flex-1 overflow-y-auto px-4 pb-4 scrollbar-thin">
            <template x-for="doc in filteredDocuments" :key="doc.id">
                <div class="doc-card p-4 rounded-2xl mb-2 border border-transparent hover:border-indigo-100 group">
                    <div class="flex items-start gap-3">
                        {{-- File Icon --}}
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                            :class="{
                                'bg-red-100 text-red-600': doc.mime_type?.includes('pdf'),
                                'bg-blue-100 text-blue-600': doc.mime_type?.includes('word') || doc.mime_type?.includes('document'),
                                'bg-emerald-100 text-emerald-600': doc.mime_type?.includes('sheet') || doc.mime_type?.includes('excel') || doc.mime_type?.includes('csv'),
                                'bg-purple-100 text-purple-600': doc.mime_type?.includes('image'),
                                'bg-gray-100 text-gray-600': !doc.mime_type
                            }"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate" x-text="doc.title"></p>
                            <p class="text-xs text-gray-500 truncate mt-0.5" x-text="doc.original_filename"></p>
                            <div class="flex items-center gap-2 mt-2">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium"
                                    :class="{
                                        'bg-amber-100 text-amber-700': doc.status === 'pending',
                                        'bg-blue-100 text-blue-700': doc.status === 'processing',
                                        'bg-emerald-100 text-emerald-700': doc.status === 'ready',
                                        'bg-red-100 text-red-700': doc.status === 'failed',
                                    }"
                                >
                                    <span x-show="doc.status === 'processing'" class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                    <span x-text="doc.status === 'ready' ? 'Pronto' : doc.status === 'pending' ? 'In attesa' : doc.status === 'processing' ? 'Elaborazione' : 'Errore'"></span>
                                </span>
                                {{-- Source badge --}}
                                <span
                                    x-show="doc.source_type && doc.source_type !== 'upload'"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium"
                                    :class="{
                                        'bg-blue-50 text-blue-600': doc.source_type === 'google_drive',
                                        'bg-sky-50 text-sky-600': doc.source_type === 'onedrive',
                                        'bg-teal-50 text-teal-600': doc.source_type === 'sharepoint',
                                        'bg-indigo-50 text-indigo-600': doc.source_type === 'dropbox',
                                    }"
                                >
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" /></svg>
                                    <span x-text="doc.source_type === 'google_drive' ? 'GDrive' : doc.source_type === 'onedrive' ? 'OneDrive' : doc.source_type === 'sharepoint' ? 'SharePoint' : doc.source_type === 'dropbox' ? 'Dropbox' : ''"></span>
                                </span>
                                <span class="text-xs text-gray-400" x-text="formatSize(doc.file_size)"></span>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a
                                :href="'/documents/' + doc.id + '/preview'"
                                target="_blank"
                                @click.stop
                                class="p-2 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all"
                                title="Anteprima"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </a>
                            <a
                                :href="'/documents/' + doc.id + '/download'"
                                @click.stop
                                class="p-2 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-all"
                                title="Scarica"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </a>
                            <button
                                @click.stop="deleteDocument(doc.id)"
                                class="p-2 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-all"
                                title="Elimina"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Empty State --}}
            <div x-show="filteredDocuments.length === 0" class="text-center py-12">
                <div class="w-20 h-20 rounded-3xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <p class="text-gray-500 font-medium" x-show="documents.length === 0">Nessun documento caricato</p>
                <p class="text-gray-500 font-medium" x-show="documents.length > 0">Nessun risultato</p>
                <p class="text-gray-400 text-sm mt-1" x-show="documents.length === 0">Carica il tuo primo documento per iniziare</p>
            </div>
        </div>
    </aside>

    {{-- Main Chat Area --}}
    <main class="flex-1 flex flex-col h-full bg-gradient-to-br from-slate-50 via-white to-indigo-50/30">
        {{-- Chat Header --}}
        <div class="px-8 py-6 border-b border-gray-100 bg-white/50 backdrop-blur-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Chat con i tuoi documenti</h2>
                    <p class="text-gray-500 mt-1">Fai domande e ottieni risposte basate sui documenti caricati</p>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-emerald-50 rounded-xl">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-sm font-medium text-emerald-700">AI Attiva</span>
                </div>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-8 scrollbar-thin" x-ref="chatContainer">
            <div class="max-w-4xl mx-auto space-y-6">
                <template x-for="(msg, i) in messages" :key="i">
                    <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'" class="animate-fade-in">
                        {{-- AI Avatar --}}
                        <template x-if="msg.role === 'assistant'">
                            <div class="w-10 h-10 rounded-2xl gradient-bg flex items-center justify-center mr-3 shrink-0 shadow-lg shadow-purple-500/20">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                            </div>
                        </template>

                        <div
                            :class="msg.role === 'user'
                                ? 'chat-bubble-user text-white rounded-3xl rounded-br-lg'
                                : 'chat-bubble-ai text-gray-800 rounded-3xl rounded-bl-lg border border-gray-100'"
                            class="max-w-2xl px-6 py-4"
                        >
                            <div class="text-sm leading-relaxed whitespace-pre-wrap" x-html="msg.role === 'assistant' ? renderMarkdown(msg.content) : msg.content"></div>

                            {{-- Source Documents --}}
                            <template x-if="msg.sources && msg.sources.length > 0">
                                <div class="mt-4 pt-4 border-t" :class="msg.role === 'user' ? 'border-white/20' : 'border-gray-100'">
                                    <p class="text-xs font-semibold mb-3 flex items-center gap-2" :class="msg.role === 'user' ? 'text-white/70' : 'text-gray-500'">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                        </svg>
                                        Fonti utilizzate
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="src in msg.sources" :key="src.id">
                                            <a
                                                :href="'/documents/' + src.id + '/preview'"
                                                target="_blank"
                                                class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium transition-all"
                                                :class="msg.role === 'user'
                                                    ? 'bg-white/20 text-white hover:bg-white/30'
                                                    : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="truncate max-w-[150px]" x-text="src.original_filename"></span>
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- User Avatar --}}
                        <template x-if="msg.role === 'user'">
                            <div class="w-10 h-10 rounded-2xl bg-gray-200 flex items-center justify-center ml-3 shrink-0">
                                <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Streaming --}}
                <div x-show="streaming" class="flex justify-start animate-fade-in">
                    <div class="w-10 h-10 rounded-2xl gradient-bg flex items-center justify-center mr-3 shrink-0 shadow-lg shadow-purple-500/20">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                    <div class="chat-bubble-ai text-gray-800 rounded-3xl rounded-bl-lg border border-gray-100 max-w-2xl px-6 py-4">
                        <div x-show="!streamingContent" class="typing-indicator flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                        </div>
                        <div x-show="streamingContent" class="text-sm leading-relaxed whitespace-pre-wrap" x-html="renderMarkdown(streamingContent)"></div>
                        <span x-show="streamingContent" class="inline-block w-0.5 h-5 bg-indigo-500 animate-pulse ml-0.5 align-middle"></span>
                    </div>
                </div>

                {{-- Empty State --}}
                <div x-show="messages.length === 0 && !streaming" class="flex items-center justify-center min-h-[400px]">
                    <div class="text-center max-w-md">
                        <div class="w-24 h-24 rounded-3xl gradient-bg flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-purple-500/30 animate-float">
                            <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Inizia una conversazione</h3>
                        <p class="text-gray-500 mb-6">Carica i tuoi documenti e chiedi qualsiasi cosa. L'AI analizzerà il contenuto e ti fornirà risposte precise.</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            <span class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-medium">"Riassumi il documento"</span>
                            <span class="px-4 py-2 bg-purple-50 text-purple-700 rounded-xl text-sm font-medium">"Quali sono i punti chiave?"</span>
                            <span class="px-4 py-2 bg-pink-50 text-pink-700 rounded-xl text-sm font-medium">"Cerca informazioni su..."</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Input Area --}}
        <div class="p-6 border-t border-gray-100 bg-white/80 backdrop-blur-sm">
            <form @submit.prevent="sendMessage" class="max-w-4xl mx-auto">
                <div class="flex gap-4">
                    <div class="flex-1 relative">
                        <input
                            x-model="input"
                            type="text"
                            placeholder="Scrivi la tua domanda..."
                            class="w-full px-6 py-4 bg-gray-50 border-0 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all pr-12"
                            :disabled="streaming"
                        >
                        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-gray-400">
                            <kbd class="px-2 py-1 bg-gray-100 rounded">Enter</kbd>
                        </div>
                    </div>
                    <button
                        x-show="!streaming"
                        type="submit"
                        :disabled="!input.trim()"
                        class="btn-primary text-white rounded-2xl px-8 py-4 text-sm font-semibold flex items-center gap-2"
                    >
                        <span>Invia</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                    <button
                        x-show="streaming"
                        x-cloak
                        type="button"
                        @click="stopStreaming()"
                        class="bg-red-500 hover:bg-red-600 text-white rounded-2xl px-8 py-4 text-sm font-semibold flex items-center gap-2 transition-all shadow-lg shadow-red-500/30"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="6" width="12" height="12" rx="2" />
                        </svg>
                        <span>Stop</span>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
function app() {
    return {
        documents: @json($documents),
        messages: [],
        input: '',
        streaming: false,
        streamingContent: '',
        streamingSources: [],
        uploading: false,
        uploadProgress: 0,
        uploadError: '',
        dragover: false,
        abortController: null,
        searchQuery: '',
        statusFilter: 'all',
        totalProcessing: 0,
        toast: { show: false, message: '', type: 'error' },
        confirmModal: {
            show: false, title: '', message: '', resolve: null,
            confirm() { this.show = false; if (this.resolve) this.resolve(true); },
            cancel() { this.show = false; if (this.resolve) this.resolve(false); },
        },

        get filteredDocuments() {
            return this.documents.filter(doc => {
                const matchesSearch = this.searchQuery === '' ||
                    doc.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    doc.original_filename.toLowerCase().includes(this.searchQuery.toLowerCase());

                let matchesStatus;
                if (this.statusFilter === 'all') {
                    matchesStatus = true;
                } else if (this.statusFilter === 'pending') {
                    matchesStatus = doc.status === 'pending' || doc.status === 'processing';
                } else {
                    matchesStatus = doc.status === this.statusFilter;
                }

                return matchesSearch && matchesStatus;
            });
        },

        get pendingCount() {
            return this.documents.filter(d => d.status === 'pending').length;
        },

        get processingCount() {
            return this.documents.filter(d => d.status === 'processing' || d.status === 'pending').length;
        },

        get processedCount() {
            return this.totalProcessing - this.processingCount;
        },

        get processingProgress() {
            if (this.totalProcessing === 0) return 0;
            return Math.round((this.processedCount / this.totalProcessing) * 100);
        },

        handleDrop(e) {
            this.dragover = false;
            const file = e.dataTransfer.files[0];
            if (file) this.uploadFile(file);
        },

        async uploadFile(file) {
            if (!file) return;

            this.uploading = true;
            this.uploadProgress = 0;
            this.uploadError = '';

            const formData = new FormData();
            formData.append('file', file);

            try {
                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                    }
                });

                const response = await new Promise((resolve, reject) => {
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(JSON.parse(xhr.responseText));
                        } else {
                            const err = JSON.parse(xhr.responseText);
                            reject(new Error(err.message || 'Upload fallito'));
                        }
                    };
                    xhr.onerror = () => reject(new Error('Errore di rete'));
                    xhr.open('POST', '{{ route("documents.store") }}');
                    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.send(formData);
                });

                const newDocs = response.documents || [response.document];
                this.totalProcessing = this.processingCount + newDocs.length;

                newDocs.forEach(doc => {
                    this.documents.unshift(doc);
                    this.pollDocumentStatus(doc.id);
                });

                this.showToast('Documento caricato con successo', 'success');
            } catch (e) {
                this.uploadError = e.message;
                this.showToast(e.message, 'error');
            } finally {
                this.uploading = false;
                this.uploadProgress = 0;
                this.$refs.fileInput.value = '';
            }
        },

        pollDocumentStatus(docId) {
            const interval = setInterval(async () => {
                try {
                    const res = await fetch(`/documents/${docId}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const doc = await res.json();
                    const idx = this.documents.findIndex(d => d.id === docId);
                    if (idx !== -1) {
                        this.documents[idx] = doc;
                    }
                    if (doc.status === 'ready' || doc.status === 'failed') {
                        clearInterval(interval);
                        if (this.processingCount === 0) {
                            this.totalProcessing = 0;
                        }
                        if (doc.status === 'ready') {
                            this.showToast(`"${doc.title}" pronto per le domande`, 'success');
                        }
                    }
                } catch {
                    clearInterval(interval);
                }
            }, 3000);
        },

        showToast(message, type = 'error') {
            this.toast = { show: true, message, type };
            setTimeout(() => this.toast.show = false, 4000);
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

        async deleteDocument(id) {
            const ok = await this.askConfirm('Elimina documento', 'Sei sicuro di voler eliminare questo documento? Questa azione non può essere annullata.');
            if (!ok) return;

            try {
                await fetch(`/documents/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                this.documents = this.documents.filter(d => d.id !== id);
                this.showToast('Documento eliminato', 'success');
            } catch (e) {
                this.showToast('Errore durante l\'eliminazione', 'error');
            }
        },

        stopStreaming() {
            if (this.abortController) {
                this.abortController.abort();
            }
        },

        async sendMessage() {
            const text = this.input.trim();
            if (!text) return;

            this.messages.push({ role: 'user', content: text });
            this.input = '';
            this.streaming = true;
            this.streamingContent = '';
            this.streamingSources = [];

            this.$nextTick(() => this.scrollToBottom());

            this.abortController = new AbortController();

            try {
                const res = await fetch('{{ route("chat") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'text/event-stream',
                    },
                    body: JSON.stringify({ message: text }),
                    signal: this.abortController.signal,
                });

                const reader = res.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n');

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.slice(6);
                            if (data === '[DONE]') continue;
                            try {
                                const parsed = JSON.parse(data);
                                if (parsed.sources) {
                                    this.streamingSources = parsed.sources;
                                } else if (parsed.text) {
                                    this.streamingContent += parsed.text;
                                    this.scrollToBottom();
                                }
                            } catch {}
                        }
                    }
                }

                if (this.streamingContent) {
                    this.messages.push({
                        role: 'assistant',
                        content: this.streamingContent,
                        sources: this.streamingSources,
                    });
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    if (this.streamingContent) {
                        this.messages.push({
                            role: 'assistant',
                            content: this.streamingContent,
                            sources: this.streamingSources,
                        });
                    }
                } else {
                    this.messages.push({ role: 'assistant', content: 'Mi dispiace, si è verificato un errore. Riprova tra poco.' });
                }
            } finally {
                this.streaming = false;
                this.streamingContent = '';
                this.streamingSources = [];
                this.abortController = null;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.chatContainer;
                if (container) container.scrollTop = container.scrollHeight;
            });
        },

        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        renderMarkdown(text) {
            if (!text) return '';
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\*\*(.*?)\*\*/g, '<strong class="font-semibold">$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code class="bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded text-sm font-mono">$1</code>')
                .replace(/\n/g, '<br>');
        }
    };
}
</script>
@endsection
