<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\EconomicIndicator;
use App\Models\Scenario;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\DataQuality\DataQualityScore;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\Documents\DocumentVerificationBlockedException;
use App\Services\Documents\DocumentVerificationGate;
use App\Services\Pv\PvEngine;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class ScenarioPlanner
{
    public const MAX_SCENARIOS = 5;

    public function __construct(
        private readonly DataQualityScorer $dataQuality,
        private readonly DocumentVerificationGate $documents,
        private readonly PvEngine $pv,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $scenarioInputs
     * @param  array{created_by_user_id?: int|string|null, actor?: Authenticatable|null}  $options
     */
    public function plan(Client $client, array $scenarioInputs, array $options = []): AnalysisRun
    {
        $this->assertScenarioCount($scenarioInputs);

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

        $baseOverlay = $this->economicOverlay();
        $created = [];

        foreach (array_values($scenarioInputs) as $index => $input) {
            $scenario = $this->normaliseScenario($input, $index + 1);
            $growthRate = $this->growthRate($scenario['assumptions'], $baseOverlay);
            $cashFlows = $this->cashFlows($scenario['assumptions'], $growthRate);
            $discountMethod = $this->discountMethod($scenario['assumptions'], $baseOverlay);
            $discountOptions = $this->discountOptions($client, $discountMethod, $scenario['assumptions']);
            $calculation = $this->pv->calculate(
                client: $client,
                type: PvType::ImprovementOpportunity,
                discountMethod: $discountMethod,
                cashFlows: $cashFlows,
                discountOptions: $discountOptions,
            );

            $scenarioOverlay = [
                ...$baseOverlay,
                'applied_growth_rate' => $growthRate,
                'discount_method' => $discountMethod->value,
            ];

            $created[] = Scenario::query()->create([
                'client_id' => $client->getKey(),
                'analysis_run_id' => $run->getKey(),
                'name' => $scenario['name'],
                'kind' => $scenario['kind'],
                'assumptions' => [
                    ...$scenario['assumptions'],
                    'cash_flows' => $this->cashFlowPayload($cashFlows),
                ],
                'economic_overlay' => $scenarioOverlay,
                'pv_calculation_id' => $calculation->getKey(),
                'pv_impact' => (float) $calculation->result['present_value'],
                'position' => $scenario['position'],
                'is_client_visible' => $scenario['is_client_visible'],
                'created_by_user_id' => $this->normaliseUserId($options['created_by_user_id'] ?? null),
            ]);
        }

        $run->forceFill([
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Predictive->value],
            'ai_model' => 'deterministic-scenario-planner',
            'prompt_version' => '2026-05-wo53',
            'prompt_hash' => hash('sha256', $client->id.json_encode($scenarioInputs, JSON_THROW_ON_ERROR)),
            'completed_at' => now(),
        ])->save();

        $this->audit->record('analysis.scenarios_planned', subject: $run, actor: $actor, after: [
            'scenario_count' => count($created),
            'client_visible_count' => collect($created)->where('is_client_visible', true)->count(),
        ]);

        return $run->refresh()->load('scenarios');
    }

    /**
     * @param  array<int, array<string, mixed>>  $scenarioInputs
     */
    private function assertScenarioCount(array $scenarioInputs): void
    {
        $count = count($scenarioInputs);

        if ($count < 1) {
            throw new InvalidArgumentException('Scenario planning requires at least one scenario.');
        }

        if ($count > self::MAX_SCENARIOS) {
            throw new InvalidArgumentException('Scenario planning supports a maximum of five scenarios.');
        }
    }

    private function createRun(Client $client, DataQualityScore $score, mixed $createdByUserId): AnalysisRun
    {
        return AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => AnalysisModule::Scenario,
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
     * @return array{name:string, kind:string, assumptions:array<string, mixed>, position:int, is_client_visible:bool}
     */
    private function normaliseScenario(array $input, int $position): array
    {
        $name = trim((string) ($input['name'] ?? 'Scenario '.$position));
        $kind = (string) ($input['kind'] ?? Scenario::KIND_CUSTOM);

        if ($name === '') {
            throw new InvalidArgumentException('Scenario names cannot be blank.');
        }

        if (! in_array($kind, Scenario::kinds(), true)) {
            throw new InvalidArgumentException("Unsupported scenario kind [{$kind}].");
        }

        $assumptions = is_array($input['assumptions'] ?? null) ? $input['assumptions'] : [];

        foreach ([
            'annual_pv_impact',
            'annual_impact',
            'duration_years',
            'growth_rate',
            'discount_method',
            'discount_options',
            'discount_rate',
            'risk_premium',
            'source_reference',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $assumptions[$key] = $input[$key];
            }
        }

        if (array_key_exists('cash_flows', $input)) {
            $assumptions['cash_flows'] = $input['cash_flows'];
        }

        return [
            'name' => $name,
            'kind' => $kind,
            'assumptions' => $assumptions,
            'position' => $position,
            'is_client_visible' => (bool) ($input['is_client_visible'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function economicOverlay(): array
    {
        $indicatorOrder = [
            EconomicIndicator::OCR,
            EconomicIndicator::CPI_ANNUAL,
            EconomicIndicator::GDP_QUARTERLY,
            EconomicIndicator::UNEMPLOYMENT_RATE,
        ];

        $indicators = EconomicIndicator::query()
            ->whereIn('indicator', $indicatorOrder)
            ->latest('period_date')
            ->latest('fetched_at')
            ->limit(40)
            ->get()
            ->unique('indicator')
            ->mapWithKeys(fn (EconomicIndicator $indicator): array => [
                $indicator->indicator => [
                    'id' => $indicator->id,
                    'label' => $indicator->label,
                    'value' => $indicator->value,
                    'unit' => $indicator->unit,
                    'period_date' => $indicator->period_date?->toDateString(),
                    'source' => $indicator->source,
                    'source_reference' => "economic_indicator:{$indicator->id}",
                ],
            ])
            ->all();

        return [
            'indicators' => $indicators,
            'base_growth_rate' => $this->baseGrowthRate($indicators),
            'as_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $indicators
     */
    private function baseGrowthRate(array $indicators): float
    {
        $values = collect([
            $indicators[EconomicIndicator::CPI_ANNUAL]['value'] ?? null,
            $indicators[EconomicIndicator::GDP_QUARTERLY]['value'] ?? null,
        ])
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value / 100)
            ->values();

        if ($values->isEmpty()) {
            return 0.0;
        }

        return round(max(-0.05, min(0.1, $values->average())), 6);
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @param  array<string, mixed>  $overlay
     */
    private function growthRate(array $assumptions, array $overlay): float
    {
        if (array_key_exists('growth_rate', $assumptions) && is_numeric($assumptions['growth_rate'])) {
            return round(max(-0.5, min(0.5, (float) $assumptions['growth_rate'])), 6);
        }

        return (float) ($overlay['base_growth_rate'] ?? 0.0);
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @return array<int, float>
     */
    private function cashFlows(array $assumptions, float $growthRate): array
    {
        if (is_array($assumptions['cash_flows'] ?? null) && $assumptions['cash_flows'] !== []) {
            $cashFlows = [];

            foreach (array_values($assumptions['cash_flows']) as $index => $amount) {
                if (! is_numeric($amount)) {
                    throw new InvalidArgumentException('Scenario cash flows must be numeric.');
                }

                $cashFlows[$index + 1] = round((float) $amount, 2);
            }

            return $cashFlows;
        }

        $annualImpact = (float) ($assumptions['annual_pv_impact'] ?? $assumptions['annual_impact'] ?? 0);
        $durationYears = max(1, min(10, (int) ($assumptions['duration_years'] ?? 1)));
        $cashFlows = [];

        foreach (range(1, $durationYears) as $year) {
            $cashFlows[$year] = round($annualImpact * ((1 + $growthRate) ** ($year - 1)), 2);
        }

        return $cashFlows;
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @param  array<string, mixed>  $overlay
     */
    private function discountMethod(array $assumptions, array $overlay): DiscountMethod
    {
        $method = $assumptions['discount_method'] ?? null;

        if ($method instanceof DiscountMethod) {
            return $method;
        }

        if (is_string($method) && $method !== '') {
            return DiscountMethod::from($method);
        }

        $indicators = is_array($overlay['indicators'] ?? null) ? $overlay['indicators'] : [];

        return array_key_exists(EconomicIndicator::OCR, $indicators)
            ? DiscountMethod::OcrLinked
            : DiscountMethod::AdvisorConfigured;
    }

    /**
     * @param  array<string, mixed>  $assumptions
     * @return array<string, mixed>
     */
    private function discountOptions(Client $client, DiscountMethod $method, array $assumptions): array
    {
        $options = is_array($assumptions['discount_options'] ?? null) ? $assumptions['discount_options'] : [];

        return match ($method) {
            DiscountMethod::OcrLinked => [
                ...$options,
                'risk_premium' => (float) ($options['risk_premium'] ?? $assumptions['risk_premium'] ?? 0.06),
            ],
            DiscountMethod::AdvisorConfigured => [
                ...$options,
                'rate' => (float) ($options['rate'] ?? $assumptions['discount_rate'] ?? 0.12),
                'rationale' => (string) ($options['rationale'] ?? 'Advisor default scenario-planning discount rate.'),
                'source_reference' => (string) ($options['source_reference'] ?? $assumptions['source_reference'] ?? "client:{$client->id}:scenario_assumption"),
            ],
            default => $options,
        };
    }

    /**
     * @param  array<int, float>  $cashFlows
     * @return array<int, array{period:int, amount:float}>
     */
    private function cashFlowPayload(array $cashFlows): array
    {
        $rows = [];

        foreach ($cashFlows as $period => $amount) {
            $rows[] = [
                'period' => (int) $period,
                'amount' => round((float) $amount, 2),
            ];
        }

        return $rows;
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
