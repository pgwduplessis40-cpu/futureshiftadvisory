<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentExpiryReminder;
use App\Models\User;
use App\Notifications\DocumentExpiryReminderNotification;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

final class DocumentExpiryReminderService
{
    public const DEFAULT_LOOKAHEAD_DAYS = 30;

    public function __construct(private readonly AuditWriter $audit) {}

    public function sendDue(int $lookaheadDays = self::DEFAULT_LOOKAHEAD_DAYS, ?CarbonInterface $now = null): int
    {
        $now = $this->clock($now);
        $windowEnd = $now->addDays(max(1, $lookaheadDays))->endOfDay();
        $sent = 0;

        Document::query()
            ->with(['client.teamMembers.user', 'client.primaryContact'])
            ->whereNotNull('client_id')
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $now->startOfDay())
            ->where('expires_at', '<=', $windowEnd)
            ->orderBy('expires_at')
            ->get()
            ->each(function (Document $document) use ($now, &$sent): void {
                foreach ($this->recipientsFor($document) as $recipient) {
                    $reminder = DocumentExpiryReminder::query()->firstOrCreate(
                        [
                            'document_id' => $document->getKey(),
                            'user_id' => $recipient->getKey(),
                            'reminder_type' => DocumentExpiryReminder::TYPE_EXPIRES_SOON,
                        ],
                        [
                            'client_id' => $document->client_id,
                            'expires_at_snapshot' => $document->expires_at,
                            'triggered_at' => $now,
                            'metadata' => [
                                'document_category' => $document->category,
                                'original_filename' => $document->original_filename,
                            ],
                        ],
                    );

                    if (! $reminder->wasRecentlyCreated) {
                        continue;
                    }

                    Notification::send($recipient, new DocumentExpiryReminderNotification($document, $reminder));
                    $sent++;

                    $this->audit->record('document.expiry_reminder_sent', subject: $document, actor: null, after: [
                        'document_id' => $document->id,
                        'client_id' => $document->client_id,
                        'user_id' => $recipient->getKey(),
                        'expires_at' => $document->expires_at?->toIso8601String(),
                    ]);
                }
            });

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(Document $document): Collection
    {
        $client = $document->client;
        if (! $client instanceof Client) {
            return collect();
        }

        $team = $client->teamMembers
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->filter(fn (?User $user): bool => $user instanceof User && in_array($user->user_type, [
                User::TYPE_ADVISOR,
                User::TYPE_JUNIOR_ADVISOR,
                User::TYPE_CLIENT_PRIMARY,
                User::TYPE_CLIENT_TEAM,
            ], true));

        $primary = $client->primaryContact instanceof User ? [$client->primaryContact] : [];

        return $team
            ->merge($primary)
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->values();
    }

    private function clock(?CarbonInterface $now): CarbonImmutable
    {
        return $now instanceof CarbonInterface
            ? CarbonImmutable::instance($now)
            : CarbonImmutable::now();
    }
}
