<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\CoachingSignal;
use App\Models\EntrepreneurProfile;
use App\Models\ReadinessAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

final class Readiness
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $responses
     */
    public function assess(EntrepreneurProfile $profile, array $responses, User $actor): ReadinessAssessment
    {
        return DB::transaction(function () use ($profile, $responses, $actor): ReadinessAssessment {
            $score = $this->score($responses);
            $personalBarriers = $this->personalBarriers($responses);
            $outcome = $this->outcome($score, $personalBarriers);

            $assessment = ReadinessAssessment::query()->create([
                'entrepreneur_profile_id' => $profile->getKey(),
                'responses' => $responses,
                'score' => $score,
                'outcome' => $outcome,
                'personal_barriers' => $personalBarriers,
                'assessed_by_user_id' => $actor->getKey(),
                'assessed_at' => now(),
            ]);

            $profile->forceFill([
                'stage' => $outcome === ReadinessAssessment::OUTCOME_READY
                    ? EntrepreneurStage::IDEA_VALIDATION
                    : EntrepreneurStage::READINESS,
            ])->save();

            if ($outcome === ReadinessAssessment::OUTCOME_DEVELOP_FIRST && $personalBarriers !== []) {
                $this->recordCoachingSignal($profile, $actor, $assessment, $personalBarriers);
            }

            $this->audit->record('entrepreneur.readiness_assessed', subject: $assessment, actor: $actor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'score' => $score,
                'outcome' => $outcome,
                'personal_barrier_count' => count($personalBarriers),
            ]);

            return $assessment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    private function score(array $responses): float
    {
        $answers = collect($responses)
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => max(0.0, min(5.0, (float) $value)));

        if ($answers->isEmpty()) {
            return 0.0;
        }

        return round(($answers->avg() / 5.0) * 100.0, 2);
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return array<int, string>
     */
    private function personalBarriers(array $responses): array
    {
        $barriers = $responses['personal_barriers'] ?? $responses['barriers'] ?? [];

        if (is_string($barriers)) {
            $barriers = preg_split('/[\r\n,;]+/', $barriers) ?: [];
        }

        if (! is_array($barriers)) {
            return [];
        }

        return collect($barriers)
            ->map(fn (mixed $barrier): string => trim((string) $barrier))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $personalBarriers
     */
    private function outcome(float $score, array $personalBarriers): string
    {
        if ($score >= 78.0 && $personalBarriers === []) {
            return ReadinessAssessment::OUTCOME_READY;
        }

        if ($score >= 45.0) {
            return ReadinessAssessment::OUTCOME_DEVELOP_FIRST;
        }

        return ReadinessAssessment::OUTCOME_NOT_YET;
    }

    /**
     * @param  array<int, string>  $personalBarriers
     */
    private function recordCoachingSignal(
        EntrepreneurProfile $profile,
        User $actor,
        ReadinessAssessment $assessment,
        array $personalBarriers,
    ): void {
        CoachingSignal::query()->create([
            'client_id' => null,
            'entrepreneur_profile_id' => $profile->getKey(),
            'user_id' => $profile->user_id,
            'trigger_checkin_id' => null,
            'signal_type' => CoachingSignal::TYPE_ENTREPRENEUR_PERSONAL_BARRIER,
            'severity' => 'advisor_attention',
            'status' => 'detected',
            'evidence' => [
                'source' => 'entrepreneur_readiness',
                'readiness_assessment_id' => $assessment->getKey(),
                'barriers' => $personalBarriers,
                'raw_observation_only' => true,
                'auto_referral' => false,
                'recorded_by_user_id' => $actor->getKey(),
            ],
            'generated_at' => now(),
        ]);
    }
}
