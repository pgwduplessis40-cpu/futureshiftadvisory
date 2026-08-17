<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\PlanPhase;
use App\Models\PlanSection;

final class BusinessPlanSnapshot
{
    public function __construct(private readonly PlanIssueReadiness $issueReadiness) {}

    /**
     * @return array<string, mixed>
     */
    public function capture(BusinessPlan $plan): array
    {
        $plan = $plan->refresh()->load('phases.sections', 'sections', 'budgetRunway');
        $requirements = $this->requirements($plan);
        $total = collect($requirements)->flatten(1)->count();
        $completed = collect($requirements)
            ->flatten(1)
            ->filter(fn (array $requirement): bool => (bool) ($requirement['complete'] ?? false))
            ->count();
        $budget = $plan->budgetRunway;

        return [
            'schema_version' => 2,
            'captured_at' => now()->toIso8601String(),
            'business_plan' => [
                'id' => $plan->getKey(),
                'title' => $plan->title,
                'status' => $plan->status,
                'submitted_at' => $plan->submitted_at?->toIso8601String(),
                'updated_at' => $plan->updated_at?->toIso8601String(),
            ],
            'completion' => [
                'total' => $total,
                'completed' => $completed,
                'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ],
            'issue_readiness' => $this->issueReadiness->evaluate($plan),
            'budget' => $budget instanceof EntrepreneurBudget ? [
                'status' => $budget->status,
                'expected_runway_months' => $budget->expected_runway_months,
                'calculated_runway_months' => data_get($budget->computed ?? [], 'runway_months'),
                'break_even_year' => data_get($budget->computed ?? [], 'break_even_year'),
                'cash_flow_positive_year' => data_get($budget->computed ?? [], 'cash_flow_positive_year'),
                'updated_at' => $budget->updated_at?->toIso8601String(),
                'assessment_evidence' => [
                    'forecast_years' => $budget->forecast_years,
                    'expected_runway_months' => $budget->expected_runway_months,
                    'assumptions' => $budget->assumptions ?? [],
                    'launch_costs' => $budget->launch_costs ?? [],
                    'monthly_fixed_costs' => $budget->monthly_fixed_costs ?? [],
                    'future_costs' => $budget->future_costs ?? [],
                    'revenue_forecast' => $budget->revenue_forecast ?? [],
                    'funding_sources' => $budget->funding_sources ?? [],
                    'funding_scenarios' => $budget->funding_scenarios ?? [],
                    'computed' => $budget->computed ?? [],
                    'flags' => $budget->flags ?? [],
                ],
            ] : null,
            'phases' => collect(PlanRequirements::definitions())
                ->map(function (array $definition, string $phaseKey) use ($plan, $requirements): array {
                    $phase = $plan->phases->first(fn (PlanPhase $candidate): bool => $candidate->key === $phaseKey);

                    return [
                        'id' => (string) ($phase?->id ?? $phaseKey),
                        'key' => $phaseKey,
                        'title' => (string) ($phase?->title ?? $definition['title']),
                        'status' => (string) ($phase?->status ?? 'pending'),
                        'position' => (int) ($phase?->position ?? PlanRequirements::phasePosition($phaseKey)),
                        'requirements' => $requirements[$phaseKey] ?? [],
                        'sections' => $phase instanceof PlanPhase
                            ? $phase->sections
                                ->sortBy('created_at')
                                ->map(fn (PlanSection $section): array => $this->sectionSnapshot($section, $phaseKey))
                                ->values()
                                ->all()
                            : [],
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function requirements(BusinessPlan $plan): array
    {
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
     * @return array<string, mixed>
     */
    private function sectionSnapshot(PlanSection $section, string $phaseKey): array
    {
        return [
            'id' => $section->id,
            'key' => $section->key,
            'title' => $section->title,
            'body' => $section->body,
            'attached_document_ids' => $section->attached_document_ids ?? [],
            'requirement_key' => $this->requirementKeyForSection($section, $phaseKey),
            'completeness_status' => $section->completeness_status,
            'created_at' => $section->created_at?->toIso8601String(),
            'updated_at' => $section->updated_at?->toIso8601String(),
        ];
    }

    private function requirementKeyForSection(PlanSection $section, string $phaseKey): ?string
    {
        $requirementKey = data_get($section->metadata, 'requirement_key');

        if (is_string($requirementKey) && trim($requirementKey) !== '') {
            return $requirementKey;
        }

        foreach (PlanRequirements::definitions()[$phaseKey]['requirements'] ?? [] as $requirement) {
            if ($section->key === 'founder-'.$phaseKey.'-'.$requirement['key']) {
                return (string) $requirement['key'];
            }
        }

        return null;
    }
}
