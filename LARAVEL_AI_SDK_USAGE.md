# Laravel AI SDK - Utilizzo nel POC RAG

Questo documento spiega come è stato utilizzato **Laravel AI SDK** per implementare il sistema RAG (Retrieval-Augmented Generation) in questo POC.

## Panoramica

Laravel AI SDK è il framework ufficiale di Laravel per integrare modelli AI nell'applicazione. Nel nostro caso, lo utilizziamo per:
1. **Generare embeddings** (vettori) dai documenti
2. **Creare un agent conversazionale** per rispondere alle domande
3. **Streamare le risposte** in tempo reale

---

## 1. Configurazione

### Provider configurato: Ollama

Il file `config/ai.php` definisce Ollama come provider predefinito:

```php
'default' => 'ollama',
'default_for_embeddings' => 'ollama',

'providers' => [
    'ollama' => [
        'driver' => 'ollama',
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],
],
```

**Perché Ollama?**
- Esecuzione locale dei modelli LLM (nessuna API esterna)
- Nessun costo per chiamata
- Privacy totale (dati non escono dal server)
- Supporto per modelli open-source (llama3.2:3b, nomic-embed-text)

---

## 2. Generazione degli Embeddings

### Processo di Indicizzazione

Quando un documento viene caricato, il job `ProcessDocument` genera gli embeddings:

**File:** `app/Jobs/ProcessDocument.php`

```php
use Laravel\Ai\Embeddings;

// 1. Estrai testo dal documento
$text = $processor->extractText($filePath, $mimeType);

// 2. Dividi in chunks
$chunks = $chunker->chunk($text);

// 3. Genera embeddings con Laravel AI SDK
$embeddings = Embeddings::for($chunks)->generate();

// 4. Salva nel database con pgvector
foreach ($chunks as $index => $chunkText) {
    DocumentChunk::create([
        'document_id' => $document->id,
        'content' => $chunkText,
        'chunk_index' => $index,
        'embedding' => $embeddings[$index], // Array di 768 float
    ]);
}
```

**Come funziona:**
- `Embeddings::for($chunks)` prepara la richiesta
- `->generate()` chiama Ollama con il modello `nomic-embed-text`
- Restituisce un `EmbeddingsResponse` con vettori a 768 dimensioni
- I vettori vengono salvati come colonna `vector(768)` in PostgreSQL con pgvector

---

## 3. Agent Conversazionale

### Implementazione dell'Agent

**File:** `app/Ai/Agents/DocumentAssistant.php`

```php
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[Provider('ollama')]
#[Model('llama3.2:3b')]
class DocumentAssistant implements Agent
{
    use Promptable;

    private string $context = '';

    public function withContext(string $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function instructions(): string
    {
        $basePrompt = <<<'PROMPT'
Sei un assistente documentale intelligente...
PROMPT;

        if ($this->context) {
            return $basePrompt . "\n\n--- DOCUMENTI RILEVANTI ---\n"
                . $this->context . "\n--- FINE DOCUMENTI ---";
        }

        return $basePrompt;
    }
}
```

**Caratteristiche:**
- **Attributi PHP 8**: `#[Provider]` e `#[Model]` definiscono quale LLM usare
- **Promptable trait**: fornisce metodi `prompt()` e `stream()`
- **instructions()**: definisce il system prompt (comportamento dell'AI)
- **withContext()**: inietta il contesto dei documenti nel prompt

---

## 4. Flusso di Ricerca e Risposta

### Controller Chat

**File:** `app/Http/Controllers/ChatController.php`

```php
public function __invoke(Request $request): StreamedResponse
{
    $message = $request->input('message');

    // 1. VECTOR SIMILARITY SEARCH
    // Cerca chunks simili alla domanda usando pgvector
    $relevantChunks = DocumentChunk::query()
        ->whereVectorSimilarTo('embedding', $message, 0.3)
        ->limit(8)
        ->get();

    // 2. NAME-BASED SEARCH
    // Cerca anche per nome file (es. "riassumi cappuccetto rosso")
    $matchedDocs = Document::where('status', 'ready')
        ->where(function ($query) use ($message) {
            $words = array_filter(
                preg_split('/[\s,.\-\/]+/', $message),
                fn ($w) => mb_strlen($w) >= 3
            );
            foreach ($words as $word) {
                $query->orWhere('title', 'ILIKE', '%' . $word . '%')
                      ->orWhere('original_filename', 'ILIKE', '%' . $word . '%');
            }
        })
        ->pluck('id');

    $nameMatchedChunks = DocumentChunk::whereIn('document_id', $matchedDocs)
        ->limit(5)
        ->get();

    // 3. MERGE RESULTS
    $allChunks = $relevantChunks->concat($nameMatchedChunks)->unique('id');

    // 4. BUILD CONTEXT
    $context = $allChunks->map(function ($chunk) {
        $docTitle = $chunk->document->title ?? 'Documento sconosciuto';
        return "--- Da: {$docTitle} ---\n{$chunk->content}";
    })->join("\n\n");

    // 5. CREATE AGENT WITH CONTEXT
    $agent = (new DocumentAssistant())->withContext($context);

    // 6. STREAM RESPONSE
    return new StreamedResponse(function () use ($agent, $message, $sourceDocuments) {
        // Invia fonti prima
        echo "data: " . json_encode([
            'sources' => $sourceDocuments->map(fn ($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'original_filename' => $doc->original_filename,
            ])->toArray()
        ]) . "\n\n";
        flush();

        // Stream della risposta AI
        $stream = $agent->stream($message);

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                echo "data: " . json_encode(['text' => $event->delta]) . "\n\n";
                flush();
            }
        }

        echo "data: [DONE]\n\n";
        flush();
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
    ]);
}
```

---

## 5. Streaming delle Risposte

### Come funziona lo streaming

Laravel AI SDK fornisce un metodo `stream()` che restituisce un generatore di eventi:

```php
$stream = $agent->stream($message);

foreach ($stream as $event) {
    if ($event instanceof TextDelta) {
        // $event->delta contiene un pezzo di testo
        echo "data: " . json_encode(['text' => $event->delta]) . "\n\n";
        flush();
    }
}
```

**Eventi disponibili:**
- `StreamStart`: inizio stream
- `TextDelta`: chunk di testo (quello che usiamo)
- `TextEnd`: fine generazione testo
- `StreamEnd`: fine stream completo

**Vantaggi:**
- Risposta istantanea (l'utente vede il testo mentre viene generato)
- Migliore UX rispetto ad attendere 10-20 secondi
- Riduce il perceived latency

---

## 6. Database Vettoriale (pgvector)

### Schema Database

**Migration:** `database/migrations/xxx_create_document_chunks_table.php`

```php
Schema::create('document_chunks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id')->constrained()->onDelete('cascade');
    $table->text('content');
    $table->integer('chunk_index');
    $table->integer('page_number')->nullable();

    // Colonna vettoriale (768 dimensioni per nomic-embed-text)
    $table->vector('embedding', 768);

    $table->timestamps();

    // Indice per ricerca vettoriale veloce
    $table->rawIndex('embedding vector_cosine_ops', 'document_chunks_embedding_idx', 'hnsw');
});
```

### Query Vettoriale

Laravel fornisce il metodo `whereVectorSimilarTo`:

```php
$results = DocumentChunk::query()
    ->whereVectorSimilarTo('embedding', $query, $minSimilarity)
    ->limit(10)
    ->get();
```

**Cosa fa:**
1. Genera embedding della query (`$query` viene convertito automaticamente)
2. Calcola distanza coseno con tutti i vettori nel database
3. Filtra per similarità minima (es. 0.3 = 70% di similarità)
4. Ordina per rilevanza
5. Limita i risultati

---

## 7. Vantaggi dell'Approccio

### Perché Laravel AI SDK?

✅ **Integrazione nativa con Laravel**
- Usa Eloquent per le query vettoriali
- Service provider già configurato
- Comandi artisan per testing

✅ **Astrazione dei provider**
- Facile switch tra Ollama, OpenAI, Anthropic
- Stesso codice, provider diverso

✅ **Type-safe**
- Attributi PHP 8 (`#[Provider]`, `#[Model]`)
- IDE autocomplete
- Errori a compile-time

✅ **Streaming built-in**
- Supporto SSE nativo
- Eventi tipizzati
- Gestione automatica del buffer

---

## 8. Limitazioni e Soluzioni

### Problema: Tool Support

**Limitazione:** `llama3.2:3b` non supporta bene i function calls/tools.

**Soluzione implementata:**
Invece di usare `SimilaritySearch` tool (che richiederebbe function calling), facciamo la ricerca **prima** nel controller e passiamo il contesto direttamente all'agent.

```php
// ❌ NON FUNZIONA con llama3.2:3b
public function tools(): iterable
{
    return [
        SimilaritySearch::usingModel(
            model: DocumentChunk::class,
            column: 'embedding',
            minSimilarity: 0.3,
        ),
    ];
}

// ✅ FUNZIONA
$context = DocumentChunk::whereVectorSimilarTo('embedding', $message, 0.3)->get();
$agent = (new DocumentAssistant())->withContext($context);
```

### Problema: Memoria Ollama

**Limitazione:** Modelli grandi (llama3.1:8b) richiedono 5GB+ RAM.

**Soluzione:** Usare modelli più piccoli (llama3.2:3b ~2GB) con performance comunque buone.

---

## 9. File Chiave

| File | Responsabilità |
|------|----------------|
| `config/ai.php` | Configurazione provider AI |
| `app/Ai/Agents/DocumentAssistant.php` | Agent conversazionale |
| `app/Jobs/ProcessDocument.php` | Generazione embeddings |
| `app/Http/Controllers/ChatController.php` | Ricerca vettoriale + streaming |
| `app/Models/DocumentChunk.php` | Model con colonna vector |
| `app/Services/DocumentProcessor.php` | Estrazione testo (PDF, DOC, etc) |
| `app/Services/TextChunker.php` | Splitting intelligente del testo |

---

## 10. Testing

### Test Embedding Generation

```bash
docker compose exec app php artisan tinker
```

```php
use Laravel\Ai\Embeddings;

$text = "Questo è un test";
$response = Embeddings::for([$text])->generate();
$embedding = $response->first();

echo "Dimensioni: " . count($embedding); // 768
echo "Primi 5 valori: " . json_encode(array_slice($embedding, 0, 5));
```

### Test Agent

```php
use App\Ai\Agents\DocumentAssistant;

$agent = new DocumentAssistant();
$agent = $agent->withContext("Cappuccetto Rosso è una bambina che va dalla nonna.");

$response = $agent->prompt("Chi è Cappuccetto Rosso?");
echo $response;
```

### Test Vector Search

```php
use App\Models\DocumentChunk;

$results = DocumentChunk::whereVectorSimilarTo('embedding', 'cappuccetto rosso', 0.3)
    ->limit(3)
    ->get();

foreach ($results as $chunk) {
    echo $chunk->content . "\n\n";
}
```

---

## 11. Architettura RAG Completa

```
┌─────────────────────────────────────────────────────────────┐
│                    UPLOAD DOCUMENTO                          │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
         ┌───────────────────────┐
         │  ProcessDocument Job  │
         └───────────┬───────────┘
                     │
         ┌───────────▼──────────┐
         │ DocumentProcessor    │  ◄── PDF/DOC/XLS/TXT/JPG
         │ (estrazione testo)   │      OCR per scansioni
         └───────────┬──────────┘
                     │
         ┌───────────▼──────────┐
         │   TextChunker        │  ◄── Recursive splitting
         │ (splitting)          │      2000 chars, 200 overlap
         └───────────┬──────────┘
                     │
         ┌───────────▼──────────┐
         │ Laravel AI SDK       │
         │ Embeddings::generate │  ◄── Ollama nomic-embed-text
         └───────────┬──────────┘
                     │
         ┌───────────▼──────────┐
         │   DocumentChunk      │  ◄── PostgreSQL + pgvector
         │ (save to database)   │      vector(768)
         └──────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    DOMANDA UTENTE                            │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────▼──────────┐
         │   ChatController     │
         └───────────┬──────────┘
                     │
         ┌───────────▼──────────────────────┐
         │ 1. Vector Similarity Search       │  ◄── pgvector
         │    whereVectorSimilarTo()         │      cosine distance
         ├───────────────────────────────────┤
         │ 2. Name-based Search              │  ◄── Cerca per nome file
         │    Document::where('title', ...)  │
         └───────────┬───────────────────────┘
                     │
         ┌───────────▼──────────┐
         │ Merge & Build Context│  ◄── Combina chunks trovati
         └───────────┬──────────┘
                     │
         ┌───────────▼──────────┐
         │ DocumentAssistant     │  ◄── Laravel AI Agent
         │ withContext()         │      + System prompt
         └───────────┬──────────┘
                     │
         ┌───────────▼──────────┐
         │ agent->stream()       │  ◄── Ollama llama3.2:3b
         │ (streaming SSE)       │      Server-Sent Events
         └───────────┬──────────┘
                     │
         ┌───────────▼──────────┐
         │ Frontend (Alpine.js) │  ◄── Real-time UI update
         │ EventStream reader   │
         └──────────────────────┘
```

---

## Conclusione

Laravel AI SDK semplifica drasticamente l'integrazione di AI in Laravel:

- **Embeddings**: Un comando per generare vettori
- **Agents**: Definizione dichiarativa con attributi PHP
- **Streaming**: Supporto nativo SSE
- **Providers**: Astratti (facile switch tra Ollama/OpenAI/etc)
- **Database**: Query vettoriali con Eloquent

Il POC dimostra un'implementazione completa RAG production-ready con:
- Indicizzazione automatica documenti
- Ricerca ibrida (vettoriale + keyword)
- Streaming real-time
- OCR per PDF scansionati
- Multi-formato (PDF, DOC, XLS, TXT, JPG)
