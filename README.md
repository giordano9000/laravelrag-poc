# Laravel RAG POC - Gestione Documentale

POC per gestione documentale con RAG (Retrieval-Augmented Generation) usando Laravel 12 AI SDK. Carica documenti (PDF, TXT, XLS, XLSX, CSV, JPG), il sistema li indicizza in un database vettoriale (pgvector), e permette di interrogare un LLM che risponde sulla base dei documenti caricati.

## Stack

| Componente | Tecnologia |
|---|---|
| Framework | Laravel 12 + `laravel/ai` |
| LLM (generazione) | Ollama + llama3.1:8b |
| LLM (embeddings) | Ollama + nomic-embed-text (768 dim) |
| Vector DB | PostgreSQL 17 + pgvector |
| PDF | smalot/pdfparser |
| Excel | phpoffice/phpspreadsheet |
| OCR (JPG) | thiagoalessio/tesseract_ocr (incluso nel container) |
| Frontend | Blade + Tailwind CSS + Alpine.js |

## Prerequisiti

- Docker e Docker Compose

> L'applicazione gira interamente in container Docker. PHP, Composer e Tesseract sono già inclusi nel container.

## Quick Start

```bash
git clone <repo-url> laravelrag-poc
cd laravelrag-poc
make run
```

Il comando `make run`:
1. Builda l'immagine Docker dell'applicazione
2. Avvia PostgreSQL, Ollama, l'app e il queue worker
3. Scarica automaticamente i modelli Ollama (llama3.1:8b + nomic-embed-text)
4. Esegue le migrations del database

> La prima volta il download dei modelli richiede ~5GB. Segui il progresso con `make logs-ollama`.

Apri **http://localhost:8000** nel browser.

## Comandi Make

| Comando | Descrizione |
|---------|-------------|
| `make run` | Avvia tutto (build + up + migrate) |
| `make build` | Build immagine Docker |
| `make up` | Avvia i container |
| `make down` | Ferma i container |
| `make clean` | Rimuove container, volumi e immagini |
| `make migrate` | Esegue le migrations |
| `make fresh` | Reset database + migrations |
| `make shell` | Shell bash nel container app |
| `make logs` | Tutti i logs |
| `make logs-app` | Logs applicazione |
| `make logs-queue` | Logs queue worker |
| `make logs-ollama` | Logs download modelli Ollama |

## Utilizzo

1. **Carica documenti** — Trascina o seleziona file dalla sidebar sinistra (PDF, TXT, XLS, XLSX, CSV, JPG)
2. **Attendi l'elaborazione** — Lo stato passa da `pending` → `processing` → `ready`
3. **Fai domande** — Scrivi nella chat a destra, il sistema cerca nei documenti e risponde in streaming

## Architettura

```
┌─────────────────────────────────────────────────────────────────┐
│                        Docker Compose                            │
├─────────────┬─────────────┬─────────────┬─────────────┬─────────┤
│     app     │    queue    │  postgres   │   ollama    │ ollama- │
│  (Laravel)  │  (worker)   │  (pgvector) │   (LLM)     │  pull   │
│  :8000      │             │  :5432      │  :11434     │ (init)  │
└─────────────┴─────────────┴─────────────┴─────────────┴─────────┘
```

### Flusso elaborazione documenti

```
Upload file
    │
    ▼
DocumentController::store()
    │
    ├── Salva file su disco
    ├── Crea record Document (status: pending)
    └── Dispatch ProcessDocument job
                │
                ▼
        ProcessDocument (queue worker)
            │
            ├── DocumentProcessor::extractText()
            │       PDF → smalot/pdfparser
            │       TXT → file_get_contents
            │       XLS/XLSX/CSV → phpspreadsheet
            │       JPG → tesseract_ocr
            │
            ├── TextChunker::chunk()
            │       Recursive splitting (2000 chars, 200 overlap)
            │
            ├── Embeddings::for($chunks)->generate()
            │       Ollama + nomic-embed-text → 768 dim vectors
            │
            └── Salva DocumentChunks con embeddings
                Document status → ready
```

### Flusso chat

```
Chat (domanda utente)
    │
    ▼
ChatController → DocumentAssistant agent
    │
    ├── SimilaritySearch tool
    │       Query vettoriale su document_chunks.embedding
    │       Restituisce i chunk più rilevanti
    │
    └── LLM genera risposta (streaming SSE)
            Ollama + llama3.1:8b
            Basata sui chunk trovati
```

## Struttura file

```
app/
├── Ai/Agents/DocumentAssistant.php    # Agent RAG con SimilaritySearch
├── Http/Controllers/
│   ├── DocumentController.php         # CRUD documenti + upload
│   └── ChatController.php            # Chat streaming SSE
├── Jobs/ProcessDocument.php           # Elaborazione asincrona documenti
├── Models/
│   ├── Document.php                   # Metadati documento
│   └── DocumentChunk.php             # Chunk con embedding vettoriale
└── Services/
    ├── DocumentProcessor.php          # Estrazione testo multi-formato
    └── TextChunker.php               # Splitting ricorsivo del testo
```

## Troubleshooting

**Il documento resta in stato `processing`/`pending`**
- Verifica i log del queue worker: `make logs-queue`
- Verifica i log dell'app: `make logs-app`

**Errore di connessione a PostgreSQL**
- Verifica che il container sia attivo: `docker compose ps`

**Errore di connessione a Ollama**
- Verifica che il container sia attivo: `docker compose ps`
- Verifica che i modelli siano scaricati: `docker compose exec ollama ollama list`

**Errore memoria Ollama (model requires more system memory)**
- Aumenta la memoria allocata a Docker Desktop (Settings → Resources → Memory)
- Consigliato: almeno 8GB per llama3.1:8b

**Timeout PHP**
- Il container è già configurato con `max_execution_time=300` (5 minuti)
