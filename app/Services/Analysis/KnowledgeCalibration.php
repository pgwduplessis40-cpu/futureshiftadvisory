<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Client;
use App\Models\CoachingSignal;
use App\Models\KnowledgeAssessment;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;

final class KnowledgeCalibration
{
    public const LEADERSHIP_GAP_THRESHOLD = 2;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forClient(Client $client): array
    {
        $assessment = KnowledgeAssessment::query()
            ->where('client_id', $client->getKey())
            ->latest('assessed_at')
            ->latest('created_at')
            ->first();

        if (! $assessment instanceof KnowledgeAssessment) {
            return $this->defaultCalibration();
        }

        return [
            ...$assessment->calibration,
            'assessment_id' => $assessment->id,
            'assessed_at' => $assessment->assessed_at?->toIso8601String(),
        ];
    }

    public function assess(
        Client $client,
        User $advisor,
        int $financialLiteracy,
        int $strategicAwareness,
        int $leadership,
    ): KnowledgeAssessment {
        $financialLiteracy = $this->score($financialLiteracy);
        $strategicAwareness = $this->score($strategicAwareness);
        $leadership = $this->score($leadership);
        $calibration = $this->calibrationFor($financialLiteracy, $strategicAwareness, $leadership);

        $assessment = KnowledgeAssessment::query()->create([
            'client_id' => $client->getKey(),
            'financial_literacy' => $financialLiteracy,
            'strategic_awareness' => $strategicAwareness,
            'leadership' => $leadership,
            'calibration' => $calibration,
            'assessed_at' => now(),
            'assessed_by_user_id' => $advisor->getKey(),
        ]);

        $this->audit->record(
            action: 'knowledge_assessment.recorded',
            subject: $assessment,
            actor: $advisor,
            after: [
                'client_id' => $client->id,
                'financial_literacy' => $financialLiteracy,
                'strategic_awareness' => $strategicAwareness,
                'leadership' => $leadership,
                'calibration' => $calibration,
            ],
        );

        if ($leadership <= self::LEADERSHIP_GAP_THRESHOLD) {
            $this->recordLeadershipGap($assessment, $advisor);
        }

        return $assessment;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultCalibration(): array
    {
        return [
            'source' => 'default',
            'language_depth' => 'standard',
            'financial_detail' => 'balanced',
            'strategic_framing' => 'balanced',
            'leadership_context' => 'standard',
            'advisor_review_note' => 'No client knowledge assessment has been recorded yet.',
            'scores' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calibrationFor(int $financialLiteracy, int $strategicAwareness, int $leadership): array
    {
        return [
            'source' => 'knowledge_assessment',
            'language_depth' => $financialLiteracy <= 2 ? 'plain_language' : ($financialLiteracy >= 4 ? 'technical' : 'standard'),
            'financial_detail' => $financialLiteracy <= 2 ? 'explain_terms' : ($financialLiteracy >= 4 ? 'advanced_metrics' : 'balanced'),
            'strategic_framing' => $strategicAwareness <= 2 ? 'step_by_step' : ($strategicAwareness >= 4 ? 'strategic_options' : 'balanced'),
            'leadership_context' => $leadership <= self::LEADERSHIP_GAP_THRESHOLD ? 'support_owner_dependency' : ($leadership >= 4 ? 'delegate_to_leadership_team' : 'standard'),
            'advisor_review_note' => 'Adapt analysis language and recommended next steps to the recorded client knowledge profile.',
            'scores' => [
                'financial_literacy' => $financialLiteracy,
                'strategic_awareness' => $strategicAwareness,
                'leadership' => $leadership,
            ],
        ];
    }

    private function recordLeadershipGap(KnowledgeAssessment $assessment, User $advisor): void
    {
        $this->context->apply('system', []);

        try {
            $signal = CoachingSignal::query()->create([
                'client_id' => $assessment->client_id,
                'user_id' => $advisor->getKey(),
                'trigger_checkin_id' => null,
                'signal_type' => CoachingSignal::TYPE_LEADERSHIP_CAPABILITY_GAP,
                'severity' => 'advisor_attention',
                'status' => 'detected',
                'evidence' => [
                    'source' => 'knowledge_assessment',
                    'knowledge_assessment_id' => $assessment->id,
                    'leadership_score' => $assessment->leadership,
                    'threshold' => self::LEADERSHIP_GAP_THRESHOLD,
                    'raw_observation_only' => true,
                    'auto_referral' => false,
                    'phase_three_consumer' => 'coach_referral_signal_calibration',
                ],
                'generated_at' => now(),
            ]);

            $this->audit->record('coaching_signal.raw_observation_recorded', subject: $signal, actor: $advisor, after: [
                'client_id' => $assessment->client_id,
                'signal_type' => $signal->signal_type,
                'source' => 'knowledge_assessment',
                'auto_referral' => false,
            ]);
        } finally {
            $this->context->apply(
                $this->context->resolveRole($advisor),
                $this->context->resolveClientIds($advisor),
                (string) $advisor->getKey(),
            );
        }
    }

    private function score(int $value): int
    {
        return max(1, min(5, $value));
    }
}
