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

    {{-- Files List --}}
    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900">File Processati ({{ $syncLog->items->count() }})</h2>
                <div class="flex gap-2">
                    <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-700'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">
                        Tutti
                    </button>
                    <button @click="filter = 'success'" :class="filter === 'success' ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-700'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">
                        Successo
                    </button>
                    <button @click="filter = 'failed'" :class="filter === 'failed' ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-700'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">
                        Falliti
                    </button>
                    <button @click="filter = 'skipped'" :class="filter === 'skipped' ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-700'" class="px-3 py-1 rounded-lg text-xs font-semibold transition-all">
                        Saltati
                    </button>
                </div>
            </div>

            <div class="divide-y divide-gray-100" x-data="{ filter: 'all' }">
                @forelse($syncLog->items as $item)
                    <div
                        x-show="filter === 'all' || filter === '{{ $item->status }}'"
                        class="px-6 py-4 hover:bg-gray-50 transition-all"
                    >
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
        </div>
    </div>
</div>
@endsection
