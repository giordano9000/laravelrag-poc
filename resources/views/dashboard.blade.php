@extends('layouts.app')

@section('content')
<div x-data="app()" class="h-full flex">

    {{-- Sidebar: Documenti --}}
    <aside class="w-80 bg-white border-r border-gray-200 flex flex-col h-screen">
        <div class="p-4 border-b border-gray-200">
            <h1 class="text-lg font-bold text-gray-800">RAG Documents</h1>
            <p class="text-xs text-gray-500 mt-1">Carica documenti e fai domande</p>
        </div>

        {{-- Upload Area --}}
        <div class="p-4 border-b border-gray-200">
            <div
                @dragover.prevent="dragover = true"
                @dragleave="dragover = false"
                @drop.prevent="handleDrop($event)"
                :class="dragover ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
                class="border-2 border-dashed rounded-lg p-4 text-center cursor-pointer transition-colors"
                @click="$refs.fileInput.click()"
            >
                <input
                    type="file"
                    x-ref="fileInput"
                    @change="uploadFile($event.target.files[0])"
                    accept=".pdf,.txt,.xls,.xlsx,.csv,.jpg,.jpeg"
                    class="hidden"
                >
                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p class="mt-1 text-sm text-gray-600">
                    <span class="font-medium text-blue-600">Clicca</span> o trascina un file
                </p>
                <p class="text-xs text-gray-500">PDF, TXT, XLS, XLSX, CSV, JPG</p>
            </div>

            {{-- Upload Progress --}}
            <div x-show="uploading" x-cloak class="mt-3">
                <div class="flex justify-between text-xs text-gray-600 mb-1">
                    <span>Caricamento...</span>
                    <span x-text="uploadProgress + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all" :style="'width: ' + uploadProgress + '%'"></div>
                </div>
            </div>

            {{-- Upload Error --}}
            <div x-show="uploadError" x-cloak class="mt-2 text-xs text-red-600" x-text="uploadError"></div>
        </div>

        {{-- Document List --}}
        <div class="flex-1 overflow-y-auto">
            <template x-for="doc in documents" :key="doc.id">
                <div class="p-3 border-b border-gray-100 hover:bg-gray-50 group">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate" x-text="doc.title"></p>
                            <p class="text-xs text-gray-500 truncate" x-text="doc.original_filename"></p>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': doc.status === 'pending',
                                        'bg-blue-100 text-blue-800': doc.status === 'processing',
                                        'bg-green-100 text-green-800': doc.status === 'ready',
                                        'bg-red-100 text-red-800': doc.status === 'failed',
                                    }"
                                    x-text="doc.status"
                                ></span>
                                <span class="text-xs text-gray-400" x-text="formatSize(doc.file_size)"></span>
                            </div>
                        </div>
                        <button
                            @click.stop="deleteDocument(doc.id)"
                            class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-red-500 transition-opacity p-1"
                            title="Elimina"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </template>
            <div x-show="documents.length === 0" class="p-4 text-center text-sm text-gray-500">
                Nessun documento caricato
            </div>
        </div>
    </aside>

    {{-- Main: Chat Area --}}
    <main class="flex-1 flex flex-col h-screen">
        {{-- Chat Header --}}
        <div class="p-4 border-b border-gray-200 bg-white">
            <h2 class="text-lg font-semibold text-gray-800">Chat con i tuoi documenti</h2>
            <p class="text-xs text-gray-500">Fai domande basate sui documenti caricati</p>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" x-ref="chatContainer">
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div
                        :class="msg.role === 'user'
                            ? 'bg-blue-600 text-white rounded-2xl rounded-br-md'
                            : 'bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-bl-md'"
                        class="max-w-2xl px-4 py-3 shadow-sm"
                    >
                        <div class="text-sm whitespace-pre-wrap" x-html="msg.role === 'assistant' ? renderMarkdown(msg.content) : msg.content"></div>
                    </div>
                </div>
            </template>

            {{-- Streaming indicator --}}
            <div x-show="streaming" class="flex justify-start">
                <div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-bl-md max-w-2xl px-4 py-3 shadow-sm">
                    <div class="text-sm whitespace-pre-wrap" x-html="renderMarkdown(streamingContent)"></div>
                    <span class="inline-block w-2 h-4 bg-gray-400 animate-pulse ml-0.5"></span>
                </div>
            </div>

            {{-- Empty state --}}
            <div x-show="messages.length === 0 && !streaming" class="flex items-center justify-center h-full">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">Carica dei documenti e inizia a fare domande</p>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div class="p-4 border-t border-gray-200 bg-white">
            <form @submit.prevent="sendMessage" class="flex gap-3">
                <input
                    x-model="input"
                    type="text"
                    placeholder="Scrivi una domanda sui tuoi documenti..."
                    class="flex-1 rounded-xl border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="streaming"
                >
                <button
                    x-show="!streaming"
                    type="submit"
                    :disabled="!input.trim()"
                    class="bg-blue-600 text-white rounded-xl px-6 py-3 text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    Invia
                </button>
                <button
                    x-show="streaming"
                    x-cloak
                    type="button"
                    @click="stopStreaming()"
                    class="bg-red-600 text-white rounded-xl px-6 py-3 text-sm font-medium hover:bg-red-700 transition-colors flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <rect x="6" y="6" width="12" height="12" rx="2" />
                    </svg>
                    Stop
                </button>
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
        uploading: false,
        uploadProgress: 0,
        uploadError: '',
        dragover: false,
        abortController: null,

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

                this.documents.unshift(response.document);
                this.pollDocumentStatus(response.document.id);
            } catch (e) {
                this.uploadError = e.message;
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
                    }
                } catch {
                    clearInterval(interval);
                }
            }, 3000);
        },

        async deleteDocument(id) {
            if (!confirm('Eliminare questo documento?')) return;

            try {
                await fetch(`/documents/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                this.documents = this.documents.filter(d => d.id !== id);
            } catch (e) {
                alert('Errore durante l\'eliminazione');
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
                                if (parsed.text) {
                                    this.streamingContent += parsed.text;
                                    this.scrollToBottom();
                                }
                            } catch {}
                        }
                    }
                }

                if (this.streamingContent) {
                    this.messages.push({ role: 'assistant', content: this.streamingContent });
                }
            } catch (e) {
                if (e.name === 'AbortError') {
                    if (this.streamingContent) {
                        this.messages.push({ role: 'assistant', content: this.streamingContent });
                    }
                } else {
                    this.messages.push({ role: 'assistant', content: 'Errore: impossibile ottenere una risposta.' });
                }
            } finally {
                this.streaming = false;
                this.streamingContent = '';
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
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code class="bg-gray-100 px-1 rounded text-sm">$1</code>')
                .replace(/\n/g, '<br>');
        }
    };
}
</script>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
