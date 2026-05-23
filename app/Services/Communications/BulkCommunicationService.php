<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Enums\ClientStatus;
use App\Mail\BulkCommunicationMail;
use App\Models\BulkCommunication;
use App\Models\BulkCommunicationRecipient;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class BulkCommunicationService
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function schedule(array $payload, User $actor): BulkCommunication
    {
        $scheduledAt = $this->scheduledAt($payload['scheduled_at'] ?? null);
        $audienceType = (string) ($payload['audience_type'] ?? BulkCommunication::AUDIENCE_SELECTED_CLIENTS);
        $selectedClientIds = $audienceType === BulkCommunication::AUDIENCE_SELECTED_CLIENTS
            ? $this->normaliseIds($payload['selected_client_ids'] ?? [])
            : null;

        $communication = BulkCommunication::query()->create([
            'title' => trim((string) $payload['title']),
            'template_key' => ! isset($payload['template_key']) || $payload['template_key'] === ''
                ? null
                : (string) $payload['template_key'],
            'subject' => trim((string) $payload['subject']),
            'body' => trim((string) $payload['body']),
            'audience_type' => $audienceType,
            'selected_client_ids' => $selectedClientIds,
            'status' => BulkCommunication::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'created_by_user_id' => $actor->getKey(),
            'metrics' => [
                'recipients_count' => 0,
                'email_recipients_count' => 0,
                'opens_count' => 0,
                'open_rate' => 0.0,
            ],
        ]);

        $this->audit->record('bulk_communication.scheduled', subject: $communication, actor: $actor, after: [
            'bulk_communication_id' => $communication->id,
            'audience_type' => $communication->audience_type,
            'selected_client_ids' => $communication->selected_client_ids,
            'scheduled_at' => $communication->scheduled_at?->toIso8601String(),
        ]);

        return $communication;
    }

    /**
     * @return Collection<int, BulkCommunication>
     */
    public function sendDue(?CarbonInterface $now = null): Collection
    {
        $now = $this->clock($now);

        return BulkCommunication::query()
            ->where('status', BulkCommunication::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (BulkCommunication $communication): BulkCommunication => $this->send($communication, $now));
    }

    public function send(BulkCommunication $communication, ?CarbonInterface $now = null): BulkCommunication
    {
        if ($communication->status === BulkCommunication::STATUS_SENT) {
            return $communication->refresh();
        }

        $now = $this->clock($now);
        $preparedRecipients = DB::transaction(fn (): Collection => $this->prepareRecipients($communication, $now));

        foreach ($preparedRecipients as $recipient) {
            $this->deliverRecipient($recipient, $now);
        }

        $communication = $this->refreshMetrics($communication->refresh());
        $communication->forceFill([
            'status' => BulkCommunication::STATUS_SENT,
            'sent_at' => $now,
        ])->save();

        $this->audit->record('bulk_communication.sent', subject: $communication, actor: $communication->createdBy, after: [
            'bulk_communication_id' => $communication->id,
            'metrics' => $communication->metrics,
            'sent_at' => $communication->sent_at?->toIso8601String(),
        ]);

        return $communication->refresh();
    }

    public function trackOpen(string $token): ?BulkCommunicationRecipient
    {
        $recipient = BulkCommunicationRecipient::query()
            ->where('open_token', $token)
            ->with('bulkCommunication')
            ->first();

        if (! $recipient instanceof BulkCommunicationRecipient) {
            return null;
        }

        if ($recipient->opened_at === null) {
            $recipient->forceFill(['opened_at' => now()])->save();
            $this->refreshMetrics($recipient->bulkCommunication);

            $this->audit->record('bulk_communication.opened', subject: $recipient, actor: null, after: [
                'bulk_communication_id' => $recipient->bulk_communication_id,
                'recipient_id' => $recipient->id,
                'client_id' => $recipient->client_id,
            ]);
        }

        return $recipient->refresh();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function templateOptions(): array
    {
        return collect(BulkCommunication::templates())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function scheduledAt(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::now();
    }

    private function clock(?CarbonInterface $now): CarbonImmutable
    {
        return $now instanceof CarbonInterface
            ? CarbonImmutable::instance($now)
            : CarbonImmutable::now();
    }

    /**
     * @return array<int, string>
     */
    private function normaliseIds(mixed $ids): array
    {
        return collect(is_array($ids) ? $ids : [])
            ->map(fn (mixed $id): string => (string) $id)
            ->filter(fn (string $id): bool => Str::isUuid($id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, BulkCommunicationRecipient>
     */
    private function prepareRecipients(BulkCommunication $communication, CarbonImmutable $now): Collection
    {
        $prepared = collect();

        foreach ($this->audienceClients($communication) as $client) {
            $clientRecipients = $this->clientRecipients($client);
            $platformRecipients = collect();

            foreach ($clientRecipients as $user) {
                $decision = $this->decisionFor($user);
                $recipient = BulkCommunicationRecipient::query()->firstOrCreate(
                    [
                        'bulk_communication_id' => $communication->getKey(),
                        'client_id' => $client->getKey(),
                        'user_id' => $user->getKey(),
                    ],
                    [
                        'channel' => $decision['channel'],
                        'preference_channel' => $decision['preference_channel'],
                        'preference_frequency' => $decision['preference_frequency'],
                        'status' => BulkCommunicationRecipient::STATUS_PENDING,
                        'open_token' => $decision['email'] ? Str::random(48) : null,
                        'delivery_metadata' => [
                            'template_key' => $communication->template_key,
                            'prepared_at' => $now->toIso8601String(),
                            'mail_now' => $decision['email'],
                            'platform_now' => $decision['platform'],
                        ],
                    ],
                );

                if ($decision['platform']) {
                    $platformRecipients->push($recipient);
                }

                $prepared->push($recipient->loadMissing(['client', 'user', 'bulkCommunication.createdBy']));
            }

            if ($platformRecipients->isNotEmpty()) {
                $message = $this->createPlatformMessage($communication, $client, $platformRecipients, $now);
                BulkCommunicationRecipient::query()
                    ->whereKey($platformRecipients->pluck('id')->all())
                    ->update(['message_id' => $message->getKey()]);
            }
        }

        return $prepared->values();
    }

    /**
     * @return EloquentCollection<int, Client>
     */
    private function audienceClients(BulkCommunication $communication): EloquentCollection
    {
        $query = Client::query()
            ->with(['teamMembers.user.communicationPreference', 'primaryContact.communicationPreference'])
            ->orderBy('legal_name');

        if ($communication->audience_type === BulkCommunication::AUDIENCE_ALL_CLIENTS) {
            return $query
                ->where('status', ClientStatus::ACTIVE->value)
                ->get();
        }

        return $query
            ->whereKey($this->normaliseIds($communication->selected_client_ids ?? []))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function clientRecipients(Client $client): Collection
    {
        $fromTeam = $client->teamMembers
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->filter(fn (?User $user): bool => $this->isClientRecipient($user));

        $primary = $this->isClientRecipient($client->primaryContact) ? [$client->primaryContact] : [];

        return $fromTeam
            ->merge($primary)
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->values();
    }

    private function isClientRecipient(?User $user): bool
    {
        return $user instanceof User
            && in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true);
    }

    /**
     * @return array{channel: string, preference_channel: string, preference_frequency: string, email: bool, platform: bool}
     */
    private function decisionFor(User $user): array
    {
        /** @var CommunicationPreference $preference */
        $preference = $user->communicationPreference()->firstOrCreate([], [
            'channel' => CommunicationPreference::CHANNEL_BOTH,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);

        $email = in_array($preference->channel, [
            CommunicationPreference::CHANNEL_EMAIL_ONLY,
            CommunicationPreference::CHANNEL_BOTH,
        ], true);
        $platform = in_array($preference->channel, [
            CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            CommunicationPreference::CHANNEL_BOTH,
        ], true);

        $channel = match (true) {
            $email && $platform => BulkCommunicationRecipient::CHANNEL_EMAIL_AND_IN_PLATFORM,
            $email => BulkCommunicationRecipient::CHANNEL_EMAIL,
            default => BulkCommunicationRecipient::CHANNEL_IN_PLATFORM,
        };

        return [
            'channel' => $channel,
            'preference_channel' => (string) $preference->channel,
            'preference_frequency' => (string) $preference->frequency,
            'email' => $email,
            'platform' => $platform,
        ];
    }

    /**
     * @param  Collection<int, BulkCommunicationRecipient>  $platformRecipients
     */
    private function createPlatformMessage(
        BulkCommunication $communication,
        Client $client,
        Collection $platformRecipients,
        CarbonImmutable $now,
    ): Message {
        $logicalKey = $this->logicalMessageKey($communication, $client);
        $existing = Message::query()
            ->where('logical_message_key', $logicalKey)
            ->where('channel', Message::CHANNEL_IN_APP)
            ->first();

        if ($existing instanceof Message) {
            return $existing;
        }

        $sender = $communication->createdBy;
        $thread = MessageThread::query()->create([
            'client_id' => $client->getKey(),
            'created_by_user_id' => $sender?->getKey(),
            'subject' => 'Bulk communication: '.$communication->subject,
            'last_activity_at' => $now,
        ]);

        $participantIds = $platformRecipients
            ->pluck('user_id')
            ->push($sender?->getKey())
            ->filter()
            ->unique()
            ->values();

        foreach ($participantIds as $userId) {
            MessageThreadParticipant::query()->firstOrCreate([
                'thread_id' => $thread->getKey(),
                'user_id' => $userId,
            ], [
                'last_read_at' => (string) $userId === (string) $sender?->getKey() ? $now : null,
            ]);
        }

        return Message::query()->create([
            'thread_id' => $thread->getKey(),
            'sender_user_id' => $sender?->getKey(),
            'channel' => Message::CHANNEL_IN_APP,
            'body' => $communication->body,
            'delivery_state' => Message::DELIVERY_SENT,
            'channel_decision' => [
                'logical_message_key' => $logicalKey,
                'bulk_communication_id' => $communication->id,
                'template_key' => $communication->template_key,
                'platform_recipient_count' => $platformRecipients->count(),
                'decided_at' => $now->toIso8601String(),
            ],
            'logical_message_key' => $logicalKey,
            'sent_at' => $now,
        ]);
    }

    private function deliverRecipient(BulkCommunicationRecipient $recipient, CarbonImmutable $now): void
    {
        $recipient = $recipient->refresh()->loadMissing(['client', 'user', 'bulkCommunication.createdBy']);
        if ($recipient->status !== BulkCommunicationRecipient::STATUS_PENDING) {
            return;
        }

        $usesEmail = in_array($recipient->channel, [
            BulkCommunicationRecipient::CHANNEL_EMAIL,
            BulkCommunicationRecipient::CHANNEL_EMAIL_AND_IN_PLATFORM,
        ], true);

        if (! $usesEmail) {
            $recipient->forceFill([
                'status' => BulkCommunicationRecipient::STATUS_SENT,
                'sent_at' => $now,
            ])->save();

            return;
        }

        if (! $recipient->client instanceof Client || ! $recipient->user instanceof User || ! $recipient->bulkCommunication instanceof BulkCommunication) {
            $recipient->forceFill([
                'status' => BulkCommunicationRecipient::STATUS_SKIPPED,
                'skipped_reason' => 'missing_recipient_context',
                'sent_at' => $now,
            ])->save();

            return;
        }

        try {
            Mail::to($recipient->user->email)->send(new BulkCommunicationMail(
                communication: $recipient->bulkCommunication,
                recipient: $recipient,
                client: $recipient->client,
                sender: $recipient->bulkCommunication->createdBy ?? $recipient->user,
            ));

            $recipient->forceFill([
                'status' => BulkCommunicationRecipient::STATUS_SENT,
                'sent_at' => $now,
            ])->save();
        } catch (Throwable $e) {
            report($e);
            $recipient->forceFill([
                'status' => BulkCommunicationRecipient::STATUS_FAILED,
                'skipped_reason' => 'mail_failure',
                'sent_at' => $now,
                'delivery_metadata' => array_merge($recipient->delivery_metadata ?? [], [
                    'delivery_error' => $e->getMessage(),
                ]),
            ])->save();
        }
    }

    private function refreshMetrics(BulkCommunication $communication): BulkCommunication
    {
        $recipients = BulkCommunicationRecipient::query()
            ->where('bulk_communication_id', $communication->getKey())
            ->get();
        $emailRecipients = $recipients->filter(fn (BulkCommunicationRecipient $recipient): bool => $recipient->open_token !== null);
        $opens = $emailRecipients->filter(fn (BulkCommunicationRecipient $recipient): bool => $recipient->opened_at !== null)->count();

        $communication->forceFill([
            'metrics' => [
                'recipients_count' => $recipients->count(),
                'email_recipients_count' => $emailRecipients->count(),
                'sent_count' => $recipients->where('status', BulkCommunicationRecipient::STATUS_SENT)->count(),
                'skipped_count' => $recipients->where('status', BulkCommunicationRecipient::STATUS_SKIPPED)->count(),
                'failed_count' => $recipients->where('status', BulkCommunicationRecipient::STATUS_FAILED)->count(),
                'opens_count' => $opens,
                'open_rate' => $emailRecipients->isEmpty() ? 0.0 : round($opens / $emailRecipients->count(), 4),
            ],
        ])->save();

        return $communication->refresh();
    }

    private function logicalMessageKey(BulkCommunication $communication, Client $client): string
    {
        return hash('sha256', implode('|', [
            'bulk_communication',
            $communication->getKey(),
            $client->getKey(),
        ]));
    }
}
