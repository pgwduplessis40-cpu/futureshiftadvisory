<?php

declare(strict_types=1);

namespace App\Services\Outcomes;

use App\Models\BusinessPlan;
use App\Models\ConversionOutcome;
use App\Models\DdEngagement;
use App\Models\DdOutcomeRecord;
use App\Models\OutcomeFollowUp;
use App\Models\PlanAssessment;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OutcomeFollowUpService
{
    /**
     * @var array<int, int>
     */
    private const CADENCE_MONTHS = [6, 12];

    /**
     * @var array<int, string>
     */
    private const FOCUS_AREA_STATUSES = [
        'implemented',
        'partially_implemented',
        'not_started',
        'not_applicable',
    ];

    public function __construct(private readonly RequestContext $context) {}

    public function scheduleDue(?CarbonInterface $now = null): int
    {
        $now ??= now();

        return $this->context->withSystemContext(function () use ($now): int {
            $created = 0;

            foreach ($this->latestFinalisedEntrepreneurAssessments() as $assessment) {
                $created += $this->scheduleEntrepreneurAssessment($assessment, $now);
            }

            foreach ($this->completedDdEngagements() as $engagement) {
                $created += $this->scheduleDdEngagement($engagement, $now);
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(OutcomeFollowUp $followUp, User $actor, array $payload): OutcomeFollowUp
    {
        return $this->context->withSystemContext(function () use ($followUp, $actor, $payload): OutcomeFollowUp {
            return DB::transaction(function () use ($followUp, $actor, $payload): OutcomeFollowUp {
                $followUp = OutcomeFollowUp::query()
                    ->lockForUpdate()
                    ->whereKey($followUp->getKey())
                    ->firstOrFail();

                if ($followUp->status !== OutcomeFollowUp::STATUS_PENDING) {
                    return $followUp;
                }

                $signal = $this->outcomeSignal($followUp, $payload, $actor);
                $outcome = $followUp->subject_type === OutcomeFollowUp::SUBJECT_DUE_DILIGENCE
                    ? $this->recordDdOutcome($followUp, $signal, $payload)
                    : $this->recordConversionOutcome($followUp, $signal);

                $followUp->forceFill([
                    'status' => OutcomeFollowUp::STATUS_COMPLETED,
                    'responded_by_user_id' => $actor->getKey(),
                    'completed_at' => now(),
                    'response_payload' => $this->responsePayload($payload),
                    'outcome_signal' => $signal,
                    'conversion_outcome_id' => $outcome instanceof ConversionOutcome ? $outcome->getKey() : $followUp->conversion_outcome_id,
                    'dd_outcome_record_id' => $outcome instanceof DdOutcomeRecord ? $outcome->getKey() : $followUp->dd_outcome_record_id,
                ])->save();

                return $followUp->refresh();
            });
        });
    }

    /**
     * @return Collection<int, PlanAssessment>
     */
    private function latestFinalisedEntrepreneurAssessments(): Collection
    {
        return PlanAssessment::query()
            ->with('businessPlan.entrepreneurProfile')
            ->whereNotNull('finalised_at')
            ->whereHas('businessPlan', function ($query): void {
                $query
                    ->whereNotNull('entrepreneur_profile_id')
                    ->whereIn('source_type', [
                        BusinessPlan::SOURCE_ENTREPRENEUR,
                        'entrepreneur_module',
                    ]);
            })
            ->latest('finalised_at')
            ->get()
            ->groupBy('business_plan_id')
            ->map(fn (Collection $assessments): PlanAssessment => $assessments->first())
            ->values();
    }

    private function scheduleEntrepreneurAssessment(PlanAssessment $assessment, CarbonInterface $now): int
    {
        $completedAt = $assessment->finalised_at;
        $plan = $assessment->businessPlan;
        $profile = $plan?->entrepreneurProfile;

        if ($completedAt === null || ! $plan instanceof BusinessPlan || $profile === null) {
            return 0;
        }

        $created = 0;

        foreach (self::CADENCE_MONTHS as $cadenceMonth) {
            $dueAt = $completedAt->copy()->addMonthsNoOverflow($cadenceMonth);

            if ($dueAt->greaterThan($now)) {
                continue;
            }

            $followUp = OutcomeFollowUp::query()->firstOrCreate(
                [
                    'plan_assessment_id' => $assessment->getKey(),
                    'cadence_month' => $cadenceMonth,
                ],
                [
                    'client_id' => $plan->client_id ?: $profile->client_id,
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'service_activation_id' => $this->entrepreneurActivationId((string) $profile->getKey()),
                    'subject_type' => OutcomeFollowUp::SUBJECT_ENTREPRENEUR,
                    'status' => OutcomeFollowUp::STATUS_PENDING,
                    'engagement_completed_at' => $completedAt,
                    'due_at' => $dueAt,
                ],
            );

            $created += $followUp->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * @return Collection<int, DdEngagement>
     */
    private function completedDdEngagements(): Collection
    {
        return DdEngagement::query()
            ->with('businessPlans')
            ->whereIn('status', [
                DdEngagement::STATUS_ACQUISITION_PROCEEDING,
                DdEngagement::STATUS_ABANDONED,
            ])
            ->latest('updated_at')
            ->get();
    }

    private function scheduleDdEngagement(DdEngagement $engagement, CarbonInterface $now): int
    {
        $completedAt = $this->ddCompletedAt($engagement);

        if ($completedAt === null) {
            return 0;
        }

        $created = 0;

        foreach (self::CADENCE_MONTHS as $cadenceMonth) {
            $dueAt = $completedAt->copy()->addMonthsNoOverflow($cadenceMonth);

            if ($dueAt->greaterThan($now)) {
                continue;
            }

            $followUp = OutcomeFollowUp::query()->firstOrCreate(
                [
                    'dd_engagement_id' => $engagement->getKey(),
                    'cadence_month' => $cadenceMonth,
                ],
                [
                    'client_id' => $engagement->client_id,
                    'service_activation_id' => $this->ddActivationId((string) $engagement->getKey()),
                    'subject_type' => OutcomeFollowUp::SUBJECT_DUE_DILIGENCE,
                    'status' => OutcomeFollowUp::STATUS_PENDING,
                    'engagement_completed_at' => $completedAt,
                    'due_at' => $dueAt,
                ],
            );

            $created += $followUp->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    private function ddCompletedAt(DdEngagement $engagement): ?CarbonInterface
    {
        $plan = $engagement->businessPlans
            ->where('source_type', BusinessPlan::SOURCE_DUE_DILIGENCE)
            ->where('status', BusinessPlan::STATUS_FOUNDING)
            ->sortByDesc('completed_at')
            ->first();

        if ($plan instanceof BusinessPlan && $plan->completed_at !== null) {
            return $plan->completed_at;
        }

        return $engagement->updated_at;
    }

    private function entrepreneurActivationId(string $profileId): ?string
    {
        $activation = ServiceActivation::query()
            ->where('service_type', ServiceActivation::SERVICE_ENTREPRENEUR)
            ->where('related_entrepreneur_profile_id', $profileId)
            ->latest()
            ->first();

        return $activation instanceof ServiceActivation ? (string) $activation->getKey() : null;
    }

    private function ddActivationId(string $engagementId): ?string
    {
        $activation = ServiceActivation::query()
            ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
            ->where('related_dd_engagement_id', $engagementId)
            ->latest()
            ->first();

        return $activation instanceof ServiceActivation ? (string) $activation->getKey() : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return ConversionOutcome|DdOutcomeRecord
     */
    private function recordConversionOutcome(OutcomeFollowUp $followUp, array $signal): ConversionOutcome
    {
        $attributes = [
            'entrepreneur_profile_id' => $followUp->entrepreneur_profile_id,
            'client_id' => $followUp->client_id,
            'plan_assessment_id' => $followUp->plan_assessment_id,
            'outcome_signal' => $signal,
            'observed_at' => now(),
        ];

        if ($followUp->conversion_outcome_id !== null) {
            $outcome = ConversionOutcome::query()->find($followUp->conversion_outcome_id);

            if ($outcome instanceof ConversionOutcome) {
                $outcome->forceFill($attributes)->save();

                return $outcome->refresh();
            }
        }

        return ConversionOutcome::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $signal
     * @param  array<string, mixed>  $payload
     */
    private function recordDdOutcome(OutcomeFollowUp $followUp, array $signal, array $payload): DdOutcomeRecord
    {
        $attributes = [
            'client_id' => $followUp->client_id,
            'dd_engagement_id' => $followUp->dd_engagement_id,
            'recorded_price' => $this->numericOrNull($payload['recorded_price'] ?? null),
            'actual_outcome' => [
                ...$signal,
                'recorded_price_source' => 'client_post_engagement_follow_up',
            ],
            'recorded_at' => now(),
        ];

        if ($followUp->dd_outcome_record_id !== null) {
            $record = DdOutcomeRecord::query()->find($followUp->dd_outcome_record_id);

            if ($record instanceof DdOutcomeRecord) {
                $record->forceFill($attributes)->save();

                return $record->refresh();
            }
        }

        return DdOutcomeRecord::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function outcomeSignal(OutcomeFollowUp $followUp, array $payload, User $actor): array
    {
        $focusAreaOutcomes = $this->focusAreaOutcomes($payload);
        $implemented = (int) ($payload['implemented_recommendations'] ?? 0);
        $total = (int) ($payload['total_recommendations'] ?? 0);
        $implementationRate = $total > 0 ? round($implemented / $total, 4) : null;
        $focusAreaImplementationRate = $this->focusAreaImplementationRate($focusAreaOutcomes);
        $status = $this->normalisedStatus($followUp, $payload);
        $revenueGrowth = $this->numericOrNull($payload['revenue_growth_percent'] ?? null);

        return [
            'source' => 'post_engagement_follow_up',
            'follow_up_id' => (string) $followUp->getKey(),
            'cadence_month' => (int) $followUp->cadence_month,
            'subject_type' => $followUp->subject_type,
            'status' => $status,
            'still_trading' => $this->nullableBoolean($payload['still_trading'] ?? null),
            'revenue_direction' => (string) ($payload['revenue_direction'] ?? 'not_available'),
            'revenue_growth_percent' => $revenueGrowth,
            'implemented_recommendations' => $implemented,
            'total_recommendations' => $total,
            'implementation_rate' => $implementationRate,
            'focus_area_outcomes' => $focusAreaOutcomes,
            'focus_area_implementation_rate' => $focusAreaImplementationRate,
            'implemented_analysis_finding_ids' => $this->analysisFindingIdsForStatus($focusAreaOutcomes, 'implemented'),
            'partially_implemented_analysis_finding_ids' => $this->analysisFindingIdsForStatus($focusAreaOutcomes, 'partially_implemented'),
            'success_score' => $this->successScore($status, $payload, $implementationRate, $revenueGrowth),
            'submitted_by_user_id' => (string) $actor->getKey(),
            'submitted_at' => now()->toIso8601String(),
            'comments_present' => trim((string) ($payload['comments'] ?? '')) !== '',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalisedStatus(OutcomeFollowUp $followUp, array $payload): string
    {
        if ($followUp->subject_type === OutcomeFollowUp::SUBJECT_DUE_DILIGENCE) {
            return match ((string) ($payload['status'] ?? '')) {
                'completed_acquisition' => 'successful',
                'renegotiated' => 'stable',
                'abandoned' => 'failed',
                default => 'stalled',
            };
        }

        $stillTrading = $this->nullableBoolean($payload['still_trading'] ?? null);

        if ($stillTrading === false) {
            return 'failed';
        }

        return match ((string) ($payload['status'] ?? '')) {
            'growth' => 'growth',
            'stable' => 'stable',
            'failed' => 'failed',
            default => 'stalled',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function successScore(string $status, array $payload, ?float $implementationRate, ?float $revenueGrowth): int
    {
        $score = match ($status) {
            'growth', 'successful' => 78,
            'stable' => 64,
            'stalled' => 42,
            'failed' => 18,
            default => 50,
        };

        $revenueDirection = (string) ($payload['revenue_direction'] ?? 'not_available');
        $score += match ($revenueDirection) {
            'up' => 8,
            'down' => -10,
            'not_started' => -4,
            default => 0,
        };

        if ($revenueGrowth !== null) {
            $score += (int) round(max(-15, min(15, $revenueGrowth * 0.4)));
        }

        if ($implementationRate !== null) {
            $score += (int) round(($implementationRate - 0.5) * 30);
        }

        return max(0, min(100, $score));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function responsePayload(array $payload): array
    {
        return [
            'status' => $payload['status'] ?? null,
            'still_trading' => $this->nullableBoolean($payload['still_trading'] ?? null),
            'revenue_direction' => $payload['revenue_direction'] ?? null,
            'revenue_growth_percent' => $this->numericOrNull($payload['revenue_growth_percent'] ?? null),
            'recorded_price' => $this->numericOrNull($payload['recorded_price'] ?? null),
            'implemented_recommendations' => (int) ($payload['implemented_recommendations'] ?? 0),
            'total_recommendations' => (int) ($payload['total_recommendations'] ?? 0),
            'focus_area_outcomes' => $this->focusAreaOutcomes($payload),
            'comments' => trim((string) ($payload['comments'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{proposal_id:string|null,analysis_finding_id:string|null,module:string|null,title:string,status:string,implemented:bool,notes:string}>
     */
    private function focusAreaOutcomes(array $payload): array
    {
        $items = $payload['focus_area_outcomes'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                $status = $this->focusAreaStatus($item['status'] ?? null, $item['implemented'] ?? null);

                return [
                    'proposal_id' => $this->optionalString($item['proposal_id'] ?? null, 80),
                    'analysis_finding_id' => $this->optionalString($item['analysis_finding_id'] ?? null, 80),
                    'module' => $this->optionalString($item['module'] ?? null, 80),
                    'title' => $this->limitedString($item['title'] ?? 'Advisory focus area', 180),
                    'status' => $status,
                    'implemented' => $status === 'implemented',
                    'notes' => $this->limitedString($item['notes'] ?? '', 500),
                ];
            })
            ->filter(
                fn (array $item): bool => $item['title'] !== ''
                    || $item['analysis_finding_id'] !== null
                    || $item['module'] !== null
            )
            ->take(25)
            ->values()
            ->all();
    }

    private function focusAreaStatus(mixed $status, mixed $implemented): string
    {
        if (is_string($status) && in_array($status, self::FOCUS_AREA_STATUSES, true)) {
            return $status;
        }

        return $this->nullableBoolean($implemented) === true ? 'implemented' : 'not_started';
    }

    /**
     * @param  array<int, array<string, mixed>>  $focusAreaOutcomes
     */
    private function focusAreaImplementationRate(array $focusAreaOutcomes): ?float
    {
        $counted = collect($focusAreaOutcomes)
            ->filter(fn (array $item): bool => ($item['status'] ?? null) !== 'not_applicable');

        if ($counted->isEmpty()) {
            return null;
        }

        return round(
            $counted->where('status', 'implemented')->count() / $counted->count(),
            4,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $focusAreaOutcomes
     * @return array<int, string>
     */
    private function analysisFindingIdsForStatus(array $focusAreaOutcomes, string $status): array
    {
        return collect($focusAreaOutcomes)
            ->filter(fn (array $item): bool => ($item['status'] ?? null) === $status)
            ->pluck('analysis_finding_id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function optionalString(mixed $value, int $limit): ?string
    {
        $value = $this->limitedString($value, $limit);

        return $value === '' ? null : $value;
    }

    private function limitedString(mixed $value, int $limit): string
    {
        return mb_substr(trim((string) $value), 0, $limit);
    }

    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
