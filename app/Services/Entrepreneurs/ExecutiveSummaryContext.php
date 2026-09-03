<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use Illuminate\Support\Collection;

/**
 * Builds the immutable input fingerprint used to bind an executive summary to
 * the plan and budget revision that passed assessment.
 */
final class ExecutiveSummaryContext
{
    public function hash(BusinessPlan $plan, EntrepreneurProfile $profile): string
    {
        $plan->loadMissing('sections.phase', 'budgetRunway');

        return hash('sha256', json_encode($this->normaliseForHash([
            'profile' => [
                'name' => $profile->name,
                'company_name' => $profile->company_name,
                'concept_summary' => $profile->concept_summary,
            ],
            'sections' => $this->sourceSections($plan)
                ->map(fn (PlanSection $section): array => [
                    'key' => $section->key,
                    'title' => $section->title,
                    'body' => $section->body,
                    'attached_document_ids' => $section->attached_document_ids ?? [],
                    'completeness_status' => $section->completeness_status,
                    'requirement_key' => data_get($section->metadata, 'requirement_key'),
                ])
                ->values()
                ->all(),
            'budget' => $plan->budgetRunway instanceof EntrepreneurBudget ? [
                'status' => $plan->budgetRunway->status,
                'expected_runway_months' => $plan->budgetRunway->expected_runway_months,
                'forecast_years' => $plan->budgetRunway->forecast_years,
                'assumptions' => $plan->budgetRunway->assumptions ?? [],
                'launch_costs' => $plan->budgetRunway->launch_costs ?? [],
                'monthly_fixed_costs' => $plan->budgetRunway->monthly_fixed_costs ?? [],
                'future_costs' => $plan->budgetRunway->future_costs ?? [],
                'revenue_forecast' => $plan->budgetRunway->revenue_forecast ?? [],
                'funding_sources' => $plan->budgetRunway->funding_sources ?? [],
                'funding_scenarios' => $plan->budgetRunway->funding_scenarios ?? [],
                'computed' => $plan->budgetRunway->computed ?? [],
                'flags' => $plan->budgetRunway->flags ?? [],
            ] : null,
        ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return Collection<int, PlanSection>
     */
    private function sourceSections(BusinessPlan $plan): Collection
    {
        return $plan->sections
            ->reject(fn (PlanSection $section): bool => $this->isExecutiveSummary($section))
            ->filter(fn (PlanSection $section): bool => trim((string) $section->body) !== '')
            ->sortBy([
                fn (PlanSection $a, PlanSection $b): int => ((int) ($a->phase->position ?? 99)) <=> ((int) ($b->phase->position ?? 99)),
                fn (PlanSection $a, PlanSection $b): int => strcmp((string) $a->created_at, (string) $b->created_at),
            ])
            ->values();
    }

    private function isExecutiveSummary(PlanSection $section): bool
    {
        return (string) data_get($section->metadata, 'requirement_key') === BusinessPlanExecutiveSummary::REQUIREMENT_KEY
            || $section->key === BusinessPlanExecutiveSummary::SECTION_KEY
            || strcasecmp(trim((string) $section->title), 'Executive summary') === 0;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function normaliseForHash(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(fn (mixed $child): mixed => is_array($child) ? $this->normaliseForHash($child) : $child, $item)
                    : $this->normaliseForHash($item);
            }
        }

        ksort($value);

        return $value;
    }
}
