<?php

namespace App\Http\Controllers;

use App\Ai\Agents\DocumentAssistant;
use Illuminate\Http\Request;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $agent = new DocumentAssistant();

        return new StreamedResponse(function () use ($agent, $request) {
            // Disabilita output buffering
            while (ob_get_level()) {
                ob_end_flush();
            }

            $stream = $agent->stream($request->input('message'));

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
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
