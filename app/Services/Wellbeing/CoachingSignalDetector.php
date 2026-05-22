<?php

declare(strict_types=1);

namespace App\Services\Wellbeing;

use App\Models\CoachingSignal;
use App\Models\WellbeingCheckin;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;

final class CoachingSignalDetector
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly RequestContext $context,
    ) {}

    public function evaluate(WellbeingCheckin $checkin): ?CoachingSignal
    {
        if ($checkin->personal_coping > 2) {
            return null;
        }

        $previousPeriod = $checkin->period_start?->copy()->subMonthNoOverflow()->startOfMonth();
        if ($previousPeriod === null) {
            return null;
        }

        $previous = WellbeingCheckin::query()
            ->where('client_id', $checkin->client_id)
            ->where('user_id', $checkin->user_id)
            ->whereDate('period_start', $previousPeriod->toDateString())
            ->first();

        if (! $previous instanceof WellbeingCheckin || $previous->personal_coping > 2) {
            return null;
        }

        $this->context->apply('system', []);

        $existing = CoachingSignal::query()
            ->where('client_id', $checkin->client_id)
            ->where('user_id', $checkin->user_id)
            ->where('signal_type', CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK)
            ->where('status', 'detected')
            ->first();

        if ($existing instanceof CoachingSignal) {
            return $existing;
        }

        $signal = CoachingSignal::query()->firstOrCreate(
            [
                'client_id' => $checkin->client_id,
                'signal_type' => CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK,
                'trigger_checkin_id' => $checkin->getKey(),
            ],
            [
                'user_id' => $checkin->user_id,
                'severity' => 'advisor_attention',
                'status' => 'detected',
                'evidence' => [
                    'rule' => 'two_consecutive_months_personal_coping_lte_2',
                    'auto_referral' => false,
                    'phase_2_boundary' => 'raw_internal_observation_only',
                    'checkins' => [
                        [
                            'id' => $previous->id,
                            'period_start' => $previous->period_start?->toDateString(),
                            'personal_coping' => $previous->personal_coping,
                        ],
                        [
                            'id' => $checkin->id,
                            'period_start' => $checkin->period_start?->toDateString(),
                            'personal_coping' => $checkin->personal_coping,
                        ],
                    ],
                ],
                'generated_at' => now(),
            ],
        );

        if ($signal->wasRecentlyCreated) {
            $this->auditWriter->record('coaching_signal.detected', subject: $signal, after: [
                'client_id' => $checkin->client_id,
                'signal_type' => $signal->signal_type,
                'auto_referral' => false,
            ]);
        }

        return $signal;
    }
}
