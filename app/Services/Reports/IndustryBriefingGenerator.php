<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EconomicIndicator;
use App\Models\IndustryBriefing;
use App\Models\User;
use App\Notifications\IndustryBriefingNotification;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class IndustryBriefingGenerator
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function generate(Client $client, ?CarbonInterface $period = null, ?User $actor = null): IndustryBriefing
    {
        $periodStart = ($period ?? now())->copy()->startOfMonth();
        $sources = $this->nzSources();

        return DB::transaction(function () use ($actor, $client, $periodStart, $sources): IndustryBriefing {
            $briefing = IndustryBriefing::query()->firstOrCreate(
                [
                    'client_id' => $client->getKey(),
                    'period' => $periodStart->toDateString(),
                ],
                [
                    'body' => $this->body($client, $periodStart, $sources),
                    'sources' => $this->sourcePayload($sources),
                    'status' => IndustryBriefing::STATUS_DRAFT,
                    'created_by_user_id' => $actor?->getKey(),
                ],
            );

            if ($briefing->wasRecentlyCreated) {
                $this->audit->record('industry_briefing.generated', subject: $briefing, actor: $actor, after: [
                    'client_id' => $client->getKey(),
                    'period' => $periodStart->toDateString(),
                    'sources' => $briefing->sources,
                ]);
            }

            return $briefing->refresh();
        });
    }

    public function reviewAndSend(IndustryBriefing $briefing, User $actor): IndustryBriefing
    {
        $briefing = $briefing->refresh()->load('client');

        if ($briefing->status === IndustryBriefing::STATUS_SENT) {
            return $briefing;
        }

        $this->context->apply('system', []);

        $briefing->forceFill([
            'status' => IndustryBriefing::STATUS_SENT,
            'reviewed_by_user_id' => $actor->getKey(),
            'reviewed_at' => now(),
            'sent_at' => now(),
        ])->save();

        $recipients = $this->clientRecipients($briefing->client);
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new IndustryBriefingNotification($briefing->refresh()));
        }

        $this->audit->record('industry_briefing.reviewed_sent', subject: $briefing, actor: $actor, after: [
            'client_id' => $briefing->client_id,
            'recipients' => $recipients->pluck('id')->values()->all(),
            'sent_at' => $briefing->sent_at?->toIso8601String(),
        ]);

        return $briefing->refresh();
    }

    /**
     * @return Collection<int, EconomicIndicator>
     */
    private function nzSources(): Collection
    {
        return EconomicIndicator::query()
            ->whereIn('indicator', [
                EconomicIndicator::OCR,
                EconomicIndicator::CPI_ANNUAL,
                EconomicIndicator::GDP_QUARTERLY,
                EconomicIndicator::UNEMPLOYMENT_RATE,
                EconomicIndicator::MINIMUM_WAGE,
                EconomicIndicator::LIVING_WAGE,
            ])
            ->latest('period_date')
            ->latest('fetched_at')
            ->get()
            ->unique('indicator')
            ->values();
    }

    /**
     * @param  Collection<int, EconomicIndicator>  $sources
     */
    private function body(Client $client, CarbonInterface $period, Collection $sources): string
    {
        $sourceLines = $sources->isEmpty()
            ? 'No current NZ economic sources have been refreshed yet.'
            : $sources
                ->map(fn (EconomicIndicator $indicator): string => sprintf(
                    '%s: %s %s (%s, %s)',
                    $indicator->label,
                    number_format($indicator->value, $indicator->unit === 'percent' ? 1 : 2),
                    $indicator->unit,
                    $indicator->source_badge,
                    $indicator->period_date?->toDateString() ?? 'no period',
                ))
                ->implode("\n");

        return sprintf(
            "Monthly industry intelligence briefing for %s.\nPeriod: %s.\nIndustry context: %s.\nNZ source signals:\n%s\nAdvisor review is required before this briefing is sent.",
            $client->legal_name,
            $period->format('Y-m'),
            $client->entity_type ?: 'general NZ business advisory',
            $sourceLines,
        );
    }

    /**
     * @param  Collection<int, EconomicIndicator>  $sources
     * @return array<int, array<string, mixed>>
     */
    private function sourcePayload(Collection $sources): array
    {
        return $sources
            ->map(fn (EconomicIndicator $indicator): array => [
                'claim' => $indicator->label,
                'source_reference' => 'economic_indicator:'.$indicator->id,
                'source' => $indicator->source,
                'source_badge' => $indicator->source_badge,
                'period_date' => $indicator->period_date?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    private function clientRecipients(?Client $client): Collection
    {
        if (! $client instanceof Client) {
            return collect();
        }

        return $client->teamMembers()
            ->with('user')
            ->whereHas('user', function ($query): void {
                $query->whereIn('user_type', [
                    User::TYPE_CLIENT_PRIMARY,
                    User::TYPE_CLIENT_TEAM,
                ]);
            })
            ->get()
            ->map(fn (ClientTeamMember $member): ?User => $member->user)
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->values();
    }
}
