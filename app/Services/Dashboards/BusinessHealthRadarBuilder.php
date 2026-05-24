<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessHealthSnapshot;
use App\Models\Client;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BusinessHealthRadarBuilder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function rowsFor(Client $client, string $assessmentBatchId, CarbonInterface $capturedAt): array
    {
        return collect($this->dimensionMap())
            ->map(function (array $modules, string $dimension) use ($client, $assessmentBatchId, $capturedAt): array {
                $moduleStates = [];
                $contributingFindings = collect();

                foreach ($modules as $module) {
                    $state = $this->moduleState($client, $module);
                    $moduleStates[$module->value] = $state['state'];

                    /** @var Collection<int, AnalysisFinding> $findings */
                    $findings = $state['findings'];
                    $contributingFindings = $contributingFindings->merge($findings);
                }

                $topFinding = $this->topFinding($contributingFindings);
                $score = $this->score($contributingFindings);

                return [
                    'client_id' => $client->getKey(),
                    'assessment_batch_id' => $assessmentBatchId,
                    'dimension' => $dimension,
                    'score' => $score,
                    'top_finding_id' => $topFinding?->getKey(),
                    'contributing_finding_ids' => $contributingFindings
                        ->map(fn (AnalysisFinding $finding): string => (string) $finding->getKey())
                        ->values()
                        ->all(),
                    'module_run_states' => $moduleStates,
                    'dimension_run_state' => $this->dimensionRunState($moduleStates),
                    'captured_at' => $capturedAt,
                    'source_attributions' => $this->sourceAttributions($contributingFindings),
                    'created_at' => $capturedAt,
                    'updated_at' => $capturedAt,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function portalPayload(Client $client): array
    {
        $batch = $this->latestCompleteBatch($client);
        $previous = $batch === null
            ? null
            : $this->previousCompleteBatch($client, (string) $batch->first()?->assessment_batch_id);

        $snapshots = $batch?->keyBy('dimension') ?? collect();
        $previousSnapshots = $previous?->keyBy('dimension') ?? collect();

        return [
            'captured_at' => $batch?->first()?->captured_at?->toIso8601String(),
            'axes' => collect($this->dimensionMap())
                ->map(function (array $modules, string $dimension) use ($snapshots, $previousSnapshots): array {
                    /** @var BusinessHealthSnapshot|null $snapshot */
                    $snapshot = $snapshots->get($dimension);
                    /** @var BusinessHealthSnapshot|null $previous */
                    $previous = $previousSnapshots->get($dimension);
                    $state = $snapshot?->dimension_run_state ?? BusinessHealthSnapshot::STATE_NEVER_RUN;

                    return [
                        'dimension' => $dimension,
                        'label' => $this->dimensionLabel($dimension),
                        'score' => $snapshot?->score,
                        'state' => $state,
                        'message' => $this->stateMessage($state, $snapshot),
                        'trend' => $this->trend($snapshot, $previous),
                        'top_finding' => $snapshot?->topFinding instanceof AnalysisFinding
                            ? $this->findingPayload($snapshot->topFinding)
                            : null,
                        'contributing_finding_ids' => $snapshot?->contributing_finding_ids ?? [],
                        'module_run_states' => $snapshot?->module_run_states ?? $this->emptyModuleStates($modules),
                        'drill_url' => $snapshot?->score === null ? null : route('portal.dashboard', [
                            'focus' => 'health',
                            'highlight' => 'health-'.$dimension,
                        ], absolute: false),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function healthFindingsPayload(Client $client): array
    {
        $batch = $this->latestCompleteBatch($client);
        $snapshots = $batch?->keyBy('dimension') ?? collect();
        $ids = $snapshots
            ->flatMap(fn (BusinessHealthSnapshot $snapshot): array => $snapshot->contributing_finding_ids ?? [])
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values();

        $findings = $ids->isEmpty()
            ? collect()
            : AnalysisFinding::query()
                ->with('run')
                ->where('client_id', $client->getKey())
                ->whereIn('id', $ids->all())
                ->where('lens', '!=', AnalysisLens::Prescriptive->value)
                ->get()
                ->keyBy(fn (AnalysisFinding $finding): string => (string) $finding->getKey());

        return collect($this->dimensionMap())
            ->map(function (array $modules, string $dimension) use ($snapshots, $findings): array {
                /** @var BusinessHealthSnapshot|null $snapshot */
                $snapshot = $snapshots->get($dimension);
                $dimensionIds = collect($snapshot?->contributing_finding_ids ?? [])
                    ->map(fn (mixed $id): string => (string) $id)
                    ->values();

                return [
                    'dimension' => $dimension,
                    'label' => $this->dimensionLabel($dimension),
                    'anchor' => 'health-'.$dimension,
                    'state' => $snapshot?->dimension_run_state ?? BusinessHealthSnapshot::STATE_NEVER_RUN,
                    'message' => $this->stateMessage($snapshot?->dimension_run_state ?? BusinessHealthSnapshot::STATE_NEVER_RUN, $snapshot),
                    'findings' => $dimensionIds
                        ->map(fn (string $id): ?AnalysisFinding => $findings->get($id))
                        ->filter(fn (?AnalysisFinding $finding): bool => $finding instanceof AnalysisFinding)
                        ->sort($this->findingSorter())
                        ->map(fn (AnalysisFinding $finding): array => $this->findingPayload($finding))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return EloquentCollection<int, BusinessHealthSnapshot>|null
     */
    public function latestCompleteBatch(Client $client): ?EloquentCollection
    {
        return $this->completeBatchCandidates($client)
            ->first();
    }

    /**
     * @return EloquentCollection<int, BusinessHealthSnapshot>|null
     */
    private function previousCompleteBatch(Client $client, string $currentBatchId): ?EloquentCollection
    {
        return $this->completeBatchCandidates($client)
            ->first(fn (EloquentCollection $batch): bool => (string) $batch->first()?->assessment_batch_id !== $currentBatchId);
    }

    /**
     * @return Collection<int, EloquentCollection<int, BusinessHealthSnapshot>>
     */
    private function completeBatchCandidates(Client $client): Collection
    {
        $dimensionSet = BusinessHealthSnapshot::dimensions();
        sort($dimensionSet);

        $candidates = BusinessHealthSnapshot::query()
            ->select('assessment_batch_id', DB::raw('max(captured_at) as captured_at'))
            ->where('client_id', $client->getKey())
            ->groupBy('assessment_batch_id')
            ->orderByDesc('captured_at')
            ->orderByDesc('assessment_batch_id')
            ->limit(20)
            ->get();

        return $candidates
            ->map(function (BusinessHealthSnapshot $candidate) use ($client): EloquentCollection {
                return BusinessHealthSnapshot::query()
                    ->with('topFinding.run')
                    ->where('client_id', $client->getKey())
                    ->where('assessment_batch_id', $candidate->assessment_batch_id)
                    ->get()
                    ->sortBy(fn (BusinessHealthSnapshot $snapshot): int => array_search($snapshot->dimension, BusinessHealthSnapshot::dimensions(), true))
                    ->values();
            })
            ->filter(function (EloquentCollection $batch) use ($dimensionSet): bool {
                $actual = $batch
                    ->pluck('dimension')
                    ->unique()
                    ->values()
                    ->all();
                sort($actual);

                return $actual === $dimensionSet;
            })
            ->values();
    }

    /**
     * @return array<string, array<int, AnalysisModule>>
     */
    private function dimensionMap(): array
    {
        $configured = (array) config('dashboards.radar.dimensions', []);

        return collect(BusinessHealthSnapshot::dimensions())
            ->mapWithKeys(function (string $dimension) use ($configured): array {
                $modules = collect($configured[$dimension] ?? [])
                    ->map(fn (mixed $module): AnalysisModule => $module instanceof AnalysisModule
                        ? $module
                        : AnalysisModule::from((string) $module))
                    ->values()
                    ->all();

                return [$dimension => $modules];
            })
            ->all();
    }

    /**
     * @return array{state: array<string, mixed>, findings: Collection<int, AnalysisFinding>}
     */
    private function moduleState(Client $client, AnalysisModule $module): array
    {
        $latestRun = $this->latestRun($client, $module);
        $scoringRun = $this->latestCompletedRun($client, $module);
        $findings = collect();
        $completedFindingCount = 0;

        if ($scoringRun instanceof AnalysisRun) {
            $completedFindingCount = AnalysisFinding::query()
                ->where('analysis_run_id', $scoringRun->getKey())
                ->count();

            $findings = AnalysisFinding::query()
                ->where('analysis_run_id', $scoringRun->getKey())
                ->where('lens', '!=', AnalysisLens::Prescriptive->value)
                ->get();
        }

        $state = $this->moduleStateName($latestRun, $scoringRun, $findings, $completedFindingCount);

        return [
            'state' => [
                'state' => $state,
                'scoring_run_id' => $findings->isNotEmpty() && $scoringRun instanceof AnalysisRun ? (string) $scoringRun->getKey() : null,
                'scoring_completed_at' => $findings->isNotEmpty() ? $scoringRun?->completed_at?->toIso8601String() : null,
                'latest_run_id' => $latestRun instanceof AnalysisRun ? (string) $latestRun->getKey() : null,
                'latest_run_status' => $latestRun?->status,
                'latest_run_at' => $latestRun instanceof AnalysisRun ? $this->runAt($latestRun)?->toIso8601String() : null,
                'stale' => $this->isStale($latestRun, $scoringRun),
            ],
            'findings' => $state === BusinessHealthSnapshot::STATE_SCORED ? $findings : collect(),
        ];
    }

    private function moduleStateName(
        ?AnalysisRun $latestRun,
        ?AnalysisRun $scoringRun,
        Collection $clientSafeFindings,
        int $completedFindingCount,
    ): string {
        if ($latestRun === null) {
            return BusinessHealthSnapshot::STATE_NEVER_RUN;
        }

        if ($scoringRun instanceof AnalysisRun && $clientSafeFindings->isNotEmpty()) {
            return BusinessHealthSnapshot::STATE_SCORED;
        }

        if (
            $scoringRun instanceof AnalysisRun
            && (string) $latestRun->getKey() !== (string) $scoringRun->getKey()
            && $latestRun->status !== AnalysisRun::STATUS_COMPLETED
        ) {
            return $this->stateFromRunStatus($latestRun->status);
        }

        if ($scoringRun instanceof AnalysisRun) {
            return $completedFindingCount > 0
                ? BusinessHealthSnapshot::STATE_COMPLETED_NO_CLIENT_SAFE_FINDINGS
                : BusinessHealthSnapshot::STATE_COMPLETED_NO_FINDINGS;
        }

        return $this->stateFromRunStatus($latestRun->status);
    }

    private function latestRun(Client $client, AnalysisModule $module): ?AnalysisRun
    {
        return AnalysisRun::query()
            ->where('client_id', $client->getKey())
            ->where('module', $module->value)
            ->orderByRaw('COALESCE(completed_at, started_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();
    }

    private function latestCompletedRun(Client $client, AnalysisModule $module): ?AnalysisRun
    {
        return AnalysisRun::query()
            ->where('client_id', $client->getKey())
            ->where('module', $module->value)
            ->where('status', AnalysisRun::STATUS_COMPLETED)
            ->orderByRaw('COALESCE(completed_at, started_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();
    }

    private function stateFromRunStatus(?string $status): string
    {
        return match ($status) {
            AnalysisRun::STATUS_BLOCKED_DOCUMENTS,
            AnalysisRun::STATUS_BLOCKED_DATA_QUALITY => BusinessHealthSnapshot::STATE_BLOCKED,
            AnalysisRun::STATUS_FAILED => BusinessHealthSnapshot::STATE_FAILED,
            AnalysisRun::STATUS_QUEUED,
            AnalysisRun::STATUS_RUNNING => BusinessHealthSnapshot::STATE_IN_PROGRESS,
            AnalysisRun::STATUS_COMPLETED => BusinessHealthSnapshot::STATE_COMPLETED_NO_FINDINGS,
            default => BusinessHealthSnapshot::STATE_NEVER_RUN,
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $moduleStates
     */
    private function dimensionRunState(array $moduleStates): string
    {
        $states = collect($moduleStates)
            ->map(fn (array $state): string => (string) $state['state'])
            ->all();

        foreach ([
            BusinessHealthSnapshot::STATE_SCORED,
            BusinessHealthSnapshot::STATE_BLOCKED,
            BusinessHealthSnapshot::STATE_FAILED,
            BusinessHealthSnapshot::STATE_IN_PROGRESS,
            BusinessHealthSnapshot::STATE_COMPLETED_NO_CLIENT_SAFE_FINDINGS,
            BusinessHealthSnapshot::STATE_COMPLETED_NO_FINDINGS,
        ] as $state) {
            if (in_array($state, $states, true)) {
                return $state;
            }
        }

        return BusinessHealthSnapshot::STATE_NEVER_RUN;
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     */
    private function score(Collection $findings): ?int
    {
        if ($findings->isEmpty()) {
            return null;
        }

        $load = $findings
            ->sum(fn (AnalysisFinding $finding): int => $this->severityWeight($finding->severity));
        $loadCap = max(1, (int) config('dashboards.radar.load_cap', 30));

        return max(0, min(100, 100 - (int) round(100 * $load / $loadCap)));
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     */
    private function topFinding(Collection $findings): ?AnalysisFinding
    {
        return $findings
            ->sort($this->findingSorter())
            ->first();
    }

    /**
     * @return callable(AnalysisFinding, AnalysisFinding): int
     */
    private function findingSorter(): callable
    {
        return function (AnalysisFinding $left, AnalysisFinding $right): int {
            $weight = $this->severityWeight($right->severity) <=> $this->severityWeight($left->severity);

            if ($weight !== 0) {
                return $weight;
            }

            $created = ($right->created_at?->getTimestamp() ?? 0) <=> ($left->created_at?->getTimestamp() ?? 0);

            return $created !== 0
                ? $created
                : strcmp((string) $right->getKey(), (string) $left->getKey());
        };
    }

    private function severityWeight(FindingSeverity|string|null $severity): int
    {
        $key = $severity instanceof FindingSeverity ? $severity->value : (string) $severity;
        $weights = (array) config('dashboards.radar.severity_weights', []);

        return (int) ($weights[$key] ?? 0);
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<int, array<string, mixed>>
     */
    private function sourceAttributions(Collection $findings): array
    {
        return $findings
            ->flatMap(fn (AnalysisFinding $finding): array => collect($finding->attributions ?? [])
                ->map(fn (mixed $attribution): array => is_array($attribution)
                    ? array_merge($attribution, ['analysis_finding_id' => (string) $finding->getKey()])
                    : ['claim' => (string) $attribution, 'analysis_finding_id' => (string) $finding->getKey()])
                ->all())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function findingPayload(AnalysisFinding $finding): array
    {
        return [
            'id' => (string) $finding->getKey(),
            'module' => $finding->run?->module?->value,
            'lens' => $finding->lens->value,
            'severity' => $finding->severity->value,
            'title' => $finding->title,
            'body' => $finding->body,
            'attributions' => $finding->attributions ?? [],
            'created_at' => $finding->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emptyModuleStates(array $modules): array
    {
        return collect($modules)
            ->mapWithKeys(fn (AnalysisModule $module): array => [
                $module->value => [
                    'state' => BusinessHealthSnapshot::STATE_NEVER_RUN,
                    'scoring_run_id' => null,
                    'scoring_completed_at' => null,
                    'latest_run_id' => null,
                    'latest_run_status' => null,
                    'latest_run_at' => null,
                    'stale' => false,
                ],
            ])
            ->all();
    }

    private function isStale(?AnalysisRun $latestRun, ?AnalysisRun $scoringRun): bool
    {
        return $latestRun instanceof AnalysisRun
            && $scoringRun instanceof AnalysisRun
            && $latestRun->status !== AnalysisRun::STATUS_COMPLETED
            && (string) $latestRun->getKey() !== (string) $scoringRun->getKey();
    }

    private function runAt(AnalysisRun $run): ?CarbonInterface
    {
        return $run->completed_at ?? $run->started_at ?? $run->created_at;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function trend(?BusinessHealthSnapshot $current, ?BusinessHealthSnapshot $previous): ?array
    {
        if ($current?->score === null || $previous?->score === null) {
            return null;
        }

        $delta = $current->score - $previous->score;

        return [
            'delta' => $delta,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
        ];
    }

    private function dimensionLabel(string $dimension): string
    {
        return match ($dimension) {
            BusinessHealthSnapshot::DIMENSION_FINANCIAL => 'Financial',
            BusinessHealthSnapshot::DIMENSION_OPERATIONAL => 'Operational',
            BusinessHealthSnapshot::DIMENSION_PEOPLE => 'People',
            BusinessHealthSnapshot::DIMENSION_STRATEGIC => 'Strategic',
            BusinessHealthSnapshot::DIMENSION_COMPLIANCE => 'Compliance',
            default => str($dimension)->replace('_', ' ')->title()->toString(),
        };
    }

    private function stateMessage(string $state, ?BusinessHealthSnapshot $snapshot): string
    {
        $base = match ($state) {
            BusinessHealthSnapshot::STATE_SCORED => 'Score reflects the latest completed client-safe analysis.',
            BusinessHealthSnapshot::STATE_COMPLETED_NO_FINDINGS => 'Analysis completed with no persisted cited findings.',
            BusinessHealthSnapshot::STATE_COMPLETED_NO_CLIENT_SAFE_FINDINGS => 'No client-safe findings to display for this dimension.',
            BusinessHealthSnapshot::STATE_BLOCKED => 'Analysis blocked - resolve document or data-quality issues.',
            BusinessHealthSnapshot::STATE_FAILED => 'Analysis did not complete - rerun needed.',
            BusinessHealthSnapshot::STATE_IN_PROGRESS => 'Analysis in progress.',
            default => 'No analysis run for this dimension yet.',
        };

        $stale = collect($snapshot?->module_run_states ?? [])
            ->first(fn (array $state): bool => (bool) ($state['stale'] ?? false));

        if (! is_array($stale)) {
            return $base;
        }

        $completedAt = $stale['scoring_completed_at'] ?? null;
        $latestStatus = $stale['latest_run_status'] ?? 'newer';

        if (! is_string($completedAt) || $completedAt === '') {
            return $base;
        }

        return $base.' Score reflects the last completed analysis on '.$completedAt.'; a newer run is '.$latestStatus.'.';
    }
}
