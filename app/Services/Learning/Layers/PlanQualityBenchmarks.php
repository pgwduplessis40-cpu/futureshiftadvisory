<?php

declare(strict_types=1);

namespace App\Services\Learning\Layers;

use App\Models\BusinessPlan;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\PlanAssessment;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\AssessmentScoring;
use App\Services\Privacy\CohortGuard;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PlanQualityBenchmarks
{
    public const LAYER_ID = 20;

    public function __construct(
        private readonly CohortGuard $cohortGuard,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function benchmarkForIndustry(string $industry): array
    {
        $industry = $this->normaliseIndustry($industry);
        $scores = $this->scoredPlans()
            ->filter(fn (array $plan): bool => $plan['industry'] === $industry)
            ->values();

        return $this->aggregateBenchmark($industry, $scores);
    }

    public function run(int $windowDays = 365, ?CarbonInterface $windowEnd = null): LearningLayerRun
    {
        $windowDays = max(1, $windowDays);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);
        $period = $windowEnd->format('o-\WW');

        $this->context->apply('system', []);

        return DB::transaction(function () use ($windowStart, $windowEnd, $windowDays, $period): LearningLayerRun {
            $candidatesCreated = 0;
            $benchmarks = $this->scoredPlans($windowStart, $windowEnd)
                ->groupBy('industry')
                ->map(fn (Collection $scores, string $industry): array => $this->aggregateBenchmark($industry, $scores))
                ->filter(fn (array $benchmark): bool => ($benchmark['suppressed'] ?? true) === false)
                ->values();

            foreach ($benchmarks as $benchmark) {
                $industry = (string) $benchmark['industry'];
                $signalKey = $this->signalKey($industry, $period);

                if ($this->detectedCandidateExists($signalKey)) {
                    continue;
                }

                $this->createCandidate($signalKey, $period, $benchmark);
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
                    'minimum_cohort' => $this->cohortGuard->minCohort(),
                    'governed_candidates_only' => true,
                    'automatic_application' => false,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('entrepreneur.plan_quality_benchmarks.ran', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'candidates_created' => $candidatesCreated,
                'minimum_cohort' => $this->cohortGuard->minCohort(),
                'automatic_application' => false,
            ]);

            return $run;
        });
    }

    /**
     * @return Collection<int, array{industry:string,score:float,grade:string}>
     */
    private function scoredPlans(?CarbonInterface $windowStart = null, ?CarbonInterface $windowEnd = null): Collection
    {
        return BusinessPlan::query()
            ->with(['entrepreneurProfile', 'assessments.ratingFramework.criteria'])
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->whereIn('status', [
                BusinessPlan::STATUS_FINALISED,
                BusinessPlan::STATUS_LAUNCHED,
                BusinessPlan::STATUS_FOUNDING,
            ])
            ->when($windowStart instanceof CarbonInterface && $windowEnd instanceof CarbonInterface, function ($query) use ($windowStart, $windowEnd): void {
                $query->where(function ($query) use ($windowStart, $windowEnd): void {
                    $query->whereBetween('completed_at', [$windowStart, $windowEnd])
                        ->orWhereNull('completed_at');
                });
            })
            ->get()
            ->map(function (BusinessPlan $plan): ?array {
                $assessment = $this->latestAssessment($plan);

                if (! $assessment instanceof PlanAssessment) {
                    return null;
                }

                return [
                    'industry' => $this->industry($plan),
                    'score' => $this->score($assessment),
                    'grade' => $assessment->overall_grade,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array{industry:string,score:float,grade:string}>  $scores
     * @return array<string, mixed>
     */
    private function aggregateBenchmark(string $industry, Collection $scores): array
    {
        $distribution = $this->distribution($scores->pluck('score'));

        return $this->cohortGuard->releaseAggregate(
            cohortSize: $scores->count(),
            aggregate: [
                'average_score' => round((float) $scores->avg('score'), 2),
                'distribution' => $distribution,
                'grade_distribution' => $scores->pluck('grade')->countBy()->all(),
            ],
            suppressedMessage: 'Not enough comparable plans for an anonymous industry benchmark.',
            metadata: ['industry' => $industry],
        );
    }

    private function latestAssessment(BusinessPlan $plan): ?PlanAssessment
    {
        return $plan->assessments
            ->sortByDesc('round')
            ->first();
    }

    private function score(PlanAssessment $assessment): float
    {
        return AssessmentScoring::weightedScore($assessment);
    }

    /**
     * @param  Collection<int, float>  $scores
     * @return array<string, int>
     */
    private function distribution(Collection $scores): array
    {
        return [
            'exceptional' => $scores->filter(fn (float $score): bool => $score >= 90)->count(),
            'strong' => $scores->filter(fn (float $score): bool => $score >= 75 && $score < 90)->count(),
            'developing' => $scores->filter(fn (float $score): bool => $score >= 60 && $score < 75)->count(),
            'needs_work' => $scores->filter(fn (float $score): bool => $score < 60)->count(),
        ];
    }

    private function detectedCandidateExists(string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'plan_quality_benchmarks')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $benchmark
     */
    private function createCandidate(string $signalKey, string $period, array $benchmark): LearningUpdate
    {
        /** @var LearningUpdate $candidate */
        $candidate = LearningUpdate::query()->create([
            'layer_id' => self::LAYER_ID,
            'source' => [
                'type' => 'plan_quality_benchmarks',
                'signal_key' => $signalKey,
                'industry' => $benchmark['industry'],
                'period' => $period,
            ],
            'summary' => sprintf('Plan quality benchmark available for the %s industry cohort.', (string) $benchmark['industry']),
            'proposed_change' => [
                'action' => 'review_entrepreneur_guidance_against_plan_quality_benchmark',
                'automatic_application' => false,
                'requires_approval' => true,
            ],
            'impact_scope' => [
                'surface' => 'entrepreneur_plan_quality',
                'industry' => $benchmark['industry'],
                'aggregate_only' => true,
            ],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.7,
            'evidence' => [
                'benchmark' => $this->cohortGuard->sanitise($benchmark),
                'guardrail' => 'cohort_guard_aggregate_only_candidate_only',
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('learning_update.detected', subject: $candidate, after: [
            'layer_id' => self::LAYER_ID,
            'source_type' => 'plan_quality_benchmarks',
            'industry' => $benchmark['industry'],
            'automatic_application' => false,
        ]);

        return $candidate;
    }

    private function signalKey(string $industry, string $period): string
    {
        return hash('sha256', implode('|', ['plan_quality_benchmarks', $industry, $period]));
    }

    private function industry(BusinessPlan $plan): string
    {
        $industry = data_get($plan->founding_advisory_payload, 'industry')
            ?? data_get($plan->founding_advisory_payload, 'target_details.industry');

        if (is_string($industry) && trim($industry) !== '') {
            return $this->normaliseIndustry($industry);
        }

        $text = strtolower((string) $plan->entrepreneurProfile?->concept_summary);

        return str_contains($text, 'retail') ? 'retail' : 'general';
    }

    private function normaliseIndustry(string $industry): string
    {
        $industry = strtolower(trim($industry));

        return $industry === '' ? 'general' : $industry;
    }
}
