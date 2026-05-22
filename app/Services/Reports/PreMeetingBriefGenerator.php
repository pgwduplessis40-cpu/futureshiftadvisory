<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FinancialAlert;
use App\Models\Meeting;
use App\Models\PreMeetingBrief;
use App\Models\Proposal;
use App\Models\RedFlag;
use App\Models\Report;
use App\Models\User;
use App\Notifications\PreMeetingBriefNotification;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class PreMeetingBriefGenerator
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function generate(Meeting $meeting, ?User $actor = null): PreMeetingBrief
    {
        $meeting = $meeting->refresh()->load('client');
        $client = $meeting->client;

        return DB::transaction(function () use ($actor, $client, $meeting): PreMeetingBrief {
            $redFlags = $this->redFlags($client);

            $brief = PreMeetingBrief::query()->firstOrCreate(
                ['meeting_id' => $meeting->getKey()],
                [
                    'client_id' => $client->getKey(),
                    'meeting_at' => $meeting->scheduled_at,
                    'body' => $this->body($meeting, $client, $redFlags),
                    'red_flag_ids' => $redFlags->pluck('id')->values()->all(),
                    'generated_at' => now(),
                ],
            );

            if ($brief->wasRecentlyCreated) {
                $this->audit->record('pre_meeting_brief.generated', subject: $brief, actor: $actor, after: [
                    'client_id' => $client->getKey(),
                    'meeting_id' => $meeting->getKey(),
                    'meeting_at' => $meeting->scheduled_at?->toIso8601String(),
                    'red_flag_ids' => $brief->red_flag_ids,
                ]);
            }

            return $brief->refresh();
        });
    }

    public function generateDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $windowStart = $now->copy()->addHours(23);
        $windowEnd = $now->copy()->addHours(25);
        $generated = 0;

        $this->context->apply('system', []);

        Meeting::query()
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->whereDoesntHave('preMeetingBrief')
            ->orderBy('scheduled_at')
            ->each(function (Meeting $meeting) use (&$generated): void {
                $this->generate($meeting);
                $generated++;
            });

        return $generated;
    }

    public function reviewAndSend(PreMeetingBrief $brief, User $actor): PreMeetingBrief
    {
        $brief = $brief->refresh()->load(['client', 'meeting']);

        if ($brief->sent_at !== null) {
            return $brief;
        }

        $this->context->apply('system', []);

        $brief->forceFill([
            'reviewed_by_user_id' => $actor->getKey(),
            'reviewed_at' => now(),
            'sent_at' => now(),
        ])->save();

        $recipients = $this->advisorRecipients($brief->client);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new PreMeetingBriefNotification($brief->refresh()));
        }

        $this->audit->record('pre_meeting_brief.reviewed_sent', subject: $brief, actor: $actor, after: [
            'client_id' => $brief->client_id,
            'meeting_id' => $brief->meeting_id,
            'recipients' => $recipients->pluck('id')->values()->all(),
            'sent_at' => $brief->sent_at?->toIso8601String(),
        ]);

        return $brief->refresh();
    }

    /**
     * @return Collection<int, RedFlag>
     */
    private function redFlags(Client $client): Collection
    {
        return RedFlag::query()
            ->where('client_id', $client->getKey())
            ->whereNull('resolved_at')
            ->latest('surfaced_at')
            ->limit(5)
            ->get();
    }

    /**
     * @param  Collection<int, RedFlag>  $redFlags
     */
    private function body(Meeting $meeting, Client $client, Collection $redFlags): string
    {
        $findings = AnalysisFinding::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->limit(3)
            ->pluck('title')
            ->implode('; ');
        $alerts = FinancialAlert::query()
            ->where('client_id', $client->getKey())
            ->latest('surfaced_at')
            ->limit(3)
            ->pluck('headline')
            ->implode('; ');
        $proposalCount = Proposal::query()
            ->where('client_id', $client->getKey())
            ->whereNotNull('released_at')
            ->count();
        $reportCount = Report::query()
            ->where('client_id', $client->getKey())
            ->count();
        $redFlagText = $redFlags->isEmpty()
            ? 'No open red flags.'
            : $redFlags->pluck('headline')->implode('; ');

        return sprintf(
            "Pre-meeting brief for %s.\nMeeting: %s at %s.\nLast actions and completions: %d released proposal(s), %d generated report(s).\nRed flags: %s\nFinancial changes: %s\nCurrent analysis focus: %s\nAdvisor review is required before this brief is used.",
            $client->legal_name,
            $meeting->title,
            $meeting->scheduled_at?->toDayDateTimeString() ?? 'unscheduled',
            $proposalCount,
            $reportCount,
            $redFlagText,
            $alerts !== '' ? $alerts : 'No recent financial alerts.',
            $findings !== '' ? $findings : 'No current analysis findings.',
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function advisorRecipients(?Client $client): Collection
    {
        if (! $client instanceof Client) {
            return collect();
        }

        return $client->teamMembers()
            ->with('user')
            ->whereHas('user', function ($query): void {
                $query->whereIn('user_type', [
                    User::TYPE_SUPER_ADMIN,
                    User::TYPE_ADVISOR,
                    User::TYPE_JUNIOR_ADVISOR,
                    User::TYPE_ENTREPRENEUR_MENTOR,
                ]);
            })
            ->get()
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->values();
    }
}
