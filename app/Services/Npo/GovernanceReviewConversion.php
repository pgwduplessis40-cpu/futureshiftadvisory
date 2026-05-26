<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\NpoConversionStatus;
use App\Enums\NpoEngagementSubType;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Notifications\GovernanceReviewConversionNudgeNotification;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

final class GovernanceReviewConversion
{
    public const NUDGE_30_DAYS = 30;

    public const NUDGE_90_DAYS = 90;

    public function __construct(private readonly AuditWriter $audit) {}

    public function markReportDelivered(NpoEngagement $engagement, User $actor, ?CarbonInterface $deliveredAt = null): NpoEngagement
    {
        $this->assertGovernanceReview($engagement);
        $deliveredAt ??= now();

        $before = $this->conversionSnapshot($engagement);

        $engagement->forceFill([
            'conversion_status' => NpoConversionStatus::ReportDelivered,
            'conversion_decline_reason' => null,
            'report_delivered_at' => $deliveredAt,
            'reengagement_due_at' => Carbon::instance($deliveredAt)->copy()->addYears(3)->toDateString(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('npo.governance_review_report_delivered', subject: $engagement, actor: $actor, before: $before, after: [
            ...$this->conversionSnapshot($engagement->refresh()),
            'reengagement_basis' => 'report_delivered_at_plus_3_years',
        ]);

        return $engagement->refresh();
    }

    public function decline(NpoEngagement $engagement, User $actor, string $reason): NpoEngagement
    {
        $this->assertGovernanceReview($engagement);
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A decline reason is required.');
        }

        $before = $this->conversionSnapshot($engagement);
        $deliveredAt = $engagement->report_delivered_at ?? now();

        $engagement->forceFill([
            'conversion_status' => NpoConversionStatus::Declined,
            'conversion_decline_reason' => $reason,
            'report_delivered_at' => $deliveredAt,
            'reengagement_due_at' => Carbon::instance($deliveredAt)->copy()->addYears(3)->toDateString(),
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('npo.governance_review_conversion_declined', subject: $engagement, actor: $actor, before: $before, after: [
            ...$this->conversionSnapshot($engagement->refresh()),
            'durable_decline_signal' => true,
        ]);

        return $engagement->refresh();
    }

    public function convert(NpoEngagement $engagement, User $actor): NpoEngagement
    {
        $this->assertGovernanceReview($engagement);

        return DB::transaction(function () use ($engagement, $actor): NpoEngagement {
            $existing = NpoEngagement::query()
                ->where('client_id', $engagement->client_id)
                ->where('converted_from_npo_engagement_id', $engagement->getKey())
                ->where('sub_type', NpoEngagementSubType::StandardNpo->value)
                ->latest()
                ->first();

            if ($existing instanceof NpoEngagement) {
                $engagement->forceFill([
                    'conversion_status' => NpoConversionStatus::Converted,
                    'conversion_decline_reason' => null,
                    'updated_by_user_id' => $actor->getKey(),
                ])->save();

                return $existing->refresh();
            }

            $before = $this->conversionSnapshot($engagement);
            $deliveredAt = $engagement->report_delivered_at ?? now();

            $converted = NpoEngagement::query()->create([
                'client_id' => $engagement->client_id,
                'sub_type' => NpoEngagementSubType::StandardNpo,
                'legal_structure' => $engagement->legal_structure,
                'isa_2022_reregistered' => $engagement->isa_2022_reregistered,
                'converted_from_npo_engagement_id' => $engagement->getKey(),
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            $engagement->forceFill([
                'conversion_status' => NpoConversionStatus::Converted,
                'conversion_decline_reason' => null,
                'report_delivered_at' => $deliveredAt,
                'reengagement_due_at' => Carbon::instance($deliveredAt)->copy()->addYears(3)->toDateString(),
                'updated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record('npo.governance_review_converted', subject: $engagement, actor: $actor, before: $before, after: [
                ...$this->conversionSnapshot($engagement->refresh()),
                'converted_npo_engagement_id' => $converted->getKey(),
                'dimension_3_prepopulation_deferred_to' => 'WO-N09',
            ]);

            return $converted->refresh();
        });
    }

    public function sendDueNudges(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $sent = 0;

        NpoEngagement::query()
            ->with(['client.teamMembers.user'])
            ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
            ->where('conversion_status', NpoConversionStatus::ReportDelivered->value)
            ->whereNotNull('report_delivered_at')
            ->orderBy('report_delivered_at')
            ->chunkById(100, function ($engagements) use ($now, &$sent): void {
                foreach ($engagements as $engagement) {
                    foreach ($this->dueNudgeDays($engagement, $now) as $nudgeDay) {
                        $recipients = $this->advisorRecipients($engagement);

                        if ($recipients->isNotEmpty()) {
                            Notification::send($recipients->all(), new GovernanceReviewConversionNudgeNotification($engagement, $nudgeDay));
                        }

                        $this->audit->record('npo.governance_review_conversion_nudge_sent', subject: $engagement, actor: null, after: [
                            'nudge_day' => $nudgeDay,
                            'recipient_count' => $recipients->count(),
                            'report_delivered_at' => $engagement->report_delivered_at?->toIso8601String(),
                            'conversion_status' => $engagement->conversion_status?->value,
                        ]);

                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * @return array<string, mixed>
     */
    public function pendingPanel(User $user): array
    {
        $clientIds = $user->user_type === User::TYPE_SUPER_ADMIN ? null : $user->accessibleClientIds();

        if ($clientIds === []) {
            return $this->emptyPanel();
        }

        $query = NpoEngagement::query()
            ->with('client')
            ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
            ->whereIn('conversion_status', [
                NpoConversionStatus::ReportDelivered->value,
                NpoConversionStatus::Declined->value,
            ])
            ->latest('report_delivered_at')
            ->latest()
            ->limit(12);

        if (is_array($clientIds)) {
            $query->whereIn('client_id', $clientIds);
        }

        $items = $query
            ->get()
            ->map(fn (NpoEngagement $engagement): array => $this->panelItem($engagement))
            ->values();

        return [
            'summary' => [
                'total' => $items->count(),
                'report_delivered' => $items->where('status', NpoConversionStatus::ReportDelivered->value)->count(),
                'declined' => $items->where('status', NpoConversionStatus::Declined->value)->count(),
                'nudge_due' => $items->filter(fn (array $item): bool => $item['next_nudge_day'] !== null)->count(),
            ],
            'items' => $items->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientSummary(Client $client): ?array
    {
        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
            ->latest('report_delivered_at')
            ->latest()
            ->first();

        if (! $engagement instanceof NpoEngagement) {
            return null;
        }

        return [
            ...$this->panelItem($engagement),
            'report_delivered_url' => route('advisor.npo-engagements.conversion.report-delivered', $engagement, absolute: false),
            'decline_url' => route('advisor.npo-engagements.conversion.decline', $engagement, absolute: false),
            'convert_url' => route('advisor.npo-engagements.conversion.convert', $engagement, absolute: false),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function dueNudgeDays(NpoEngagement $engagement, CarbonInterface $now): array
    {
        if (! $engagement->report_delivered_at instanceof CarbonInterface) {
            return [];
        }

        $daysSinceDelivery = $engagement->report_delivered_at->copy()->startOfDay()->diffInDays(Carbon::instance($now)->copy()->startOfDay(), false);

        return collect([self::NUDGE_30_DAYS, self::NUDGE_90_DAYS])
            ->filter(fn (int $day): bool => $daysSinceDelivery >= $day && ! $this->nudgeAlreadySent($engagement, $day))
            ->values()
            ->all();
    }

    private function nudgeAlreadySent(NpoEngagement $engagement, int $nudgeDay): bool
    {
        return AuditEvent::query()
            ->where('action', 'npo.governance_review_conversion_nudge_sent')
            ->where('subject_id', (string) $engagement->getKey())
            ->where('after->nudge_day', $nudgeDay)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function panelItem(NpoEngagement $engagement): array
    {
        $dueDays = $this->dueNudgeDays($engagement, now());

        return [
            'id' => $engagement->id,
            'client_id' => $engagement->client_id,
            'client_name' => $engagement->client?->legal_name,
            'status' => $engagement->conversion_status?->value,
            'status_label' => $engagement->conversion_status?->label(),
            'decline_reason' => $engagement->conversion_decline_reason,
            'report_delivered_at' => $engagement->report_delivered_at?->toIso8601String(),
            'reengagement_due_at' => $engagement->reengagement_due_at?->toDateString(),
            'next_nudge_day' => $dueDays[0] ?? null,
            'client_url' => route('advisor.clients.show', $engagement->client_id, absolute: false),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function advisorRecipients(NpoEngagement $engagement): Collection
    {
        $engagement->loadMissing('client.teamMembers.user');

        return $engagement->client?->teamMembers
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->filter(fn (?User $user): bool => $user instanceof User
                && in_array($user->user_type, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true))
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values() ?? collect();
    }

    /**
     * @return array<string, mixed>
     */
    private function conversionSnapshot(NpoEngagement $engagement): array
    {
        return [
            'conversion_status' => $engagement->conversion_status?->value,
            'conversion_decline_reason' => $engagement->conversion_decline_reason,
            'report_delivered_at' => $engagement->report_delivered_at?->toIso8601String(),
            'reengagement_due_at' => $engagement->reengagement_due_at?->toDateString(),
        ];
    }

    private function assertGovernanceReview(NpoEngagement $engagement): void
    {
        if ($engagement->sub_type !== NpoEngagementSubType::GovernanceReview) {
            throw new InvalidArgumentException('Only governance-review NPO engagements can use the conversion pipeline.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPanel(): array
    {
        return [
            'summary' => [
                'total' => 0,
                'report_delivered' => 0,
                'declined' => 0,
                'nudge_due' => 0,
            ],
            'items' => [],
        ];
    }
}
