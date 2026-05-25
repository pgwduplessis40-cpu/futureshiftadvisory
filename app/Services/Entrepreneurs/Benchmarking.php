<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\PlanAssessment;
use App\Services\Privacy\CohortGuard;
use Illuminate\Support\Collection;

final class Benchmarking
{
    public function __construct(private readonly CohortGuard $cohortGuard) {}

    /**
     * @return array<string, mixed>
     */
    public function forPlan(BusinessPlan $plan): array
    {
        $minCohort = $this->cohortGuard->minCohort();
        $plan->loadMissing('entrepreneurProfile', 'assessments.ratingFramework.criteria');
        $currentAssessment = $this->latestAssessment($plan);

        if (! $currentAssessment instanceof PlanAssessment) {
            return $this->cohortGuard->suppress('No current assessment is available for benchmarking yet.');
        }

        $industry = $this->industry($plan);
        $cohortScores = $this->cohortScores($plan, $industry);

        if ($cohortScores->count() < $minCohort) {
            return $this->cohortGuard->suppress('Not enough comparable finalised plans yet.');
        }

        $currentScore = $this->score($currentAssessment);
        $belowOrEqual = $cohortScores->filter(fn (float $score): bool => $score <= $currentScore)->count();
        $percentile = round(($belowOrEqual / max(1, $cohortScores->count())) * 100, 2);

        return $this->cohortGuard->releaseAggregate(
            cohortSize: $cohortScores->count(),
            aggregate: [
                'cohort_average_score' => round($cohortScores->avg(), 2),
                'percentile_band' => $this->percentileBand($percentile),
                'distribution' => $this->distribution($cohortScores),
            ],
            metadata: ['industry' => $industry],
        );
    }

    /**
     * @return Collection<int, float>
     */
    private function cohortScores(BusinessPlan $plan, string $industry): Collection
    {
        return BusinessPlan::query()
            ->with(['entrepreneurProfile', 'assessments.ratingFramework.criteria'])
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->whereIn('status', [
                BusinessPlan::STATUS_FINALISED,
                BusinessPlan::STATUS_LAUNCHED,
                BusinessPlan::STATUS_FOUNDING,
            ])
            ->whereKeyNot($plan->getKey())
            ->get()
            ->filter(fn (BusinessPlan $candidate): bool => $this->industry($candidate) === $industry)
            ->map(fn (BusinessPlan $candidate): ?float => ($assessment = $this->latestAssessment($candidate)) instanceof PlanAssessment
                ? $this->score($assessment)
                : null)
            ->filter(fn (?float $score): bool => $score !== null)
            ->values();
    }

    private function latestAssessment(BusinessPlan $plan): ?PlanAssessment
    {
        return $plan->assessments
            ->sortByDesc('round')
            ->first();
    }

    private function score(PlanAssessment $assessment): float
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

    private function percentileBand(float $percentile): string
    {
        return match (true) {
            $percentile >= 75 => 'top_quartile',
            $percentile >= 50 => 'above_median',
            $percentile >= 25 => 'below_median',
            default => 'bottom_quartile',
        };
    }

    private function industry(BusinessPlan $plan): string
    {
        $industry = data_get($plan->founding_advisory_payload, 'industry')
            ?? data_get($plan->founding_advisory_payload, 'target_details.industry');

        if (is_string($industry) && trim($industry) !== '') {
            return strtolower(trim($industry));
        }

        $text = strtolower((string) $plan->entrepreneurProfile?->concept_summary);

        return str_contains($text, 'retail') ? 'retail' : 'general';
    }
}
