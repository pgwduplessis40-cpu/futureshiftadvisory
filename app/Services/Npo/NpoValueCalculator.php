<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\NpoEngagementSubType;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\FinancialSnapshot;
use App\Models\LearningUpdate;
use App\Models\NpoEngagement;
use App\Models\NpoValueCalculation;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Learning\LayerCadenceRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class NpoValueCalculator
{
    public function __construct(
        private readonly LayerCadenceRegistry $registry,
        private readonly NpoFunderMonitor $funders,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function calculateCostPerBeneficiary(NpoEngagement $engagement, array $input, ?User $actor = null): NpoValueCalculation
    {
        $this->assertFullEngagement($engagement);

        $programmeExpenditure = $this->positiveNumber($input['programme_expenditure'] ?? null, 'Programme expenditure');
        $beneficiaryCount = $this->positiveNumber($input['beneficiary_count'] ?? null, 'Beneficiary count');
        $programmeType = $this->slug((string) ($input['programme_type'] ?? 'community_services'));
        $sizeBand = $this->slug((string) ($input['size_band'] ?? $this->sizeBand($beneficiaryCount)));
        $benchmark = $this->costPerBeneficiaryBenchmark($programmeType, $sizeBand);
        $costPerBeneficiary = round($programmeExpenditure / $beneficiaryCount, 2);
        $benchmarkCost = is_numeric($benchmark['cost_per_beneficiary'] ?? null)
            ? (float) $benchmark['cost_per_beneficiary']
            : null;
        $hasBenchmark = $benchmarkCost !== null && $benchmarkCost > 0;
        $variance = $hasBenchmark ? round($costPerBeneficiary - $benchmarkCost, 2) : null;
        $annualSaving = $hasBenchmark ? max(0.0, round((float) $variance * $beneficiaryCount, 2)) : 0.0;
        $additionalBeneficiaries = $hasBenchmark && $costPerBeneficiary > 0
            ? round($annualSaving / $costPerBeneficiary, 2)
            : null;
        $disclosure = $hasBenchmark
            ? 'Projection keeps programme scope, beneficiary demand, and delivery cost base stable; every projection carries a +/-15% uncertainty range.'
            : 'No single official NZ cost-per-beneficiary benchmark exists. This calculation records the organisation\'s own cost per beneficiary; FSA platform comparison starts once at least five comparable NZ NPOs exist for the programme type and size band.';

        $result = [
            'cost_per_beneficiary' => $costPerBeneficiary,
            'benchmark_cost_per_beneficiary' => $benchmarkCost,
            'variance_to_benchmark' => $variance,
            'rating' => $hasBenchmark ? $this->costPerBeneficiaryRating($costPerBeneficiary, $benchmarkCost) : 'benchmark_pending',
            'improvement' => [
                'annual_saving_mid' => $annualSaving,
                'additional_beneficiaries_mid' => $additionalBeneficiaries,
            ],
            'mission_framing' => $hasBenchmark
                ? sprintf(
                    'This programme currently serves each beneficiary for NZD %s against a benchmark of NZD %s; any efficiency gain is framed as capacity for more mission delivery, not profit extraction.',
                    number_format($costPerBeneficiary, 2),
                    number_format($benchmarkCost, 2),
                )
                : sprintf(
                    'This programme currently serves each beneficiary for NZD %s. No official external NZ cost-per-beneficiary benchmark is available; compare against the organisation\'s own trend until the FSA platform has at least five comparable NPOs for this programme category and size band.',
                    number_format($costPerBeneficiary, 2),
                ),
            'stable_assumption_disclosure' => $disclosure,
            'benchmark_note' => $benchmark['benchmark_note'] ?? null,
            'impact_governance' => $this->impactGovernance($input),
            'projections' => $hasBenchmark ? [
                $this->projection('annual_saving', 'Annual saving / reinvestment capacity', $annualSaving, 'nzd'),
                $this->projection('additional_beneficiaries', 'Additional beneficiaries served', (float) $additionalBeneficiaries, 'beneficiaries'),
            ] : [
                $this->projection('benchmark_pending_baseline', 'Benchmark pending baseline', 0.0, 'nzd'),
            ],
        ];

        return $this->persistCalculation(
            engagement: $engagement,
            type: NpoValueCalculation::TYPE_COST_PER_BENEFICIARY,
            dimensionNumber: NpoHealthScorer::DIMENSION_FINANCIAL_SUSTAINABILITY,
            programmeType: $programmeType,
            sizeBand: $sizeBand,
            rating: (string) $result['rating'],
            inputs: [
                'programme_expenditure' => $programmeExpenditure,
                'beneficiary_count' => $beneficiaryCount,
                'programme_type' => $programmeType,
                'size_band' => $sizeBand,
            ],
            result: $result,
            benchmarkConfig: $benchmark,
            sourceAttributions: [
                [
                    'claim' => $hasBenchmark
                        ? 'Cost-per-beneficiary benchmark was selected from implemented FSA platform data by programme type and size band.'
                        : 'No official external NZ cost-per-beneficiary benchmark is available; FSA platform benchmarking is pending a sufficient comparable sample.',
                    'source_reference' => (string) $benchmark['source_reference'],
                ],
            ],
            disclosure: $disclosure,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function calculateFundingRisk(NpoEngagement $engagement, array $input = [], ?User $actor = null): NpoValueCalculation
    {
        $this->assertFullEngagement($engagement);

        $client = $engagement->client()->firstOrFail();
        $snapshot = $this->latestFinancialSnapshot($client);
        $financials = $this->financialInputs($snapshot, $input);
        $concentration = $this->funders->concentrationForClient($client);
        $largestFunderAmount = (float) $concentration['largest_funder_amount'];
        $concentrationRatio = $financials['annual_revenue'] > 0
            ? round($largestFunderAmount / $financials['annual_revenue'], 4)
            : 0.0;
        $thresholds = $this->fundingRiskThresholds();
        $runwayMonths = $financials['monthly_opex'] > 0
            ? round($financials['unrestricted_reserves'] / $financials['monthly_opex'], 2)
            : null;
        $renewalScenario = $this->renewalWeightedScenario($client);
        $reserveGap = $financials['monthly_opex'] > 0
            ? max(0.0, round(($financials['monthly_opex'] * 6) - $financials['unrestricted_reserves'], 2))
            : 0.0;
        $riskExposure = round($renewalScenario['weighted_value_at_risk'] + $reserveGap, 2);
        $concentrationRating = $this->concentrationRating($concentrationRatio, $thresholds);
        $runwayRating = $this->runwayRating($runwayMonths);
        $rating = $this->worstRating([$concentrationRating, $runwayRating]);
        $disclosure = 'Projection keeps current revenue, unrestricted reserves, monthly operating cost base, and recorded funder renewal probabilities stable; every projection carries a +/-15% uncertainty range.';

        $result = [
            'rating' => $rating,
            'concentration' => [
                ...$concentration,
                'annual_revenue' => $financials['annual_revenue'],
                'largest_funder_to_revenue_ratio' => $concentrationRatio,
                'rating' => $concentrationRating,
                'thresholds' => $thresholds['concentration'],
            ],
            'runway' => [
                'unrestricted_reserves' => $financials['unrestricted_reserves'],
                'monthly_opex' => $financials['monthly_opex'],
                'months' => $runwayMonths,
                'rating' => $runwayRating,
                'thresholds' => $thresholds['runway'],
            ],
            'renewal_weighted_scenario' => $renewalScenario,
            'reserve_gap_to_six_months' => $reserveGap,
            'mission_framing' => sprintf(
                'Funding risk value estimates NZD %s of mission delivery capacity exposed through renewal uncertainty and reserve runway pressure.',
                number_format($riskExposure, 0),
            ),
            'stable_assumption_disclosure' => $disclosure,
            'impact_governance' => $this->impactGovernance($input),
            'projections' => [
                $this->projection('risk_exposure', 'Funding risk value', $riskExposure, 'nzd'),
            ],
        ];

        return $this->persistCalculation(
            engagement: $engagement,
            type: NpoValueCalculation::TYPE_FUNDING_RISK,
            dimensionNumber: NpoHealthScorer::DIMENSION_SERVICE_OPERATIONS,
            programmeType: null,
            sizeBand: null,
            rating: $rating,
            inputs: [
                'annual_revenue' => $financials['annual_revenue'],
                'unrestricted_reserves' => $financials['unrestricted_reserves'],
                'monthly_opex' => $financials['monthly_opex'],
                'financial_snapshot_id' => $snapshot?->getKey(),
            ],
            result: $result,
            benchmarkConfig: $thresholds,
            sourceAttributions: $this->fundingRiskAttributions($snapshot, $thresholds),
            disclosure: $disclosure,
            actor: $actor,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clientSummary(Client $client): ?array
    {
        $engagement = NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();

        if (! $engagement instanceof NpoEngagement) {
            return null;
        }

        $calculations = NpoValueCalculation::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->orderByDesc('calculated_at')
            ->orderByDesc('created_at')
            ->get()
            ->unique('type')
            ->values();

        if ($calculations->isEmpty()) {
            return null;
        }

        return [
            'npo_engagement_id' => $engagement->id,
            'calculations' => $calculations
                ->map(fn (NpoValueCalculation $calculation): array => $this->calculationPayload($calculation))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $benchmarkConfig
     * @param  array<int, array<string, mixed>>  $sourceAttributions
     */
    private function persistCalculation(
        NpoEngagement $engagement,
        string $type,
        int $dimensionNumber,
        ?string $programmeType,
        ?string $sizeBand,
        string $rating,
        array $inputs,
        array $result,
        array $benchmarkConfig,
        array $sourceAttributions,
        string $disclosure,
        ?User $actor,
    ): NpoValueCalculation {
        $primaryProjection = $result['projections'][0] ?? null;
        if (! is_array($primaryProjection)) {
            throw new InvalidArgumentException('NPO value calculations require a primary projection.');
        }

        return DB::transaction(function () use ($engagement, $type, $dimensionNumber, $programmeType, $sizeBand, $rating, $inputs, $result, $benchmarkConfig, $sourceAttributions, $disclosure, $primaryProjection, $actor): NpoValueCalculation {
            /** @var NpoValueCalculation $calculation */
            $calculation = NpoValueCalculation::query()->create([
                'client_id' => $engagement->client_id,
                'npo_engagement_id' => $engagement->getKey(),
                'type' => $type,
                'dimension_number' => $dimensionNumber,
                'programme_type' => $programmeType,
                'size_band' => $sizeBand,
                'rating' => $rating,
                'projection_mid' => (float) $primaryProjection['mid'],
                'projection_low' => (float) $primaryProjection['low'],
                'projection_high' => (float) $primaryProjection['high'],
                'inputs' => $inputs,
                'result' => $result,
                'benchmark_config' => $benchmarkConfig,
                'source_attributions' => $sourceAttributions,
                'stable_assumption_disclosure' => $disclosure,
                'calculated_at' => now(),
            ]);

            $this->audit->record('npo.value_calculation.created', subject: $calculation, actor: $actor, after: [
                'type' => $type,
                'dimension_number' => $dimensionNumber,
                'rating' => $rating,
                'projection_mid' => $calculation->projection_mid,
                'uncertainty_rate' => NpoValueCalculation::UNCERTAINTY_RATE,
            ]);

            return $calculation->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function calculationPayload(NpoValueCalculation $calculation): array
    {
        return [
            'id' => $calculation->id,
            'type' => $calculation->type,
            'label' => $calculation->type === NpoValueCalculation::TYPE_COST_PER_BENEFICIARY
                ? 'Cost per beneficiary'
                : 'Funding risk value',
            'dimension_number' => $calculation->dimension_number,
            'rating' => $calculation->rating,
            'projection_mid' => $calculation->projection_mid,
            'projection_low' => $calculation->projection_low,
            'projection_high' => $calculation->projection_high,
            'mission_framing' => (string) ($calculation->result['mission_framing'] ?? ''),
            'stable_assumption_disclosure' => $calculation->stable_assumption_disclosure,
            'impact_governance' => $calculation->result['impact_governance'] ?? $this->defaultImpactGovernance(),
            'projections' => $calculation->result['projections'] ?? [],
            'calculated_at' => $calculation->calculated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function costPerBeneficiaryBenchmark(string $programmeType, string $sizeBand): array
    {
        $minimumSample = $this->minimumCpbComparableSample();

        $update = $this->latestImplementedLayerUpdate(LayerCadenceRegistry::LAYER_NPO_COST_PER_BENEFICIARY_BENCHMARKS);
        if ($update instanceof LearningUpdate) {
            foreach ($this->normaliseBenchmarkOverrides((array) data_get($update->proposed_change, 'benchmarks', [])) as $override) {
                $type = $this->slug((string) $override['programme_type']);
                $band = $this->slug((string) $override['size_band']);

                if ($type === $programmeType && $band === $sizeBand && (int) $override['sample_size'] >= $minimumSample) {
                    return [
                        'programme_type' => $programmeType,
                        'size_band' => $sizeBand,
                        'cost_per_beneficiary' => (float) $override['cost_per_beneficiary'],
                        'source' => 'fsa_platform_data',
                        'learning_update_id' => $update->getKey(),
                        'sample_size' => (int) $override['sample_size'],
                        'minimum_sample_size' => $minimumSample,
                        'source_reference' => "fsa_platform_data:n={$override['sample_size']}:learning_update:{$update->getKey()}",
                        'benchmark_note' => "FSA platform data, n={$override['sample_size']} comparable organisations.",
                    ];
                }
            }
        }

        return [
            'programme_type' => $programmeType,
            'size_band' => $sizeBand,
            'cost_per_beneficiary' => null,
            'source' => 'fsa_platform_pending_sample',
            'learning_update_id' => $update?->getKey(),
            'sample_size' => 0,
            'minimum_sample_size' => $minimumSample,
            'source_reference' => "fsa_platform_data:pending_minimum_sample:n<{$minimumSample}",
            'benchmark_note' => "No official external NZ benchmark exists. FSA platform benchmark pending at least {$minimumSample} comparable organisations.",
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<int, array{programme_type:string, size_band:string, cost_per_beneficiary:float, sample_size:int}>
     */
    private function normaliseBenchmarkOverrides(array $raw): array
    {
        if ($raw === []) {
            return [];
        }

        if (array_is_list($raw)) {
            return collect($raw)
                ->filter(fn (mixed $row): bool => is_array($row) && isset($row['programme_type'], $row['size_band'], $row['cost_per_beneficiary']))
                ->map(fn (array $row): array => [
                    'programme_type' => (string) $row['programme_type'],
                    'size_band' => (string) $row['size_band'],
                    'cost_per_beneficiary' => (float) $row['cost_per_beneficiary'],
                    'sample_size' => (int) ($row['sample_size'] ?? $row['comparable_organisations'] ?? $row['n'] ?? 0),
                ])
                ->values()
                ->all();
        }

        $rows = [];
        foreach ($raw as $programmeType => $bands) {
            if (! is_array($bands)) {
                continue;
            }

            foreach ($bands as $sizeBand => $value) {
                $rows[] = [
                    'programme_type' => (string) $programmeType,
                    'size_band' => (string) $sizeBand,
                    'cost_per_beneficiary' => (float) (is_array($value) ? ($value['cost_per_beneficiary'] ?? 0) : $value),
                    'sample_size' => (int) (is_array($value) ? ($value['sample_size'] ?? $value['comparable_organisations'] ?? $value['n'] ?? 0) : 0),
                ];
            }
        }

        return array_values(array_filter($rows, fn (array $row): bool => $row['cost_per_beneficiary'] > 0));
    }

    private function minimumCpbComparableSample(): int
    {
        $definition = $this->registry->definition(LayerCadenceRegistry::LAYER_NPO_COST_PER_BENEFICIARY_BENCHMARKS);

        return max(5, (int) data_get($definition, 'metadata.min_sample_guard.programme_type_size_band', 5));
    }

    /**
     * @return array<string, mixed>
     */
    private function fundingRiskThresholds(): array
    {
        $definition = $this->registry->definition(LayerCadenceRegistry::LAYER_NPO_FUNDING_CONCENTRATION_THRESHOLDS);
        $default = (array) data_get($definition, 'metadata.default_thresholds', ['high' => 40, 'medium' => 25]);
        $high = $this->normaliseRate($default['high'] ?? 40);
        $medium = $this->normaliseRate($default['medium'] ?? 25);
        $source = 'layer_37_default_thresholds';
        $learningUpdateId = null;

        $update = $this->latestApprovedLayerUpdate(LayerCadenceRegistry::LAYER_NPO_FUNDING_CONCENTRATION_THRESHOLDS);
        if ($update instanceof LearningUpdate) {
            $thresholds = (array) data_get($update->proposed_change, 'thresholds', []);
            $high = $this->normaliseRate($thresholds['high'] ?? $thresholds['concentration_high'] ?? $high);
            $medium = $this->normaliseRate($thresholds['medium'] ?? $thresholds['concentration_medium'] ?? $medium);
            $source = 'learning_layer_37';
            $learningUpdateId = $update->getKey();
        }

        if ($medium > $high) {
            throw new InvalidArgumentException('Funding concentration medium threshold cannot exceed the high threshold.');
        }

        return [
            'source' => $source,
            'learning_update_id' => $learningUpdateId,
            'source_reference' => $learningUpdateId !== null
                ? "learning_update:{$learningUpdateId}"
                : 'layer_37:default_funding_concentration_thresholds',
            'concentration' => [
                'high_above' => $high,
                'medium_from' => $medium,
            ],
            'runway' => [
                'critical_below_months' => 3,
                'high_below_months' => 6,
                'medium_below_months' => 12,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financialInputs(?FinancialSnapshot $snapshot, array $input): array
    {
        $annualRevenue = $this->nonNegativeNumber(
            $input['annual_revenue'] ?? data_get($snapshot?->profit_and_loss, 'revenue'),
            'Annual revenue',
        );
        $unrestrictedReserves = $this->nonNegativeNumber(
            $input['unrestricted_reserves']
                ?? data_get($snapshot?->balance_sheet, 'unrestricted_reserves')
                ?? data_get($snapshot?->balance_sheet, 'cash')
                ?? data_get($snapshot?->balance_sheet, 'cash_balance')
                ?? data_get($snapshot?->metrics, 'unrestricted_reserves'),
            'Unrestricted reserves',
        );
        $monthlyOpex = isset($input['monthly_opex'])
            ? $this->positiveNumber($input['monthly_opex'], 'Monthly operating expenditure')
            : $this->monthlyOpexFromSnapshot($snapshot);

        if ($annualRevenue <= 0) {
            throw new InvalidArgumentException('Funding risk value requires positive annual revenue.');
        }

        return [
            'annual_revenue' => $annualRevenue,
            'unrestricted_reserves' => $unrestrictedReserves,
            'monthly_opex' => $monthlyOpex,
        ];
    }

    private function monthlyOpexFromSnapshot(?FinancialSnapshot $snapshot): float
    {
        if (! $snapshot instanceof FinancialSnapshot) {
            throw new InvalidArgumentException('Funding risk value requires monthly operating expenditure or a financial snapshot.');
        }

        $operatingExpenses = $this->positiveNumber(data_get($snapshot->profit_and_loss, 'operating_expenses'), 'Operating expenses');
        $months = $this->snapshotMonths($snapshot);

        return round($operatingExpenses / $months, 2);
    }

    private function snapshotMonths(FinancialSnapshot $snapshot): float
    {
        $start = $snapshot->period_start;
        $end = $snapshot->period_end;

        return max(1.0, (float) ((($end->year - $start->year) * 12) + ($end->month - $start->month) + 1));
    }

    /**
     * @return array<string, mixed>
     */
    private function renewalWeightedScenario(Client $client): array
    {
        $records = ClientFunderRecord::query()
            ->with('funder')
            ->where('client_id', $client->getKey())
            ->where(function ($query): void {
                $query->whereNull('period_end')
                    ->orWhere('period_end', '>=', now()->toDateString());
            })
            ->get();
        $weighted = $records->sum(function (ClientFunderRecord $record): float {
            $probability = max(0, min(100, (int) ($record->renewal_probability ?? 50)));

            return (float) $record->grant_amount * ((100 - $probability) / 100);
        });

        return [
            'active_funder_records' => $records->count(),
            'weighted_value_at_risk' => round((float) $weighted, 2),
            'records' => $records
                ->map(fn (ClientFunderRecord $record): array => [
                    'funder_id' => $record->funder_id,
                    'funder_name' => $record->funder?->name,
                    'grant_amount' => (float) $record->grant_amount,
                    'renewal_probability' => $record->renewal_probability,
                    'weighted_value_at_risk' => round((float) $record->grant_amount * ((100 - max(0, min(100, (int) ($record->renewal_probability ?? 50)))) / 100), 2),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $thresholds
     * @return array<int, array<string, mixed>>
     */
    private function fundingRiskAttributions(?FinancialSnapshot $snapshot, array $thresholds): array
    {
        $attributions = [
            [
                'claim' => 'Funding concentration and renewal-weighted exposure use current client funder records.',
                'source_reference' => 'client_funder_records',
            ],
            [
                'claim' => 'Funding concentration thresholds came from Layer 37 configuration.',
                'source_reference' => (string) $thresholds['source_reference'],
            ],
        ];

        if ($snapshot instanceof FinancialSnapshot) {
            $attributions[] = [
                'claim' => 'Revenue, reserves, and operating expenditure came from the latest connected accounting snapshot.',
                'source_reference' => "financial_snapshot:{$snapshot->getKey()}",
            ];
        }

        return $attributions;
    }

    private function latestFinancialSnapshot(Client $client): ?FinancialSnapshot
    {
        return FinancialSnapshot::query()
            ->where('client_id', $client->getKey())
            ->orderByDesc('period_end')
            ->orderByDesc('pulled_at')
            ->first();
    }

    private function latestApprovedLayerUpdate(int $layerId): ?LearningUpdate
    {
        return LearningUpdate::query()
            ->where('layer_id', $layerId)
            ->whereIn('status', [LearningUpdate::STATUS_APPROVED, LearningUpdate::STATUS_IMPLEMENTED])
            ->latest('updated_at')
            ->latest('created_at')
            ->first();
    }

    private function latestImplementedLayerUpdate(int $layerId): ?LearningUpdate
    {
        return LearningUpdate::query()
            ->where('layer_id', $layerId)
            ->where('status', LearningUpdate::STATUS_IMPLEMENTED)
            ->latest('updated_at')
            ->latest('created_at')
            ->first();
    }

    private function costPerBeneficiaryRating(float $costPerBeneficiary, float $benchmark): string
    {
        return match (true) {
            $costPerBeneficiary <= ($benchmark * 0.85) => 'strong',
            $costPerBeneficiary <= ($benchmark * 1.15) => 'in_range',
            $costPerBeneficiary <= ($benchmark * 1.35) => 'watch',
            default => 'high_cost',
        };
    }

    /**
     * @param  array<string, mixed>  $thresholds
     */
    private function concentrationRating(float $ratio, array $thresholds): string
    {
        $high = (float) data_get($thresholds, 'concentration.high_above', 0.4);
        $medium = (float) data_get($thresholds, 'concentration.medium_from', 0.25);

        return match (true) {
            $ratio > $high => 'high',
            $ratio >= $medium => 'medium',
            default => 'low',
        };
    }

    private function runwayRating(?float $months): string
    {
        if ($months === null) {
            return 'critical';
        }

        return match (true) {
            $months < 3 => 'critical',
            $months < 6 => 'high',
            $months < 12 => 'medium',
            default => 'low',
        };
    }

    /**
     * @param  array<int, string>  $ratings
     */
    private function worstRating(array $ratings): string
    {
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];

        return collect($ratings)
            ->sortByDesc(fn (string $rating): int => $rank[$rating] ?? -1)
            ->first() ?? 'low';
    }

    /**
     * @return array<string, mixed>
     */
    private function projection(string $key, string $label, float $mid, string $unit): array
    {
        $mid = round(max(0.0, $mid), 2);

        return [
            'key' => $key,
            'label' => $label,
            'unit' => $unit,
            'low' => round($mid * (1 - NpoValueCalculation::UNCERTAINTY_RATE), 2),
            'mid' => $mid,
            'high' => round($mid * (1 + NpoValueCalculation::UNCERTAINTY_RATE), 2),
            'uncertainty' => [
                'rate' => NpoValueCalculation::UNCERTAINTY_RATE,
                'label' => '+/-15%',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function impactGovernance(array $input): array
    {
        $theoryOfChange = $input['theory_of_change'] ?? $input['outcomes_pathway'] ?? null;
        $stakeholders = $input['stakeholder_evidence'] ?? $input['stakeholders_consulted'] ?? [];
        $verificationSource = $input['external_verification_source'] ?? $input['impact_verification_source'] ?? null;
        $externallyVerified = (bool) ($input['externally_verified'] ?? false)
            || (is_string($verificationSource) && trim($verificationSource) !== '');
        $theoryCaptured = (is_string($theoryOfChange) && trim($theoryOfChange) !== '')
            || (is_array($theoryOfChange) && $theoryOfChange !== []);
        $stakeholdersCaptured = (is_string($stakeholders) && trim($stakeholders) !== '')
            || (is_array($stakeholders) && $stakeholders !== []);

        return [
            'verification_status' => $externallyVerified ? 'externally_verified' : 'internal_estimate_unverified',
            'verification_label' => $externallyVerified
                ? 'Externally verified impact evidence'
                : 'Internal estimate - not externally verified',
            'verification_source' => is_string($verificationSource) && trim($verificationSource) !== ''
                ? trim($verificationSource)
                : null,
            'theory_of_change_status' => $theoryCaptured ? 'captured' : 'not_captured',
            'stakeholder_involvement_status' => $stakeholdersCaptured ? 'captured' : 'not_captured',
            'principles_prompted' => [
                'involve_stakeholders',
                'understand_change',
                'value_what_matters',
                'do_not_overclaim',
                'verify_the_result',
            ],
            'limitation' => 'Mission value is an advisory estimate until stakeholder evidence, theory of change, and independent verification are recorded.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultImpactGovernance(): array
    {
        return [
            'verification_status' => 'internal_estimate_unverified',
            'verification_label' => 'Internal estimate - not externally verified',
            'verification_source' => null,
            'theory_of_change_status' => 'not_captured',
            'stakeholder_involvement_status' => 'not_captured',
            'principles_prompted' => [
                'involve_stakeholders',
                'understand_change',
                'value_what_matters',
                'do_not_overclaim',
                'verify_the_result',
            ],
            'limitation' => 'Mission value is an advisory estimate until stakeholder evidence, theory of change, and independent verification are recorded.',
        ];
    }

    private function sizeBand(float $beneficiaryCount): string
    {
        return match (true) {
            $beneficiaryCount < 100 => 'small',
            $beneficiaryCount < 1000 => 'medium',
            default => 'large',
        };
    }

    private function positiveNumber(mixed $value, string $label): float
    {
        $number = $this->number($value);
        if ($number === null || $number <= 0) {
            throw new InvalidArgumentException("{$label} must be greater than zero.");
        }

        return $number;
    }

    private function nonNegativeNumber(mixed $value, string $label): float
    {
        $number = $this->number($value);
        if ($number === null || $number < 0) {
            throw new InvalidArgumentException("{$label} must be zero or greater.");
        }

        return $number;
    }

    private function number(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function normaliseRate(mixed $value): float
    {
        $rate = $this->number($value) ?? 0.0;
        $rate = $rate > 1 ? $rate / 100 : $rate;

        return max(0.0, min(1.0, $rate));
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim(str_replace([' ', '-'], '_', $value)));

        return $slug !== '' ? $slug : 'default';
    }

    private function assertFullEngagement(NpoEngagement $engagement): void
    {
        if (! in_array($engagement->sub_type, [NpoEngagementSubType::StandardNpo, NpoEngagementSubType::SocialEnterprise], true)) {
            throw new InvalidArgumentException('NPO value calculations require a full NPO engagement.');
        }
    }
}
