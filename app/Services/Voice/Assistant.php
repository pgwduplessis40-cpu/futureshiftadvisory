<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\CallLog;
use App\Models\Client;
use App\Models\User;
use App\Models\VoiceAssistantSession;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Assistant
{
    public function __construct(
        private readonly VoiceNoteProcessor $processor,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function startShortcutSession(
        Client $client,
        User $advisor,
        string $intent = VoiceAssistantSession::INTENT_CAPTURE_CALL_NOTE,
        array $context = [],
    ): VoiceAssistantSession {
        $this->assertStaticIntent($intent);

        return DB::transaction(function () use ($client, $advisor, $intent, $context): VoiceAssistantSession {
            /** @var VoiceAssistantSession $session */
            $session = VoiceAssistantSession::query()->create([
                'client_id' => $client->getKey(),
                'advisor_user_id' => $advisor->getKey(),
                'status' => VoiceAssistantSession::STATUS_STARTED,
                'shortcut_intent' => $intent,
                'shortcut_payload' => $this->shortcutPayload($client, $advisor, $intent, $context),
                'started_at' => now(),
            ]);

            $this->audit->record('voice_assistant.session_started', subject: $session, actor: $advisor, after: [
                'intent' => $intent,
                'client_id' => $client->getKey(),
            ]);

            return $session->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function completeShortcutSession(VoiceAssistantSession $session, array $input, ?User $actor = null): VoiceAssistantSession
    {
        if ($session->status !== VoiceAssistantSession::STATUS_STARTED) {
            throw new InvalidArgumentException('Voice assistant session is not active.');
        }

        $session->loadMissing('client');
        $client = $session->client;
        if (! $client instanceof Client) {
            throw new InvalidArgumentException('Voice assistant session client is missing.');
        }

        return DB::transaction(function () use ($session, $input, $actor, $client): VoiceAssistantSession {
            $transcript = trim((string) ($input['transcript'] ?? ''));
            $summary = trim((string) ($input['summary'] ?? ''));

            /** @var CallLog $callLog */
            $callLog = $this->processor->recordCallLog($client, [
                'title' => trim((string) ($input['title'] ?? '')) ?: 'Voice assistant note',
                'channel' => CallLog::CHANNEL_PHONE_CALL,
                'occurred_at' => $input['occurred_at'] ?? now(),
                'transcript' => $transcript !== '' ? $transcript : null,
                'summary' => $summary !== '' ? $summary : null,
                'action_items' => is_array($input['action_items'] ?? null) ? $input['action_items'] : [],
            ], $actor);

            $session->forceFill([
                'status' => VoiceAssistantSession::STATUS_COMPLETED,
                'call_log_id' => $callLog->getKey(),
                'transcript' => $transcript !== '' ? $transcript : null,
                'completed_at' => now(),
            ])->save();

            $this->audit->record('voice_assistant.session_completed', subject: $session, actor: $actor, after: [
                'call_log_id' => $callLog->getKey(),
                'intent' => $session->shortcut_intent,
            ]);

            return $session->refresh();
        });
    }

    public function cancel(VoiceAssistantSession $session, User $actor): VoiceAssistantSession
    {
        if ($session->status !== VoiceAssistantSession::STATUS_STARTED) {
            return $session->refresh();
        }

        $session->forceFill([
            'status' => VoiceAssistantSession::STATUS_CANCELLED,
            'completed_at' => now(),
        ])->save();

        $this->audit->record('voice_assistant.session_cancelled', subject: $session, actor: $actor, after: [
            'intent' => $session->shortcut_intent,
        ]);

        return $session->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function shortcutPayload(Client $client, User $advisor, string $intent, array $context = []): array
    {
        $this->assertStaticIntent($intent);

        return [
            'schema_version' => '1',
            'intent' => $intent,
            'client_id' => (string) $client->getKey(),
            'client_name' => (string) ($client->legal_name ?? $client->name ?? ''),
            'advisor_user_id' => (string) $advisor->getKey(),
            'capture_fields' => ['transcript', 'summary', 'action_items'],
            'context' => array_intersect_key($context, array_flip(['source', 'device', 'timezone'])),
        ];
    }

    private function assertStaticIntent(string $intent): void
    {
        if (! in_array($intent, VoiceAssistantSession::intents(), true)) {
            throw new InvalidArgumentException('Unsupported voice assistant shortcut intent.');
        }
    }
}
