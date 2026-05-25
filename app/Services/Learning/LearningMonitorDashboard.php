<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningLayerRun;
use App\Models\LearningLayerState;
use App\Models\LearningUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class LearningMonitorDashboard
{
    public function __construct(private readonly LayerCadenceRegistry $registry) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $historyLimit = 25): array
    {
        $latestRuns = LearningLayerRun::query()
            ->latest('ran_at')
            ->get()
            ->unique('layer_id')
            ->keyBy('layer_id');
        $recentRuns = LearningLayerRun::query()
            ->latest('ran_at')
            ->limit(max(1, $historyLimit))
            ->get();
        $queueCounts = LearningUpdate::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count);
        $layerStates = Schema::hasTable('learning_layer_state')
            ? LearningLayerState::query()->get()->keyBy('layer_id')
            : collect();

        return [
            'summary' => [
                'registered_layers' => $this->registry->definitions()->count(),
                'active_layers' => $layerStates->where('active', true)->count(),
                'queued_candidates' => $queueCounts->get(LearningUpdate::STATUS_DETECTED, 0),
                'approved_candidates' => $queueCounts->get(LearningUpdate::STATUS_APPROVED, 0),
                'recent_runs' => $recentRuns->count(),
            ],
            'layers' => $this->layers($latestRuns, $layerStates),
            'recent_runs' => $recentRuns
                ->map(fn (LearningLayerRun $run): array => $this->runPayload($run))
                ->values()
                ->all(),
            'queue_by_status' => $queueCounts->all(),
        ];
    }

    /**
     * @param  Collection<int, LearningLayerRun>  $latestRuns
     * @param  Collection<int, LearningLayerState>  $layerStates
     * @return array<int, array<string, mixed>>
     */
    private function layers(Collection $latestRuns, Collection $layerStates): array
    {
        return $this->registry->definitions()
            ->map(function (array $definition) use ($latestRuns, $layerStates): array {
                $latest = $latestRuns->get($definition['id']);
                $state = $layerStates->get($definition['id']);

                return [
                    'id' => $definition['id'],
                    'name' => $definition['name'],
                    'cadence' => $definition['cadence'],
                    'window_days' => $definition['window_days'],
                    'command' => $definition['command'],
                    'governed_candidates_only' => $definition['governed_candidates_only'],
                    'active' => $state instanceof LearningLayerState ? $state->active : false,
                    'next_due_at' => $state instanceof LearningLayerState ? $state->next_due_at?->toIso8601String() : null,
                    'min_sample' => $state instanceof LearningLayerState ? $state->min_sample : $definition['window_days'],
                    'latest_run' => $latest instanceof LearningLayerRun ? $this->runPayload($latest) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(LearningLayerRun $run): array
    {
        return [
            'id' => $run->id,
            'layer_id' => $run->layer_id,
            'ran_at' => $run->ran_at?->toIso8601String(),
            'candidates_created' => $run->candidates_created,
            'status' => $run->status,
            'window' => $run->window,
        ];
    }
}
