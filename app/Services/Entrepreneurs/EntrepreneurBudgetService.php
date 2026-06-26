<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

final class EntrepreneurBudgetService
{
    private const RUNWAY_MISMATCH_TOLERANCE_MONTHS = 2;

    public function __construct(
        private readonly BudgetCalculator $calculator,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurMilestones $milestones,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(BusinessPlan $plan, array $input, User $actor): EntrepreneurBudget
    {
        return DB::transaction(function () use ($plan, $input, $actor): EntrepreneurBudget {
            $budget = $plan->budgetRunway()->firstOrNew();
            $existingFlags = (array) ($budget->flags ?? []);
            $launchCosts = $this->calculator->normaliseRows((array) ($input['launch_costs'] ?? []));
            $monthlyFixedCosts = $this->calculator->normaliseRows((array) ($input['monthly_fixed_costs'] ?? []));
            $revenueForecast = $this->calculator->normaliseRows((array) ($input['revenue_forecast'] ?? []));
            $fundingSources = $this->calculator->normaliseRows((array) ($input['funding_sources'] ?? []));
            $expectedRunwayMonths = $this->expectedRunway($input['expected_runway_months'] ?? null);
            $computed = $this->calculator->compute(
                launchCosts: $launchCosts,
                monthlyFixedCosts: $monthlyFixedCosts,
                revenueForecast: $revenueForecast,
                fundingSources: $fundingSources,
                expectedRunwayMonths: $expectedRunwayMonths,
            );

            $budget->forceFill([
                'business_plan_id' => $plan->getKey(),
                'expected_runway_months' => $expectedRunwayMonths,
                'status' => $this->status($launchCosts, $monthlyFixedCosts, $revenueForecast, $fundingSources, $expectedRunwayMonths),
                'launch_costs' => $launchCosts,
                'monthly_fixed_costs' => $monthlyFixedCosts,
                'revenue_forecast' => $revenueForecast,
                'funding_sources' => $fundingSources,
                'computed' => $computed,
                'flags' => $this->flags($computed, $expectedRunwayMonths, $existingFlags),
            ])->save();

            $this->audit->record('entrepreneur.budget_updated', subject: $budget, actor: $actor, after: [
                'business_plan_id' => $plan->getKey(),
                'status' => $budget->status,
                'runway_months' => data_get($computed, 'runway_months'),
                'break_even_month' => data_get($computed, 'break_even_month'),
                'flag_count' => count($this->activeFlags($budget)),
            ]);
            $this->milestones->awardCompletedPhases($plan->refresh()->load('entrepreneurProfile', 'phases', 'sections', 'budgetRunway'));

            return $budget->refresh();
        });
    }

    public function acknowledgeFlag(EntrepreneurBudget $budget, string $key, User $actor): EntrepreneurBudget
    {
        $flags = collect((array) $budget->flags)
            ->map(function (array $flag) use ($key, $actor): array {
                if ((string) ($flag['key'] ?? '') !== $key) {
                    return $flag;
                }

                return [
                    ...$flag,
                    'acknowledged_at' => now()->toIso8601String(),
                    'acknowledged_by_user_id' => $actor->getKey(),
                ];
            })
            ->values()
            ->all();

        $budget->forceFill(['flags' => $flags])->save();
        $this->audit->record('entrepreneur.budget_flag_acknowledged', subject: $budget, actor: $actor, after: [
            'flag_key' => $key,
        ]);

        return $budget->refresh();
    }

    public function dismissAdvisorLineNudge(EntrepreneurBudget $budget, User $actor): EntrepreneurBudget
    {
        $budget->forceFill(['advisor_line_nudge_seen_at' => now()])->save();
        $this->audit->record('entrepreneur.budget_advisor_nudge_dismissed', subject: $budget, actor: $actor);

        return $budget->refresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeFlags(EntrepreneurBudget $budget): array
    {
        return collect((array) ($budget->flags ?? []))
            ->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $launchCosts
     * @param  array<int, array<string, mixed>>  $monthlyFixedCosts
     * @param  array<int, array<string, mixed>>  $revenueForecast
     * @param  array<int, array<string, mixed>>  $fundingSources
     */
    private function status(
        array $launchCosts,
        array $monthlyFixedCosts,
        array $revenueForecast,
        array $fundingSources,
        ?int $expectedRunwayMonths,
    ): string {
        $complete = $launchCosts !== []
            && $monthlyFixedCosts !== []
            && $revenueForecast !== []
            && $fundingSources !== []
            && $expectedRunwayMonths !== null;

        if ($complete) {
            return EntrepreneurBudget::STATUS_COMPLETE;
        }

        if ($launchCosts !== [] || $monthlyFixedCosts !== [] || $revenueForecast !== [] || $fundingSources !== [] || $expectedRunwayMonths !== null) {
            return EntrepreneurBudget::STATUS_PARTIAL;
        }

        return EntrepreneurBudget::STATUS_NOT_STARTED;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<int, array<string, mixed>>  $existingFlags
     * @return array<int, array<string, mixed>>
     */
    private function flags(array $computed, ?int $expectedRunwayMonths, array $existingFlags): array
    {
        $flags = [];
        $existingByKey = collect($existingFlags)->keyBy('key');
        $availableAfterLaunch = (float) ($computed['available_after_launch'] ?? 0);
        $runwayMonths = $computed['runway_months'] ?? null;
        $runwayOpenEnded = (bool) ($computed['runway_open_ended'] ?? false);

        if ($availableAfterLaunch < 0) {
            $flags[] = $this->flag(
                'funding_shortfall',
                'Launch funding gap',
                'Launch costs are higher than available funding. Consider reducing setup costs, adding funding, or delaying some spend.',
                'high',
                $existingByKey->get('funding_shortfall'),
            );
        }

        if (($computed['input_count'] ?? 0) > 0 && ! (bool) ($computed['break_even_reached'] ?? false)) {
            $flags[] = $this->flag(
                'no_break_even',
                'Break-even not visible yet',
                'The first 12 months do not yet show revenue covering fixed and variable costs. This may need more revenue evidence or lower monthly costs.',
                'medium',
                $existingByKey->get('no_break_even'),
            );
        }

        if ($expectedRunwayMonths !== null && is_int($runwayMonths)) {
            $effectiveRunway = $runwayOpenEnded ? max($runwayMonths, self::RUNWAY_MISMATCH_TOLERANCE_MONTHS + $expectedRunwayMonths) : $runwayMonths;
            if (abs($expectedRunwayMonths - $effectiveRunway) > self::RUNWAY_MISMATCH_TOLERANCE_MONTHS) {
                $flags[] = $this->flag(
                    'runway_mismatch',
                    'Runway needs checking',
                    'The expected runway is more than two months away from the budget calculation. Check the assumptions or explain the difference.',
                    'medium',
                    $existingByKey->get('runway_mismatch'),
                );
            }
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function flag(string $key, string $title, string $message, string $severity, ?array $existing): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'first_raised_at' => (string) ($existing['first_raised_at'] ?? now()->toIso8601String()),
            'acknowledged_at' => $existing['acknowledged_at'] ?? null,
            'acknowledged_by_user_id' => $existing['acknowledged_by_user_id'] ?? null,
        ];
    }

    private function expectedRunway(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return min(60, max(0, (int) $value));
    }
}
