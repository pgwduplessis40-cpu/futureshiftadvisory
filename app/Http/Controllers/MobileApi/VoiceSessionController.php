<?php

declare(strict_types=1);

namespace App\Http\Controllers\MobileApi;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Models\VoiceAssistantSession;
use App\Services\Voice\Assistant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class VoiceSessionController extends Controller
{
    public function store(Request $request, Assistant $assistant): array
    {
        $validated = $request->validate([
            'client_id' => ['required', 'uuid'],
            'intent' => ['sometimes', 'string', Rule::in(VoiceAssistantSession::intents())],
            'context' => ['sometimes', 'array'],
            'context.source' => ['sometimes', 'string', 'max:80'],
            'context.device' => ['sometimes', 'string', 'max:120'],
            'context.timezone' => ['sometimes', 'string', 'max:80'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $client = Client::query()->whereKey((string) $validated['client_id'])->first();
        if (! $client instanceof Client || ! in_array((string) $client->getKey(), $user->accessibleClientIds(), true)) {
            throw new NotFoundHttpException;
        }

        $session = $assistant->startShortcutSession(
            client: $client,
            advisor: $user,
            intent: (string) ($validated['intent'] ?? VoiceAssistantSession::INTENT_CAPTURE_CALL_NOTE),
            context: is_array($validated['context'] ?? null) ? $validated['context'] : [],
        );

        return [
            'session' => [
                'id' => (string) $session->getKey(),
                'status' => $session->status,
                'shortcut_payload' => $session->shortcut_payload,
            ],
        ];
    }
}
