<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\CoachingSignal;
use App\Models\EconomicIndicator;
use App\Models\SuccessionPlan;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\DataQuality\DataQualityScore;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Documents\DocumentVerificationBlockedException;
use App\Services\Documents\DocumentVerificationGate;
use App\Services\Pv\PvEngine;
use App\Support\RequestContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class SuccessionPlanner
{
    public function __construct(
        private readonly DataQualityScorer $dataQuality,
        private readonly DocumentVerificationGate $documents,
        private readonly PvEngine $pv,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array{created_by_user_id?: int|string|null, actor?: Authenticatable|null}  $options
     */
    public function plan(Client $client, array $input, array $options = []): AnalysisRun
    {
        $score = $this->dataQuality->score($client);
        $actor = $this->actor($options['actor'] ?? null);
        $run = $this->createRun($client, $score, $options['created_by_user_id'] ?? null);

        if ($score->level === Client::DATA_QUALITY_INSUFFICIENT) {
            try {
                $this->documents->ensureClear($client);
            } catch (DocumentVerificationBlockedException $e) {
                return $this->blockForDocuments($run, $e, $actor);
            }

            return $this->blockForDataQuality($run, $score, $actor);
        }

        try {
            $this->documents->ensureClear($client);
        } catch (DocumentVerificationBlockedException $e) {
            return $this->blockForDocuments($run, $e, $actor);
        }

        $readinessInputs = $this->readinessInputs($input);
        $exitReadinessScore = $this->exitReadinessScore($readinessInputs);
        $ownerIsConstraint = $this->ownerReadinessIsPrimaryConstraint($input, $readinessInputs);
        $targetExitPv = $this->targetExitPv($client, $input);
        $plan = SuccessionPlan::query()->create([
            'client_id' => $client->getKey(),
            'analysis_run_id' => $run->getKey(),
            'exit_readiness_score' => $exitReadinessScore,
            'options' => $this->optionsAssessment($input, $exitReadinessScore),
            'owner_dependency_plan' => $this->ownerDependencyPlan(
                input: $input,
                scores: $readinessInputs,
                ownerIsConstraint: $ownerIsConstraint,
                targetExitPv: (float) $targetExitPv->result['present_value'],
            ),
            'target_exit_pv_calculation_id' => $targetExitPv->getKey(),
            'target_exit_pv' => (float) $targetExitPv->result['present_value'],
            'owner_readiness_is_primary_constraint' => $ownerIsConstraint,
            'created_by_user_id' => $this->normaliseUserId($options['created_by_user_id'] ?? null),
        ]);

        if ($ownerIsConstraint) {
            $this->recordOwnerReadinessSignal($plan, $actor, $readinessInputs);
        }

        $run->forceFill([
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Predictive->value, AnalysisLens::Prescriptive->value],
            'ai_model' => 'deterministic-succession-planner',
            'prompt_version' => '2026-05-wo54',
            'prompt_hash' => hash('sha256', $client->id.json_encode($input, JSON_THROW_ON_ERROR)),
            'completed_at' => now(),
        ])->save();

        $this->audit->record('analysis.succession_planned', subject: $run, actor: $actor, after: [
            'succession_plan_id' => $plan->id,
            'exit_readiness_score' => $plan->exit_readiness_score,
            'target_exit_pv' => $plan->target_exit_pv,
            'owner_readiness_is_primary_constraint' => $ownerIsConstraint,
        ]);

        return $run->refresh()->load('successionPlans');
    }

    private function createRun(Client $client, DataQualityScore $score, mixed $createdByUserId): AnalysisRun
    {
        return AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => AnalysisModule::Succession,
            'status' => AnalysisRun::STATUS_RUNNING,
            'framework_lenses' => [],
            'data_quality_snapshot' => $score->toPayload(),
            'tokens_in' => 0,
            'tokens_out' => 0,
            'started_at' => now(),
            'created_by_user_id' => $this->normaliseUserId($createdByUserId),
        ]);
    }

    private function blockForDataQuality(AnalysisRun $run, DataQualityScore $score, ?Authenticatable $actor): AnalysisRun
    {
        $run->forceFill([
            'status' => AnalysisRun::STATUS_BLOCKED_DATA_QUALITY,
            'completed_at' => now(),
        ])->save();

        $this->audit->record('analysis.blocked_data_quality', subject: $run, actor: $actor, after: [
            'data_quality' => $score->toPayload(),
        ]);

        return $run->refresh();
    }

    private function blockForDocuments(
        AnalysisRun $run,
        DocumentVerificationBlockedException $exception,
        ?Authenticatable $actor,
    ): AnalysisRun {
        $run->forceFill([
            'status' => AnalysisRun::STATUS_BLOCKED_DOCUMENTS,
            'completed_at' => now(),
        ])->save();

        $this->audit->record('analysis.blocked_documents', subject: $run, actor: $actor, after: [
            'blocking_verification_ids' => $exception->flags
                ->map(static fn ($flag): string => (string) $flag->getKey())
                ->values()
                ->all(),
        ]);

        return $run->refresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{owner:int, management:int, process:int, financial:int, timeline:int}
     */
    private function readinessInputs(array $input): array
    {
        return [
            'owner' => $this->score($input['owner_readiness_score'] ?? $input['owner_readiness'] ?? 5),
            'management' => $this->score($input['management_depth_score'] ?? $input['management_depth'] ?? 5),
            'process' => $this->score($input['process_documentation_score'] ?? $input['process_documentation'] ?? 5),
            'financial' => $this->score($input['financial_readiness_score'] ?? $input['financial_readiness'] ?? 5),
            'timeline' => $this->score($input['exit_timeline_score'] ?? $input['exit_timeline_clarity'] ?? 5),
        ];
    }

    /**
     * @param  array{owner:int, management:int, process:int, financial:int, timeline:int}  $scores
     */
    private function exitReadinessScore(array $scores): int
    {
        $weighted = ($scores['owner'] * 0.3)
            + ($scores['management'] * 0.25)
            + ($scores['process'] * 0.2)
            + ($scores['financial'] * 0.15)
            + ($scores['timeline'] * 0.1);

        return max(1, min(10, (int) round($weighted)));
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{owner:int, management:int, process:int, financial:int, timeline:int}  $scores
     */
    private function ownerReadinessIsPrimaryConstraint(array $input, array $scores): bool
    {
        if (array_key_exists('owner_readiness_is_primary_constraint', $input)) {
            return (bool) $input['owner_readiness_is_primary_constraint'];
        }

        return $scores['owner'] <= 5 && $scores['owner'] === min($scores);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function targetExitPv(Client $client, array $input)
    {
        $cashFlows = $this->targetExitCashFlows($input);
        $discountMethod = $this->discountMethod($input);

        return $this->pv->calculate(
            client: $client,
            type: PvType::BusinessValuation,
            discountMethod: $discountMethod,
            cashFlows: $cashFlows,
            discountOptions: $this->discountOptions($client, $input, $discountMethod),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, float>
     */
    private function targetExitCashFlows(array $input): array
    {
        if (is_array($input['target_exit_cash_flows'] ?? null) && $input['target_exit_cash_flows'] !== []) {
            $cashFlows = [];

            foreach (array_values($input['target_exit_cash_flows']) as $index => $amount) {
                if (! is_numeric($amount)) {
                    throw new InvalidArgumentException('Target exit cash flows must be numeric.');
                }

                $cashFlows[$index + 1] = round((float) $amount, 2);
            }

            return $this->withTargetExitTerminalValue($cashFlows, $input);
        }

        $annualCashFlow = (float) ($input['target_exit_annual_cash_flow'] ?? $input['maintainable_earnings'] ?? 0);

        if ($annualCashFlow <= 0) {
            throw new InvalidArgumentException('Succession planning requires target exit annual cash flow or cash flows.');
        }

        $growthRate = max(-0.5, min(0.5, (float) ($input['target_exit_growth_rate'] ?? 0.02)));
        $durationYears = max(1, min(10, (int) ($input['duration_years'] ?? $input['target_exit_duration_years'] ?? 5)));
        $cashFlows = [];

        foreach (range(1, $durationYears) as $year) {
            $cashFlows[$year] = round($annualCashFlow * ((1 + $growthRate) ** ($year - 1)), 2);
        }

        return $this->withTargetExitTerminalValue($cashFlows, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function discountMethod(array $input): DiscountMethod
    {
        $method = $input['discount_method'] ?? null;

        if ($method instanceof DiscountMethod) {
            return $method;
        }

        if (is_string($method) && $method !== '') {
            return DiscountMethod::from($method);
        }

        return EconomicIndicator::query()->where('indicator', EconomicIndicator::OCR)->exists()
            ? DiscountMethod::OcrLinked
            : DiscountMethod::AdvisorConfigured;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function discountOptions(Client $client, array $input, DiscountMethod $method): array
    {
        $options = is_array($input['discount_options'] ?? null) ? $input['discount_options'] : [];

        return match ($method) {
            DiscountMethod::OcrLinked => [
                ...$options,
                'risk_premium' => (float) ($options['risk_premium'] ?? $input['risk_premium'] ?? 0.06),
            ],
            DiscountMethod::AdvisorConfigured => [
                ...$options,
                'rate' => (float) ($options['rate'] ?? $input['discount_rate'] ?? 0.12),
                'rationale' => (string) ($options['rationale'] ?? 'Advisor default succession target-exit PV rate.'),
                'source_reference' => (string) ($options['source_reference'] ?? $input['source_reference'] ?? "client:{$client->id}:succession_assumption"),
            ],
            default => $options,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function optionsAssessment(array $input, int $exitReadinessScore): array
    {
        if (is_array($input['options'] ?? null) && $input['options'] !== []) {
            return array_values(array_map(
                fn (array $option): array => [
                    'name' => (string) ($option['name'] ?? $option['option'] ?? 'Exit option'),
                    'fit_score' => $this->score($option['fit_score'] ?? $exitReadinessScore),
                    'rationale' => (string) ($option['rationale'] ?? 'Advisor-supplied succession option.'),
                ],
                array_filter($input['options'], 'is_array'),
            ));
        }

        return [
            [
                'name' => 'Trade sale',
                'fit_score' => max(1, min(10, $exitReadinessScore)),
                'rationale' => 'Assessed against current readiness, management depth, process documentation, and financial evidence.',
            ],
            [
                'name' => 'Management buyout',
                'fit_score' => max(1, min(10, $exitReadinessScore - 1)),
                'rationale' => 'Requires management bench strength and owner-dependency reduction before execution.',
            ],
            [
                'name' => 'Family or internal succession',
                'fit_score' => max(1, min(10, $exitReadinessScore - 2)),
                'rationale' => 'Depends on successor readiness, governance, and staged delegation.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{owner:int, management:int, process:int, financial:int, timeline:int}  $scores
     * @return array<string, mixed>
     */
    private function ownerDependencyPlan(array $input, array $scores, bool $ownerIsConstraint, float $targetExitPv): array
    {
        $base = is_array($input['owner_dependency_plan'] ?? null)
            ? $input['owner_dependency_plan']
            : [
                'owner_readiness_score' => $scores['owner'],
                'owner_readiness_is_primary_constraint' => $ownerIsConstraint,
                'actions' => [
                    'Document owner-held client, supplier, and operational routines.',
                    'Delegate at least two recurring decisions to the leadership team.',
                    'Run a 30-day owner-absence test and review gaps with the advisor.',
                ],
            ];

        return [
            ...$base,
            'wealth_gap' => $this->wealthGap($input, $targetExitPv),
            'buyer_attractiveness' => $this->buyerAttractiveness($scores),
            'terminal_value_treatment' => [
                'included' => is_numeric($input['target_exit_terminal_value'] ?? null) && (float) $input['target_exit_terminal_value'] > 0,
                'terminal_value_nzd' => is_numeric($input['target_exit_terminal_value'] ?? null)
                    ? round(max(0.0, (float) $input['target_exit_terminal_value']), 2)
                    : 0.0,
                'message' => is_numeric($input['target_exit_terminal_value'] ?? null) && (float) $input['target_exit_terminal_value'] > 0
                    ? 'Target-exit PV includes the advisor-supplied terminal value in the final forecast period.'
                    : 'Target-exit PV discounts the entered cash-flow plan only; no separate terminal value was included.',
            ],
        ];
    }

    /**
     * @param  array<int, float>  $cashFlows
     * @param  array<string, mixed>  $input
     * @return array<int, float>
     */
    private function withTargetExitTerminalValue(array $cashFlows, array $input): array
    {
        $terminalValue = $input['target_exit_terminal_value'] ?? null;

        if (! is_numeric($terminalValue) || (float) $terminalValue <= 0) {
            return $cashFlows;
        }

        $lastPeriod = max(array_keys($cashFlows));
        $cashFlows[$lastPeriod] = round((float) $cashFlows[$lastPeriod] + (float) $terminalValue, 2);

        return $cashFlows;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function wealthGap(array $input, float $targetExitPv): array
    {
        $required = $input['owner_required_exit_proceeds']
            ?? $input['required_exit_proceeds']
            ?? $input['owner_wealth_need']
            ?? null;

        if (! is_numeric($required) || (float) $required <= 0) {
            return [
                'status' => 'not_captured',
                'owner_required_exit_proceeds_nzd' => null,
                'target_exit_pv_nzd' => round($targetExitPv, 2),
                'gap_nzd' => null,
                'surplus_nzd' => null,
                'message' => 'Owner wealth requirement has not been captured, so exit value cannot yet be compared with the owner need.',
            ];
        }

        $requiredAmount = round((float) $required, 2);
        $gap = round(max(0.0, $requiredAmount - $targetExitPv), 2);
        $surplus = round(max(0.0, $targetExitPv - $requiredAmount), 2);

        return [
            'status' => $gap > 0 ? 'gap_to_close' : 'target_met_on_current_assumptions',
            'owner_required_exit_proceeds_nzd' => $requiredAmount,
            'target_exit_pv_nzd' => round($targetExitPv, 2),
            'gap_nzd' => $gap,
            'surplus_nzd' => $surplus,
            'message' => $gap > 0
                ? 'Target-exit PV is below the owner wealth requirement; succession actions should prioritise closing this quantified gap.'
                : 'Target-exit PV meets or exceeds the captured owner wealth requirement on current assumptions.',
        ];
    }

    /**
     * @param  array{owner:int, management:int, process:int, financial:int, timeline:int}  $scores
     * @return array<string, mixed>
     */
    private function buyerAttractiveness(array $scores): array
    {
        $score = (int) round((
            ($scores['management'] * 0.30)
            + ($scores['process'] * 0.25)
            + ($scores['financial'] * 0.25)
            + ($scores['timeline'] * 0.10)
            + ($scores['owner'] * 0.10)
        ) * 10);

        $classification = match (true) {
            $score >= 75 => 'attractive',
            $score >= 55 => 'developing',
            default => 'limited',
        };

        $constraints = collect($scores)
            ->filter(fn (int $value): bool => $value <= 5)
            ->keys()
            ->values()
            ->all();

        return [
            'score' => max(0, min(100, $score)),
            'classification' => $classification,
            'constraints' => $constraints,
            'message' => $constraints === []
                ? 'Buyer attractiveness is supported by the current readiness scores.'
                : 'Buyer attractiveness is constrained by '.implode(', ', $constraints).'.',
        ];
    }

    /**
     * @param  array{owner:int, management:int, process:int, financial:int, timeline:int}  $scores
     */
    private function recordOwnerReadinessSignal(
        SuccessionPlan $plan,
        ?Authenticatable $actor,
        array $scores,
    ): void {
        $previousContext = [
            'role' => $actor instanceof User ? $this->context->resolveRole($actor) : 'system',
            'client_ids' => $actor instanceof User ? $this->context->resolveClientIds($actor) : [],
            'user_id' => $actor instanceof User ? (string) $actor->getKey() : null,
        ];

        $this->context->apply('system', []);

        try {
            $signal = CoachingSignal::query()->create([
                'client_id' => $plan->client_id,
                'user_id' => $actor instanceof User ? $actor->getKey() : $plan->created_by_user_id,
                'trigger_checkin_id' => null,
                'signal_type' => CoachingSignal::TYPE_OWNER_READINESS_PRIMARY_CONSTRAINT,
                'severity' => 'advisor_attention',
                'status' => 'detected',
                'evidence' => [
                    'source' => 'succession_plan',
                    'succession_plan_id' => $plan->id,
                    'owner_readiness_score' => $scores['owner'],
                    'exit_readiness_score' => $plan->exit_readiness_score,
                    'raw_observation_only' => true,
                    'auto_referral' => false,
                    'phase_three_consumer' => 'coach_referral_signal_calibration',
                ],
                'generated_at' => now(),
            ]);

            $this->audit->record('coaching_signal.raw_observation_recorded', subject: $signal, actor: $actor, after: [
                'client_id' => $plan->client_id,
                'signal_type' => $signal->signal_type,
                'source' => 'succession_plan',
                'auto_referral' => false,
            ]);
        } finally {
            $this->context->apply(
                $previousContext['role'],
                $previousContext['client_ids'],
                $previousContext['user_id'],
            );
        }
    }

    private function score(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Succession readiness scores must be numeric.');
        }

        return max(1, min(10, (int) round((float) $value)));
    }

    private function normaliseUserId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    private function actor(mixed $actor): ?Authenticatable
    {
        if ($actor instanceof User) {
            return $actor;
        }

        return $actor instanceof Authenticatable ? $actor : null;
    }
}
