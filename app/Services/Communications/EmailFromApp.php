<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Mail\ClientEmailFromApp;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Notifications\ChannelResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EmailFromApp
{
    public function __construct(private readonly AuditWriter $auditWriter) {}

    /**
     * @param  array<int, int|string>  $recipientUserIds
     */
    public function send(
        Client $client,
        User $sender,
        array $recipientUserIds,
        string $subject,
        string $body,
        ?string $logicalMessageKey = null,
    ): Message {
        $subject = trim($subject);
        $body = trim($body);
        $logicalMessageKey = $this->logicalMessageKey($client, $recipientUserIds, $subject, $body, $logicalMessageKey);

        $existing = Message::query()
            ->where('logical_message_key', $logicalMessageKey)
            ->where('channel', Message::CHANNEL_EMAIL)
            ->with(['thread.client', 'sender'])
            ->first();

        if ($existing instanceof Message) {
            return $existing;
        }

        $recipients = $this->recipientsFor($client, $recipientUserIds);
        $parallelInApp = Message::query()
            ->where('logical_message_key', $logicalMessageKey)
            ->where('channel', Message::CHANNEL_IN_APP)
            ->with('thread')
            ->first();

        [$recipientLog, $decisions] = $this->sendMail($client, $sender, $recipients, $subject, $body, $parallelInApp instanceof Message);
        $deliveryState = $this->deliveryState($recipientLog);

        $message = DB::transaction(function () use (
            $client,
            $sender,
            $subject,
            $body,
            $logicalMessageKey,
            $parallelInApp,
            $recipientLog,
            $decisions,
            $deliveryState,
        ): Message {
            $thread = $this->threadFor($client, $sender, $subject, $parallelInApp);
            $sentAt = now();

            foreach ([$sender, ...$this->usersFromLog($recipientLog)] as $participant) {
                MessageThreadParticipant::query()->firstOrCreate([
                    'thread_id' => $thread->getKey(),
                    'user_id' => $participant->getKey(),
                ]);
            }

            $message = Message::query()->create([
                'thread_id' => $thread->getKey(),
                'sender_user_id' => $sender->getKey(),
                'channel' => Message::CHANNEL_EMAIL,
                'body' => $body,
                'attachments' => null,
                'delivery_state' => $deliveryState,
                'channel_decision' => [
                    'logical_message_key' => $logicalMessageKey,
                    'parallel_in_app_exists' => $parallelInApp instanceof Message,
                    'recipients' => $decisions,
                    'decided_at' => $sentAt->toIso8601String(),
                ],
                'logical_message_key' => $logicalMessageKey,
                'email_subject' => $subject,
                'email_recipients' => $recipientLog,
                'sent_at' => $sentAt,
            ]);

            $thread->forceFill(['last_activity_at' => $sentAt])->save();

            MessageThreadParticipant::query()->updateOrCreate(
                [
                    'thread_id' => $thread->getKey(),
                    'user_id' => $sender->getKey(),
                ],
                ['last_read_at' => $sentAt],
            );

            $this->auditWriter->record('email_from_app.sent', subject: $thread, actor: $sender, after: [
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'client_id' => $client->id,
                'delivery_state' => $deliveryState,
                'logical_message_key' => $logicalMessageKey,
            ]);

            return $message;
        });

        return $message->refresh()->loadMissing(['thread.client', 'sender']);
    }

    /**
     * @param  array<int, int|string>  $recipientUserIds
     * @return Collection<int, User>
     */
    private function recipientsFor(Client $client, array $recipientUserIds): Collection
    {
        $requestedIds = collect($recipientUserIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            throw ValidationException::withMessages(['recipient_user_ids' => 'Select at least one recipient.']);
        }

        $allowedIds = ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->whereHas('user', fn ($query) => $query->whereIn('user_type', [
                User::TYPE_CLIENT_PRIMARY,
                User::TYPE_CLIENT_TEAM,
            ]))
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id);

        if ($client->primary_contact_user_id !== null) {
            $allowedIds->push((int) $client->primary_contact_user_id);
        }

        $allowedIds = $allowedIds->unique()->values();
        $invalidIds = $requestedIds->diff($allowedIds);
        if ($invalidIds->isNotEmpty()) {
            throw ValidationException::withMessages(['recipient_user_ids' => 'One or more recipients are not assigned to this client.']);
        }

        $recipients = User::query()
            ->whereKey($requestedIds->all())
            ->get()
            ->keyBy(fn (User $user): int => (int) $user->getKey());

        if ($recipients->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages(['recipient_user_ids' => 'One or more recipients could not be found.']);
        }

        return $requestedIds
            ->map(fn (int $id): User => $recipients->get($id))
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function sendMail(
        Client $client,
        User $sender,
        Collection $recipients,
        string $subject,
        string $body,
        bool $parallelInAppExists,
    ): array {
        $recipientLog = [];
        $decisions = [];

        foreach ($recipients as $recipient) {
            $decision = $this->decisionFor($recipient, $parallelInAppExists);
            $deliveryState = $this->deliveryStateForDecision($decision);

            if ($deliveryState === Message::DELIVERY_SENT) {
                try {
                    Mail::to($recipient->email)->send(new ClientEmailFromApp(
                        client: $client,
                        sender: $sender,
                        subjectLine: $subject,
                        bodyText: $body,
                    ));
                } catch (Throwable $e) {
                    report($e);
                    $deliveryState = Message::DELIVERY_FAILED;
                    $decision['delivery_error'] = $e->getMessage();
                    $decision['mail_now'] = false;
                }
            }

            $recipientLog[] = [
                'user_id' => $recipient->id,
                'name' => $recipient->name,
                'email' => $recipient->email,
                'delivery_state' => $deliveryState,
            ];

            $decisions[] = [
                'user_id' => $recipient->id,
                'email' => $recipient->email,
                ...$decision,
                'delivery_state' => $deliveryState,
            ];
        }

        return [$recipientLog, $decisions];
    }

    /**
     * @return array<string, mixed>
     */
    private function decisionFor(User $recipient, bool $parallelInAppExists): array
    {
        /** @var CommunicationPreference $preference */
        $preference = $recipient->communicationPreference()->firstOrCreate([], [
            'channel' => CommunicationPreference::CHANNEL_BOTH,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);

        $emailAllowed = in_array($preference->channel, [
            CommunicationPreference::CHANNEL_EMAIL_ONLY,
            CommunicationPreference::CHANNEL_BOTH,
        ], true);
        $platformAllowed = in_array($preference->channel, [
            CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            CommunicationPreference::CHANNEL_BOTH,
        ], true);
        $mailNow = $emailAllowed && ! $parallelInAppExists;

        return [
            'channels' => array_values(array_filter([
                $mailNow ? 'mail' : null,
                $platformAllowed ? ChannelResolver::DATABASE_CHANNEL : null,
            ])),
            'urgency' => 'normal',
            'preference_channel' => $preference->channel,
            'frequency' => $preference->frequency,
            'mail_now' => $mailNow,
            'email_deferred' => false,
            'platform_now' => $platformAllowed,
            'bypassed_preference' => false,
            'parallel_in_app_exists' => $parallelInAppExists,
            'skipped_reason' => $parallelInAppExists
                ? 'parallel_in_app'
                : ($emailAllowed ? null : 'preference'),
            'decided_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function deliveryStateForDecision(array $decision): string
    {
        if (($decision['parallel_in_app_exists'] ?? false) === true) {
            return Message::DELIVERY_SKIPPED_PARALLEL_IN_APP;
        }

        if (($decision['mail_now'] ?? false) !== true) {
            return Message::DELIVERY_SKIPPED_PREFERENCE;
        }

        return Message::DELIVERY_SENT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $recipientLog
     */
    private function deliveryState(array $recipientLog): string
    {
        $states = collect($recipientLog)->pluck('delivery_state')->all();
        $unique = array_values(array_unique(array_map('strval', $states)));

        if ($unique === [Message::DELIVERY_SENT]) {
            return Message::DELIVERY_SENT;
        }

        if ($unique === [Message::DELIVERY_SKIPPED_PARALLEL_IN_APP]) {
            return Message::DELIVERY_SKIPPED_PARALLEL_IN_APP;
        }

        if ($unique === [Message::DELIVERY_SKIPPED_PREFERENCE]) {
            return Message::DELIVERY_SKIPPED_PREFERENCE;
        }

        if ($unique === [Message::DELIVERY_FAILED]) {
            return Message::DELIVERY_FAILED;
        }

        return Message::DELIVERY_PARTIAL;
    }

    private function threadFor(Client $client, User $sender, string $subject, ?Message $parallelInApp): MessageThread
    {
        if ($parallelInApp instanceof Message && $parallelInApp->thread instanceof MessageThread) {
            return $parallelInApp->thread;
        }

        return MessageThread::query()->create([
            'client_id' => $client->getKey(),
            'created_by_user_id' => $sender->getKey(),
            'subject' => 'Email: '.$subject,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $recipientLog
     * @return array<int, User>
     */
    private function usersFromLog(array $recipientLog): array
    {
        $ids = collect($recipientLog)
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return User::query()->whereKey($ids)->get()->all();
    }

    /**
     * @param  array<int, int|string>  $recipientUserIds
     */
    private function logicalMessageKey(
        Client $client,
        array $recipientUserIds,
        string $subject,
        string $body,
        ?string $provided,
    ): string {
        $provided = is_string($provided) ? trim($provided) : '';

        if ($provided !== '') {
            return Str::limit($provided, 120, '');
        }

        $recipientKey = collect($recipientUserIds)
            ->map(fn (int|string $id): string => (string) $id)
            ->sort()
            ->implode(',');

        return hash('sha256', implode('|', [
            $client->getKey(),
            $recipientKey,
            Str::lower($subject),
            $body,
        ]));
    }
}
