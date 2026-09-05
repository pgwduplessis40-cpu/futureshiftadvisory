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
            ->map(function ($criterion) use ($assessment, $framework, $aiScores, $advisorScores, $totalWeight): array {
                $advisor = $advisorScores->get((int) $criterion->number);
                $ai = $aiScores->get((int) $criterion->number);
                $hasAdvisorScore = is_array($advisor) && is_numeric($advisor['score'] ?? null);
                $aiScore = is_array($ai) && is_numeric($ai['score'] ?? null)
                    ? (float) $ai['score']
                    : null;
                $hasScore = $hasAdvisorScore || $aiScore !== null;
                $score = $hasAdvisorScore
                    ? (float) $advisor['score']
                    : $aiScore;
                $scoreBand = $hasAdvisorScore || ! is_array($ai)
                    ? null
                    : self::normaliseBand(data_get($ai, 'metadata.score_band'));
                $scoreScale = is_array($ai) && is_array(data_get($ai, 'metadata.score_scale'))
                    ? data_get($ai, 'metadata.score_scale')
                    : null;
                $weight = (float) $criterion->weight;
                $normalisedWeight = $totalWeight > 0 ? $weight / $totalWeight : 0;
                $scoreSource = is_array($ai)
                    ? (string) ($ai['score_source'] ?? data_get($ai, 'metadata.score_source'))
                    : '';
                $legacyReused = $scoreSource === 'reused_identical_context';
                $reusedUnchangedEvidence = $scoreSource === 'reused_unchanged_evidence';
                $fallback = $scoreSource === 'deterministic_fallback';
                $source = 'automated_assessment';
                $sourceLabel = sprintf('Round %d automated score', max(1, (int) $assessment->round));

                if ($hasAdvisorScore) {
                    $source = 'advisor_review';
                    $sourceLabel = 'Advisor reviewed';
                } elseif (! $hasScore) {
                    $source = 'incomplete';
                    $sourceLabel = 'No valid score is recorded for this criterion; this assessment is incomplete and unavailable for advice or progression';
                } elseif ($fallback) {
                    $source = 'invalid_fallback';
                    $sourceLabel = 'No valid AI score was returned; this historical fallback is unavailable for advice or progression';
                } elseif ($legacyReused) {
                    $source = 'reused_assessment';
                    $sourceLabel = sprintf(
                        'Round %d carried forward from round %d; no fresh AI score was generated',
                        max(1, (int) $assessment->round),
                        max(1, (int) data_get($ai, 'metadata.reused_from_round', $assessment->round)),
                    );
                } elseif ($reusedUnchangedEvidence) {
                    $source = 'reused_assessment';
                    $sourceLabel = sprintf(
                        'Round %d retained from unchanged criterion evidence in round %d',
                        max(1, (int) $assessment->round),
                        max(1, (int) data_get($ai, 'metadata.reused_from_round', $assessment->round)),
                    );
                }

                return [
                    'criterion_id' => (string) $criterion->getKey(),
                    'criterion_number' => $criterion->number,
                    'criterion_name' => $criterion->name,
                    'number' => $criterion->number,
                    'name' => $criterion->name,
                    'weight' => $weight,
                    'normalised_weight' => round($normalisedWeight * 100, 3),
                    'score' => $score,
                    'is_complete' => $hasScore,
                    'ai_score' => $aiScore,
                    'advisor_score' => $hasAdvisorScore ? (float) $advisor['score'] : null,
                    'grade' => $hasScore ? ($scoreBand ?? $framework->gradeFor((float) $score)) : null,
                    'score_band' => $scoreBand,
                    'score_scale' => $scoreScale,
                    'scoring_method' => is_array($ai)
                        ? data_get($ai, 'metadata.scoring_method')
                        : ($hasAdvisorScore ? 'advisor_review' : null),
                    'contribution' => $hasScore ? round(((float) $score) * $normalisedWeight, 2) : null,
                    'source' => $source,
                    'source_label' => $sourceLabel,
                    'rationale' => $hasAdvisorScore
                        ? (string) ($advisor['note'] ?? '')
                        : (string) (is_array($ai) ? ($ai['rationale'] ?? '') : ''),
                    'attributions' => is_array($ai) && is_array($ai['attributions'] ?? null) ? $ai['attributions'] : [],
                    'context_hash' => is_array($ai) ? data_get($ai, 'metadata.context_hash') : null,
                    'evidence_mode' => is_array($ai) ? data_get($ai, 'metadata.evidence_mode') : null,
                    'evidence_section_count' => is_array($ai) ? data_get($ai, 'metadata.evidence_section_count') : null,
                    'budget_evidence_included' => is_array($ai)
                        ? (bool) data_get($ai, 'metadata.budget_evidence_included', false)
                        : false,
                    'source_sections' => is_array($ai) && is_array(data_get($ai, 'metadata.source_sections'))
                        ? array_values(data_get($ai, 'metadata.source_sections'))
                        : [],
                ];
            })
            ->values()
            ->all();
    }

    public static function weightedScore(PlanAssessment $assessment): float
    {
        return round(collect(self::criteriaPayload($assessment))->sum('contribution'), 2);
    }

    public static function hasFallbackScores(PlanAssessment $assessment): bool
    {
        return collect($assessment->ai_scores ?? [])
            ->contains(fn (mixed $score): bool => is_array($score)
                && (string) ($score['score_source'] ?? data_get($score, 'metadata.score_source')) === 'deterministic_fallback');
    }

    public static function hasIncompleteScores(PlanAssessment $assessment): bool
    {
        return self::incompleteCriterionNumbers($assessment) !== [];
    }

    /**
     * @return list<int>
     */
    public static function incompleteCriterionNumbers(PlanAssessment $assessment): array
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $framework = $assessment->ratingFramework;

        if (! $framework instanceof RatingFramework) {
            return [];
        }

        $aiScores = self::scoresByCriterion($assessment->ai_scores ?? []);
        $advisorScores = self::scoresByCriterion($assessment->advisor_scores ?? []);

        return $framework->criteria
            ->filter(function ($criterion) use ($aiScores, $advisorScores): bool {
                $criterionNumber = (int) $criterion->number;
                $ai = $aiScores->get($criterionNumber);
                $advisor = $advisorScores->get($criterionNumber);

                return ! (is_array($ai) && is_numeric($ai['score'] ?? null))
                    && ! (is_array($advisor) && is_numeric($advisor['score'] ?? null));
            })
            ->pluck('number')
            ->map(fn (mixed $number): int => (int) $number)
            ->values()
            ->all();
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

    private static function normaliseBand(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $band = strtolower(trim(str_replace([' ', '-'], '_', $value)));

        return array_key_exists($band, RatingFramework::DEFAULT_CRITERION_BAND_SCORES)
            ? $band
            : null;
    }
}
