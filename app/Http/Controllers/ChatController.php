<?php

namespace App\Http\Controllers;

use App\Ai\Agents\DocumentAssistant;
use Illuminate\Http\Request;
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
            $stream = $agent->stream($request->input('message'));

            foreach ($stream as $event) {
                echo "data: " . json_encode(['text' => (string) $event]) . "\n\n";
                ob_flush();
                flush();
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
