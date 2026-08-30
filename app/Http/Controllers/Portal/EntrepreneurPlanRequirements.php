<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\PlanSection;
use App\Services\Entrepreneurs\EntrepreneurBudgetService;
use App\Services\Entrepreneurs\PlanRequirements;

/**
 * @phpstan-type RequirementPayload array{key:string, title:string, description:string, phase_key:string, phase_title:string, complete:bool, section_id:string|null, section_title:string|null}
 * @phpstan-type RequirementsPayload array<string, list<RequirementPayload>>
 * @phpstan-type RequirementCompletion array{complete:bool, missing:list<string>, completed:list<string>}
 * @phpstan-type BudgetValue scalar|null|array<array-key, scalar|null|array<array-key, scalar|null|array<array-key, scalar|null>>>
 * @phpstan-type BudgetPayload array<string, BudgetValue>
 * @phpstan-type RequirementDefinition array{key:string, title:string, description:string, type?:string}
 */
final class EntrepreneurPlanRequirements
{
    private const BUDGET_UNLOCK_REQUIREMENT_KEY = 'business-type-location';

    private const BUDGET_ASSUMPTIONS_REQUIREMENT_KEY = 'financial-assumptions';

    public function __construct(
        private readonly EntrepreneurBudgetService $budgets,
    ) {}

    /** @return RequirementsPayload */
    public function payload(BusinessPlan $plan): array
    {
        $plan->loadMissing('budgetRunway');
        $sections = $plan->sections;
        $budget = $plan->budgetRunway;

        return collect(PlanRequirements::definitions())
            ->mapWithKeys(function (array $definition, string $phaseKey) use ($sections, $budget): array {
                return [
                    $phaseKey => collect($definition['requirements'])
                        ->map(function (array $requirement) use ($phaseKey, $definition, $sections, $budget): array {
                            $section = $sections->first(fn (PlanSection $candidate): bool => (
                                (string) data_get($candidate->metadata, 'requirement_key') === $requirement['key']
                                || $candidate->key === 'founder-'.$phaseKey.'-'.$requirement['key']
                            ));
                            $isBudget = ($requirement['type'] ?? null) === 'budget';

                            return [
                                ...$requirement,
                                'phase_key' => $phaseKey,
                                'phase_title' => $definition['title'],
                                'complete' => $isBudget
                                    ? $budget instanceof EntrepreneurBudget && $budget->status === EntrepreneurBudget::STATUS_COMPLETE
                                    : $section instanceof PlanSection && $section->completeness_status === PlanSection::STATUS_COMPLETE,
                                'section_id' => $section?->id,
                                'section_title' => $section?->title,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  RequirementsPayload|null  $requirements
     * @return RequirementCompletion
     */
    public function completion(BusinessPlan $plan, ?array $requirements = null): array
    {
        $requirements ??= $this->payload($plan);
        $flattened = collect($requirements)->flatMap(fn (array $rows): array => $rows)->values();
        $missing = $flattened
            ->reject(fn (array $requirement): bool => (bool) $requirement['complete'])
            ->map(fn (array $requirement): string => $requirement['phase_title'].': '.$requirement['title'])
            ->values()
            ->all();

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'completed' => $flattened
                ->filter(fn (array $requirement): bool => $requirement['complete'])
                ->pluck('key')
                ->values()
                ->all(),
        ];
    }

    /** @return BudgetPayload */
    public function budgetPayload(BusinessPlan $plan, ?EntrepreneurBudget $budget): array
    {
        $packAvailable = $this->budgetUnlocked($plan);

        return [
            'id' => $budget?->id,
            'expected_runway_months' => $budget?->expected_runway_months,
            'forecast_years' => $budget instanceof EntrepreneurBudget ? $budget->forecast_years : 3,
            'status' => $budget instanceof EntrepreneurBudget
                ? $budget->status
                : EntrepreneurBudget::STATUS_NOT_STARTED,
            'assumptions' => $budget instanceof EntrepreneurBudget ? $budget->assumptions : [],
            'launch_costs' => $budget instanceof EntrepreneurBudget ? $budget->launch_costs : [],
            'monthly_fixed_costs' => $budget instanceof EntrepreneurBudget ? $budget->monthly_fixed_costs : [],
            'future_costs' => $budget instanceof EntrepreneurBudget ? $budget->future_costs : [],
            'revenue_forecast' => $budget instanceof EntrepreneurBudget ? $budget->revenue_forecast : [],
            'funding_sources' => $budget instanceof EntrepreneurBudget ? $budget->funding_sources : [],
            'funding_scenarios' => $budget instanceof EntrepreneurBudget ? $budget->funding_scenarios : [],
            'computed' => $budget instanceof EntrepreneurBudget ? $budget->computed : [
                'forecast_years' => 3,
                'total_launch_costs' => 0,
                'monthly_fixed_costs' => 0,
                'total_funding' => 0,
                'available_after_launch' => 0,
                'runway_months' => null,
                'runway_open_ended' => false,
                'break_even_month' => null,
                'break_even_year' => null,
                'first_profitable_year' => null,
                'cash_flow_positive_year' => null,
                'break_even_reached' => false,
                'annual_totals' => [],
                'missing_assumptions' => [],
                'explanations' => [],
                'monthly_series' => [],
                'populated_inputs' => [],
            ],
            'flags' => $budget instanceof EntrepreneurBudget ? $budget->flags : [],
            'active_flags' => $budget instanceof EntrepreneurBudget ? $this->budgets->activeFlags($budget) : [],
            'advisor_line_nudge_seen_at' => $budget?->advisor_line_nudge_seen_at?->toIso8601String(),
            'pack_available' => $packAvailable,
            'budget_pack_url' => $packAvailable ? route('portal.entrepreneur.plan.budget-pack.show', absolute: false) : null,
            'budget_pack_pdf_url' => $packAvailable ? route('portal.entrepreneur.plan.budget-pack.pdf', absolute: false) : null,
        ];
    }

    public function budgetUnlocked(BusinessPlan $plan): bool
    {
        $plan->loadMissing('sections');

        return $this->complete($plan, 'foundation', self::BUDGET_UNLOCK_REQUIREMENT_KEY)
            && $this->complete($plan, 'financial', self::BUDGET_ASSUMPTIONS_REQUIREMENT_KEY);
    }

    /** @return RequirementDefinition */
    public function requirement(string $phaseKey, string $requirementKey): array
    {
        $requirement = collect(PlanRequirements::definitions()[$phaseKey]['requirements'] ?? [])
            ->first(fn (array $definition): bool => $definition['key'] === $requirementKey);
        abort_unless(is_array($requirement), 422);

        return $requirement;
    }

    public function integrateDatedUpdate(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $monthPattern = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';
        $pattern = '/(?:^|\n)\s*(?:#{1,6}\s*)?(?:(?:update(?:d)?|amendment|patch)\b[^\n]*(?:20\d{2}|'.$monthPattern.')|(?:'.$monthPattern.')\s+\d{1,2}(?:st|nd|rd|th)?\s*(?:update|amendment|patch)?)[^\n]*(?:\n|$)/i';
        $matches = [];
        $matchCount = preg_match_all($pattern, $body, $matches, PREG_OFFSET_CAPTURE);

        if ($matchCount === false || $matchCount === 0) {
            return $body;
        }

        $last = end($matches[0]);
        if (! is_array($last)) {
            return $body;
        }

        $replacement = trim(substr($body, (int) $last[1] + strlen((string) $last[0])));

        return strlen($replacement) >= 80 ? $replacement : $body;
    }

    private function complete(BusinessPlan $plan, string $phaseKey, string $requirementKey): bool
    {
        $plan->loadMissing('sections');

        return $plan->sections->contains(fn (PlanSection $section): bool => (
            $section->completeness_status === PlanSection::STATUS_COMPLETE
            && (
                (string) data_get($section->metadata, 'requirement_key') === $requirementKey
                || $section->key === 'founder-'.$phaseKey.'-'.$requirementKey
            )
        ));
    }
}
