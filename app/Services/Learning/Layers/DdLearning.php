<?php

declare(strict_types=1);

namespace App\Services\Learning\Layers;

use App\Enums\AnalysisModule;
use App\Models\AnalysisFinding;
use App\Models\DdOutcomeRecord;
use App\Models\DdValuation;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DdLearning
{
    public const LAYER_ID = 21;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(
        int $minOutcomes = 1,
        float $varianceThreshold = 0.15,
        int $patternThreshold = 3,
        int $windowDays = 180,
        ?CarbonInterface $windowEnd = null,
    ): LearningLayerRun {
        $minOutcomes = max(1, $minOutcomes);
        $varianceThreshold = max(0.01, $varianceThreshold);
        $patternThreshold = max(2, $patternThreshold);
        $windowDays = max(1, $windowDays);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);

        $this->context->apply('system', []);

        return DB::transaction(function () use (
            $minOutcomes,
            $varianceThreshold,
            $patternThreshold,
            $windowDays,
            $windowStart,
            $windowEnd,
        ): LearningLayerRun {
            $candidatesCreated = 0;

            $valuationSignal = $this->valuationAccuracySignal($windowStart, $windowEnd, $minOutcomes, $varianceThreshold);
            if ($valuationSignal !== null && ! $this->detectedCandidateExists('dd_valuation_accuracy', $valuationSignal['signal_key'])) {
                $this->createCandidate(
                    sourceType: 'dd_valuation_accuracy',
                    signal: $valuationSignal,
                    summary: 'DD outcome records show acquisition prices diverging from valuation mid-points.',
                    action: 'review_dd_valuation_calibration',
                );
                $candidatesCreated++;
            }

            foreach ($this->findingPatternSignals($windowStart, $windowEnd, $patternThreshold) as $signal) {
                if ($this->detectedCandidateExists('dd_finding_pattern', (string) $signal['signal_key'])) {
                    continue;
                }

                $this->createCandidate(
                    sourceType: 'dd_finding_pattern',
                    signal: $signal,
                    summary: sprintf('DD findings repeatedly identified the "%s" pattern.', (string) $signal['pattern']),
                    action: 'review_dd_checklist_pattern',
                );
                $candidatesCreated++;
            }

            /** @var LearningLayerRun $run */
            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $candidatesCreated,
                'window' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'window_days' => $windowDays,
                    'min_outcomes' => $minOutcomes,
                    'variance_threshold' => $varianceThreshold,
                    'pattern_threshold' => $patternThreshold,
                    'governed_candidates_only' => true,
                    'automatic_application' => false,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('dd.learning_layer.ran', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'candidates_created' => $candidatesCreated,
                'automatic_application' => false,
            ]);

            return $run;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function valuationAccuracySignal(
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        int $minOutcomes,
        float $varianceThreshold,
    ): ?array {
        $outcomes = DdOutcomeRecord::query()
            ->with(['engagement.valuations.businessValuation'])
            ->whereBetween('recorded_at', [$windowStart, $windowEnd])
            ->oldest('recorded_at')
            ->get()
            ->map(fn (DdOutcomeRecord $record): ?array => $this->valuationOutcome($record))
            ->filter()
            ->values();

        if ($outcomes->count() < $minOutcomes) {
            return null;
        }

        $averageAbsoluteVariance = round((float) $outcomes->avg('absolute_variance_rate'), 4);

        if ($averageAbsoluteVariance < $varianceThreshold) {
            return null;
        }

        $clientIds = $outcomes->pluck('client_id')->unique()->values()->all();
        $recordIds = $outcomes->pluck('outcome_record_id')->values()->all();

        return [
            'signal_key' => hash('sha256', 'dd_valuation_accuracy|'.implode('|', $recordIds)),
            'type' => 'valuation_accuracy',
            'record_count' => $outcomes->count(),
            'clients_affected' => count($clientIds),
            'client_ids' => $clientIds,
            'outcome_record_ids' => $recordIds,
            'dd_engagement_ids' => $outcomes->pluck('dd_engagement_id')->unique()->values()->all(),
            'dd_valuation_ids' => $outcomes->pluck('dd_valuation_id')->unique()->values()->all(),
            'average_absolute_variance_rate' => $averageAbsoluteVariance,
            'variance_threshold' => $varianceThreshold,
            'examples' => $outcomes->take(10)->values()->all(),
            'impact_scope' => ['surface' => 'dd_valuation', 'client_ids' => $clientIds],
            'magnitude' => $averageAbsoluteVariance >= ($varianceThreshold * 2) ? 'high' : 'medium',
            'confidence' => min(0.9, 0.55 + $averageAbsoluteVariance),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function valuationOutcome(DdOutcomeRecord $record): ?array
    {
        $valuation = $record->engagement?->valuations
            ->sortByDesc(fn (DdValuation $valuation): int => $valuation->as_at?->getTimestamp() ?? 0)
            ->first();
        $predictedMid = $valuation instanceof DdValuation
            ? (float) (data_get($valuation->normalised_values, 'reconciled.mid') ?: $valuation->businessValuation?->reconciled_mid)
            : 0.0;
        $actualPrice = (float) $record->recorded_price;

        if ($predictedMid <= 0.0 || $actualPrice <= 0.0 || ! $valuation instanceof DdValuation) {
            return null;
        }

        $variance = round(($actualPrice - $predictedMid) / $predictedMid, 4);

        return [
            'outcome_record_id' => $record->id,
            'client_id' => $record->client_id,
            'dd_engagement_id' => $record->dd_engagement_id,
            'dd_valuation_id' => $valuation->id,
            'predicted_mid' => round($predictedMid, 2),
            'recorded_price' => round($actualPrice, 2),
            'variance_rate' => $variance,
            'absolute_variance_rate' => abs($variance),
            'recorded_at' => $record->recorded_at?->toIso8601String(),
            'actual_outcome' => $record->actual_outcome,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function findingPatternSignals(
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        int $patternThreshold,
    ): Collection {
        return AnalysisFinding::query()
            ->with('run')
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->whereHas('run', fn ($query) => $query->where('module', AnalysisModule::DdWorkstream->value))
            ->oldest('created_at')
            ->get()
            ->groupBy(fn (AnalysisFinding $finding): string => $this->normalisedPattern($finding->title))
            ->filter(fn (Collection $findings, string $pattern): bool => $pattern !== '' && $findings->count() >= $patternThreshold)
            ->map(fn (Collection $findings, string $pattern): array => $this->findingPatternSignal($pattern, $findings, $patternThreshold))
            ->values();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return array<string, mixed>
     */
    private function findingPatternSignal(string $pattern, Collection $findings, int $patternThreshold): array
    {
        $clientIds = $findings->pluck('client_id')->unique()->values()->all();

        return [
            'signal_key' => hash('sha256', 'dd_finding_pattern|'.$pattern.'|'.implode('|', $findings->pluck('id')->sort()->values()->all())),
            'type' => 'finding_pattern',
            'pattern' => $pattern,
            'pattern_threshold' => $patternThreshold,
            'finding_count' => $findings->count(),
            'clients_affected' => count($clientIds),
            'client_ids' => $clientIds,
            'finding_ids' => $findings->pluck('id')->values()->all(),
            'run_ids' => $findings->pluck('analysis_run_id')->unique()->values()->all(),
            'severities' => $findings
                ->map(fn (AnalysisFinding $finding): string => $finding->severity->value)
                ->countBy()
                ->all(),
            'sample_titles' => $findings->pluck('title')->take(5)->values()->all(),
            'impact_scope' => ['surface' => 'dd_checklist', 'client_ids' => $clientIds],
            'magnitude' => $findings->count() >= ($patternThreshold * 2) ? 'medium' : 'low',
            'confidence' => min(0.85, 0.5 + ($findings->count() / 20)),
        ];
    }

    private function detectedCandidateExists(string $sourceType, string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', $sourceType)
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function createCandidate(string $sourceType, array $signal, string $summary, string $action): LearningUpdate
    {
        /** @var LearningUpdate $candidate */
        $candidate = LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => $sourceType,
                'signal_key' => $signal['signal_key'],
                'metric' => $signal['type'],
            ],
            'summary' => $summary,
            'proposed_change' => [
                'action' => $action,
                'automatic_application' => false,
                'requires_approval' => true,
            ],
            'impact_scope' => $signal['impact_scope'] ?? ['surface' => 'due_diligence'],
            'clients_affected' => (int) ($signal['clients_affected'] ?? 0),
            'magnitude' => (string) ($signal['magnitude'] ?? 'low'),
            'confidence' => (float) ($signal['confidence'] ?? 0.6),
            'evidence' => [
                ...$signal,
                'guardrail' => 'candidate_only_no_runtime_behavior_change',
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $candidate, after: [
            'layer_id' => self::LAYER_ID,
            'source_type' => $sourceType,
            'automatic_application' => false,
        ]);

        return $candidate;
    }

    private function normalisedPattern(string $title): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower($title)));
    }
}
