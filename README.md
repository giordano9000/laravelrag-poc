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
| OCR (JPG) | thiagoalessio/tesseract_ocr |
| Frontend | Blade + Tailwind CSS + Alpine.js |

## Prerequisiti

- PHP 8.3+
- Composer
- Docker e Docker Compose
- Tesseract OCR (solo se si usano immagini JPG)

### Installare Tesseract (opzionale, per OCR su JPG)

```bash
# macOS
brew install tesseract tesseract-lang

# Ubuntu/Debian
sudo apt install tesseract-ocr tesseract-ocr-ita tesseract-ocr-eng
```

## Setup

### 1. Clona e installa dipendenze

```bash
git clone <repo-url> laravelrag-poc
cd laravelrag-poc
composer install
cp .env.example .env   # oppure usa il .env già configurato
php artisan key:generate
```

### 2. Avvia PostgreSQL + Ollama

```bash
docker compose up -d
```

Questo avvia:
- **PostgreSQL 17 + pgvector** su `localhost:5432` (user: `laravelrag`, password: `secret`, db: `laravelrag`)
- **Ollama** su `localhost:11434`

### 3. Scarica i modelli Ollama

```bash
docker compose exec ollama ollama pull llama3.1:8b
docker compose exec ollama ollama pull nomic-embed-text
```

> La prima volta il download richiede ~5GB (llama3.1:8b) + ~270MB (nomic-embed-text).

### 4. Esegui le migrations

```bash
php artisan migrate
```

Crea le tabelle `documents` e `document_chunks` (con colonna `vector(768)` per gli embeddings) e abilita l'estensione pgvector.

### 5. Avvia il queue worker

```bash
php artisan queue:work
```

Il worker elabora i documenti caricati in background: estrazione testo → chunking → generazione embeddings → salvataggio nel database vettoriale.

### 6. Avvia il server

In un altro terminale:

```bash
php artisan serve
```

Apri **http://localhost:8000** nel browser.

## Utilizzo

1. **Carica documenti** — Trascina o seleziona file dalla sidebar sinistra (PDF, TXT, XLS, XLSX, CSV, JPG)
2. **Attendi l'elaborazione** — Lo stato passa da `pending` → `processing` → `ready`
3. **Fai domande** — Scrivi nella chat a destra, il sistema cerca nei documenti e risponde in streaming

## Configurazione

Le variabili principali nel `.env`:

```env
# Database (PostgreSQL + pgvector)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravelrag
DB_USERNAME=laravelrag
DB_PASSWORD=secret

# Ollama
OLLAMA_URL=http://localhost:11434

# Queue (necessario per elaborazione documenti)
QUEUE_CONNECTION=database
```

La configurazione AI è in `config/ai.php`. I default sono già impostati per usare Ollama come provider per generazione testo ed embeddings.

## Architettura

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
- Verifica che il queue worker sia attivo: `php artisan queue:work`
- Controlla i log: `tail -f storage/logs/laravel.log`

**Errore di connessione a PostgreSQL**
- Verifica che il container sia attivo: `docker compose ps`
- Verifica le credenziali nel `.env`

**Errore di connessione a Ollama**
- Verifica che il container sia attivo: `docker compose ps`
- Verifica che i modelli siano scaricati: `docker compose exec ollama ollama list`

**OCR non funziona (JPG)**
- Installa Tesseract: vedi sezione Prerequisiti
- Verifica: `tesseract --version`
