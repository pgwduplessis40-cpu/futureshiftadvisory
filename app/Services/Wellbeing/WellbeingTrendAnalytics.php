<?php

declare(strict_types=1);

namespace App\Services\Wellbeing;

use App\Models\ClientTeamMember;
use App\Models\CoachingSignal;
use App\Models\User;
use App\Models\WellbeingCheckin;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class WellbeingTrendAnalytics
{
    /**
     * A null client id list means all clients.
     *
     * @param  array<int, string>|null  $clientIds
     * @return array<string, mixed>
     */
    public function forClientIds(?array $clientIds): array
    {
        if ($clientIds === []) {
            return $this->empty();
        }

        $windowStart = now()->subMonthsNoOverflow(5)->startOfMonth();
        $checkins = $this->checkins($clientIds, $windowStart);
        $signals = $this->signals($clientIds);
        $promptPopulation = $this->promptPopulation($clientIds);
        $currentPeriodRespondents = $this->currentPeriodRespondents($clientIds);

        return [
            'summary' => [
                'checkins' => $checkins->count(),
                'clients' => $checkins->pluck('client_id')->unique()->count(),
                'average_business_confidence' => $this->average($checkins, 'business_confidence'),
                'average_personal_coping' => $this->average($checkins, 'personal_coping'),
                'low_personal_coping_checkins' => $checkins->filter(fn (WellbeingCheckin $checkin): bool => $checkin->personal_coping <= 2)->count(),
                'active_low_coping_signals' => $signals->count(),
                'current_period_completion_rate' => $promptPopulation === 0
                    ? 0.0
                    : round($currentPeriodRespondents / $promptPopulation, 4),
            ],
            'monthly' => $this->monthly($checkins),
            'signals' => $signals
                ->map(fn (CoachingSignal $signal): array => [
                    'id' => $signal->id,
                    'client_id' => $signal->client_id,
                    'client_name' => $signal->client?->legal_name,
                    'signal_type' => $signal->signal_type,
                    'severity' => $signal->severity,
                    'generated_at' => $signal->generated_at?->toIso8601String(),
                    'auto_referral' => (bool) data_get($signal->evidence, 'auto_referral', false),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return Collection<int, WellbeingCheckin>
     */
    private function checkins(?array $clientIds, CarbonInterface $windowStart): Collection
    {
        return WellbeingCheckin::query()
            ->with(['client', 'user'])
            ->whereDate('period_start', '>=', $windowStart->toDateString())
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->orderBy('period_start')
            ->get();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     * @return Collection<int, CoachingSignal>
     */
    private function signals(?array $clientIds): Collection
    {
        return CoachingSignal::query()
            ->with('client')
            ->where('signal_type', CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK)
            ->where('status', 'detected')
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->latest('generated_at')
            ->limit(12)
            ->get();
    }

    /**
     * @param  Collection<int, WellbeingCheckin>  $checkins
     */
    private function average(Collection $checkins, string $column): float
    {
        if ($checkins->isEmpty()) {
            return 0.0;
        }

        return round((float) $checkins->avg($column), 2);
    }

    /**
     * @param  Collection<int, WellbeingCheckin>  $checkins
     * @return array<int, array<string, mixed>>
     */
    private function monthly(Collection $checkins): array
    {
        return $checkins
            ->groupBy(fn (WellbeingCheckin $checkin): string => $checkin->period_start?->toDateString() ?? 'unknown')
            ->map(fn (Collection $month, string $periodStart): array => [
                'period_start' => $periodStart,
                'checkins' => $month->count(),
                'average_business_confidence' => $this->average($month, 'business_confidence'),
                'average_personal_coping' => $this->average($month, 'personal_coping'),
                'low_personal_coping_checkins' => $month->filter(fn (WellbeingCheckin $checkin): bool => $checkin->personal_coping <= 2)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     */
    private function promptPopulation(?array $clientIds): int
    {
        return ClientTeamMember::query()
            ->whereHas('user', function ($query): void {
                $query->whereIn('user_type', [
                    User::TYPE_CLIENT_PRIMARY,
                    User::TYPE_CLIENT_TEAM,
                ]);
            })
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->get(['client_id', 'user_id'])
            ->unique(fn (ClientTeamMember $member): string => $member->client_id.'|'.$member->user_id)
            ->count();
    }

    /**
     * @param  array<int, string>|null  $clientIds
     */
    private function currentPeriodRespondents(?array $clientIds): int
    {
        return WellbeingCheckin::query()
            ->whereDate('period_start', now()->startOfMonth()->toDateString())
            ->when(is_array($clientIds), fn ($query) => $query->whereIn('client_id', $clientIds))
            ->get(['client_id', 'user_id'])
            ->unique(fn (WellbeingCheckin $checkin): string => $checkin->client_id.'|'.$checkin->user_id)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'summary' => [
                'checkins' => 0,
                'clients' => 0,
                'average_business_confidence' => 0.0,
                'average_personal_coping' => 0.0,
                'low_personal_coping_checkins' => 0,
                'active_low_coping_signals' => 0,
                'current_period_completion_rate' => 0.0,
            ],
            'monthly' => [],
            'signals' => [],
        ];
    }
}
