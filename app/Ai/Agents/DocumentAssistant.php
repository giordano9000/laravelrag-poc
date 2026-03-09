<?php

namespace App\Ai\Agents;

use App\Models\DocumentChunk;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\SimilaritySearch;
use Stringable;

#[Provider('ollama')]
#[Model('llama3.1:8b')]
class DocumentAssistant implements Agent, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
Sei un assistente documentale intelligente. Il tuo compito è rispondere alle domande degli utenti
basandoti ESCLUSIVAMENTE sui documenti caricati nel sistema.

Regole:
1. Usa SEMPRE lo strumento di ricerca per trovare informazioni pertinenti nei documenti prima di rispondere.
2. Basa le tue risposte SOLO sulle informazioni trovate nei documenti. Non inventare informazioni.
3. Se non trovi informazioni pertinenti nei documenti, dillo chiaramente all'utente.
4. Cita i documenti fonte quando possibile, indicando da quale documento proviene l'informazione.
5. Rispondi nella stessa lingua della domanda dell'utente.
6. Sii preciso e conciso nelle risposte.
PROMPT;
    }

    public function tools(): iterable
    {
        return [
            SimilaritySearch::usingModel(
                model: DocumentChunk::class,
                column: 'embedding',
                minSimilarity: 0.5,
                limit: 10,
            )->withDescription(
                'Cerca nei documenti caricati informazioni pertinenti alla query. Restituisce i passaggi più rilevanti.'
            ),
        ];
    }
}
