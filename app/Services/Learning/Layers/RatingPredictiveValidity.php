<?php

declare(strict_types=1);

namespace App\Services\Learning\Layers;

use App\Models\ConversionOutcome;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Models\RatingValidityTest;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AssessmentScoring;
use App\Services\Privacy\CohortGuard;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RatingPredictiveValidity
{
    public const LAYER_ID = 18;

    public function __construct(
        private readonly CohortGuard $cohortGuard,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function run(int $windowDays = 1095, ?CarbonInterface $testedAt = null, ?string $period = null): LearningLayerRun
    {
        $windowDays = max(1, $windowDays);
        $testedAt ??= now()->addMinute();
        $windowStart = $testedAt->copy()->subDays($windowDays);
        $period ??= $this->period($testedAt);

        $this->context->apply('system', []);

        return DB::transaction(function () use ($windowStart, $testedAt, $windowDays, $period): LearningLayerRun {
            $candidatesCreated = 0;
            $groups = $this->observations($windowStart, $testedAt)->groupBy('rating_framework_id');
            $testsRecorded = 0;

            foreach ($groups as $frameworkId => $observations) {
                $aggregate = $this->aggregate($observations);
                $test = RatingValidityTest::query()->updateOrCreate(
                    [
                        'rating_framework_id' => (string) $frameworkId,
                        'period' => $period,
                    ],
                    [
                        'correlation' => $aggregate,
                        'tested_at' => $testedAt,
                    ],
                );
                $testsRecorded++;

                if (($aggregate['suppressed'] ?? true) === true) {
                    continue;
                }

                $signalKey = $this->signalKey((string) $frameworkId, $period);
                if ($this->detectedCandidateExists($signalKey)) {
                    continue;
                }

                $this->createCandidate($signalKey, $period, (string) $frameworkId, $test, $aggregate);
                $candidatesCreated++;
            }

            /** @var LearningLayerRun $run */
            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $candidatesCreated,
                'window' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $testedAt->toIso8601String(),
                    'window_days' => $windowDays,
                    'period' => $period,
                    'tests_recorded' => $testsRecorded,
                    'minimum_cohort' => $this->cohortGuard->minCohort(),
                    'governed_candidates_only' => true,
                    'automatic_application' => false,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('entrepreneur.rating_predictive_validity.ran', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'period' => $period,
                'tests_recorded' => $testsRecorded,
                'candidates_created' => $candidatesCreated,
                'automatic_application' => false,
            ]);

            return $run;
        });
    }

    /**
     * @param  Collection<int, array{rating_framework_id:string,plan_score:float,outcome_score:float}>  $observations
     * @return array<string, mixed>
     */
    public function aggregate(Collection $observations): array
    {
        return $this->cohortGuard->releaseAggregate(
            cohortSize: $observations->count(),
            aggregate: [
                'plan_to_outcome_correlation' => $this->correlation(
                    $observations->pluck('plan_score'),
                    $observations->pluck('outcome_score'),
                ),
                'average_plan_score' => round((float) $observations->avg('plan_score'), 2),
                'average_outcome_score' => round((float) $observations->avg('outcome_score'), 2),
                'outcome_distribution' => $this->distribution($observations->pluck('outcome_score')),
            ],
            suppressedMessage: 'Not enough conversion outcomes for rating validity testing.',
            metadata: ['signal' => 'rating_predictive_validity'],
        );
    }

    /**
     * @return Collection<int, array{rating_framework_id:string,plan_score:float,outcome_score:float}>
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
                    'rating_framework_id' => (string) $assessment->rating_framework_id,
                    'plan_score' => $this->planScore($assessment),
                    'outcome_score' => $outcomeScore,
                ];
            })
            ->filter()
            ->values();
    }

    private function planScore(PlanAssessment $assessment): float
    {
        return AssessmentScoring::weightedScore($assessment);
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

        return null;
    }

    /**
     * @param  Collection<int, float>  $x
     * @param  Collection<int, float>  $y
     */
    private function correlation(Collection $x, Collection $y): ?float
    {
        $xValues = $x->values();
        $yValues = $y->values();

        if ($xValues->count() < 2 || $xValues->count() !== $yValues->count()) {
            return null;
        }

        $meanX = (float) $xValues->avg();
        $meanY = (float) $yValues->avg();
        $numerator = 0.0;
        $sumX = 0.0;
        $sumY = 0.0;

        foreach ($xValues as $index => $xValue) {
            $dx = ((float) $xValue) - $meanX;
            $dy = ((float) $yValues->get($index)) - $meanY;
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
            ->where('source->type', 'rating_predictive_validity')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $aggregate
     */
    private function createCandidate(
        string $signalKey,
        string $period,
        string $frameworkId,
        RatingValidityTest $test,
        array $aggregate,
    ): LearningUpdate {
        /** @var LearningUpdate $candidate */
        $candidate = LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'rating_predictive_validity',
                'signal_key' => $signalKey,
                'rating_framework_id' => $frameworkId,
                'period' => $period,
            ],
            'summary' => 'Rating framework predictive validity results are ready for governed review.',
            'proposed_change' => [
                'action' => 'review_rating_framework_predictive_validity',
                'rating_framework_id' => $frameworkId,
                'automatic_application' => false,
                'requires_approval' => true,
            ],
            'impact_scope' => [
                'surface' => 'entrepreneur_rating_framework',
                'rating_framework_id' => $frameworkId,
                'aggregate_only' => true,
            ],
            'clients_affected' => 0,
            'magnitude' => 'medium',
            'confidence' => 0.72,
            'evidence' => [
                'rating_validity_test_id' => $test->id,
                'correlation' => $this->cohortGuard->sanitise($aggregate),
                'guardrail' => 'candidate_only_no_rating_framework_change',
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $candidate, after: [
            'layer_id' => self::LAYER_ID,
            'source_type' => 'rating_predictive_validity',
            'period' => $period,
            'automatic_application' => false,
        ]);

        return $candidate;
    }

    private function signalKey(string $frameworkId, string $period): string
    {
        return hash('sha256', implode('|', ['rating_predictive_validity', $frameworkId, $period]));
    }

    private function period(CarbonInterface $testedAt): string
    {
        return sprintf('%s-H%s', $testedAt->format('Y'), (int) $testedAt->format('n') <= 6 ? 1 : 2);
    }
}
