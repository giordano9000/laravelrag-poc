@extends('layouts.app')

@section('content')
<div x-data="syncLogsApp()" class="h-[calc(100vh-4rem)] overflow-y-auto">

    {{-- Toast Notification --}}
    <div
        x-show="toast.show"
        x-cloak
        x-transition
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-4 rounded-2xl shadow-2xl text-sm font-medium"
        :class="toast.type === 'error' ? 'bg-red-500 text-white' : 'bg-emerald-500 text-white'"
    >
        <span x-text="toast.message"></span>
        <button @click="toast.show = false" class="ml-2 hover:opacity-70 transition-opacity">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div x-show="deleteModal.show" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="deleteModal.show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="deleteModal.show = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="deleteModal.show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-2xl px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-semibold text-gray-900" id="modal-title">Elimina Log</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">Sei sicuro di voler eliminare questo log? Questa azione non può essere annullata.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                    <button @click="confirmDelete()" type="button" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Elimina
                    </button>
                    <button @click="deleteModal.show = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm transition-colors">
                        Annulla
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Page Header --}}
    <div class="bg-white/80 backdrop-blur-xl border-b border-gray-200/50 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Log Sincronizzazioni</h1>
                <p class="text-sm text-gray-500 mt-1">Cronologia completa di import e sincronizzazioni</p>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="bg-gray-50/80 border-b border-gray-200/50">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="text-sm font-semibold text-gray-700">Filtri:</span>
                <div class="flex flex-wrap gap-3">
                    <select @change="filterChanged" x-model="filters.connection_id" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Tutte le connessioni</option>
                        @foreach($connections as $conn)
                            <option value="{{ $conn->id }}">{{ $conn->name }}</option>
                        @endforeach
                    </select>

                    <select @change="filterChanged" x-model="filters.type" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Tutti i tipi</option>
                        <option value="import">Import</option>
                        <option value="sync">Sync</option>
                        <option value="full_sync">Sync Completo</option>
                    </select>

                    <select @change="filterChanged" x-model="filters.status" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Tutti gli stati</option>
                        <option value="running">In esecuzione</option>
                        <option value="completed">Completati</option>
                        <option value="failed">Falliti</option>
                        <option value="partial">Parziali</option>
                    </select>

                    @if(request()->hasAny(['connection_id', 'type', 'status']))
                        <button @click="resetFilters" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium transition-colors">
                            Reset
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Sync Logs List --}}
    <div class="max-w-7xl mx-auto px-6 py-8">
        @if($syncLogs->isEmpty())
            <div class="text-center py-20">
                <div class="w-24 h-24 rounded-3xl bg-gray-100 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Nessun log disponibile</h3>
                <p class="text-gray-500">Non sono ancora state eseguite operazioni di import o sincronizzazione.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($syncLogs as $log)
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-all p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                {{-- Header --}}
                                <div class="flex items-center gap-3 mb-3">
                                    <span class="px-3 py-1 rounded-lg text-xs font-semibold
                                        {{ $log->type === 'import' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $log->type === 'sync' ? 'bg-purple-100 text-purple-700' : '' }}
                                        {{ $log->type === 'full_sync' ? 'bg-indigo-100 text-indigo-700' : '' }}">
                                        {{ $log->type === 'import' ? 'Import' : ($log->type === 'sync' ? 'Sync' : 'Sync Completo') }}
                                    </span>
                                    <span class="px-3 py-1 rounded-lg text-xs font-semibold
                                        {{ $log->status === 'running' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $log->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                        {{ $log->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $log->status === 'partial' ? 'bg-orange-100 text-orange-700' : '' }}">
                                        {{ $log->status === 'running' ? 'In esecuzione' : '' }}
                                        {{ $log->status === 'completed' ? 'Completato' : '' }}
                                        {{ $log->status === 'failed' ? 'Fallito' : '' }}
                                        {{ $log->status === 'partial' ? 'Parziale' : '' }}
                                    </span>
                                    <span class="text-sm font-semibold text-gray-900">{{ $log->sourceConnection->name }}</span>
                                </div>

                                {{-- Stats --}}
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3">
                                    <div>
                                        <div class="text-xs text-gray-500">Totale</div>
                                        <div class="text-lg font-bold text-gray-900">{{ $log->total_items }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Successo</div>
                                        <div class="text-lg font-bold text-emerald-600">{{ $log->successful_items }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Falliti</div>
                                        <div class="text-lg font-bold text-red-600">{{ $log->failed_items }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">Saltati</div>
                                        <div class="text-lg font-bold text-orange-600">{{ $log->skipped_items }}</div>
                                    </div>
                                </div>

                                {{-- Time info --}}
                                <div class="flex items-center gap-4 text-xs text-gray-500">
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span>Iniziato: {{ $log->started_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    @if($log->completed_at)
                                        <div class="flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <span>Completato: {{ $log->completed_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <div>Durata: {{ $log->duration }}s</div>
                                    @endif
                                </div>

                                @if($log->error_message)
                                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-xl">
                                        <div class="text-xs font-semibold text-red-700 mb-1">Errore:</div>
                                        <div class="text-sm text-red-600">{{ $log->error_message }}</div>
                                    </div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 ml-4">
                                <a href="{{ route('sync-logs.show', $log) }}" class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-semibold hover:bg-indigo-100 transition-all">
                                    Dettagli
                                </a>
                                <button @click="deleteLog({{ $log->id }})" class="p-2 text-red-600 hover:bg-red-50 rounded-xl transition-all" title="Elimina log">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $syncLogs->links() }}
            </div>
        @endif
    </div>
</div>

<script>
function syncLogsApp() {
    return {
        toast: { show: false, message: '', type: 'error' },
        deleteModal: { show: false, logId: null },
        filters: {
            connection_id: '{{ request("connection_id") }}',
            type: '{{ request("type") }}',
            status: '{{ request("status") }}'
        },

        filterChanged() {
            const params = new URLSearchParams();
            if (this.filters.connection_id) params.append('connection_id', this.filters.connection_id);
            if (this.filters.type) params.append('type', this.filters.type);
            if (this.filters.status) params.append('status', this.filters.status);

            window.location.href = '{{ route("sync-logs.index") }}?' + params.toString();
        },

        resetFilters() {
            window.location.href = '{{ route("sync-logs.index") }}';
        },

        deleteLog(id) {
            this.deleteModal = { show: true, logId: id };
        },

        async confirmDelete() {
            const id = this.deleteModal.logId;
            this.deleteModal.show = false;

            try {
                const res = await fetch(`/sync-logs/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                if (!res.ok) throw new Error('Errore durante l\'eliminazione');

                this.showToast('Log eliminato con successo', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },

        showToast(message, type = 'error') {
            this.toast = { show: true, message, type };
            setTimeout(() => this.toast.show = false, 4000);
        },
    };
}
</script>
@endsection
