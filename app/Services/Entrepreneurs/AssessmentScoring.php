<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\PlanAssessment;
use App\Models\RatingFramework;

final class AssessmentScoring
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function criteriaPayload(PlanAssessment $assessment): array
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $framework = $assessment->ratingFramework;

        if (! $framework instanceof RatingFramework) {
            return [];
        }

        $aiScores = self::scoresByCriterion($assessment->ai_scores ?? []);
        $advisorScores = self::scoresByCriterion($assessment->advisor_scores ?? []);
        $totalWeight = self::totalWeight($framework);

        return $framework->criteria
            ->map(function ($criterion) use ($framework, $aiScores, $advisorScores, $totalWeight): array {
                $advisor = $advisorScores->get((int) $criterion->number);
                $ai = $aiScores->get((int) $criterion->number);
                $hasAdvisorScore = is_array($advisor) && is_numeric($advisor['score'] ?? null);
                $aiScore = is_array($ai) && is_numeric($ai['score'] ?? null)
                    ? (float) $ai['score']
                    : null;
                $score = $hasAdvisorScore
                    ? (float) $advisor['score']
                    : (float) ($aiScore ?? 0);
                $weight = (float) $criterion->weight;
                $normalisedWeight = $totalWeight > 0 ? $weight / $totalWeight : 0;

                return [
                    'criterion_id' => (string) $criterion->getKey(),
                    'criterion_number' => $criterion->number,
                    'criterion_name' => $criterion->name,
                    'number' => $criterion->number,
                    'name' => $criterion->name,
                    'weight' => $weight,
                    'normalised_weight' => round($normalisedWeight * 100, 3),
                    'score' => $score,
                    'ai_score' => $aiScore,
                    'advisor_score' => $hasAdvisorScore ? (float) $advisor['score'] : null,
                    'grade' => $framework->gradeFor($score),
                    'contribution' => round($score * $normalisedWeight, 2),
                    'source' => $hasAdvisorScore ? 'advisor_review' : 'first_pass',
                    'source_label' => $hasAdvisorScore ? 'Advisor reviewed' : 'First pass',
                    'rationale' => $hasAdvisorScore
                        ? (string) ($advisor['note'] ?? '')
                        : (string) (is_array($ai) ? ($ai['rationale'] ?? '') : ''),
                    'attributions' => is_array($ai) && is_array($ai['attributions'] ?? null) ? $ai['attributions'] : [],
                ];
            })
            ->values()
            ->all();
    }

    public static function weightedScore(PlanAssessment $assessment): float
    {
        return round(collect(self::criteriaPayload($assessment))->sum('contribution'), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $aiScores
     * @param  array<string, array<string, mixed>>|array<int, array<string, mixed>>  $advisorScores
     */
    public static function weightedScoreForFramework(RatingFramework $framework, array $aiScores, array $advisorScores = []): float
    {
        $totalWeight = self::totalWeight($framework);
        $ai = self::scoresByCriterion($aiScores);
        $advisor = self::scoresByCriterion($advisorScores);

        return round($framework->criteria->sum(function ($criterion) use ($totalWeight, $ai, $advisor): float {
            if ($totalWeight <= 0) {
                return 0.0;
            }

            $advisorScore = $advisor->get((int) $criterion->number);
            $aiScore = $ai->get((int) $criterion->number);
            $score = is_array($advisorScore) && is_numeric($advisorScore['score'] ?? null)
                ? (float) $advisorScore['score']
                : (float) (is_array($aiScore) && is_numeric($aiScore['score'] ?? null) ? $aiScore['score'] : 0);

            return $score * (((float) $criterion->weight) / $totalWeight);
        }), 2);
    }

    private static function totalWeight(RatingFramework $framework): float
    {
        $framework->loadMissing('criteria');

        return (float) $framework->criteria->sum('weight');
    }

    /**
     * @param  array<mixed>  $scores
     */
    private static function scoresByCriterion(array $scores)
    {
        $isList = array_is_list($scores);

        return collect($scores)
            ->mapWithKeys(function (mixed $row, int|string $key) use ($isList): array {
                if (is_array($row)) {
                    $criterionNumber = (int) ($row['criterion_number'] ?? self::criterionNumberFromKey($key, $isList));

                    if ($criterionNumber <= 0 || ! is_numeric($row['score'] ?? null)) {
                        return [];
                    }

                    return [$criterionNumber => $row];
                }

                if (! is_numeric($row)) {
                    return [];
                }

                $criterionNumber = self::criterionNumberFromKey($key, $isList);

                return $criterionNumber > 0
                    ? [$criterionNumber => ['criterion_number' => $criterionNumber, 'score' => (float) $row]]
                    : [];
            });
    }

    private static function criterionNumberFromKey(int|string $key, bool $isList): int
    {
        if ($isList && is_int($key)) {
            return $key + 1;
        }

        return is_numeric($key) ? (int) $key : 0;
    }
}
