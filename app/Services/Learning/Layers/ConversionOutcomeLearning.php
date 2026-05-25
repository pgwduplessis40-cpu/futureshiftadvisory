<?php

declare(strict_types=1);

namespace App\Services\Learning\Layers;

use App\Models\ConversionOutcome;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Services\Audit\AuditWriter;
use App\Services\Privacy\CohortGuard;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ConversionOutcomeLearning
{
    public const LAYER_ID = 19;

    public function __construct(
        private readonly CohortGuard $cohortGuard,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(int $windowDays = 1095, ?CarbonInterface $windowEnd = null): LearningLayerRun
    {
        $windowDays = max(1, $windowDays);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);
        $period = $windowEnd->format('Y-m');

        $this->context->apply('system', []);

        return DB::transaction(function () use ($windowStart, $windowEnd, $windowDays, $period): LearningLayerRun {
            $observations = $this->observations($windowStart, $windowEnd);
            $aggregate = $this->aggregate($observations);
            $candidatesCreated = 0;

            if (($aggregate['suppressed'] ?? true) === false) {
                $signalKey = $this->signalKey($period);

                if (! $this->detectedCandidateExists($signalKey)) {
                    $this->createCandidate($signalKey, $period, $aggregate);
                    $candidatesCreated++;
                }
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
                    'minimum_cohort' => $this->cohortGuard->minCohort(),
                    'suppressed' => (bool) ($aggregate['suppressed'] ?? true),
                    'governed_candidates_only' => true,
                    'automatic_application' => false,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('entrepreneur.conversion_outcome_learning.ran', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'candidates_created' => $candidatesCreated,
                'minimum_cohort' => $this->cohortGuard->minCohort(),
                'automatic_application' => false,
            ]);

            return $run;
        });
    }

    /**
     * @param  Collection<int, array{plan_score:float,outcome_score:float}>  $observations
     * @return array<string, mixed>
     */
    public function aggregate(Collection $observations): array
    {
        $cohortSize = $observations->count();

        return $this->cohortGuard->releaseAggregate(
            cohortSize: $cohortSize,
            aggregate: [
                'average_plan_score' => round((float) $observations->avg('plan_score'), 2),
                'average_outcome_score' => round((float) $observations->avg('outcome_score'), 2),
                'correlation' => $this->correlation($observations->pluck('plan_score'), $observations->pluck('outcome_score')),
                'outcome_distribution' => $this->distribution($observations->pluck('outcome_score')),
            ],
            suppressedMessage: 'Not enough conversion outcomes for anonymous learning.',
            metadata: ['signal' => 'plan_quality_to_realised_outcome'],
        );
    }

    /**
     * @return Collection<int, array{plan_score:float,outcome_score:float}>
     */
    private function observations(CarbonInterface $windowStart, CarbonInterface $windowEnd): Collection
    {
        return ConversionOutcome::query()
            ->with('planAssessment.ratingFramework.criteria')
            ->whereBetween('observed_at', [$windowStart, $windowEnd])
            ->oldest('observed_at')
            ->get()
            ->map(function (ConversionOutcome $outcome): ?array {
                $assessment = $outcome->planAssessment;
                $outcomeScore = $this->outcomeScore($outcome->outcome_signal ?? []);

                if (! $assessment instanceof PlanAssessment || $outcomeScore === null) {
                    return null;
                }

                return [
                    'plan_score' => $this->planScore($assessment),
                    'outcome_score' => $outcomeScore,
                ];
            })
            ->filter()
            ->values();
    }

    private function planScore(PlanAssessment $assessment): float
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $weights = $assessment->ratingFramework->criteria->pluck('weight', 'number');
        $scores = collect($assessment->ai_scores ?? [])->keyBy(fn (array $row): int => (int) ($row['criterion_number'] ?? 0));
        $advisorScores = collect($assessment->advisor_scores ?? [])->keyBy(fn (array $row): int => (int) ($row['criterion_number'] ?? 0));

        return round($assessment->ratingFramework->criteria->sum(function ($criterion) use ($weights, $scores, $advisorScores): float {
            $advisor = $advisorScores->get($criterion->number);
            $ai = $scores->get($criterion->number, []);
            $score = is_array($advisor) && is_numeric($advisor['score'] ?? null)
                ? (float) $advisor['score']
                : (float) ($ai['score'] ?? 0);

            return $score * (((float) $weights->get($criterion->number, 0)) / 100);
        }), 2);
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function outcomeScore(array $signal): ?float
    {
        if (is_numeric($signal['success_score'] ?? null)) {
            return max(0.0, min(100.0, round((float) $signal['success_score'], 2)));
        }

        if (is_numeric($signal['revenue_growth_percent'] ?? null)) {
            return max(0.0, min(100.0, round(50 + ((float) $signal['revenue_growth_percent'] * 1.5), 2)));
        }

        $status = strtolower((string) ($signal['status'] ?? ''));

        return match ($status) {
            'successful', 'growth', 'converted' => 85.0,
            'stable' => 65.0,
            'stalled' => 35.0,
            'failed' => 15.0,
            default => null,
        };
    }

    /**
     * @param  Collection<int, float>  $x
     * @param  Collection<int, float>  $y
     */
    private function correlation(Collection $x, Collection $y): ?float
    {
        if ($x->count() < 2 || $x->count() !== $y->count()) {
            return null;
        }

        $meanX = (float) $x->avg();
        $meanY = (float) $y->avg();
        $numerator = 0.0;
        $sumX = 0.0;
        $sumY = 0.0;

        foreach ($x->values() as $index => $xValue) {
            $dx = ((float) $xValue) - $meanX;
            $dy = ((float) $y->values()->get($index)) - $meanY;
            $numerator += $dx * $dy;
            $sumX += $dx ** 2;
            $sumY += $dy ** 2;
        }

        if ($sumX <= 0.0 || $sumY <= 0.0) {
            return null;
        }

        return round($numerator / sqrt($sumX * $sumY), 4);
    }

    /**
     * @param  Collection<int, float>  $scores
     * @return array<string, int>
     */
    private function distribution(Collection $scores): array
    {
        return [
            'strong_outcome' => $scores->filter(fn (float $score): bool => $score >= 75)->count(),
            'moderate_outcome' => $scores->filter(fn (float $score): bool => $score >= 50 && $score < 75)->count(),
            'weak_outcome' => $scores->filter(fn (float $score): bool => $score < 50)->count(),
        ];
    }

    private function detectedCandidateExists(string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'conversion_outcome_learning')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    private function createCandidate(string $signalKey, string $period, array $aggregate): LearningUpdate
    {
        /** @var LearningUpdate $candidate */
        $candidate = LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'conversion_outcome_learning',
                'signal_key' => $signalKey,
                'period' => $period,
            ],
            'summary' => 'Conversion outcomes are ready for governed review against plan-quality signals.',
            'proposed_change' => [
                'action' => 'review_conversion_outcome_guidance_signal',
                'automatic_application' => false,
                'requires_approval' => true,
            ],
            'impact_scope' => [
                'surface' => 'entrepreneur_guidance',
                'aggregate_only' => true,
            ],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.68,
            'evidence' => [
                'aggregate' => $this->cohortGuard->sanitise($aggregate),
                'guardrail' => 'cohort_guard_aggregate_only_candidate_only',
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $candidate, after: [
            'layer_id' => self::LAYER_ID,
            'source_type' => 'conversion_outcome_learning',
            'automatic_application' => false,
        ]);

        return $candidate;
    }

    private function signalKey(string $period): string
    {
        return hash('sha256', implode('|', ['conversion_outcome_learning', $period]));
    }
}
