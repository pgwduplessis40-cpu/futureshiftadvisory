<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Models\BusinessPlan;
use App\Models\PlanSection;

final class AcquisitionPlanRequirements
{
    /**
     * @return array<string, array{title: string, requirements: array<int, array<string, mixed>>}>
     */
    public function definitions(): array
    {
        return [
            'foundation' => [
                'title' => 'Foundation',
                'requirements' => [
                    [
                        'key' => 'target_context',
                        'title' => 'Target context from DD',
                        'description' => 'Confirm the target, industry, NZBN, and DD status imported from the DD engagement.',
                        'satisfied_by' => ['dd-foundation-target'],
                    ],
                    [
                        'key' => 'acquisition_thesis',
                        'title' => 'Buyer acquisition thesis',
                        'description' => 'Explain why this acquisition should proceed, what problem it solves, and what has to be true after settlement.',
                        'satisfied_by' => [],
                    ],
                ],
            ],
            'market' => [
                'title' => 'Market',
                'requirements' => [
                    [
                        'key' => 'market_position',
                        'title' => 'Customer and market position',
                        'description' => 'Use commercial DD, customer concentration, competitor position, and demand assumptions to explain the market plan.',
                        'satisfied_by' => [],
                        'workstreams' => ['commercial_market'],
                    ],
                ],
            ],
            'strategy' => [
                'title' => 'Strategy',
                'requirements' => [
                    [
                        'key' => 'first_100_days',
                        'title' => 'First 100-day operating plan',
                        'description' => 'Set priorities, milestones, decision gates, and integration assumptions for the first 100 days.',
                        'satisfied_by' => ['dd-strategy-integration'],
                    ],
                ],
            ],
            'legal_operations' => [
                'title' => 'Legal & Operations',
                'requirements' => [
                    [
                        'key' => 'handover_risks',
                        'title' => 'Legal, people, and operational handover risks',
                        'description' => 'Capture the contracts, consents, people, systems, compliance, and handover controls that must be managed.',
                        'satisfied_by' => [],
                        'workstreams' => ['legal', 'tax', 'operational', 'hr_people', 'nz_regulatory'],
                    ],
                ],
            ],
            'financial' => [
                'title' => 'Financial',
                'requirements' => [
                    [
                        'key' => 'valuation_price_range',
                        'title' => 'Valuation and purchase-price range',
                        'description' => 'Use the DCF-led valuation, market multiples, precedents, structure, synergy, and DD-risk adjustments.',
                        'satisfied_by' => ['dd-valuation-summary'],
                    ],
                    [
                        'key' => 'funding_structure',
                        'title' => 'Funding and deal-structure assumptions',
                        'description' => 'Confirm funding source, completion accounts, earnout, vendor finance, working capital, and post-settlement cash needs.',
                        'satisfied_by' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templatePayload(): array
    {
        return collect($this->definitions())
            ->map(fn (array $definition, string $phaseKey): array => [
                'key' => $phaseKey,
                'title' => $definition['title'],
                'requirements' => collect($definition['requirements'])
                    ->map(fn (array $requirement): array => [
                        'key' => $requirement['key'],
                        'phase_key' => $phaseKey,
                        'phase_title' => $definition['title'],
                        'title' => $requirement['title'],
                        'description' => $requirement['description'],
                        'complete' => false,
                        'section_id' => null,
                        'section_title' => null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function payload(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections');
        $definitions = $this->definitions();
        $phasesByKey = $plan->phases->keyBy('key');

        return collect($definitions)
            ->mapWithKeys(function (array $definition, string $phaseKey) use ($phasesByKey): array {
                $phase = $phasesByKey->get($phaseKey);

                return [
                    $phaseKey => collect($definition['requirements'])
                        ->map(function (array $requirement) use ($phase, $phaseKey, $definition): array {
                            $section = $phase === null
                                ? null
                                : $this->sectionForRequirement($phase->sections, $requirement);

                            return [
                                'key' => $requirement['key'],
                                'phase_key' => $phaseKey,
                                'phase_title' => (string) ($phase?->title ?? $definition['title']),
                                'title' => $requirement['title'],
                                'description' => $requirement['description'],
                                'complete' => $section instanceof PlanSection,
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
     * @param  array<string, array<int, array<string, mixed>>>|null  $requirements
     * @return array{complete: bool, missing: array<int, string>}
     */
    public function completion(BusinessPlan $plan, ?array $requirements = null): array
    {
        $requirements ??= $this->payload($plan);
        $missing = collect($requirements)
            ->flatMap(fn (array $phaseRequirements): array => collect($phaseRequirements)
                ->reject(fn (array $requirement): bool => (bool) $requirement['complete'])
                ->map(fn (array $requirement): string => $requirement['phase_title'].': '.$requirement['title'])
                ->values()
                ->all())
            ->values()
            ->all();

        return [
            'complete' => $missing === [],
            'missing' => $missing,
        ];
    }

    private function sectionForRequirement($sections, array $requirement): ?PlanSection
    {
        return $sections->first(function (PlanSection $section) use ($requirement): bool {
            if ($section->completeness_status !== PlanSection::STATUS_COMPLETE) {
                return false;
            }

            if (data_get($section->metadata, 'requirement_key') === $requirement['key']) {
                return true;
            }

            return in_array($section->key, $requirement['satisfied_by'] ?? [], true)
                || in_array((string) data_get($section->metadata, 'workstream'), $requirement['workstreams'] ?? [], true);
        });
    }
}
