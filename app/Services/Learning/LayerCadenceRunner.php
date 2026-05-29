<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\LearningLayerRun;
use App\Services\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class LayerCadenceRunner
{
    public function __construct(
        private readonly LayerCadenceRegistry $registry,
        private readonly AuditWriter $audit,
        private readonly ApprovalFlow $approvalFlow,
    ) {}

    /**
     * @param  array<int, int>  $onlyLayerIds
     * @return Collection<int, LearningLayerRun>
     */
    public function recordDueRuns(?CarbonInterface $at = null, array $onlyLayerIds = []): Collection
    {
        $at ??= now();
        $latestRuns = LearningLayerRun::query()
            ->latest('ran_at')
            ->get()
            ->unique('layer_id')
            ->keyBy('layer_id');

        $runs = $this->registry->definitions()
            ->filter(fn (array $definition): bool => $onlyLayerIds === [] || in_array($definition['id'], $onlyLayerIds, true))
            ->filter(fn (array $definition): bool => $onlyLayerIds !== [] || $this->registry->isDue(
                $definition,
                $at,
                $latestRuns->get($definition['id']),
            ))
            ->map(fn (array $definition): LearningLayerRun => $this->recordRun($definition, $at))
            ->values();

        $implemented = $this->approvalFlow->implementDue($at);

        if ($implemented->isNotEmpty()) {
            $this->audit->record('learning_layer.approved_updates_implemented', after: [
                'implemented_count' => $implemented->count(),
                'implementation_ids' => $implemented->pluck('id')->values()->all(),
                'ran_at' => $at->toIso8601String(),
            ]);
        }

        return $runs;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function recordRun(array $definition, CarbonInterface $at): LearningLayerRun
    {
        /** @var LearningLayerRun $run */
        $run = LearningLayerRun::query()->create([
            'layer_id' => $definition['id'],
            'ran_at' => $at,
            'candidates_created' => 0,
            'window' => [
                'window_start' => $at->copy()->subDays((int) $definition['window_days'])->toIso8601String(),
                'window_end' => $at->toIso8601String(),
                'window_days' => $definition['window_days'],
                'cadence' => $definition['cadence'],
                'registered_name' => $definition['name'],
                'command' => $definition['command'],
                'governed_candidates_only' => true,
                'automatic_application' => false,
                'metadata' => $definition['metadata'] ?? [],
                'cadence_monitor_only' => true,
            ],
            'status' => LearningLayerRun::STATUS_COMPLETED,
        ]);

        $this->audit->record('learning_layer.cadence_recorded', subject: $run, after: [
            'layer_id' => $definition['id'],
            'cadence' => $definition['cadence'],
            'governed_candidates_only' => true,
            'automatic_application' => false,
        ]);

        return $run;
    }
}
