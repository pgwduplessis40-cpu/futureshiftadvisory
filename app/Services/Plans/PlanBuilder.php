<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\AnalysisFinding;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Models\User;
use InvalidArgumentException;

final class PlanBuilder
{
    public const PHASES = [
        'foundation' => [
            'title' => 'Foundation',
            'depends_on' => [],
        ],
        'market' => [
            'title' => 'Market',
            'depends_on' => ['foundation'],
        ],
        'strategy' => [
            'title' => 'Strategy',
            'depends_on' => ['foundation', 'market'],
        ],
        'legal_operations' => [
            'title' => 'Legal & Operations',
            'depends_on' => ['foundation'],
        ],
        'financial' => [
            'title' => 'Financial',
            'depends_on' => ['foundation', 'strategy'],
        ],
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createOrUpdate(Client $client, array $attributes, ?User $actor = null): BusinessPlan
    {
        $lookup = [
            'client_id' => $client->getKey(),
            'source_type' => (string) ($attributes['source_type'] ?? BusinessPlan::SOURCE_ENTREPRENEUR),
        ];

        if (isset($attributes['dd_engagement_id'])) {
            $lookup['dd_engagement_id'] = $attributes['dd_engagement_id'];
        }

        $plan = BusinessPlan::query()->firstOrNew($lookup);
        $plan->title = (string) ($attributes['title'] ?? 'Business plan');

        if (! $plan->exists) {
            $plan->status = (string) ($attributes['status'] ?? BusinessPlan::STATUS_DRAFT);
            $plan->created_by_user_id = $actor?->getKey();
        } elseif (array_key_exists('status', $attributes)) {
            $plan->status = (string) $attributes['status'];
        }

        $plan->save();

        $this->ensurePhases($plan);

        return $plan->refresh()->load('phases.sections');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function upsertSection(
        BusinessPlan $plan,
        string $phaseKey,
        string $key,
        string $title,
        string $body,
        string $sourceType,
        ?AnalysisFinding $finding = null,
        array $metadata = [],
    ): PlanSection {
        $phase = $this->phase($plan, $phaseKey);
        $section = PlanSection::query()->updateOrCreate(
            [
                'business_plan_id' => $plan->getKey(),
                'key' => $key,
            ],
            [
                'plan_phase_id' => $phase->getKey(),
                'title' => $title,
                'body' => $body,
                'source_type' => $sourceType,
                'source_analysis_finding_id' => $finding?->getKey(),
                'completeness_status' => trim($body) === '' ? PlanSection::STATUS_DRAFT : PlanSection::STATUS_COMPLETE,
                'metadata' => $metadata,
            ],
        );

        $this->refreshPhaseStatus($phase);

        return $section->refresh();
    }

    /**
     * @return array{complete:bool, missing_phases:array<int, string>, completed_phases:array<int, string>}
     */
    public function completion(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections');
        $missing = [];
        $completed = [];

        foreach ($plan->phases->sortBy('position') as $phase) {
            $complete = $phase->sections->contains(
                fn (PlanSection $section): bool => $section->completeness_status === PlanSection::STATUS_COMPLETE,
            );

            if ($complete) {
                $completed[] = $phase->key;
            } else {
                $missing[] = $phase->key;
            }
        }

        return [
            'complete' => $missing === [],
            'missing_phases' => $missing,
            'completed_phases' => $completed,
        ];
    }

    public function assertComplete(BusinessPlan $plan): void
    {
        $completion = $this->completion($plan);

        if (! $completion['complete']) {
            throw new InvalidArgumentException('Business plan is incomplete: missing '.implode(', ', $completion['missing_phases']).'.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function foundingPayload(BusinessPlan $plan): array
    {
        $plan->loadMissing('phases.sections');

        return [
            'business_plan_id' => $plan->id,
            'title' => $plan->title,
            'source_type' => $plan->source_type,
            'phases' => $plan->phases
                ->sortBy('position')
                ->map(fn (PlanPhase $phase): array => [
                    'key' => $phase->key,
                    'title' => $phase->title,
                    'sections' => $phase->sections
                        ->sortBy('created_at')
                        ->map(fn (PlanSection $section): array => [
                            'key' => $section->key,
                            'title' => $section->title,
                            'body' => $section->body,
                            'source_type' => $section->source_type,
                            'source_analysis_finding_id' => $section->source_analysis_finding_id,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function ensurePhases(BusinessPlan $plan): void
    {
        $position = 1;

        foreach (self::PHASES as $key => $definition) {
            PlanPhase::query()->updateOrCreate(
                [
                    'business_plan_id' => $plan->getKey(),
                    'key' => $key,
                ],
                [
                    'title' => $definition['title'],
                    'position' => $position,
                    'depends_on' => $definition['depends_on'],
                ],
            );

            $position++;
        }
    }

    private function phase(BusinessPlan $plan, string $phaseKey): PlanPhase
    {
        if (! array_key_exists($phaseKey, self::PHASES)) {
            throw new InvalidArgumentException("Unknown business plan phase [{$phaseKey}].");
        }

        /** @var PlanPhase $phase */
        $phase = PlanPhase::query()
            ->where('business_plan_id', $plan->getKey())
            ->where('key', $phaseKey)
            ->firstOrFail();

        return $phase;
    }

    private function refreshPhaseStatus(PlanPhase $phase): void
    {
        $hasCompleteSection = $phase->sections()
            ->where('completeness_status', PlanSection::STATUS_COMPLETE)
            ->exists();

        $phase->forceFill([
            'status' => $hasCompleteSection ? PlanPhase::STATUS_COMPLETE : PlanPhase::STATUS_PENDING,
        ])->save();
    }
}
