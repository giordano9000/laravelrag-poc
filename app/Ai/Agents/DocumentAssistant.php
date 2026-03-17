<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

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

    public function timeout(): int
    {
        return 180;
    }

    public function instructions(): Stringable|string
    {
        $basePrompt = <<<'PROMPT'
Sei un assistente documentale intelligente. Il tuo compito è rispondere alle domande degli utenti
basandoti ESCLUSIVAMENTE sui documenti forniti nel contesto.

Regole:
1. Basa le tue risposte SOLO sulle informazioni trovate nei documenti. Non inventare informazioni.
2. Se il contesto non contiene informazioni pertinenti alla domanda, dillo chiaramente all'utente.
3. Cita i documenti fonte quando possibile.
4. Rispondi nella stessa lingua della domanda dell'utente.
5. Sii preciso e conciso nelle risposte.
PROMPT;

        if ($this->context) {
            return $basePrompt . "\n\n--- DOCUMENTI RILEVANTI ---\n" . $this->context . "\n--- FINE DOCUMENTI ---";
        }

        return $basePrompt;
    }
}
