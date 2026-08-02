<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use App\Models\PlanAssessment;
use Illuminate\Support\Str;

final class AssessmentFeedback
{
    public function __construct(private readonly FounderChangeRequestMessage $changeRequestMessages) {}

    public function draft(PlanAssessment $assessment): string
    {
        $score = AssessmentScoring::weightedScore($assessment);
        $threshold = AdvisoryReadiness::THRESHOLD;
        $priorities = collect(AssessmentScoring::criteriaPayload($assessment))
            ->sortBy('score')
            ->take(3)
            ->map(function (array $criterion): string {
                $rationale = Str::squish((string) ($criterion['rationale'] ?? ''));
                $detail = $rationale === ''
                    ? 'Add clearer evidence and practical detail to this part of the plan.'
                    : Str::limit($rationale, 280);

                return sprintf(
                    '- %s (%.0f/100): %s',
                    (string) ($criterion['name'] ?? 'Plan requirement'),
                    (float) ($criterion['score'] ?? 0),
                    $detail,
                );
            })
            ->all();

        $readiness = $score >= $threshold ? 'meets' : 'is below';
        $intro = sprintf(
            'I have completed the assessment. The current score is %.1f/100, which %s the %.0f/100 advisory-readiness threshold.',
            $score,
            $readiness,
            $threshold,
        );

        if ($priorities === []) {
            return $intro;
        }

        return implode("\n\n", [
            $intro,
            'The most useful priorities for the next revision are:',
            implode("\n", $priorities),
            'Please strengthen these areas with specific evidence and updated assumptions before the next assessment.',
        ]);
    }

    public function proposedReply(EntrepreneurProfile $profile, string $feedback): string
    {
        return $this->changeRequestMessages->fromAssessmentFeedback($profile, $feedback);
    }
}
