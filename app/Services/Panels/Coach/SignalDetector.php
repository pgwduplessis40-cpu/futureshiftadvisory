<?php

declare(strict_types=1);

namespace App\Services\Panels\Coach;

use App\Enums\CoachSpecialisation;
use App\Models\ClientTeamMember;
use App\Models\CoachingSignal;
use App\Models\CoachReferralSuggestion;
use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SignalDetector implements ProvidesMethodology
{
    public const LAYER_ID = 17;

    public static function methodologyIds(): array
    {
        return ['coach.signal_mapping'];
    }

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function suggest(CoachingSignal $signal): CoachReferralSuggestion
    {
        $mapping = $this->map($signal);

        $suggestion = CoachReferralSuggestion::query()->updateOrCreate(
            ['coaching_signal_id' => $signal->getKey()],
            [
                'client_id' => $signal->client_id,
                'suggested_specialisation' => $mapping['specialisation'],
                'threshold_ref' => $mapping['threshold_ref'],
                'rationale' => $mapping['rationale'],
                'evidence' => [
                    'signal_type' => $signal->signal_type,
                    'signal_id' => $signal->getKey(),
                    'severity' => $signal->severity,
                    'raw_evidence' => $signal->evidence ?? [],
                    'advisor_final_decision_required' => true,
                    'auto_referral' => false,
                ],
                'status' => CoachReferralSuggestion::STATUS_SUGGESTED,
                'surfaced_at' => now(),
            ],
        );

        $this->audit->record('coach.signal_suggested', subject: $suggestion, after: [
            'coaching_signal_id' => $signal->getKey(),
            'client_id' => $signal->client_id,
            'suggested_specialisation' => $mapping['specialisation'],
            'auto_referral' => false,
        ]);

        return $suggestion->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function advisorPanel(User $advisor, int $limit = 10): array
    {
        $clientIds = ClientTeamMember::query()
            ->where('user_id', $advisor->getKey())
            ->whereIn('role', ['lead_advisor', 'advisor'])
            ->pluck('client_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        if ($clientIds === []) {
            return $this->emptyPanel();
        }

        $query = CoachReferralSuggestion::query()
            ->with(['client', 'coachingSignal'])
            ->whereIn('client_id', $clientIds)
            ->where('status', CoachReferralSuggestion::STATUS_SUGGESTED)
            ->latest('surfaced_at');

        return [
            'summary' => [
                'total' => (clone $query)->count(),
                'auto_referrals' => 0,
            ],
            'items' => $query
                ->limit(max(1, $limit))
                ->get()
                ->map(fn (CoachReferralSuggestion $suggestion): array => [
                    'id' => $suggestion->id,
                    'client_id' => $suggestion->client_id,
                    'client_name' => $suggestion->client?->legal_name,
                    'client_url' => route('advisor.clients.show', $suggestion->client_id, absolute: false),
                    'signal_type' => $suggestion->coachingSignal?->signal_type,
                    'suggested_specialisation' => $suggestion->suggested_specialisation,
                    'threshold_ref' => $suggestion->threshold_ref,
                    'rationale' => $suggestion->rationale,
                    'surfaced_at' => $suggestion->surfaced_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    public function runCalibrationLayer(
        int $minimumSignals = 3,
        int $windowDays = 90,
        ?CarbonInterface $windowEnd = null,
    ): LearningLayerRun {
        $minimumSignals = max(1, $minimumSignals);
        $windowDays = max(1, $windowDays);
        $windowEnd ??= now()->addMinute();
        $windowStart = $windowEnd->copy()->subDays($windowDays);

        $this->context->apply('system', []);

        return DB::transaction(function () use ($minimumSignals, $windowDays, $windowStart, $windowEnd): LearningLayerRun {
            $signals = CoachingSignal::query()
                ->whereBetween('generated_at', [$windowStart, $windowEnd])
                ->where('status', 'detected')
                ->get();
            $suggestions = $signals->map(fn (CoachingSignal $signal): CoachReferralSuggestion => $this->suggest($signal));
            $created = $this->createCalibrationCandidates($suggestions, $minimumSignals, $windowStart, $windowEnd);

            $run = LearningLayerRun::query()->create([
                'layer_id' => self::LAYER_ID,
                'ran_at' => now(),
                'candidates_created' => $created,
                'window' => [
                    'window_start' => $windowStart->toIso8601String(),
                    'window_end' => $windowEnd->toIso8601String(),
                    'window_days' => $windowDays,
                    'minimum_signals' => $minimumSignals,
                    'signals_consumed' => $signals->count(),
                    'suggestions_surfaced' => $suggestions->count(),
                    'auto_referrals_created' => 0,
                ],
                'status' => LearningLayerRun::STATUS_COMPLETED,
            ]);

            $this->audit->record('coach.signal_calibration_layer.ran', subject: $run, after: [
                'layer_id' => self::LAYER_ID,
                'signals_consumed' => $signals->count(),
                'suggestions_surfaced' => $suggestions->count(),
                'candidates_created' => $created,
                'auto_referrals_created' => 0,
            ]);

            return $run;
        });
    }

    /**
     * @return array{specialisation:string, threshold_ref:string, rationale:string}
     */
    private function map(CoachingSignal $signal): array
    {
        return match ($signal->signal_type) {
            CoachingSignal::TYPE_LOW_PERSONAL_COPING_STREAK => [
                'specialisation' => CoachSpecialisation::MENTAL_HEALTH_WELLBEING->value,
                'threshold_ref' => '15.4.low_personal_coping_streak',
                'rationale' => 'Low personal coping over repeated check-ins suggests a wellbeing-boundary coaching review for advisor consideration.',
            ],
            CoachingSignal::TYPE_LEADERSHIP_CAPABILITY_GAP => [
                'specialisation' => CoachSpecialisation::BUSINESS_EXECUTIVE->value,
                'threshold_ref' => '15.4.leadership_capability_gap',
                'rationale' => 'Leadership capability gaps map to business/executive coaching for advisor review.',
            ],
            CoachingSignal::TYPE_OWNER_READINESS_PRIMARY_CONSTRAINT => [
                'specialisation' => CoachSpecialisation::LIFE->value,
                'threshold_ref' => '15.4.owner_readiness_primary_constraint',
                'rationale' => 'Owner-readiness constraints map to life coaching support around readiness and transition.',
            ],
            CoachingSignal::TYPE_FINANCIAL_STRESS => [
                'specialisation' => CoachSpecialisation::FINANCIAL_WELLNESS->value,
                'threshold_ref' => '15.4.financial_stress',
                'rationale' => 'Financial stress indicators map to financial-wellness coaching for advisor review.',
            ],
            CoachingSignal::TYPE_CAREER_TRANSITION => [
                'specialisation' => CoachSpecialisation::CAREER->value,
                'threshold_ref' => '15.4.career_transition',
                'rationale' => 'Career-transition indicators map to career coaching for advisor review.',
            ],
            default => throw new InvalidArgumentException("Unsupported coaching signal type [{$signal->signal_type}]."),
        };
    }

    /**
     * @param  Collection<int, CoachReferralSuggestion>  $suggestions
     */
    private function createCalibrationCandidates(
        Collection $suggestions,
        int $minimumSignals,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
    ): int {
        $created = 0;

        $suggestions
            ->groupBy(fn (CoachReferralSuggestion $suggestion): string => $suggestion->threshold_ref.'|'.$suggestion->suggested_specialisation)
            ->each(function (Collection $group) use (&$created, $minimumSignals, $windowStart, $windowEnd): void {
                if ($group->count() < $minimumSignals) {
                    return;
                }

                /** @var CoachReferralSuggestion $first */
                $first = $group->first();
                $signalKey = $this->signalKey($first, $windowStart, $windowEnd);

                if ($this->candidateExists($signalKey)) {
                    return;
                }

                LearningUpdate::query()->create([
                    'layer_id' => self::LAYER_ID,
                    'source' => [
                        'type' => 'coach_referral_signal_calibration',
                        'signal_key' => $signalKey,
                        'threshold_ref' => $first->threshold_ref,
                        'suggested_specialisation' => $first->suggested_specialisation,
                        'window_start' => $windowStart->toIso8601String(),
                        'window_end' => $windowEnd->toIso8601String(),
                    ],
                    'summary' => sprintf(
                        'Coach referral signal mapping %s → %s reached %d suggestions; review calibration before any behaviour changes.',
                        $first->threshold_ref,
                        $first->suggested_specialisation,
                        $group->count(),
                    ),
                    'proposed_change' => [
                        'action' => 'review_coach_signal_mapping',
                        'threshold_ref' => $first->threshold_ref,
                        'suggested_specialisation' => $first->suggested_specialisation,
                        'automatic_application' => false,
                    ],
                    'impact_scope' => [
                        'surface' => 'coach_referral_signal_suggestions',
                        'auto_referral' => false,
                    ],
                    'clients_affected' => $group->pluck('client_id')->unique()->count(),
                    'magnitude' => $group->count() >= 10 ? 'medium' : 'low',
                    'confidence' => 0.68,
                    'evidence' => [
                        'suggestion_ids' => $group->pluck('id')->values()->all(),
                        'signal_ids' => $group->pluck('coaching_signal_id')->values()->all(),
                        'count' => $group->count(),
                    ],
                    'status' => LearningUpdate::STATUS_DETECTED,
                ]);

                $created++;
            });

        return $created;
    }

    private function candidateExists(string $signalKey): bool
    {
        return LearningUpdate::query()
            ->where('layer_id', self::LAYER_ID)
            ->where('status', LearningUpdate::STATUS_DETECTED)
            ->where('source->type', 'coach_referral_signal_calibration')
            ->where('source->signal_key', $signalKey)
            ->exists();
    }

    private function signalKey(CoachReferralSuggestion $suggestion, CarbonInterface $windowStart, CarbonInterface $windowEnd): string
    {
        return hash('sha256', implode('|', [
            $suggestion->threshold_ref,
            $suggestion->suggested_specialisation,
            $windowStart->toDateString(),
            $windowEnd->toDateString(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPanel(): array
    {
        return [
            'summary' => [
                'total' => 0,
                'auto_referrals' => 0,
            ],
            'items' => [],
        ];
    }
}
