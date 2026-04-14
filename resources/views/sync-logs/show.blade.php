@extends('layouts.app')

@section('content')
<div class="h-[calc(100vh-4rem)] overflow-y-auto">
    {{-- Page Header --}}
    <div class="bg-white/80 backdrop-blur-xl border-b border-gray-200/50 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center gap-3 mb-4">
                <a href="{{ route('sync-logs.index') }}" class="p-2 rounded-xl hover:bg-gray-100 transition-all" title="Torna ai Log">
                    <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                </a>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900">Dettagli Sincronizzazione</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ $syncLog->sourceConnection->name }} - {{ $syncLog->started_at->format('d/m/Y H:i') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-lg text-xs font-semibold
                        {{ $syncLog->type === 'import' ? 'bg-blue-100 text-blue-700' : '' }}
                        {{ $syncLog->type === 'sync' ? 'bg-purple-100 text-purple-700' : '' }}
                        {{ $syncLog->type === 'full_sync' ? 'bg-indigo-100 text-indigo-700' : '' }}">
                        {{ $syncLog->type === 'import' ? 'Import' : ($syncLog->type === 'sync' ? 'Sync' : 'Sync Completo') }}
                    </span>
                    <span class="px-3 py-1 rounded-lg text-xs font-semibold
                        {{ $syncLog->status === 'running' ? 'bg-yellow-100 text-yellow-700' : '' }}
                        {{ $syncLog->status === 'completed' ? 'bg-emerald-100 text-emerald-700' : '' }}
                        {{ $syncLog->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                        {{ $syncLog->status === 'partial' ? 'bg-orange-100 text-orange-700' : '' }}">
                        {{ $syncLog->status === 'running' ? 'In esecuzione' : '' }}
                        {{ $syncLog->status === 'completed' ? 'Completato' : '' }}
                        {{ $syncLog->status === 'failed' ? 'Fallito' : '' }}
                        {{ $syncLog->status === 'partial' ? 'Parziale' : '' }}
                    </span>
                </div>
            </div>

            {{-- Summary Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="text-xs text-gray-500 mb-1">Totale File</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $syncLog->total_items }}</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="text-xs text-gray-500 mb-1">Successo</div>
                    <div class="text-2xl font-bold text-emerald-600">{{ $syncLog->successful_items }}</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="text-xs text-gray-500 mb-1">Falliti</div>
                    <div class="text-2xl font-bold text-red-600">{{ $syncLog->failed_items }}</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="text-xs text-gray-500 mb-1">Saltati</div>
                    <div class="text-2xl font-bold text-orange-600">{{ $syncLog->skipped_items }}</div>
                </div>
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <div class="text-xs text-gray-500 mb-1">Tasso Successo</div>
                    <div class="text-2xl font-bold text-indigo-600">{{ number_format($syncLog->success_rate, 1) }}%</div>
                </div>
            </div>

            @if($syncLog->error_message)
                <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-xl">
                    <div class="font-semibold text-red-700 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Errore Generale
                    </div>
                    <div class="text-sm text-red-600">{{ $syncLog->error_message }}</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="bg-gray-50/80 border-b border-gray-200/50" x-data="syncLogShowApp()">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex flex-col md:flex-row gap-3">
                <div class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            type="text"
                            x-model="filters.search"
                            @keyup.enter="applyFilters"
                            placeholder="Cerca per nome file..."
                            class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                </div>
                <div class="flex gap-3">
                    <select @change="applyFilters" x-model="filters.status" class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Tutti gli stati</option>
                        <option value="success">Successo</option>
                        <option value="failed">Falliti</option>
                        <option value="skipped">Saltati</option>
                    </select>
                    <button @click="resetFilters" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium transition-colors">
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Files List --}}
    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-900">File Processati ({{ $items->total() }})</h2>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($items as $item)
                    <div class="px-6 py-4 hover:bg-gray-50 transition-all">
                        <div class="flex items-start gap-4">
                            {{-- Status Icon --}}
                            <div class="shrink-0 mt-1">
                                @if($item->status === 'success')
                                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                @elseif($item->status === 'failed')
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </div>
                                @else
                                    <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                @endif
                            </div>

                            {{-- File Info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-semibold text-gray-900 truncate">{{ $item->file_name }}</h3>
                                    <span class="shrink-0 px-2 py-0.5 rounded text-xs font-medium
                                        {{ $item->status === 'success' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                        {{ $item->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $item->status === 'skipped' ? 'bg-orange-100 text-orange-700' : '' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                                    @if($item->file_path)
                                        <div class="flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                            <span class="truncate">{{ $item->file_path }}</span>
                                        </div>
                                    @endif
                                    @if($item->file_size)
                                        <span>{{ $item->formatted_size }}</span>
                                    @endif
                                    @if($item->mime_type)
                                        <span>{{ $item->mime_type }}</span>
                                    @endif
                                </div>

                                @if($item->status === 'skipped' && $item->skip_reason)
                                    <div class="mt-2 flex items-start gap-2 p-2 bg-orange-50 border border-orange-200 rounded-lg">
                                        <svg class="w-4 h-4 text-orange-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <div>
                                            <div class="text-xs font-semibold text-orange-700">Motivo dello skip:</div>
                                            <div class="text-xs text-orange-600">{{ $item->skip_reason_label }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if($item->status === 'failed' && $item->error_message)
                                    <div class="mt-2 flex items-start gap-2 p-2 bg-red-50 border border-red-200 rounded-lg">
                                        <svg class="w-4 h-4 text-red-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <div>
                                            <div class="text-xs font-semibold text-red-700">Errore:</div>
                                            <div class="text-xs text-red-600">{{ $item->error_message }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if($item->document_id)
                                    <div class="mt-2">
                                        <a href="{{ route('documents.show', $item->document_id) }}" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            Vai al documento
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <p>Nessun file processato</p>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($items->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $items->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function syncLogShowApp() {
    return {
        filters: {
            search: '{{ request("search") }}',
            status: '{{ request("status") }}'
        },

        applyFilters() {
            const params = new URLSearchParams();
            if (this.filters.search) params.append('search', this.filters.search);
            if (this.filters.status) params.append('status', this.filters.status);

            window.location.href = '{{ route("sync-logs.show", $syncLog) }}?' + params.toString();
        },

        resetFilters() {
            window.location.href = '{{ route("sync-logs.show", $syncLog) }}';
        },
    };
}
</script>
@endsection
