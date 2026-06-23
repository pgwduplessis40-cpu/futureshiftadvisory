<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\PlanAssessment;
use App\Models\User;
use App\Notifications\AdvisoryReadinessNotification;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class AdvisoryReadiness implements ProvidesMethodology
{
    public const THRESHOLD = 75.0;

    public static function methodologyIds(): array
    {
        return ['entrepreneur.advisory_readiness'];
    }

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly EntrepreneurMilestones $milestones,
    ) {}

    public function evaluate(BusinessPlan $plan, ?User $actor = null): ?AdvisoryReadinessSignal
    {
        $plan->loadMissing('entrepreneurProfile.assignedAdvisor', 'assessments.ratingFramework.criteria');
        $profile = $plan->entrepreneurProfile;
        $assessment = $plan->assessments->sortByDesc('round')->first();

        if (! $profile || ! $assessment instanceof PlanAssessment) {
            return null;
        }

        $score = $this->score($assessment);
        if ($score < self::THRESHOLD) {
            return null;
        }

        return DB::transaction(function () use ($plan, $profile, $assessment, $score, $actor): AdvisoryReadinessSignal {
            $signal = AdvisoryReadinessSignal::query()->updateOrCreate(
                ['entrepreneur_profile_id' => $profile->getKey()],
                [
                    'business_plan_id' => $plan->getKey(),
                    'plan_assessment_id' => $assessment->getKey(),
                    'score' => $score,
                    'surfaced_at' => now(),
                ],
            );

            $profile->forceFill([
                'stage' => EntrepreneurStage::ADVISORY_READY,
            ])->save();

            if ($profile->assignedAdvisor instanceof User) {
                Notification::send($profile->assignedAdvisor, new AdvisoryReadinessNotification($signal));
                $signal->forceFill(['advisor_notified_at' => now()])->save();
            }

            $this->audit->record('entrepreneur.advisory_readiness_signal', subject: $signal, actor: $actor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'business_plan_id' => $plan->getKey(),
                'score' => $score,
                'advisor_notified_at' => $signal->advisor_notified_at?->toIso8601String(),
            ]);
            $this->milestones->awardAdvisoryReady($signal->refresh()->load('entrepreneurProfile', 'planAssessment.ratingFramework.criteria'));

            return $signal->refresh();
        });
    }

    private function score(PlanAssessment $assessment): float
    {
        $assessment->loadMissing('ratingFramework.criteria');
        $weights = $assessment->ratingFramework->criteria->pluck('weight', 'number');
        $aiScores = collect($assessment->ai_scores ?? [])->keyBy(fn (array $row): int => (int) ($row['criterion_number'] ?? 0));
        $advisorScores = collect($assessment->advisor_scores ?? [])->keyBy(fn (array $row): int => (int) ($row['criterion_number'] ?? 0));

        return round($assessment->ratingFramework->criteria->sum(function ($criterion) use ($weights, $aiScores, $advisorScores): float {
            $advisor = $advisorScores->get($criterion->number);
            $ai = $aiScores->get($criterion->number, []);
            $score = is_array($advisor) && is_numeric($advisor['score'] ?? null)
                ? (float) $advisor['score']
                : (float) ($ai['score'] ?? 0);

            return $score * (((float) $weights->get($criterion->number, 0)) / 100);
        }), 2);
    }
}
