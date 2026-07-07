<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EconomicIndicator;
use App\Models\EntrepreneurBudget;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Learning\LayerCadenceRegistry;
use Illuminate\Support\Facades\DB;

final class EntrepreneurBudgetService
{
    private const SUPPORTED_FORECAST_YEARS = [1, 2, 3, 5];

    private const CHANGE_TRACKED_FIELDS = [
        'expected_runway_months',
        'forecast_years',
        'status',
        'assumptions',
        'launch_costs',
        'monthly_fixed_costs',
        'future_costs',
        'revenue_forecast',
        'funding_sources',
        'funding_scenarios',
        'computed',
        'flags',
    ];

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
            $beforeFingerprint = $budget->exists ? $this->changeFingerprint($budget) : null;
            $launchCosts = $this->calculator->normaliseRows((array) ($input['launch_costs'] ?? []));
            $monthlyFixedCosts = $this->calculator->normaliseRows((array) ($input['monthly_fixed_costs'] ?? []));
            $revenueForecast = $this->calculator->normaliseRows((array) ($input['revenue_forecast'] ?? []));
            $fundingSources = $this->calculator->normaliseRows((array) ($input['funding_sources'] ?? []));
            $futureCosts = $this->calculator->normaliseFutureCosts((array) ($input['future_costs'] ?? []));
            $fundingScenarios = $this->calculator->normaliseFundingScenarios((array) ($input['funding_scenarios'] ?? []));
            $forecastYears = $this->forecastYears($input['forecast_years'] ?? null);
            $assumptions = (array) ($input['assumptions'] ?? []);
            $expectedRunwayMonths = $this->expectedRunway($input['expected_runway_months'] ?? null);
            $companyTaxRate = $this->economicPercent(EconomicIndicator::COMPANY_TAX_RATE);
            $cpi = $this->economicPercent(EconomicIndicator::CPI_ANNUAL);
            $computed = $this->calculator->compute(
                launchCosts: $launchCosts,
                monthlyFixedCosts: $monthlyFixedCosts,
                revenueForecast: $revenueForecast,
                fundingSources: $fundingSources,
                expectedRunwayMonths: $expectedRunwayMonths,
                forecastYears: $forecastYears,
                assumptions: $assumptions,
                futureCosts: $futureCosts,
                fundingScenarios: $fundingScenarios,
                companyTaxRatePercent: $companyTaxRate,
                defaultCostInflationPercent: $cpi,
            );
            $confidence = $this->confidenceSummary(
                $launchCosts,
                $monthlyFixedCosts,
                $revenueForecast,
                $fundingSources,
                $futureCosts,
                $fundingScenarios,
            );
            $flags = $this->flags($computed, $expectedRunwayMonths, $existingFlags, $confidence);

            $budget->forceFill([
                'business_plan_id' => $plan->getKey(),
                'expected_runway_months' => $expectedRunwayMonths,
                'forecast_years' => $forecastYears,
                'status' => $this->status(
                    $launchCosts,
                    $monthlyFixedCosts,
                    $revenueForecast,
                    $fundingSources,
                    $futureCosts,
                    $fundingScenarios,
                    $expectedRunwayMonths,
                    (array) data_get($computed, 'missing_assumptions', []),
                ),
                'assumptions' => data_get($computed, 'assumptions', []),
                'launch_costs' => $launchCosts,
                'monthly_fixed_costs' => $monthlyFixedCosts,
                'future_costs' => $futureCosts,
                'revenue_forecast' => $revenueForecast,
                'funding_sources' => $fundingSources,
                'funding_scenarios' => $fundingScenarios,
                'computed' => $computed,
                'flags' => $flags,
            ]);

            $changed = $beforeFingerprint !== $this->changeFingerprint($budget);

            if ($changed) {
                $budget->save();

                $this->audit->record('entrepreneur.budget_updated', subject: $budget, actor: $actor, after: [
                    'business_plan_id' => $plan->getKey(),
                    'status' => $budget->status,
                    'runway_months' => data_get($computed, 'runway_months'),
                    'break_even_month' => data_get($computed, 'break_even_month'),
                    'break_even_year' => data_get($computed, 'break_even_year'),
                    'cash_flow_positive_year' => data_get($computed, 'cash_flow_positive_year'),
                    'forecast_years' => $forecastYears,
                    'flag_count' => count(collect($flags)->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))),
                ]);
            } else {
                $budget->syncOriginal();
            }

            $this->milestones->awardCompletedPhases($plan->refresh()->load('entrepreneurProfile', 'phases', 'sections', 'budgetRunway'));
            $this->queueBudgetLearning($budget->refresh(), $computed, $flags, $confidence);

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
        array $futureCosts,
        array $fundingScenarios,
        ?int $expectedRunwayMonths,
        array $missingAssumptions,
    ): string {
        $complete = $launchCosts !== []
            && $monthlyFixedCosts !== []
            && $revenueForecast !== []
            && $fundingSources !== []
            && $expectedRunwayMonths !== null
            && $missingAssumptions === [];

        if ($complete) {
            return EntrepreneurBudget::STATUS_COMPLETE;
        }

        if (
            $launchCosts !== []
            || $monthlyFixedCosts !== []
            || $revenueForecast !== []
            || $fundingSources !== []
            || $futureCosts !== []
            || $fundingScenarios !== []
            || $expectedRunwayMonths !== null
            || $missingAssumptions !== []
        ) {
            return EntrepreneurBudget::STATUS_PARTIAL;
        }

        return EntrepreneurBudget::STATUS_NOT_STARTED;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<int, array<string, mixed>>  $existingFlags
     * @return array<int, array<string, mixed>>
     */
    private function flags(array $computed, ?int $expectedRunwayMonths, array $existingFlags, array $confidence): array
    {
        $flags = [];
        $existingByKey = collect($existingFlags)->keyBy('key');
        $availableAfterLaunch = (float) ($computed['available_after_launch'] ?? 0);
        $runwayMonths = $computed['runway_months'] ?? null;
        $runwayOpenEnded = (bool) ($computed['runway_open_ended'] ?? false);
        $missingAssumptions = (array) ($computed['missing_assumptions'] ?? []);
        $fieldLabels = (array) data_get($computed, 'assumptions.field_labels', []);
        $annualTotals = collect((array) ($computed['annual_totals'] ?? []));

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
                'The forecast does not yet show a year where net profit before tax is zero or positive. Review revenue, margins, costs, or timing before relying on this budget.',
                'medium',
                $existingByKey->get('no_break_even'),
            );
        }

        if (($computed['input_count'] ?? 0) > 0 && data_get($computed, 'cash_flow_positive_year') === null) {
            $flags[] = $this->flag(
                'cash_not_positive',
                'Cash does not turn positive yet',
                'Cumulative cash does not become positive inside this forecast. This affects bank, investor, and viability conversations.',
                'medium',
                $existingByKey->get('cash_not_positive'),
            );
        }

        if ($missingAssumptions !== []) {
            $labels = collect($missingAssumptions)
                ->map(fn (string $key): string => (string) ($fieldLabels[$key] ?? str($key)->replace('_', ' ')->title()))
                ->implode(', ');
            $flags[] = $this->flag(
                'missing_financial_assumptions',
                'Financial assumptions need detail',
                'Update the Financial assumptions business-plan section for: '.$labels.'. The budget can be saved, but these gaps reduce confidence and scoring.',
                'medium',
                $existingByKey->get('missing_financial_assumptions'),
            );
        }

        if (! (bool) data_get($computed, 'assumptions.company_tax_configured', false)) {
            $flags[] = $this->flag(
                'tax_not_configured',
                'Company tax rate not configured',
                'The budget pack can still be generated, but after-tax profit will show a tax-not-configured warning until Admin adds the company tax rate to Reference data.',
                'medium',
                $existingByKey->get('tax_not_configured'),
            );
        }

        if (($confidence['total'] ?? 0) >= 3 && ($confidence['guess_ratio'] ?? 0) >= 0.5) {
            $flags[] = $this->flag(
                'low_confidence_budget',
                'Too many budget guesses',
                'More than half of the entered rows are still guesses. Replace the highest-value guesses with quotes, supplier pricing, market evidence, or advisor-reviewed estimates.',
                'medium',
                $existingByKey->get('low_confidence_budget'),
            );
        }

        $latestAnnual = $annualTotals->last();
        if (is_array($latestAnnual) && (float) ($latestAnnual['revenue'] ?? 0) > 0) {
            $targetGpp = (float) data_get($computed, 'assumptions.target_gross_profit_percent', 0);
            $targetNpbtp = (float) data_get($computed, 'assumptions.target_net_profit_before_tax_percent', 0);
            $targetNpatp = (float) data_get($computed, 'assumptions.target_net_profit_after_tax_percent', 0);
            $gapLabels = [];

            if ($targetGpp > 0 && (float) ($latestAnnual['gross_profit_percent'] ?? 0) < $targetGpp) {
                $gapLabels[] = 'GP%';
            }

            if ($targetNpbtp > 0 && (float) ($latestAnnual['net_profit_before_tax_percent'] ?? 0) < $targetNpbtp) {
                $gapLabels[] = 'net profit before tax %';
            }

            if ($targetNpatp > 0 && (float) ($latestAnnual['net_profit_after_tax_percent'] ?? 0) < $targetNpatp) {
                $gapLabels[] = 'net profit after tax %';
            }

            if ($gapLabels !== []) {
                $flags[] = $this->flag(
                    'target_margin_gap',
                    'Target margin gap',
                    'The latest forecast year is below the target '.implode(', ', $gapLabels).'. Check price, unit cost, volume, overheads, or growth assumptions.',
                    'medium',
                    $existingByKey->get('target_margin_gap'),
                );
            }
        }

        if ($expectedRunwayMonths !== null && is_int($runwayMonths)) {
            $tolerance = $this->runwayMismatchToleranceMonths();
            $effectiveRunway = $runwayOpenEnded ? max($runwayMonths, $tolerance + $expectedRunwayMonths) : $runwayMonths;
            if (abs($expectedRunwayMonths - $effectiveRunway) > $tolerance) {
                $flags[] = $this->flag(
                    'runway_mismatch',
                    'Runway needs checking',
                    'The expected runway is more than '.$tolerance.' months away from the budget calculation. Check the assumptions or explain the difference.',
                    'medium',
                    $existingByKey->get('runway_mismatch'),
                );
            }
        }

        return $flags;
    }

    private function forecastYears(mixed $value): int
    {
        $years = is_numeric($value) ? (int) $value : 3;

        return in_array($years, self::SUPPORTED_FORECAST_YEARS, true) ? $years : 3;
    }

    private function runwayMismatchToleranceMonths(): int
    {
        return max(0, (int) config('entrepreneurs.budget.runway_mismatch_tolerance_months', 2));
    }

    private function changeFingerprint(EntrepreneurBudget $budget): string
    {
        $payload = [];

        foreach (self::CHANGE_TRACKED_FIELDS as $field) {
            $payload[$field] = $this->canonicalValue($budget->getAttribute($field));
        }

        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 4);
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalValue($item), $value);
    }

    private function economicPercent(string $indicator): ?float
    {
        $value = EconomicIndicator::query()
            ->where('indicator', $indicator)
            ->latest('period_date')
            ->latest('fetched_at')
            ->value('value');

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$groups
     * @return array{known:int,estimate:int,guess:int,total:int,guess_ratio:float}
     */
    private function confidenceSummary(array ...$groups): array
    {
        $summary = ['known' => 0, 'estimate' => 0, 'guess' => 0, 'total' => 0, 'guess_ratio' => 0.0];

        foreach ($groups as $group) {
            foreach ($group as $row) {
                $confidence = in_array($row['confidence'] ?? '', ['known', 'estimate', 'guess'], true)
                    ? (string) $row['confidence']
                    : 'estimate';
                $summary[$confidence]++;
                $summary['total']++;
            }
        }

        if ($summary['total'] > 0) {
            $summary['guess_ratio'] = round($summary['guess'] / $summary['total'], 4);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<int, array<string, mixed>>  $flags
     * @param  array<string, mixed>  $confidence
     */
    private function queueBudgetLearning(EntrepreneurBudget $budget, array $computed, array $flags, array $confidence): void
    {
        $activeFlags = collect($flags)->filter(fn (array $flag): bool => empty($flag['acknowledged_at']))->values();
        $missingAssumptions = (array) ($computed['missing_assumptions'] ?? []);

        if ($activeFlags->isEmpty() && $missingAssumptions === []) {
            return;
        }

        $budget->loadMissing('businessPlan.entrepreneurProfile');
        $signalKey = hash('sha256', implode('|', [
            $budget->getKey(),
            now()->toDateString(),
            $activeFlags->pluck('key')->sort()->implode(','),
            collect($missingAssumptions)->sort()->implode(','),
            (string) data_get($computed, 'break_even_year', 'none'),
            (string) data_get($computed, 'cash_flow_positive_year', 'none'),
        ]));

        $exists = LearningUpdate::query()
            ->where('layer_id', LayerCadenceRegistry::LAYER_ENTREPRENEUR_BUDGET_MODEL)
            ->whereIn('status', [
                LearningUpdate::STATUS_DETECTED,
                LearningUpdate::STATUS_STAGED,
                LearningUpdate::STATUS_APPROVED,
                LearningUpdate::STATUS_DEFERRED,
            ])
            ->where('source->signal_key', $signalKey)
            ->exists();

        if ($exists) {
            return;
        }

        LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_ENTREPRENEUR_BUDGET_MODEL,
            'source' => [
                'type' => 'entrepreneur_budget_model',
                'signal_key' => $signalKey,
                'business_plan_id' => $budget->business_plan_id,
                'entrepreneur_profile_id' => $budget->businessPlan?->entrepreneur_profile_id,
                'budget_id' => $budget->getKey(),
            ],
            'summary' => 'Budget assistant learning signal detected: '.($activeFlags->pluck('title')->implode('; ') ?: 'missing financial assumptions').'.',
            'proposed_change' => [
                'action' => 'review_budget_guidance_calibration',
                'automatic_application' => false,
                'requires_approval' => true,
                'candidate_surfaces' => [
                    'business_plan_financial_assumptions',
                    'budget_setup_assistant',
                    'budget_scoring_flags',
                    'budget_pack_explanations',
                ],
            ],
            'impact_scope' => [
                'module' => 'entrepreneur',
                'surface' => 'budget_model',
                'governance_gate' => 'admin_or_advisor_approval_required',
                'direct_write_policy' => 'no_auto_budget_or_score_changes',
            ],
            'clients_affected' => 1,
            'magnitude' => $activeFlags->contains(fn (array $flag): bool => in_array($flag['key'] ?? '', ['funding_shortfall', 'no_break_even', 'cash_not_positive'], true)) ? 'medium' : 'low',
            'confidence' => 0.68,
            'evidence' => [
                'budget_status' => $budget->status,
                'forecast_years' => $budget->forecast_years,
                'active_flag_keys' => $activeFlags->pluck('key')->values()->all(),
                'missing_assumptions' => $missingAssumptions,
                'confidence_summary' => $confidence,
                'break_even_year' => data_get($computed, 'break_even_year'),
                'first_profitable_year' => data_get($computed, 'first_profitable_year'),
                'cash_flow_positive_year' => data_get($computed, 'cash_flow_positive_year'),
                'tax_configured' => data_get($computed, 'assumptions.company_tax_configured', false),
                'client_pii_excluded' => true,
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
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
