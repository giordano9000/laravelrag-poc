@extends('layouts.app')

@section('content')
<div x-data="sourcesApp()" class="min-h-screen">

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
                <button @click="confirmModal.cancel()" class="flex-1 px-6 py-3 text-sm font-semibold text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-all">Annulla</button>
                <button @click="confirmModal.confirm()" class="flex-1 px-6 py-3 text-sm font-semibold text-white bg-red-500 rounded-xl hover:bg-red-600 transition-all hover:shadow-lg hover:shadow-red-500/25">Elimina</button>
            </div>
        </div>
    </div>

    {{-- Add Connection Modal --}}
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAddModal = false"></div>
        <div
            x-show="showAddModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            class="relative bg-white rounded-3xl shadow-2xl p-8 w-full max-w-lg"
        >
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">Aggiungi Connessione</h3>
                <button @click="showAddModal = false" class="p-2 rounded-xl hover:bg-gray-100 transition-all">
                    <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form @submit.prevent="createConnection">
                {{-- Provider Selection --}}
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Provider</label>
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="p in providers" :key="p.value">
                            <button
                                type="button"
                                @click="newConnection.provider = p.value"
                                :class="newConnection.provider === p.value ? 'ring-2 ring-indigo-500 bg-indigo-50 border-indigo-200' : 'border-gray-200 hover:bg-gray-50'"
                                class="flex items-center gap-3 p-4 rounded-xl border transition-all"
                            >
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="p.bgClass">
                                    <span x-html="p.icon" class="w-5 h-5"></span>
                                </div>
                                <span class="text-sm font-semibold text-gray-900" x-text="p.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Name --}}
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nome connessione</label>
                    <input x-model="newConnection.name" type="text" required placeholder="es. Il mio Google Drive" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all">
                </div>

                {{-- Client ID --}}
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Client ID</label>
                    <input x-model="newConnection.client_id" type="text" required placeholder="OAuth Client ID" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all font-mono">
                </div>

                {{-- Client Secret --}}
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Client Secret</label>
                    <input x-model="newConnection.client_secret" type="password" required placeholder="OAuth Client Secret" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all font-mono">
                </div>

                {{-- SharePoint: Tenant ID --}}
                <template x-if="newConnection.provider === 'sharepoint'">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Tenant ID</label>
                        <input x-model="newConnection.metadata.tenant_id" type="text" required placeholder="Azure AD Tenant ID" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all font-mono">
                    </div>
                </template>

                {{-- SharePoint: Site ID --}}
                <template x-if="newConnection.provider === 'sharepoint'">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Site ID</label>
                        <input x-model="newConnection.metadata.site_id" type="text" required placeholder="SharePoint Site ID" class="w-full px-4 py-3 bg-gray-50 border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all font-mono">
                    </div>
                </template>

                <button
                    type="submit"
                    :disabled="creatingConnection"
                    class="w-full btn-primary text-white rounded-xl px-6 py-3 text-sm font-semibold mt-2"
                >
                    <span x-show="!creatingConnection">Crea Connessione</span>
                    <span x-show="creatingConnection">Creazione...</span>
                </button>
            </form>
        </div>
    </div>

    {{-- File Browser Modal --}}
    <div x-show="showBrowser" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeBrowser"></div>
        <div
            x-show="showBrowser"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            class="relative bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col"
        >
            {{-- Browser Header --}}
            <div class="p-6 border-b border-gray-100 flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Esplora File</h3>
                    <p class="text-sm text-gray-500 mt-1" x-text="browsingConnection?.name"></p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        @click="importSelected"
                        :disabled="selectedFiles.length === 0 || importing"
                        class="btn-primary text-white rounded-xl px-5 py-2.5 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        <span x-text="importing ? 'Importazione...' : 'Importa Selezionati (' + selectedFiles.length + ')'"></span>
                    </button>
                    <button @click="closeBrowser" class="p-2 rounded-xl hover:bg-gray-100 transition-all">
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>

            {{-- Breadcrumb --}}
            <div class="px-6 py-3 border-b border-gray-50 flex items-center gap-2 text-sm shrink-0 overflow-x-auto">
                <button @click="navigateToFolder('', 'Root')" class="text-indigo-600 hover:underline font-medium">Root</button>
                <template x-for="(crumb, i) in breadcrumbs" :key="i">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        <button @click="navigateToFolder(crumb.id, crumb.name)" class="text-indigo-600 hover:underline font-medium" x-text="crumb.name"></button>
                    </div>
                </template>
            </div>

            {{-- File List --}}
            <div class="flex-1 overflow-y-auto p-4 scrollbar-thin">
                <div x-show="loadingFiles" class="flex items-center justify-center py-12">
                    <div class="flex items-center gap-3 text-gray-500">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>Caricamento...</span>
                    </div>
                </div>

                <div x-show="!loadingFiles && browserItems.length === 0" class="text-center py-12 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                    <p>Cartella vuota</p>
                </div>

                <div x-show="!loadingFiles" class="space-y-1">
                    <template x-for="item in browserItems" :key="item.id">
                        <div
                            @click="item.type === 'folder' ? navigateToFolder(item.id, item.name) : (isFileSupported(item) ? toggleFileSelection(item) : null)"
                            class="flex items-center gap-4 p-3 rounded-xl transition-all"
                            :class="{
                                'hover:bg-gray-50 cursor-pointer': item.type === 'folder',
                                'hover:bg-indigo-50 cursor-pointer': item.type === 'file' && isFileSupported(item) && !isFileSelected(item.id),
                                'bg-indigo-50 ring-1 ring-indigo-200': isFileSelected(item.id),
                                'opacity-40 cursor-not-allowed': item.type === 'file' && !isFileSupported(item),
                            }"
                        >
                            {{-- Checkbox for files --}}
                            <div class="shrink-0">
                                <template x-if="item.type === 'file'">
                                    <div
                                        class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all"
                                        :class="isFileSelected(item.id) ? 'bg-indigo-500 border-indigo-500' : 'border-gray-300'"
                                    >
                                        <svg x-show="isFileSelected(item.id)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                </template>
                                <template x-if="item.type === 'folder'">
                                    <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" /></svg>
                                </template>
                            </div>

                            {{-- File icon --}}
                            <div
                                x-show="item.type === 'file'"
                                class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                :class="getFileIconClass(item)"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-medium text-gray-900 truncate" x-text="item.name"></p>
                                    <span x-show="item.type === 'file' && !isFileSupported(item)" class="shrink-0 px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 rounded">Non supportato</span>
                                </div>
                                <div class="flex items-center gap-3 mt-0.5">
                                    <span x-show="item.size" class="text-xs text-gray-500" x-text="formatSize(item.size)"></span>
                                    <span x-show="item.modifiedAt" class="text-xs text-gray-400" x-text="formatDate(item.modifiedAt)"></span>
                                </div>
                            </div>

                            {{-- Arrow for folders --}}
                            <svg x-show="item.type === 'folder'" class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Page Header --}}
    <div class="bg-white/80 backdrop-blur-xl border-b border-gray-200/50 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ route('dashboard') }}" class="p-2 rounded-xl hover:bg-gray-100 transition-all" title="Torna alla Dashboard">
                        <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Fonti Documentali</h1>
                        <p class="text-sm text-gray-500 mt-1">Collega servizi cloud per importare documenti automaticamente</p>
                    </div>
                </div>
                <button
                    @click="openAddModal"
                    class="btn-primary text-white rounded-xl px-6 py-3 text-sm font-semibold flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Aggiungi Connessione
                </button>
            </div>
        </div>
    </div>

    {{-- Connection List --}}
    <div class="max-w-7xl mx-auto px-6 py-8">
        {{-- Empty State --}}
        <div x-show="connections.length === 0" class="text-center py-20">
            <div class="w-24 h-24 rounded-3xl bg-gray-100 flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" /></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Nessuna connessione configurata</h3>
            <p class="text-gray-500 mb-6">Collega Google Drive, OneDrive, SharePoint o Dropbox per importare documenti.</p>
            <button @click="openAddModal" class="btn-primary text-white rounded-xl px-6 py-3 text-sm font-semibold inline-flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Aggiungi Connessione
            </button>
        </div>

        {{-- Connection Cards --}}
        <div x-show="connections.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="conn in connections" :key="conn.id">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-6">
                    {{-- Provider Header --}}
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center" :class="getProviderBgClass(conn.provider)">
                                <span x-html="getProviderIcon(conn.provider)" class="w-6 h-6"></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900" x-text="conn.name"></h3>
                                <p class="text-xs text-gray-500" x-text="getProviderLabel(conn.provider)"></p>
                            </div>
                        </div>
                        <span
                            class="px-3 py-1 rounded-lg text-xs font-semibold"
                            :class="{
                                'bg-amber-100 text-amber-700': conn.status === 'pending',
                                'bg-emerald-100 text-emerald-700': conn.status === 'connected',
                                'bg-red-100 text-red-700': conn.status === 'expired',
                                'bg-gray-100 text-gray-600': conn.status === 'disconnected',
                            }"
                            x-text="getStatusLabel(conn.status)"
                        ></span>
                    </div>

                    {{-- Info --}}
                    <div class="text-xs text-gray-400 mb-5 space-y-1">
                        <p x-show="conn.last_synced_at">
                            Ultimo sync: <span x-text="formatDate(conn.last_synced_at)"></span>
                        </p>
                        <p x-show="!conn.last_synced_at">Mai sincronizzato</p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-col gap-2">
                        <div class="flex flex-wrap gap-2">
                            <template x-if="conn.status === 'pending' || conn.status === 'expired'">
                                <button
                                    @click="startAuth(conn)"
                                    class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-semibold hover:bg-indigo-100 transition-all"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                    Connetti
                                </button>
                            </template>
                            <template x-if="conn.status === 'connected'">
                                <button
                                    @click="openBrowser(conn)"
                                    class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-semibold hover:bg-indigo-100 transition-all"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                    Esplora
                                </button>
                            </template>
                            <button
                                @click="deleteConnection(conn.id)"
                                class="flex items-center justify-center gap-2 px-4 py-2.5 bg-red-50 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-100 transition-all"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>

                        {{-- Sync buttons with tooltips --}}
                        <template x-if="conn.status === 'connected'">
                            <div class="flex gap-2">
                                <div class="flex-1 relative group">
                                    <button
                                        @click="syncConnection(conn, false)"
                                        class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-emerald-50 text-emerald-700 rounded-xl text-xs font-semibold hover:bg-emerald-100 transition-all"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                        Sync
                                    </button>
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                        Aggiorna solo i file già importati modificati
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                                <div class="flex-1 relative group">
                                    <button
                                        @click="syncConnection(conn, true)"
                                        class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl text-xs font-semibold hover:bg-blue-100 transition-all"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        Sync Completo
                                    </button>
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                        Importa anche nuovi file dalla cartella root
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function sourcesApp() {
    return {
        connections: @json($connections),
        showAddModal: false,
        showBrowser: false,
        creatingConnection: false,
        loadingFiles: false,
        importing: false,
        browsingConnection: null,
        browserItems: [],
        breadcrumbs: [],
        selectedFiles: [],
        toast: { show: false, message: '', type: 'error' },
        confirmModal: {
            show: false, title: '', message: '', resolve: null,
            confirm() { this.show = false; if (this.resolve) this.resolve(true); },
            cancel() { this.show = false; if (this.resolve) this.resolve(false); },
        },
        newConnection: {
            name: '',
            provider: 'google_drive',
            client_id: '',
            client_secret: '',
            metadata: { tenant_id: '', site_id: '' },
        },

        providers: [
            {
                value: 'google_drive',
                label: 'Google Drive',
                bgClass: 'bg-blue-100',
                icon: '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-blue-600"><path d="M7.71 3.5L1.15 15l3.43 5.98h6.56l-3.43-5.98L7.71 3.5zm1.14 0l6.56 11.5H22l-6.56-11.5H8.85zm7.57 12.5H9.86L6.43 22h13.14l3.43-6h-6.58z"/></svg>'
            },
            {
                value: 'onedrive',
                label: 'OneDrive',
                bgClass: 'bg-sky-100',
                icon: '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-sky-600"><path d="M10.5 18h8.25a4.5 4.5 0 10-1.08-8.87A6 6 0 006 10.5c0 .32.03.64.08.95A4.5 4.5 0 006 19.5h4.5z"/></svg>'
            },
            {
                value: 'sharepoint',
                label: 'SharePoint',
                bgClass: 'bg-teal-100',
                icon: '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-teal-600"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>'
            },
            {
                value: 'dropbox',
                label: 'Dropbox',
                bgClass: 'bg-indigo-100',
                icon: '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-indigo-600"><path d="M6 2l6 3.75L6 9.5 0 5.75 6 2zm12 0l6 3.75-6 3.75-6-3.75L18 2zM0 13.25L6 9.5l6 3.75L6 17 0 13.25zm18-3.75l6 3.75L18 17l-6-3.75L18 9.5zM6 18.25l6-3.75 6 3.75L12 22l-6-3.75z"/></svg>'
            },
        ],

        openAddModal() {
            this.newConnection = {
                name: '',
                provider: 'google_drive',
                client_id: '',
                client_secret: '',
                metadata: { tenant_id: '', site_id: '' },
            };
            this.showAddModal = true;
        },

        async createConnection() {
            this.creatingConnection = true;
            try {
                const body = {
                    name: this.newConnection.name,
                    provider: this.newConnection.provider,
                    client_id: this.newConnection.client_id,
                    client_secret: this.newConnection.client_secret,
                };
                if (this.newConnection.provider === 'sharepoint') {
                    body.metadata = {
                        tenant_id: this.newConnection.metadata.tenant_id,
                        site_id: this.newConnection.metadata.site_id,
                    };
                }

                const res = await fetch('{{ route("sources.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Errore nella creazione');

                this.connections.unshift(data.connection);
                this.showAddModal = false;
                this.showToast('Connessione creata! Procedi con l\'autenticazione.', 'success');
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.creatingConnection = false;
            }
        },

        async startAuth(conn) {
            try {
                const res = await fetch(`/sources/${conn.id}/auth`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Errore');

                window.location.href = data.url;
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },

        async openBrowser(conn) {
            this.browsingConnection = conn;
            this.browserItems = [];
            this.breadcrumbs = [];
            this.selectedFiles = [];
            this.showBrowser = true;
            await this.loadFolder('');
        },

        closeBrowser() {
            this.showBrowser = false;
            this.browsingConnection = null;
            this.browserItems = [];
            this.breadcrumbs = [];
            this.selectedFiles = [];
        },

        async loadFolder(folderId) {
            this.loadingFiles = true;
            try {
                const params = folderId ? `?folder_id=${encodeURIComponent(folderId)}` : '';
                const res = await fetch(`/sources/${this.browsingConnection.id}/browse${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Errore');

                this.browserItems = data.items;
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.loadingFiles = false;
            }
        },

        navigateToFolder(folderId, folderName) {
            if (folderId === '') {
                this.breadcrumbs = [];
            } else {
                const idx = this.breadcrumbs.findIndex(b => b.id === folderId);
                if (idx >= 0) {
                    this.breadcrumbs = this.breadcrumbs.slice(0, idx + 1);
                } else {
                    this.breadcrumbs.push({ id: folderId, name: folderName });
                }
            }
            this.loadFolder(folderId);
        },

        toggleFileSelection(item) {
            const idx = this.selectedFiles.findIndex(f => f.id === item.id);
            if (idx >= 0) {
                this.selectedFiles.splice(idx, 1);
            } else {
                this.selectedFiles.push(item);
            }
        },

        isFileSelected(fileId) {
            return this.selectedFiles.some(f => f.id === fileId);
        },

        async importSelected() {
            if (this.selectedFiles.length === 0) return;
            this.importing = true;

            try {
                const res = await fetch(`/sources/${this.browsingConnection.id}/import`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        file_ids: this.selectedFiles.map(f => f.id),
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Errore');

                this.showToast(data.message, 'success');
                this.closeBrowser();
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.importing = false;
            }
        },

        async syncConnection(conn, fullSync = false) {
            try {
                const endpoint = fullSync ? `/sources/${conn.id}/full-sync` : `/sources/${conn.id}/sync`;
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Errore');

                this.showToast(data.message, 'success');
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },

        async deleteConnection(id) {
            const ok = await this.askConfirm('Elimina connessione', 'Sei sicuro di voler eliminare questa connessione? I documenti importati non verranno eliminati.');
            if (!ok) return;

            try {
                await fetch(`/sources/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                this.connections = this.connections.filter(c => c.id !== id);
                this.showToast('Connessione eliminata', 'success');
            } catch (e) {
                this.showToast('Errore durante l\'eliminazione', 'error');
            }
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

        getProviderLabel(provider) {
            const map = { google_drive: 'Google Drive', onedrive: 'OneDrive', sharepoint: 'SharePoint', dropbox: 'Dropbox' };
            return map[provider] || provider;
        },

        getProviderBgClass(provider) {
            const map = { google_drive: 'bg-blue-100', onedrive: 'bg-sky-100', sharepoint: 'bg-teal-100', dropbox: 'bg-indigo-100' };
            return map[provider] || 'bg-gray-100';
        },

        getProviderIcon(provider) {
            const p = this.providers.find(x => x.value === provider);
            return p ? p.icon : '';
        },

        getStatusLabel(status) {
            const map = { pending: 'In attesa', connected: 'Connesso', expired: 'Scaduto', disconnected: 'Disconnesso' };
            return map[status] || status;
        },

        getFileIconClass(item) {
            const mime = item.mimeType || '';
            if (mime.includes('pdf')) return 'bg-red-100 text-red-600';
            if (mime.includes('word') || mime.includes('document')) return 'bg-blue-100 text-blue-600';
            if (mime.includes('sheet') || mime.includes('excel') || mime.includes('csv')) return 'bg-emerald-100 text-emerald-600';
            if (mime.includes('image')) return 'bg-purple-100 text-purple-600';
            return 'bg-gray-100 text-gray-600';
        },

        formatSize(bytes) {
            if (!bytes) return '';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        isFileSupported(item) {
            if (!item.mimeType) return false;

            const supported = [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'application/zip',
                'application/x-zip-compressed',
            ];

            if (supported.includes(item.mimeType)) return true;
            if (item.mimeType.startsWith('image/')) return true;

            return false;
        },
    };
}
</script>
@endsection
