<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanSection;
use Illuminate\Support\Collection;

final class PlanRequirements
{
    public const DEFINITIONS = [
        'foundation' => [
            'title' => 'Foundation',
            'requirements' => [
                ['key' => 'business-type-location', 'title' => 'Business type, location, and operating model'],
                ['key' => 'mission-vision', 'title' => 'Mission and vision'],
            ],
        ],
        'market' => [
            'title' => 'Market',
            'requirements' => [
                ['key' => 'industry-context', 'title' => 'Industry and customer demand'],
                ['key' => 'differentiation', 'title' => 'What sets the business apart'],
            ],
        ],
        'strategy' => [
            'title' => 'Strategy',
            'requirements' => [
                ['key' => 'success-factors', 'title' => 'Unique success factors'],
                ['key' => 'goals-objectives', 'title' => 'Goals and objectives'],
                ['key' => 'culture', 'title' => 'Culture'],
            ],
        ],
        'legal_operations' => [
            'title' => 'Legal & Operations',
            'requirements' => [
                ['key' => 'intellectual-property', 'title' => 'Intellectual property'],
                ['key' => 'legal-environment', 'title' => 'Legal environment'],
            ],
        ],
        'financial' => [
            'title' => 'Financial',
            'requirements' => [
                ['key' => 'revenue-model', 'title' => 'Revenue model'],
                ['key' => 'launch-funding', 'title' => 'Launch funding and support'],
            ],
        ],
    ];

    /**
     * @return array<string, array{title:string, requirements:array<int, array{key:string, title:string}>}>
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
        $plan->loadMissing('sections');
        $total = 0;
        $completed = 0;

        foreach (self::DEFINITIONS as $phaseKey => $definition) {
            foreach ($definition['requirements'] as $requirement) {
                $total++;

                if (self::requirementComplete($plan->sections, $phaseKey, $requirement['key'])) {
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
        $plan->loadMissing('sections');
        $definition = self::DEFINITIONS[$phaseKey] ?? null;

        if (! is_array($definition)) {
            return false;
        }

        foreach ($definition['requirements'] as $requirement) {
            if (! self::requirementComplete($plan->sections, $phaseKey, $requirement['key'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, PlanSection>  $sections
     */
    private static function requirementComplete(Collection $sections, string $phaseKey, string $requirementKey): bool
    {
        return $sections->contains(fn (PlanSection $section): bool => (
            $section->completeness_status === PlanSection::STATUS_COMPLETE
            && (
                (string) data_get($section->metadata, 'requirement_key') === $requirementKey
                || $section->key === 'founder-'.$phaseKey.'-'.$requirementKey
            )
        ));
    }
}
