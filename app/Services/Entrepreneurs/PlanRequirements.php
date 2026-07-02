<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\PlanSection;
use Illuminate\Support\Collection;

final class PlanRequirements
{
    public const DEFINITIONS = [
        'foundation' => [
            'title' => 'Foundation',
            'requirements' => [
                [
                    'key' => 'business-type-location',
                    'title' => 'Business type, location, and operating model',
                    'description' => 'Describe the type of business, location, and means of doing business.',
                ],
                [
                    'key' => 'mission-vision',
                    'title' => 'Mission and vision',
                    'description' => 'Explain the mission, vision, and the problem the business exists to solve.',
                ],
            ],
        ],
        'market' => [
            'title' => 'Market',
            'requirements' => [
                [
                    'key' => 'industry-context',
                    'title' => 'Industry and customer demand',
                    'description' => 'Discuss the industry, customer segment, demand evidence, and market timing.',
                ],
                [
                    'key' => 'differentiation',
                    'title' => 'What sets the business apart',
                    'description' => 'Describe competitors, alternatives, and why customers would choose this business.',
                ],
            ],
        ],
        'strategy' => [
            'title' => 'Strategy',
            'requirements' => [
                [
                    'key' => 'success-factors',
                    'title' => 'Unique success factors',
                    'description' => 'Describe the capabilities, relationships, or assets that improve the chance of success.',
                ],
                [
                    'key' => 'goals-objectives',
                    'title' => 'Goals and objectives',
                    'description' => 'Set the launch goals, milestones, decisions, and measures of success.',
                ],
                [
                    'key' => 'culture',
                    'title' => 'Culture',
                    'description' => 'Explain the team culture, values, operating behaviours, and customer promise.',
                ],
            ],
        ],
        'legal_operations' => [
            'title' => 'Legal & Operations',
            'requirements' => [
                [
                    'key' => 'intellectual-property',
                    'title' => 'Intellectual property',
                    'description' => 'Identify brand, data, methods, contracts, licences, or IP that need protection.',
                ],
                [
                    'key' => 'legal-environment',
                    'title' => 'Legal environment',
                    'description' => 'List legal, privacy, compliance, supplier, employment, or industry obligations.',
                ],
                [
                    'key' => 'systems-software-processes',
                    'title' => 'What systems/software/processes will be required to run this business if viable?',
                    'description' => 'List the software, operating systems, workflows, responsibilities, suppliers, controls, and implementation gaps needed to run the business if the concept proves viable.',
                ],
            ],
        ],
        'financial' => [
            'title' => 'Financial',
            'requirements' => [
                [
                    'key' => 'financial-assumptions',
                    'title' => 'Financial assumptions',
                    'description' => 'Set the planning assumptions for the budget: business model, revenue streams, target gross profit, target net profit before and after tax, revenue growth, cost inflation, funding scenarios, and known future costs.',
                ],
                [
                    'key' => 'revenue-model',
                    'title' => 'Revenue model',
                    'description' => 'Explain pricing, margin, cost drivers, cash cycle, and early revenue assumptions.',
                ],
                [
                    'key' => 'launch-funding',
                    'title' => 'Launch funding and support',
                    'description' => 'Describe start-up funding, support needed, runway, and financial risk controls.',
                ],
                [
                    'key' => 'budget-runway',
                    'title' => 'Budget',
                    'description' => 'Enter launch costs, monthly costs, revenue assumptions, funding sources, and expected runway.',
                    'type' => 'budget',
                ],
            ],
        ],
    ];

    /**
     * @return array<string, array{title:string, requirements:array<int, array{key:string, title:string, description:string, type?:string}>}>
     */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function phasePosition(string $phaseKey): int
    {
        $position = array_search($phaseKey, array_keys(self::DEFINITIONS), true);

        return $position === false ? 0 : $position + 1;
    }

    public static function phaseTitle(string $phaseKey): string
    {
        return (string) (self::DEFINITIONS[$phaseKey]['title'] ?? $phaseKey);
    }

    /**
     * @return array{total:int, completed:int, percent:int}
     */
    public static function completion(BusinessPlan $plan): array
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $total = 0;
        $completed = 0;

        foreach (self::DEFINITIONS as $phaseKey => $definition) {
            foreach ($definition['requirements'] as $requirement) {
                $total++;

                if (self::requirementComplete($plan->sections, $plan->budgetRunway, $phaseKey, $requirement)) {
                    $completed++;
                }
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    public static function phaseComplete(BusinessPlan $plan, string $phaseKey): bool
    {
        $plan->loadMissing('sections', 'budgetRunway');
        $definition = self::DEFINITIONS[$phaseKey] ?? null;

        if (! is_array($definition)) {
            return false;
        }

        foreach ($definition['requirements'] as $requirement) {
            if (! self::requirementComplete($plan->sections, $plan->budgetRunway, $phaseKey, $requirement)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, PlanSection>  $sections
     * @param  array<string, mixed>  $requirement
     */
    private static function requirementComplete(Collection $sections, ?EntrepreneurBudget $budget, string $phaseKey, array $requirement): bool
    {
        $requirementKey = (string) $requirement['key'];

        if (($requirement['type'] ?? null) === 'budget') {
            return $budget instanceof EntrepreneurBudget && $budget->status === EntrepreneurBudget::STATUS_COMPLETE;
        }

        return $sections->contains(fn (PlanSection $section): bool => (
            $section->completeness_status === PlanSection::STATUS_COMPLETE
            && (
                (string) data_get($section->metadata, 'requirement_key') === $requirementKey
                || $section->key === 'founder-'.$phaseKey.'-'.$requirementKey
            )
        ));
    }
}
