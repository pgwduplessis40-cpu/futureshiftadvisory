<?php

declare(strict_types=1);

namespace App\Services\Budgets;

use App\Enums\EngagementType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EconomicIndicator;
use App\Models\FinancialSnapshot;
use App\Models\Proposal;
use App\Models\StrategicBudget;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\BudgetCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class StrategicBudgetService
{
    private const PLAN_SECTION_KEYS = [
        'goals',
        'current_position',
        'market_customers',
        'operations',
        'risks',
        'swot',
        'action_priorities',
        'evidence_documents',
    ];

    private const FINANCIAL_KEYWORDS = [
        'p&l',
        'p and l',
        'profit and loss',
        'profit-loss',
        'profit_loss',
        'management accounts',
        'management_accounts',
        'management-account',
    ];

    public function __construct(
        private readonly BudgetCalculator $calculator,
        private readonly AuditWriter $audit,
    ) {}

    public function ensureForClient(Client $client, ?BusinessPlan $plan = null): StrategicBudget
    {
        $pathway = $this->pathway($client);
        $financials = $this->financialDocuments($client);
        $unlocked = $financials->isNotEmpty();
        $budget = StrategicBudget::query()->firstOrNew([
            'client_id' => $client->getKey(),
            'pathway' => $pathway,
        ]);
        $existingStatus = (string) ($budget->status ?: StrategicBudget::STATUS_LOCKED);

        if (! $budget->exists) {
            $budget->forceFill([
                'label' => $this->label($pathway),
                'status' => $unlocked ? StrategicBudget::STATUS_SYSTEM_DRAFT : StrategicBudget::STATUS_LOCKED,
                'horizon_months' => $this->defaultHorizonMonths($client),
                'source_financials' => $this->sourceFinancialsPayload($financials),
                'client_goals' => $this->clientGoals($client),
                'advisor_goals' => [],
                'business_plan_sections' => [],
                'business_plan_source_drafts' => [],
                'business_plan_prompts' => [],
                'assumptions' => [],
                'implementation_costs' => [],
                'monthly_fixed_costs' => [],
                'future_costs' => [],
                'revenue_forecast' => [],
                'funding_sources' => [],
                'funding_scenarios' => [],
                'computed' => [],
                'flags' => [],
                'confidence' => [],
            ]);
        }

        $status = $existingStatus;
        if ($unlocked && $existingStatus === StrategicBudget::STATUS_LOCKED) {
            $status = StrategicBudget::STATUS_SYSTEM_DRAFT;
        }
        if (! $unlocked) {
            $status = StrategicBudget::STATUS_LOCKED;
        }

        $budget->forceFill([
            'business_plan_id' => $plan?->getKey() ?? $budget->business_plan_id,
            'label' => $this->label($pathway),
            'status' => $status,
            'horizon_months' => (int) ($budget->horizon_months ?: $this->defaultHorizonMonths($client)),
            'source_financials' => $this->sourceFinancialsPayload($financials),
            'client_goals' => $this->clientGoals($client),
            'advisor_goals' => $budget->advisor_goals ?? [],
            'business_plan_prompts' => $this->businessPlanPrompts($pathway),
            'business_plan_source_drafts' => $this->sourceDrafts($client, $plan, $pathway),
            'business_plan_sections' => $this->normaliseBusinessPlanSections(
                (array) ($budget->business_plan_sections ?? []),
                $pathway,
            ),
        ])->save();

        $budget = $budget->refresh();

        if ($budget->isUnlocked() && ($budget->computed ?? []) === []) {
            $this->recompute($budget);
        } else {
            $this->refreshReadiness($budget);
        }

        return $budget->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(StrategicBudget $budget, array $input, User $actor): StrategicBudget
    {
        return DB::transaction(function () use ($budget, $input, $actor): StrategicBudget {
            $status = in_array($budget->status, [
                StrategicBudget::STATUS_ADVISOR_APPROVED,
                StrategicBudget::STATUS_USED_IN_PROPOSAL,
                StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
            ], true)
                ? StrategicBudget::STATUS_CLIENT_WORKING_DRAFT
                : (string) ($budget->status ?: StrategicBudget::STATUS_CLIENT_WORKING_DRAFT);

            if ($status === StrategicBudget::STATUS_SYSTEM_DRAFT) {
                $status = StrategicBudget::STATUS_CLIENT_WORKING_DRAFT;
            }

            $updates = [
                'status' => $status,
                'business_plan_sections' => $this->normaliseBusinessPlanSections(
                    (array) ($input['business_plan_sections'] ?? $budget->business_plan_sections ?? []),
                    (string) $budget->pathway,
                ),
                'business_plan_prompts' => $this->businessPlanPrompts((string) $budget->pathway),
                'business_plan_submitted_at' => null,
                'business_plan_approved_at' => null,
                'business_plan_approved_by_user_id' => null,
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
            ];

            if ($budget->isUnlocked()) {
                $updates = [
                    ...$updates,
                    'horizon_months' => $this->horizonMonths($input['horizon_months'] ?? $budget->horizon_months),
                    'expected_runway_months' => $this->expectedRunway($input['expected_runway_months'] ?? null),
                    'assumptions' => (array) ($input['assumptions'] ?? []),
                    'implementation_costs' => $this->calculator->normaliseRows((array) ($input['implementation_costs'] ?? [])),
                    'monthly_fixed_costs' => $this->calculator->normaliseRows((array) ($input['monthly_fixed_costs'] ?? [])),
                    'future_costs' => $this->calculator->normaliseFutureCosts((array) ($input['future_costs'] ?? [])),
                    'revenue_forecast' => $this->calculator->normaliseRows((array) ($input['revenue_forecast'] ?? [])),
                    'funding_sources' => $this->calculator->normaliseRows((array) ($input['funding_sources'] ?? [])),
                    'funding_scenarios' => $this->calculator->normaliseFundingScenarios((array) ($input['funding_scenarios'] ?? [])),
                ];
            }

            $budget->forceFill($updates)->save();

            $budget = $budget->isUnlocked()
                ? $this->recompute($budget->refresh())
                : $this->refreshReadiness($budget->refresh());

            $this->audit->record('strategic_budget.updated', subject: $budget, actor: $actor, after: [
                'client_id' => $budget->client_id,
                'pathway' => $budget->pathway,
                'status' => $budget->status,
                'horizon_months' => $budget->horizon_months,
                'confidence_score' => data_get($budget->confidence, 'score'),
            ]);

            return $budget->refresh();
        });
    }

    public function submit(StrategicBudget $budget, User $actor): StrategicBudget
    {
        abort_unless($budget->isUnlocked(), 422);
        abort_unless($this->businessPlanReady($budget), 422);

        $budget = $this->recompute($budget);
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
            'submitted_at' => now(),
            'business_plan_submitted_at' => now(),
            'approved_at' => null,
            'approved_by_user_id' => null,
            'business_plan_approved_at' => null,
            'business_plan_approved_by_user_id' => null,
        ])->save();

        $this->audit->record('strategic_budget.submitted', subject: $budget, actor: $actor, after: [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'confidence_score' => data_get($budget->confidence, 'score'),
            'business_plan_readiness' => $this->businessPlanReadiness($budget),
        ]);

        return $budget->refresh();
    }

    public function approve(StrategicBudget $budget, User $actor): StrategicBudget
    {
        abort_unless($budget->isUnlocked(), 422);
        abort_unless($this->businessPlanReady($budget), 422);

        $budget = $this->recompute($budget);
        $budget->forceFill([
            'status' => StrategicBudget::STATUS_ADVISOR_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $actor->getKey(),
            'business_plan_approved_at' => now(),
            'business_plan_approved_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('strategic_budget.approved', subject: $budget, actor: $actor, after: [
            'client_id' => $budget->client_id,
            'pathway' => $budget->pathway,
            'confidence_score' => data_get($budget->confidence, 'score'),
        ]);

        return $budget->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $goals
     */
    public function updateAdvisorGoals(StrategicBudget $budget, array $goals, User $actor): StrategicBudget
    {
        $normalised = collect($goals)
            ->filter(fn (mixed $goal): bool => is_array($goal))
            ->map(fn (array $goal): array => [
                'title' => trim((string) ($goal['title'] ?? '')),
                'measure' => trim((string) ($goal['measure'] ?? '')),
            ])
            ->filter(fn (array $goal): bool => $goal['title'] !== '' || $goal['measure'] !== '')
            ->values()
            ->all();

        $budget->forceFill(['advisor_goals' => $normalised])->save();
        $this->audit->record('strategic_budget.advisor_goals_updated', subject: $budget, actor: $actor, after: [
            'goal_count' => count($normalised),
        ]);

        return $this->refreshReadiness($budget->refresh());
    }

    public function markUsedInProposal(StrategicBudget $budget, Proposal $proposal, User $actor): StrategicBudget
    {
        if (! $budget->isApprovedForProposal()) {
            return $budget->refresh();
        }

        $budget->forceFill([
            'status' => StrategicBudget::STATUS_USED_IN_PROPOSAL,
            'proposal_id' => $proposal->getKey(),
            'used_in_proposal_at' => now(),
        ])->save();

        $this->audit->record('strategic_budget.used_in_proposal', subject: $budget, actor: $actor, after: [
            'proposal_id' => $proposal->getKey(),
        ]);

        return $budget->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function portalPayload(StrategicBudget $budget): array
    {
        return [
            ...$this->basePayload($budget),
            'update_url' => route('portal.business-plan-budget.update', absolute: false),
            'submit_url' => route('portal.business-plan-budget.submit', absolute: false),
            'export_url' => route('portal.business-plan-budget.export', absolute: false),
            'budget_pack_available' => $budget->accepted_snapshot_at !== null,
            'budget_pack_locked_reason' => $budget->accepted_snapshot_at === null
                ? 'Budget Pack PDF unlocks automatically after the proposal is accepted.'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function advisorPayload(StrategicBudget $budget): array
    {
        return [
            ...$this->basePayload($budget),
            'approve_url' => route('advisor.clients.strategic-budget.approve', $budget->client_id, absolute: false),
            'advisor_goals_url' => route('advisor.clients.strategic-budget.advisor-goals', $budget->client_id, absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalGuardPayload(StrategicBudget $budget): array
    {
        return [
            'id' => $budget->id,
            'status' => $budget->status,
            'status_label' => $this->statusLabel($budget->status),
            'approved' => $budget->isApprovedForProposal(),
            'confidence_score' => (int) data_get($budget->confidence, 'score', 0),
            'warning' => $budget->isApprovedForProposal()
                ? null
                : $budget->label.' has not been advisor-approved. Generating a proposal now requires a hard acknowledgement override.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyticsPayload(StrategicBudget $budget): array
    {
        $computed = (array) ($budget->computed ?? []);
        $confidence = (array) ($budget->confidence ?? []);
        $annualForecast = $this->annualForecastRows($computed);
        $monthlyForecast = $this->monthlyForecastRows($computed);
        $scenarioRows = $this->scenarioRows($computed);
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $firstYear = $annualForecast[0] ?? [];
        $rowConfidence = (array) data_get($confidence, 'row_confidence', []);
        $costDrivers = $this->costDrivers($budget);
        $missingAssumptions = $this->missingAssumptions($computed);
        $flags = (array) ($budget->flags ?? []);
        $prescriptiveActions = $this->prescriptiveActions($budget, $flags, $computed, $confidence);
        $yearOneRevenue = (float) ($firstYear['revenue'] ?? 0);
        $yearOneFixedCosts = (float) ($firstYear['fixed_costs'] ?? 0);
        $totalFunding = (float) data_get($computed, 'total_funding', 0);
        $runwayText = $this->runwayText($computed);
        $breakEvenText = $this->yearText(data_get($computed, 'break_even_year'));
        $cashFlowPositiveText = $this->yearText(data_get($computed, 'cash_flow_positive_year'));
        $knownRows = (int) ($rowConfidence['known'] ?? 0);
        $estimateRows = (int) ($rowConfidence['estimate'] ?? 0);
        $guessRows = (int) ($rowConfidence['guess'] ?? 0);
        $topDriver = collect($costDrivers)->first(fn (array $driver): bool => (float) ($driver['value'] ?? 0) > 0);
        $firstFlag = collect($flags)->first(fn (mixed $flag): bool => is_array($flag));
        $planSections = collect((array) ($budget->business_plan_sections ?? []))
            ->filter(fn (mixed $section): bool => is_array($section))
            ->values();
        $completedPlanSections = $planSections
            ->filter(fn (array $section): bool => trim((string) ($section['answer'] ?? '')) !== '')
            ->count();
        $planSectionTotal = max(1, $planSections->count());
        $planCoverage = "{$completedPlanSections}/{$planSectionTotal} plan sections complete";
        $goalExcerpt = $this->sectionExcerpt($planSections->all(), 'goals');
        $riskExcerpt = $this->sectionExcerpt($planSections->all(), 'risks');
        $actionExcerpt = $this->sectionExcerpt($planSections->all(), 'action_priorities');
        $descriptions = array_values(array_filter([
            "Plan coverage: {$planCoverage}",
            $goalExcerpt ? "Plan goal: {$goalExcerpt}" : null,
            "Year 1 revenue: {$this->money($yearOneRevenue)}",
            "Year 1 fixed costs: {$this->money($yearOneFixedCosts)}",
            "Funding available: {$this->money($totalFunding)}",
            "Runway: {$runwayText}",
        ]));
        $diagnoses = collect($flags)
            ->filter(fn (mixed $flag): bool => is_array($flag))
            ->map(fn (array $flag): string => (string) ($flag['title'] ?? 'Budget warning').': '.(string) ($flag['message'] ?? 'Review this budget signal.'))
            ->values()
            ->all();
        $diagnoses = array_values(array_filter([
            ...$diagnoses,
            $riskExcerpt ? "Plan risk noted: {$riskExcerpt}" : null,
            count($missingAssumptions) > 0 ? count($missingAssumptions).' missing budget assumption'.(count($missingAssumptions) === 1 ? '' : 's') : 'No missing budget assumptions',
            "Evidence base: {$knownRows} known, {$estimateRows} estimates, {$guessRows} guesses",
            $topDriver ? 'Largest cost driver: '.(string) ($topDriver['label'] ?? 'Cost driver').' at '.$this->money((float) ($topDriver['value'] ?? 0)) : null,
        ]));
        $predictions = array_values(array_filter([
            "Runway is {$runwayText}.",
            "Break-even is {$breakEvenText}.",
            "Cash-flow positive timing is {$cashFlowPositiveText}.",
            $scenarioRows !== [] ? 'Base scenario ending cash is '.$this->money((float) ($scenarioRows[0]['ending_cash'] ?? 0)).'.' : null,
        ]));
        $prescriptions = collect($prescriptiveActions)
            ->take(3)
            ->map(fn (array $action): string => ucfirst((string) ($action['priority'] ?? 'medium')).': '.(string) ($action['action'] ?? 'Review this budget signal.'))
            ->values()
            ->all();
        $prescriptions = array_values(array_filter([
            $actionExcerpt ? "Plan action priority to fund: {$actionExcerpt}" : null,
            ...$prescriptions,
        ]));

        return [
            'descriptive' => [
                'summary' => (bool) ($sourceFinancials['unlocked'] ?? false)
                    ? "Plan coverage is {$planCoverage}; Year 1 is forecasting {$this->money($yearOneRevenue)} revenue, {$this->money($yearOneFixedCosts)} fixed costs, {$this->money($totalFunding)} funding available, and {$runwayText} runway."
                    : 'Budget is locked until a verified P&L or management accounts file is available.',
                'explanation' => 'Current budget view based on uploaded financial evidence and client-entered budget assumptions.',
                'findings' => $descriptions,
                'metrics' => [
                    $this->metric('Year 1 revenue', (float) ($firstYear['revenue'] ?? 0), 'currency'),
                    $this->metric('Year 1 fixed costs', (float) ($firstYear['fixed_costs'] ?? 0), 'currency'),
                    $this->metric('Funding available', (float) data_get($computed, 'total_funding', 0), 'currency'),
                    $this->metric('Runway', data_get($computed, 'runway_open_ended') ? 'Open ended' : data_get($computed, 'runway_months'), 'months'),
                ],
                'source_financials' => $sourceFinancials,
            ],
            'diagnostic' => [
                'summary' => $flags === []
                    ? "No active budget warnings are present; evidence mix is {$knownRows} known, {$estimateRows} estimates, and {$guessRows} guesses."
                    : count($flags).' active budget warning'.(count($flags) === 1 ? '' : 's').': '.(string) ($firstFlag['title'] ?? 'Review budget risk').'.',
                'explanation' => 'Explains why the budget is strong, weak, incomplete, or risky.',
                'findings' => $diagnoses,
                'flags' => $flags,
                'cost_drivers' => $costDrivers,
                'missing_assumptions' => $missingAssumptions,
                'confidence_mix' => [
                    'known' => $knownRows,
                    'estimate' => $estimateRows,
                    'guess' => $guessRows,
                    'total' => (int) ($rowConfidence['total'] ?? 0),
                ],
            ],
            'predictive' => [
                'summary' => "Runway is {$runwayText}; break-even is {$breakEvenText}; cash-flow positive timing is {$cashFlowPositiveText}.",
                'explanation' => 'Projects runway, break-even timing, cash-flow timing, and scenario outcomes.',
                'findings' => $predictions,
                'key_events' => [
                    $this->metric('Break-even', data_get($computed, 'break_even_year'), 'year'),
                    $this->metric('Cash-flow positive', data_get($computed, 'cash_flow_positive_year'), 'year'),
                    $this->metric('Runway', data_get($computed, 'runway_open_ended') ? 'Open ended' : data_get($computed, 'runway_months'), 'months'),
                ],
                'annual_forecast' => $annualForecast,
                'monthly_forecast' => $monthlyForecast,
                'scenarios' => $scenarioRows,
            ],
            'prescriptive' => [
                'summary' => 'Next action: '.(string) ($prescriptiveActions[0]['action'] ?? 'Maintain the current budget and proceed to advisor review when the plan is complete.'),
                'explanation' => 'Turns budget signals into advisor/client actions before proposal reliance.',
                'findings' => $prescriptions,
                'actions' => $prescriptiveActions,
                'advisor_decision_points' => [
                    'Confirm whether the financial upload is sufficient evidence for proposal reliance.',
                    'Check whether guessed rows need client confirmation or advisor-reviewed estimates.',
                    'Confirm that funding, runway, and break-even timing support the proposed engagement and payment terms.',
                ],
            ],
            'charts' => [
                'annual_revenue_costs' => $this->annualChartRows($annualForecast),
                'margin_percentages' => $this->marginChartRows($annualForecast),
                'monthly_cash' => $this->monthlyChartRows($monthlyForecast),
                'scenario_comparison' => $scenarioRows,
                'confidence_mix' => [
                    ['label' => 'Known', 'value' => $knownRows],
                    ['label' => 'Estimate', 'value' => $estimateRows],
                    ['label' => 'Guess', 'value' => $guessRows],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(StrategicBudget $budget): array
    {
        $computed = (array) ($budget->computed ?? []);
        $confidence = (array) ($budget->confidence ?? []);

        return [
            'id' => $budget->id,
            'label' => $budget->label,
            'pathway' => $budget->pathway,
            'status' => $budget->status,
            'status_label' => $this->statusLabel($budget->status),
            'locked' => ! $budget->isUnlocked(),
            'horizon_months' => $budget->horizon_months,
            'expected_runway_months' => $budget->expected_runway_months,
            'source_financials' => $budget->source_financials ?? [],
            'client_goals' => $budget->client_goals ?? [],
            'advisor_goals' => $budget->advisor_goals ?? [],
            'business_plan_sections' => $budget->business_plan_sections ?? [],
            'business_plan_source_drafts' => $budget->business_plan_source_drafts ?? [],
            'business_plan_prompts' => $budget->business_plan_prompts ?? [],
            'business_plan_readiness_score' => $this->businessPlanReadiness($budget),
            'business_plan_ready' => $this->businessPlanReady($budget),
            'business_plan_submitted_at' => $budget->business_plan_submitted_at?->toIso8601String(),
            'business_plan_approved_at' => $budget->business_plan_approved_at?->toIso8601String(),
            'assumptions' => $budget->assumptions ?? [],
            'implementation_costs' => $budget->implementation_costs ?? [],
            'monthly_fixed_costs' => $budget->monthly_fixed_costs ?? [],
            'future_costs' => $budget->future_costs ?? [],
            'revenue_forecast' => $budget->revenue_forecast ?? [],
            'funding_sources' => $budget->funding_sources ?? [],
            'funding_scenarios' => $budget->funding_scenarios ?? [],
            'computed' => $computed,
            'flags' => $budget->flags ?? [],
            'confidence' => $confidence,
            'analytics' => $this->analyticsPayload($budget),
            'readiness_score' => (int) data_get($confidence, 'score', 0),
            'progress_score' => (int) data_get($confidence, 'progress_score', 0),
            'submitted_at' => $budget->submitted_at?->toIso8601String(),
            'approved_at' => $budget->approved_at?->toIso8601String(),
            'used_in_proposal_at' => $budget->used_in_proposal_at?->toIso8601String(),
            'accepted_snapshot_at' => $budget->accepted_snapshot_at?->toIso8601String(),
        ];
    }

    private function recompute(StrategicBudget $budget): StrategicBudget
    {
        if (! $budget->isUnlocked()) {
            return $this->refreshReadiness($budget);
        }

        $computed = $this->calculator->compute(
            launchCosts: (array) ($budget->implementation_costs ?? []),
            monthlyFixedCosts: (array) ($budget->monthly_fixed_costs ?? []),
            revenueForecast: (array) ($budget->revenue_forecast ?? []),
            fundingSources: (array) ($budget->funding_sources ?? []),
            expectedRunwayMonths: $budget->expected_runway_months,
            forecastYears: max(1, (int) ceil(((int) $budget->horizon_months) / 12)),
            assumptions: (array) ($budget->assumptions ?? []),
            futureCosts: (array) ($budget->future_costs ?? []),
            fundingScenarios: (array) ($budget->funding_scenarios ?? []),
            companyTaxRatePercent: $this->economicPercent(EconomicIndicator::COMPANY_TAX_RATE),
            defaultCostInflationPercent: $this->economicPercent(EconomicIndicator::CPI_ANNUAL),
        );
        $confidence = $this->confidence($budget, $computed);
        $flags = $this->flags($budget, $computed, $confidence);

        $budget->forceFill([
            'computed' => $computed,
            'confidence' => $confidence,
            'flags' => $flags,
        ])->save();

        return $budget->refresh();
    }

    /**
     * @return array{label:string,value:mixed,format:string,detail:?string}
     */
    private function metric(string $label, mixed $value, string $format = 'number', ?string $detail = null): array
    {
        return compact('label', 'value', 'format', 'detail');
    }

    private function money(float $value): string
    {
        return 'NZ$'.number_format($value, 0);
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function runwayText(array $computed): string
    {
        if ((bool) data_get($computed, 'runway_open_ended', false)) {
            return 'open ended';
        }

        $months = data_get($computed, 'runway_months');

        return is_numeric($months) ? ((int) $months).' months' : 'not yet known';
    }

    private function yearText(mixed $year): string
    {
        return is_numeric($year) && (int) $year > 0 ? 'Year '.(int) $year : 'not yet visible';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function sectionExcerpt(array $sections, string $key): ?string
    {
        $section = collect($sections)->first(
            fn (array $section): bool => (string) ($section['key'] ?? '') === $key,
        );

        if (! is_array($section)) {
            return null;
        }

        $answer = trim((string) ($section['answer'] ?? ''));

        return $answer === '' ? null : str($answer)->squish()->limit(150)->toString();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array<string, mixed>>
     */
    private function annualForecastRows(array $computed): array
    {
        return collect((array) data_get($computed, 'annual_totals', []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'year' => (int) ($row['year'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'variable_costs' => (float) ($row['variable_costs'] ?? 0),
                'fixed_costs' => (float) ($row['fixed_costs'] ?? 0),
                'interest' => (float) ($row['interest'] ?? 0),
                'tax' => (float) ($row['tax'] ?? 0),
                'loan_principal' => (float) ($row['loan_principal'] ?? 0),
                'funding_inflow' => (float) ($row['funding_inflow'] ?? 0),
                'launch_costs' => (float) ($row['launch_costs'] ?? 0),
                'gross_profit' => (float) ($row['gross_profit'] ?? 0),
                'net_profit_before_tax' => (float) ($row['net_profit_before_tax'] ?? 0),
                'net_profit_after_tax' => (float) ($row['net_profit_after_tax'] ?? 0),
                'net_cash_flow' => (float) ($row['net_cash_flow'] ?? 0),
                'ending_cash' => (float) ($row['ending_cash'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array<string, mixed>>
     */
    private function monthlyForecastRows(array $computed): array
    {
        return collect((array) data_get($computed, 'monthly_detail', data_get($computed, 'monthly_series', [])))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'month' => (int) ($row['month'] ?? 0),
                'year' => (int) ($row['year'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'variable_costs' => (float) ($row['variable_costs'] ?? 0),
                'fixed_costs' => (float) ($row['fixed_costs'] ?? 0),
                'interest' => (float) ($row['interest'] ?? 0),
                'tax' => (float) ($row['tax'] ?? 0),
                'loan_principal' => (float) ($row['loan_principal'] ?? 0),
                'funding_inflow' => (float) ($row['funding_inflow'] ?? 0),
                'launch_costs' => (float) ($row['launch_costs'] ?? 0),
                'net_profit_after_tax' => (float) ($row['net_profit_after_tax'] ?? 0),
                'net_cash_flow' => (float) ($row['net_cash_flow'] ?? 0),
                'cumulative_cash' => (float) ($row['cumulative_cash'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array<string, mixed>>
     */
    private function scenarioRows(array $computed): array
    {
        return collect((array) data_get($computed, 'scenarios', []))
            ->filter(fn (mixed $scenario): bool => is_array($scenario))
            ->map(function (array $scenario): array {
                $annualRows = collect((array) ($scenario['annual_totals'] ?? []))
                    ->filter(fn (mixed $row): bool => is_array($row));
                $lastYear = $annualRows->last();
                $summary = (array) ($scenario['summary'] ?? []);

                return [
                    'key' => (string) ($scenario['key'] ?? ''),
                    'name' => (string) ($scenario['name'] ?? 'Scenario'),
                    'type' => (string) ($scenario['type'] ?? ''),
                    'runway_months' => $summary['runway_months'] ?? null,
                    'runway_open_ended' => (bool) ($summary['runway_open_ended'] ?? false),
                    'break_even_year' => $summary['break_even_year'] ?? null,
                    'cash_flow_positive_year' => $summary['cash_flow_positive_year'] ?? null,
                    'total_funding' => (float) ($summary['total_funding'] ?? 0),
                    'ending_cash' => is_array($lastYear) ? (float) ($lastYear['ending_cash'] ?? 0) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $annualForecast
     * @return array<int, array<string, mixed>>
     */
    private function annualChartRows(array $annualForecast): array
    {
        return collect($annualForecast)
            ->map(fn (array $row): array => [
                'label' => 'Year '.(int) ($row['year'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'costs' => (float) ($row['variable_costs'] ?? 0)
                    + (float) ($row['fixed_costs'] ?? 0)
                    + (float) ($row['interest'] ?? 0)
                    + (float) ($row['tax'] ?? 0)
                    + (float) ($row['loan_principal'] ?? 0)
                    + (float) ($row['launch_costs'] ?? 0),
                'net_cash_flow' => (float) ($row['net_cash_flow'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $annualForecast
     * @return array<int, array<string, mixed>>
     */
    private function marginChartRows(array $annualForecast): array
    {
        return collect($annualForecast)
            ->map(fn (array $row): array => [
                'label' => 'Year '.(int) ($row['year'] ?? 0),
                'gross_profit_percent' => $this->marginPercent(
                    (float) ($row['gross_profit'] ?? 0),
                    (float) ($row['revenue'] ?? 0),
                ),
                'net_profit_before_tax_percent' => $this->marginPercent(
                    (float) ($row['net_profit_before_tax'] ?? 0),
                    (float) ($row['revenue'] ?? 0),
                ),
                'net_profit_after_tax_percent' => $this->marginPercent(
                    (float) ($row['net_profit_after_tax'] ?? 0),
                    (float) ($row['revenue'] ?? 0),
                ),
            ])
            ->values()
            ->all();
    }

    private function marginPercent(float $profit, float $revenue): float
    {
        if ($revenue === 0.0) {
            return 0.0;
        }

        return round(($profit / $revenue) * 100, 1);
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyForecast
     * @return array<int, array<string, mixed>>
     */
    private function monthlyChartRows(array $monthlyForecast): array
    {
        return collect($monthlyForecast)
            ->take(36)
            ->map(fn (array $row): array => [
                'label' => 'M'.(int) ($row['month'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
                'costs' => (float) ($row['variable_costs'] ?? 0)
                    + (float) ($row['fixed_costs'] ?? 0)
                    + (float) ($row['interest'] ?? 0)
                    + (float) ($row['tax'] ?? 0)
                    + (float) ($row['loan_principal'] ?? 0)
                    + (float) ($row['launch_costs'] ?? 0),
                'cumulative_cash' => (float) ($row['cumulative_cash'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string,value:float}>
     */
    private function costDrivers(StrategicBudget $budget): array
    {
        return collect([
            ['label' => 'Implementation costs', 'value' => $this->inputRowsTotal((array) ($budget->implementation_costs ?? []))],
            ['label' => 'Monthly fixed costs', 'value' => $this->inputRowsTotal((array) ($budget->monthly_fixed_costs ?? []))],
            ['label' => 'Future costs', 'value' => $this->inputRowsTotal((array) ($budget->future_costs ?? []))],
        ])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function inputRowsTotal(array $rows): float
    {
        return round(array_reduce(
            $rows,
            fn (float $total, array $row): float => $total
                + ((float) ($row['amount'] ?? 0) * max(1.0, (float) ($row['quantity'] ?? 1))),
            0.0,
        ), 2);
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<int, array{key:string,label:string}>
     */
    private function missingAssumptions(array $computed): array
    {
        $labels = (array) data_get($computed, 'assumptions.field_labels', []);

        return collect((array) ($computed['missing_assumptions'] ?? []))
            ->map(function (mixed $key) use ($labels): array {
                $key = (string) $key;

                return [
                    'key' => $key,
                    'label' => (string) ($labels[$key] ?? str($key)->replace('_', ' ')->title()->toString()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $flags
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $confidence
     * @return array<int, array<string, string>>
     */
    private function prescriptiveActions(StrategicBudget $budget, array $flags, array $computed, array $confidence): array
    {
        $actions = collect($flags)
            ->filter(fn (mixed $flag): bool => is_array($flag))
            ->map(function (array $flag): array {
                $key = (string) ($flag['key'] ?? '');

                return [
                    'priority' => (string) ($flag['severity'] ?? 'medium'),
                    'action' => match ($key) {
                        'financial_upload_required' => 'Upload and verify a P&L or management accounts file before relying on this budget.',
                        'partial_financials' => 'Request an additional financial upload to strengthen the evidence base.',
                        'business_plan_incomplete' => 'Complete every plan section before advisor approval.',
                        'implementation_costs_missing' => 'Add one-off setup, transition, advisory, or project costs.',
                        'revenue_forecast_missing', 'no_break_even' => 'Update the revenue forecast and margin assumptions until break-even is visible or the risk is accepted.',
                        'missing_assumptions' => 'Complete growth, margin, inflation, and profit-target assumptions.',
                        'too_many_guesses' => 'Replace guessed rows with uploaded evidence, client confirmation, or advisor-reviewed estimates.',
                        'tax_not_configured' => 'Configure current company tax reference data before relying on after-tax outputs.',
                        'financial_snapshot_discrepancy' => 'Reconcile the budget forecast against the latest accounting snapshot before advisor approval.',
                        default => (string) ($flag['message'] ?? 'Review this budget signal before proposal reliance.'),
                    },
                    'reason' => (string) ($flag['message'] ?? ''),
                ];
            });

        if ((int) data_get($confidence, 'score', 0) < 55 && $budget->isUnlocked()) {
            $actions->push([
                'priority' => 'medium',
                'action' => 'Treat the budget as preliminary until evidence and assumptions improve.',
                'reason' => 'Budget confidence is below the developing threshold.',
            ]);
        }

        if ((float) data_get($computed, 'available_after_launch', 0) < 0) {
            $actions->push([
                'priority' => 'high',
                'action' => 'Confirm extra funding, delay implementation spend, or reduce launch costs.',
                'reason' => 'Available cash after launch costs is negative.',
            ]);
        }

        if ($actions->isEmpty()) {
            $actions->push([
                'priority' => 'low',
                'action' => 'Maintain the current budget and proceed to advisor review when the plan is complete.',
                'reason' => 'No active budget warnings are present.',
            ]);
        }

        return $actions->values()->all();
    }

    private function refreshReadiness(StrategicBudget $budget): StrategicBudget
    {
        $confidence = $this->confidence($budget, (array) ($budget->computed ?? []));
        $flags = $this->flags($budget, (array) ($budget->computed ?? []), $confidence);

        $budget->forceFill([
            'confidence' => $confidence,
            'flags' => $flags,
        ])->save();

        return $budget->refresh();
    }

    /**
     * @return Collection<int, Document>
     */
    private function financialDocuments(Client $client): Collection
    {
        return Document::query()
            ->where('client_id', $client->getKey())
            ->where('scanner_result', Document::SCANNER_CLEAN)
            ->with('verifications')
            ->latest()
            ->get()
            ->filter(fn (Document $document): bool => $this->isBudgetFinancialDocument($document)
                && $this->hasVerifiedFinancialEvidence($document))
            ->values();
    }

    private function isBudgetFinancialDocument(Document $document): bool
    {
        if ($document->category === Document::CATEGORY_FINANCIAL_STATEMENT) {
            return true;
        }

        $filename = str((string) $document->original_filename)->lower()->toString();

        foreach (self::FINANCIAL_KEYWORDS as $keyword) {
            if (str_contains($filename, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function hasVerifiedFinancialEvidence(Document $document): bool
    {
        if ($document->verifications->isEmpty()) {
            return false;
        }

        return $document->verifications->every(
            fn (DocumentVerification $verification): bool => $verification->outcome === DocumentVerification::OUTCOME_VERIFIED,
        );
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<string, mixed>
     */
    private function sourceFinancialsPayload(Collection $documents): array
    {
        $items = $documents
            ->take(8)
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'filename' => $document->original_filename,
                'category' => $document->category,
                'uploaded_at' => $document->created_at?->toIso8601String(),
                'detected_as' => $this->detectedFinancialType($document),
                'verification_status' => 'verified',
            ])
            ->values()
            ->all();

        return [
            'unlocked' => $documents->isNotEmpty(),
            'count' => $documents->count(),
            'items' => $items,
            'required_tags' => ['P&L', 'Management Accounts'],
            'system_review' => $documents->isNotEmpty()
                ? 'Verified financial upload is suitable as a starting point.'
                : 'Upload and verify a P&L or management accounts file to unlock the budget.',
        ];
    }

    private function detectedFinancialType(Document $document): string
    {
        $filename = str((string) $document->original_filename)->lower()->toString();

        if ($document->category === Document::CATEGORY_FINANCIAL_STATEMENT) {
            return 'Financial statement';
        }

        if (str_contains($filename, 'management')) {
            return 'Management accounts';
        }

        if (str_contains($filename, 'p&l') || str_contains($filename, 'profit')) {
            return 'P&L';
        }

        return 'Financial upload';
    }

    /**
     * @return array<int, array{title:string,measure:string,owner:string,locked:bool}>
     */
    private function clientGoals(Client $client): array
    {
        $state = is_array($client->onboarding_wizard_state) ? $client->onboarding_wizard_state : [];
        $goals = (array) data_get($state, 'steps.goals', []);
        $primary = trim((string) ($goals['primary_goal'] ?? ''));
        $measure = trim((string) ($goals['success_measure'] ?? ''));

        if ($primary === '' && $measure === '') {
            return [];
        }

        return [[
            'title' => $primary !== '' ? $primary : 'Client onboarding goal',
            'measure' => $measure,
            'owner' => 'client',
            'locked' => false,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array{key:string,title:string,prompt:string,answer:string}>
     */
    private function normaliseBusinessPlanSections(array $sections, string $pathway): array
    {
        $byKey = collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section))
            ->keyBy(fn (array $section): string => (string) ($section['key'] ?? ''));
        $prompts = collect($this->businessPlanPrompts($pathway))->keyBy('key');

        return collect(self::PLAN_SECTION_KEYS)
            ->map(function (string $key) use ($byKey, $prompts): array {
                $prompt = (array) ($prompts->get($key) ?? []);
                $section = (array) ($byKey->get($key) ?? []);

                return [
                    'key' => $key,
                    'title' => (string) ($prompt['title'] ?? str($key)->replace('_', ' ')->title()->toString()),
                    'prompt' => (string) ($prompt['prompt'] ?? ''),
                    'answer' => trim((string) ($section['answer'] ?? $section['body'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,title:string,prompt:string}>
     */
    private function businessPlanPrompts(string $pathway): array
    {
        $variant = match ($pathway) {
            StrategicBudget::PATHWAY_DUE_DILIGENCE => 'due_diligence',
            StrategicBudget::PATHWAY_NPO => 'npo',
            default => 'advisory',
        };

        $prompts = [
            'advisory' => [
                'goals' => 'Confirm the practical business outcomes this advisory work must support.',
                'current_position' => 'Describe the current operating, financial, and leadership position.',
                'market_customers' => 'Summarise core customers, market position, demand signals, and customer risks.',
                'operations' => 'Explain the operating model, systems, people, capacity, and delivery constraints.',
                'risks' => 'Identify the most important commercial, financial, compliance, people, and execution risks.',
                'swot' => 'Summarise strengths, weaknesses, opportunities, and threats in plain language.',
                'action_priorities' => 'Set the near-term actions that would make the proposal more likely to succeed.',
                'evidence_documents' => 'List the documents, numbers, and evidence that support this plan.',
            ],
            'due_diligence' => [
                'goals' => 'Confirm the acquisition goal, target outcome, and what must be true after settlement.',
                'current_position' => 'Describe the buyer, target, DD status, and acquisition context.',
                'market_customers' => 'Summarise target customers, market position, concentration risk, and demand assumptions.',
                'operations' => 'Explain target operations, handover requirements, systems, people, and integration constraints.',
                'risks' => 'Identify acquisition, valuation, funding, integration, and post-settlement risks.',
                'swot' => 'Summarise the acquisition strengths, weaknesses, opportunities, and threats.',
                'action_priorities' => 'Set the first decision gates, completion actions, and first 100-day priorities.',
                'evidence_documents' => 'List DD evidence, financial uploads, workstream findings, and valuation sources.',
            ],
            'npo' => [
                'goals' => 'Confirm mission, operating, funding, governance, and impact outcomes.',
                'current_position' => 'Describe the current governance, service, funding, operational, and compliance position.',
                'market_customers' => 'Summarise beneficiaries, funders, communities, partners, and demand for services.',
                'operations' => 'Explain programmes, volunteers/staff, delivery capacity, systems, and reporting rhythm.',
                'risks' => 'Identify funding, governance, compliance, service-delivery, and reputation risks.',
                'swot' => 'Summarise mission strengths, capability gaps, opportunities, and threats.',
                'action_priorities' => 'Set practical operating priorities that improve sustainability and impact.',
                'evidence_documents' => 'List funding records, budgets, governance documents, impact evidence, and financial uploads.',
            ],
        ];

        $titles = [
            'goals' => 'Goals',
            'current_position' => 'Current position',
            'market_customers' => 'Market / customers',
            'operations' => 'Operations',
            'risks' => 'Risks',
            'swot' => 'SWOT',
            'action_priorities' => 'Action priorities',
            'evidence_documents' => 'Evidence / documents',
        ];

        return collect(self::PLAN_SECTION_KEYS)
            ->map(fn (string $key): array => [
                'key' => $key,
                'title' => $titles[$key],
                'prompt' => $prompts[$variant][$key],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key:string,title:string,source_label:string,source_url:string,source_help:string,body:string}>
     */
    private function sourceDrafts(Client $client, ?BusinessPlan $plan, string $pathway): array
    {
        $clientGoals = $this->clientGoals($client);
        $goalDraft = collect($clientGoals)
            ->map(fn (array $goal): string => trim($goal['title'].' '.($goal['measure'] ?? '')))
            ->filter()
            ->implode("\n");
        $ddDraft = $this->ddSourceDraft($plan);
        $documentCount = Document::query()
            ->where('client_id', $client->getKey())
            ->count();

        $sourceLabel = $pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
            ? 'Source draft from Due Diligence'
            : 'Source draft from onboarding and evidence';
        $sourceUrl = $pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
            ? route('portal.dd-plan.show', absolute: false)
            : route('portal.onboarding.step', ['step' => 'documents'], absolute: false);
        $sourceHelp = $pathway === StrategicBudget::PATHWAY_DUE_DILIGENCE
            ? 'Open the due diligence workspace used to populate this source draft.'
            : 'Open onboarding documents and uploaded evidence used as source material for this draft.';

        $drafts = [
            'goals' => $goalDraft,
            'current_position' => $ddDraft !== ''
                ? $ddDraft
                : trim(($client->trading_name ?: $client->legal_name).' is in the '.$this->engagementLabel($client).' pathway.'),
            'market_customers' => '',
            'operations' => '',
            'risks' => '',
            'swot' => '',
            'action_priorities' => '',
            'evidence_documents' => $documentCount > 0
                ? "{$documentCount} document(s) are available as plan evidence. Confirm which documents support each section."
                : 'No supporting evidence has been attached to this plan yet.',
        ];
        $prompts = collect($this->businessPlanPrompts($pathway))->keyBy('key');

        return collect(self::PLAN_SECTION_KEYS)
            ->map(fn (string $key): array => [
                'key' => $key,
                'title' => (string) data_get($prompts->get($key), 'title', str($key)->replace('_', ' ')->title()->toString()),
                'source_label' => $sourceLabel,
                'source_url' => $sourceUrl,
                'source_help' => $sourceHelp,
                'body' => trim((string) ($drafts[$key] ?? '')),
            ])
            ->values()
            ->all();
    }

    private function ddSourceDraft(?BusinessPlan $plan): string
    {
        if (! $plan instanceof BusinessPlan) {
            return '';
        }

        return $plan->sections()
            ->latest('updated_at')
            ->take(5)
            ->get(['title', 'body'])
            ->map(fn ($section): string => trim($section->title.': '.$section->body))
            ->filter()
            ->implode("\n\n");
    }

    private function engagementLabel(Client $client): string
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $engagementType?->label() ?? str((string) $client->engagement_type)->replace('_', ' ')->title()->toString();
    }

    private function businessPlanReadiness(StrategicBudget $budget): int
    {
        $sections = (array) ($budget->business_plan_sections ?? []);
        if ($sections === []) {
            return 0;
        }

        $completed = collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section) && trim((string) ($section['answer'] ?? '')) !== '')
            ->count();

        return (int) round(($completed / count(self::PLAN_SECTION_KEYS)) * 100);
    }

    private function businessPlanReady(StrategicBudget $budget): bool
    {
        return $this->businessPlanReadiness($budget) >= 100;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array<string, mixed>
     */
    private function confidence(StrategicBudget $budget, array $computed): array
    {
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $hasFinancials = (bool) ($sourceFinancials['unlocked'] ?? false);
        $inputCount = (int) ($computed['input_count'] ?? 0);
        $missingAssumptions = (array) ($computed['missing_assumptions'] ?? []);
        $rowConfidence = $this->rowConfidence(
            (array) ($budget->implementation_costs ?? []),
            (array) ($budget->monthly_fixed_costs ?? []),
            (array) ($budget->future_costs ?? []),
            (array) ($budget->revenue_forecast ?? []),
            (array) ($budget->funding_sources ?? []),
            (array) ($budget->funding_scenarios ?? []),
        );

        $sourceScore = $hasFinancials ? 30 : 0;
        $inputScore = min(30, $inputCount * 4);
        $assumptionScore = max(0, 20 - (count($missingAssumptions) * 4));
        $rowScore = (int) round((float) ($rowConfidence['confidence_ratio'] ?? 0) * 20);
        $score = max(0, min(100, $sourceScore + $inputScore + $assumptionScore + $rowScore));

        return [
            'score' => $score,
            'progress_score' => $this->progressScore($budget, $computed, $hasFinancials),
            'source_score' => $sourceScore,
            'input_score' => $inputScore,
            'assumption_score' => $assumptionScore,
            'row_confidence_score' => $rowScore,
            'row_confidence' => $rowConfidence,
            'overall' => match (true) {
                $score >= 80 => 'strong',
                $score >= 55 => 'developing',
                $score > 0 => 'preliminary',
                default => 'locked',
            },
            'message' => $this->confidenceMessage($score, $hasFinancials),
        ];
    }

    /**
     * @param  array<string, mixed>  $computed
     */
    private function progressScore(StrategicBudget $budget, array $computed, bool $hasFinancials): int
    {
        $steps = [
            $this->businessPlanReady($budget),
            $hasFinancials,
            ((array) ($budget->implementation_costs ?? [])) !== [],
            ((array) ($budget->monthly_fixed_costs ?? [])) !== [],
            ((array) ($budget->revenue_forecast ?? [])) !== [],
            ((array) ($budget->funding_sources ?? [])) !== [],
            $budget->expected_runway_months !== null,
            ((array) ($computed['missing_assumptions'] ?? [])) === [],
            in_array($budget->status, [
                StrategicBudget::STATUS_SUBMITTED_FOR_REVIEW,
                StrategicBudget::STATUS_ADVISOR_APPROVED,
                StrategicBudget::STATUS_USED_IN_PROPOSAL,
                StrategicBudget::STATUS_ACCEPTED_PROPOSAL_SNAPSHOT,
            ], true),
            $budget->isApprovedForProposal(),
        ];

        return (int) round((count(array_filter($steps)) / count($steps)) * 100);
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$groups
     * @return array{known:int,estimate:int,guess:int,total:int,guess_ratio:float,confidence_ratio:float}
     */
    private function rowConfidence(array ...$groups): array
    {
        $summary = ['known' => 0, 'estimate' => 0, 'guess' => 0, 'total' => 0, 'guess_ratio' => 0.0, 'confidence_ratio' => 0.0];

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
            $weighted = ($summary['known'] * 1) + ($summary['estimate'] * 0.65) + ($summary['guess'] * 0.2);
            $summary['confidence_ratio'] = round($weighted / $summary['total'], 4);
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @param  array<string, mixed>  $confidence
     * @return array<int, array<string, string>>
     */
    private function flags(StrategicBudget $budget, array $computed, array $confidence): array
    {
        $flags = [];
        $sourceFinancials = (array) ($budget->source_financials ?? []);
        $planLabel = $budget->pathway === StrategicBudget::PATHWAY_NPO
            ? 'Operating Plan'
            : 'Business Plan';

        if (! (bool) ($sourceFinancials['unlocked'] ?? false)) {
            $flags[] = $this->flag('financial_upload_required', 'Verify financials', 'Upload and verify a P&L or management accounts file before the budget can be edited.', 'high');
        } elseif ((int) ($sourceFinancials['count'] ?? 0) < 2) {
            $flags[] = $this->flag('partial_financials', 'Preliminary financial base', 'Only one qualifying financial upload is present. The budget can start, but more source files will improve the business plan and proposal.', 'medium');
        }

        if (! $this->businessPlanReady($budget)) {
            $flags[] = $this->flag('business_plan_incomplete', "{$planLabel} needs completion", "Complete every {$planLabel} section before submitting the combined plan and budget for advisor approval.", 'medium');
        }

        if (((array) ($budget->implementation_costs ?? [])) === []) {
            $flags[] = $this->flag('implementation_costs_missing', 'Implementation costs needed', 'Add the one-off setup, transition, advisory, or project costs that the plan needs to fund.', 'medium');
        }

        if (((array) ($budget->revenue_forecast ?? [])) === []) {
            $flags[] = $this->flag('revenue_forecast_missing', 'Revenue forecast needed', 'Add the expected revenue lines so affordability and proposal timing can be assessed.', 'medium');
        }

        if ((array) ($computed['missing_assumptions'] ?? []) !== []) {
            $flags[] = $this->flag('missing_assumptions', 'Financial assumptions need detail', 'Growth, margin, inflation, or profit-target assumptions are missing. This lowers the confidence score.', 'medium');
        }

        if (($confidence['row_confidence']['guess_ratio'] ?? 0) >= 0.5) {
            $flags[] = $this->flag('too_many_guesses', 'Too many guessed rows', 'Replace the highest-value guesses with uploaded evidence, advisor-reviewed estimates, or client-confirmed figures.', 'medium');
        }

        $snapshotDiscrepancy = $this->financialSnapshotDiscrepancy($budget, $computed);
        if ($snapshotDiscrepancy !== null) {
            $flags[] = $this->flag(
                'financial_snapshot_discrepancy',
                'Forecast differs from latest financial snapshot',
                sprintf(
                    'Year 1 budget revenue is %s while the latest accounting snapshot shows %s, a %s variance.',
                    $this->money((float) $snapshotDiscrepancy['budget_revenue']),
                    $this->money((float) $snapshotDiscrepancy['snapshot_revenue']),
                    number_format((float) $snapshotDiscrepancy['variance_percent'], 1).'%',
                ),
                'medium',
            );
        }

        if (($computed['input_count'] ?? 0) > 0 && ! (bool) ($computed['break_even_reached'] ?? false)) {
            $flags[] = $this->flag('no_break_even', 'Break-even not visible', 'The current budget does not yet show a break-even year. This should be addressed before relying on the proposal.', 'medium');
        }

        if (! (bool) data_get($computed, 'assumptions.company_tax_configured', false)) {
            $flags[] = $this->flag('tax_not_configured', 'Company tax rate not configured', 'After-tax profit uses a warning state until Admin reference data has a current company tax rate.', 'medium');
        }

        return $flags;
    }

    /**
     * @param  array<string, mixed>  $computed
     * @return array{budget_revenue:float,snapshot_revenue:float,variance_percent:float}|null
     */
    private function financialSnapshotDiscrepancy(StrategicBudget $budget, array $computed): ?array
    {
        $budgetRevenue = (float) data_get($computed, 'annual_totals.0.revenue', 0);
        if ($budgetRevenue <= 0) {
            return null;
        }

        $snapshot = FinancialSnapshot::query()
            ->where('client_id', $budget->client_id)
            ->latest('period_end')
            ->latest('pulled_at')
            ->first();

        if (! $snapshot instanceof FinancialSnapshot) {
            return null;
        }

        $snapshotRevenue = (float) data_get($snapshot->profit_and_loss, 'revenue', 0);
        if ($snapshotRevenue <= 0) {
            return null;
        }

        $variance = abs($budgetRevenue - $snapshotRevenue) / $snapshotRevenue;
        $threshold = (float) config('entrepreneurs.budget.snapshot_revenue_variance_threshold', 0.2);

        if ($variance < $threshold) {
            return null;
        }

        return [
            'budget_revenue' => round($budgetRevenue, 2),
            'snapshot_revenue' => round($snapshotRevenue, 2),
            'variance_percent' => round($variance * 100, 2),
        ];
    }

    /**
     * @return array{key:string,title:string,message:string,severity:string}
     */
    private function flag(string $key, string $title, string $message, string $severity): array
    {
        return compact('key', 'title', 'message', 'severity');
    }

    private function pathway(Client $client): string
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return match ($engagementType) {
            EngagementType::DUE_DILIGENCE => StrategicBudget::PATHWAY_DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => StrategicBudget::PATHWAY_POST_ACQUISITION,
            EngagementType::NPO => StrategicBudget::PATHWAY_NPO,
            default => StrategicBudget::PATHWAY_ADVISORY,
        };
    }

    private function label(string $pathway): string
    {
        return $pathway === StrategicBudget::PATHWAY_NPO
            ? 'Operating Plan & Budget'
            : 'Business Plan & Budget';
    }

    private function defaultHorizonMonths(Client $client): int
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return match ($engagementType) {
            EngagementType::DUE_DILIGENCE,
            EngagementType::POST_ACQUISITION_ADVISORY => 24,
            default => 12,
        };
    }

    private function horizonMonths(mixed $value): int
    {
        $months = is_numeric($value) ? (int) $value : 12;

        return in_array($months, [12, 24, 36], true) ? $months : 12;
    }

    private function expectedRunway(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? min(60, max(0, (int) $value)) : null;
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

    private function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    private function confidenceMessage(int $score, bool $hasFinancials): string
    {
        if (! $hasFinancials) {
            return 'Upload and verify a P&L or management accounts file to unlock this budget.';
        }

        return match (true) {
            $score >= 80 => 'Budget confidence is strong enough for advisor proposal readiness review.',
            $score >= 55 => 'Budget confidence is developing; review flagged assumptions before proposal generation.',
            default => 'Budget confidence is preliminary and will adversely affect the business plan and proposal unless improved.',
        };
    }
}
