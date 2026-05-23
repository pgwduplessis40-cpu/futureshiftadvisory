<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use Illuminate\Support\Collection;

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

        return [
            'summary' => [
                'registered_layers' => $this->registry->definitions()->count(),
                'queued_candidates' => $queueCounts->get(LearningUpdate::STATUS_DETECTED, 0),
                'approved_candidates' => $queueCounts->get(LearningUpdate::STATUS_APPROVED, 0),
                'recent_runs' => $recentRuns->count(),
            ],
            'layers' => $this->layers($latestRuns),
            'recent_runs' => $recentRuns
                ->map(fn (LearningLayerRun $run): array => $this->runPayload($run))
                ->values()
                ->all(),
            'queue_by_status' => $queueCounts->all(),
        ];
    }

    /**
     * @param  Collection<int, LearningLayerRun>  $latestRuns
     * @return array<int, array<string, mixed>>
     */
    private function layers(Collection $latestRuns): array
    {
        return $this->registry->definitions()
            ->map(function (array $definition) use ($latestRuns): array {
                $latest = $latestRuns->get($definition['id']);

                return [
                    'id' => $definition['id'],
                    'name' => $definition['name'],
                    'cadence' => $definition['cadence'],
                    'window_days' => $definition['window_days'],
                    'command' => $definition['command'],
                    'governed_candidates_only' => $definition['governed_candidates_only'],
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
