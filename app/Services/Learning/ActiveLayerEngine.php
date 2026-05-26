<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningLayerRun;
use App\Models\LearningLayerState;
use App\Models\LearningUpdate;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class ActiveLayerEngine
{
    public function __construct(
        private readonly LayerCadenceRegistry $registry,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return Collection<int, LearningLayerState>
     */
    public function syncDefinitions(): Collection
    {
        return $this->registry->definitions()
            ->map(function (array $definition): LearningLayerState {
                /** @var LearningLayerState $state */
                $state = LearningLayerState::query()->updateOrCreate(
                    ['layer_id' => $definition['id']],
                    [
                        'name' => $definition['name'],
                        'cadence' => $definition['cadence'],
                        'min_sample' => $definition['window_days'],
                        'config' => [
                            'window_days' => $definition['window_days'],
                            'command' => $definition['command'],
                            'governed_candidates_only' => true,
                            'automatic_application' => false,
                            'metadata' => $definition['metadata'] ?? [],
                        ],
                    ],
                );

                return $state;
            })
            ->values();
    }

    /**
     * @return Collection<int, LearningLayerState>
     */
    public function activateAll(?CarbonInterface $nextDueAt = null): Collection
    {
        $nextDueAt ??= now();

        return $this->syncDefinitions()
            ->each(function (LearningLayerState $state) use ($nextDueAt): void {
                $state->forceFill([
                    'active' => true,
                    'next_due_at' => $state->next_due_at ?? $nextDueAt,
                ])->save();
            })
            ->values();
    }

    /**
     * @param  array<int, int>  $onlyLayerIds
     * @return Collection<int, LearningLayerRun>
     */
    public function runDue(?CarbonInterface $at = null, array $onlyLayerIds = []): Collection
    {
        $at ??= now();

        if (! (bool) config('learning.active_learning')) {
            return collect();
        }

        $this->syncDefinitions();

        return LearningLayerState::query()
            ->where('active', true)
            ->when($onlyLayerIds !== [], fn ($query) => $query->whereIn('layer_id', $onlyLayerIds))
            ->when($onlyLayerIds === [], fn ($query) => $query->where(function ($query) use ($at): void {
                $query->whereNull('next_due_at')
                    ->orWhere('next_due_at', '<=', $at);
            }))
            ->orderBy('layer_id')
            ->get()
            ->map(fn (LearningLayerState $state): LearningLayerRun => $this->runLayer($state, $at))
            ->values();
    }

    private function runLayer(LearningLayerState $state, CarbonInterface $at): LearningLayerRun
    {
        $candidate = $this->createCandidate($state, $at);

        /** @var LearningLayerRun $run */
        $run = LearningLayerRun::query()->create([
            'layer_id' => $state->layer_id,
            'ran_at' => $at,
            'candidates_created' => 1,
            'window' => [
                'window_start' => $at->copy()->subDays(max(1, $state->min_sample))->toIso8601String(),
                'window_end' => $at->toIso8601String(),
                'window_days' => $state->min_sample,
                'cadence' => $state->cadence,
                'registered_name' => $state->name,
                'command' => $state->config['command'] ?? null,
                'governed_candidates_only' => true,
                'automatic_application' => false,
                'metadata' => $state->config['metadata'] ?? [],
                'active_learning' => true,
                'candidate_id' => $candidate->id,
            ],
            'status' => LearningLayerRun::STATUS_COMPLETED,
        ]);

        $state->forceFill([
            'last_run_at' => $at,
            'next_due_at' => $this->nextDueAt($state->cadence, $at),
        ])->save();

        $this->audit->record('learning_layer.active_run_recorded', subject: $run, after: [
            'layer_id' => $state->layer_id,
            'learning_update_id' => $candidate->id,
            'governed_candidates_only' => true,
            'automatic_application' => false,
        ]);

        return $run;
    }

    private function createCandidate(LearningLayerState $state, CarbonInterface $at): LearningUpdate
    {
        /** @var LearningUpdate $update */
        $update = LearningUpdate::query()->create([
            'layer_id' => $state->layer_id,
            'source' => [
                'type' => 'active_layer_engine',
                'cadence' => $state->cadence,
                'ran_at' => $at->toIso8601String(),
            ],
            'summary' => sprintf('%s produced a governed learning review candidate.', $state->name),
            'proposed_change' => [
                'action' => 'review_active_layer_signal',
                'layer_id' => $state->layer_id,
                'automatic_application' => false,
                'requires_approval' => (bool) config('learning.require_approval', true),
            ],
            'impact_scope' => [
                'surface' => 'learning_layer',
                'layer_id' => $state->layer_id,
                'tenant_scope' => 'global',
            ],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.5,
            'evidence' => [
                'state' => [
                    'cadence' => $state->cadence,
                    'min_sample' => $state->min_sample,
                    'config' => $state->config,
                ],
                'guardrail' => 'candidate_only_no_runtime_behavior_change',
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $update, after: [
            'layer_id' => $state->layer_id,
            'source_type' => 'active_layer_engine',
            'automatic_application' => false,
        ]);

        return $update;
    }

    private function nextDueAt(string $cadence, CarbonInterface $at): CarbonInterface
    {
        return match ($cadence) {
            LayerCadenceRegistry::CADENCE_HOURLY => $at->copy()->addHour(),
            LayerCadenceRegistry::CADENCE_DAILY => $at->copy()->addDay(),
            LayerCadenceRegistry::CADENCE_WEEKLY => $at->copy()->addWeek(),
            LayerCadenceRegistry::CADENCE_MONTHLY => $at->copy()->addMonth(),
            default => $at->copy()->addDay(),
        };
    }
}
