<?php

declare(strict_types=1);

namespace App\Services\Fees;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\NpoEngagementSubType;
use App\Models\Client;
use App\Models\FeeCalculation;
use App\Models\FinancialSnapshot;
use App\Models\ImprovementOpportunity;
use App\Models\NpoEngagement;
use App\Models\RiskCost;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class FeeCalculator implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['fees.hours_based', 'fees.outcome_based', 'fees.entrepreneur', 'fees.governance_review', 'fees.npo_retainer'];
    }

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ServiceRateManager $serviceRates,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array{created_by_user_id?: int|string|null, npo_engagement_id?: string|null}  $options
     */
    public function calculate(Client $client, FeeMethod $method, array $inputs = [], array $options = []): FeeCalculation
    {
        $inputs = $this->normaliseInputs($method, $inputs);
        $pv = $this->pvTotals($client);
        $npoEngagement = $this->npoEngagement(
            $client,
            $inputs['npo_engagement_id'] ?? $options['npo_engagement_id'] ?? null,
            $method,
        );
        $result = match ($method) {
            FeeMethod::HoursBased => $this->hoursBased($client, $inputs, $npoEngagement),
            FeeMethod::OutcomeBased => $this->outcomeBased($client, $inputs, $pv),
            FeeMethod::Entrepreneur => $this->entrepreneur($inputs),
            FeeMethod::GovernanceReview => $this->governanceReview($inputs),
            FeeMethod::NpoRetainer => $this->npoRetainer($inputs, $npoEngagement),
        };

        if ($this->serviceRates->freeAccessModeActive()) {
            $result = $this->freeAccessResult($result);
        }

        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'npo_engagement_id' => $npoEngagement?->getKey(),
            'method' => $method,
            'inputs' => $inputs,
            'suggested_low' => $result['low'],
            'suggested_mid' => $result['mid'],
            'suggested_high' => $result['high'],
            'improvement_pv_total' => $pv['improvement'],
            'risk_cost_pv_total' => $pv['risk'],
            'roi_ratio' => $this->roiRatio($pv['improvement'], $result['mid']),
            'justification' => $result['justification'],
            'created_by_user_id' => $this->normaliseUserId($options['created_by_user_id'] ?? null),
        ]);

        $this->audit->record('fee_calculation.created', subject: $calculation, after: [
            'method' => $method->value,
            'npo_engagement_id' => $npoEngagement?->getKey(),
            'suggested_mid' => $calculation->suggested_mid,
            'improvement_pv_total' => $calculation->improvement_pv_total,
            'risk_cost_pv_total' => $calculation->risk_cost_pv_total,
            'roi_ratio' => $calculation->roi_ratio,
            'pro_bono' => (bool) data_get($result['justification'], 'pro_bono.flagged', false),
            'social_enterprise_rate_basis' => data_get($result['justification'], 'social_enterprise_rate_rule.basis'),
        ]);

        return $calculation->refresh();
    }

    /**
     * @return array{improvement:float, risk:float}
     */
    private function pvTotals(Client $client): array
    {
        return [
            'improvement' => round((float) ImprovementOpportunity::query()
                ->where('client_id', $client->getKey())
                ->active()
                ->sum('pv_of_impact'), 2),
            'risk' => round((float) RiskCost::query()
                ->where('client_id', $client->getKey())
                ->active()
                ->sum('pv_of_cost'), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{low:float, mid:float, high:float, justification:array<string, mixed>}
     */
    private function hoursBased(Client $client, array $inputs, ?NpoEngagement $npoEngagement): array
    {
        $services = $this->services($inputs, $this->npoServiceDiscountApplies($client, $npoEngagement));
        $total = round((float) collect($services)->sum('line_total'), 2);
        $retainerMonths = max(1, (int) ($inputs['retainer_months'] ?? 12));
        $retainerMonthlyFee = (bool) ($inputs['retainer_conversion'] ?? false)
            ? round($total / $retainerMonths, 2)
            : null;

        return [
            'low' => $total,
            'mid' => $total,
            'high' => $total,
            'justification' => [
                'method' => FeeMethod::HoursBased->value,
                'basis' => 'Sum of advisor-estimated service hours multiplied by service rates.',
                'services' => $services,
                'retainer' => [
                    'months' => $retainerMonths,
                    'monthly_fee' => $retainerMonthlyFee,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<int, array{name:string, hours:float, rate:float, base_rate:float, rate_source:string, currency:string, service_rate_setting_id:?string, service_rate_effective_from:?string, npo_service_discount_percent:float, npo_discount_applied:bool, line_total:float}>
     */
    private function services(array $inputs, bool $applyNpoServiceDiscount): array
    {
        $rawServices = is_array($inputs['services'] ?? null) ? $inputs['services'] : [];

        if ($rawServices === []) {
            $rawServices = [[
                'name' => $inputs['service_name'] ?? 'Advisory service',
                'hours' => $inputs['hours'] ?? 0,
            ]];
        }

        $rateSnapshot = $this->serviceRates->currentRateSnapshot($applyNpoServiceDiscount);
        $rate = $this->nonNegativeNumber($rateSnapshot['hourly_rate'], 'Service rate');
        $baseRate = $this->nonNegativeNumber($rateSnapshot['base_hourly_rate'], 'Base service rate');

        return array_values(array_map(function (array $service) use ($baseRate, $rate, $rateSnapshot): array {
            $hours = $this->nonNegativeNumber($service['hours'] ?? 0, 'Service hours');

            return [
                'name' => (string) ($service['name'] ?? 'Advisory service'),
                'hours' => $hours,
                'rate' => $rate,
                'base_rate' => $baseRate,
                'rate_source' => $rateSnapshot['rate_source'],
                'currency' => $rateSnapshot['currency'],
                'service_rate_setting_id' => $rateSnapshot['service_rate_setting_id'],
                'service_rate_effective_from' => $rateSnapshot['effective_from'],
                'npo_service_discount_percent' => $rateSnapshot['npo_service_discount_percent'],
                'npo_discount_applied' => $rateSnapshot['npo_discount_applied'],
                'line_total' => round($hours * $rate, 2),
            ];
        }, array_filter($rawServices, 'is_array')));
    }

    /**
     * @param  array{low:float, mid:float, high:float, justification:array<string, mixed>}  $result
     * @return array{low:float, mid:float, high:float, justification:array<string, mixed>}
     */
    private function freeAccessResult(array $result): array
    {
        return [
            'low' => 0.0,
            'mid' => 0.0,
            'high' => 0.0,
            'justification' => [
                ...$result['justification'],
                'free_access_mode' => [
                    'active' => true,
                    'reason' => 'Admin service rates are inactive; fees are calculated at zero until rates are activated.',
                    'nominal_range' => [
                        'low' => $result['low'],
                        'mid' => $result['mid'],
                        'high' => $result['high'],
                    ],
                    'stripe_required' => false,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function normaliseInputs(FeeMethod $method, array $inputs): array
    {
        if ($method !== FeeMethod::HoursBased) {
            if ($method === FeeMethod::NpoRetainer) {
                unset($inputs['npo_discount_rate'], $inputs['npo_discount_percent']);
            }

            return $inputs;
        }

        foreach (['rate', 'hourly_rate', 'service_rate'] as $rateKey) {
            unset($inputs[$rateKey]);
        }

        if (is_array($inputs['services'] ?? null)) {
            $inputs['services'] = array_map(function (mixed $service): mixed {
                if (! is_array($service)) {
                    return $service;
                }

                foreach (['rate', 'hourly_rate', 'service_rate'] as $rateKey) {
                    unset($service[$rateKey]);
                }

                return $service;
            }, $inputs['services']);
        }

        return $inputs;
    }

    private function npoServiceDiscountApplies(Client $client, ?NpoEngagement $npoEngagement): bool
    {
        return $client->engagement_type === EngagementType::NPO || $npoEngagement instanceof NpoEngagement;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array{improvement:float, risk:float}  $pv
     * @return array{low:float, mid:float, high:float, justification:array<string, mixed>}
     */
    private function outcomeBased(Client $client, array $inputs, array $pv): array
    {
        $revenue = $this->annualRevenue($client, $inputs);
        $complexity = (string) ($inputs['complexity'] ?? 'standard');
        $complexityMultiplier = $this->complexityMultiplier($complexity, $inputs);
        $valueShare = (float) ($inputs['value_share'] ?? 0.12);
        $revenueComponentRate = (float) ($inputs['revenue_component_rate'] ?? 0.01);
        $pvPool = $pv['improvement'] + $pv['risk'];
        $mid = max(2500.0, round((($pvPool * $valueShare) + ($revenue * $revenueComponentRate)) * $complexityMultiplier, 2));

        return [
            'low' => round($mid * 0.8, 2),
            'mid' => $mid,
            'high' => round($mid * 1.2, 2),
            'justification' => [
                'method' => FeeMethod::OutcomeBased->value,
                'basis' => 'Outcome fee range references improvement PV, risk-cost PV, revenue scale, and complexity.',
                'pv_reference' => [
                    'improvement_pv_total' => $pv['improvement'],
                    'risk_cost_pv_total' => $pv['risk'],
                    'pv_pool' => $pvPool,
                    'value_share' => $valueShare,
                ],
                'revenue_reference' => [
                    'annual_revenue' => $revenue,
                    'revenue_component_rate' => $revenueComponentRate,
                ],
                'complexity' => [
                    'level' => $complexity,
                    'multiplier' => $complexityMultiplier,
                ],
                'roi_ratio_basis' => 'improvement_pv_total / suggested_mid',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function annualRevenue(Client $client, array $inputs): float
    {
        if (is_numeric($inputs['annual_revenue'] ?? null)) {
            return round((float) $inputs['annual_revenue'], 2);
        }

        $snapshot = FinancialSnapshot::query()
            ->where('client_id', $client->getKey())
            ->latest('period_end')
            ->latest('pulled_at')
            ->first();

        return $snapshot instanceof FinancialSnapshot
            ? round((float) data_get($snapshot->profit_and_loss, 'revenue', 0), 2)
            : 0.0;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function complexityMultiplier(string $complexity, array $inputs): float
    {
        if (is_numeric($inputs['complexity_multiplier'] ?? null)) {
            return max(0.5, min(2.0, (float) $inputs['complexity_multiplier']));
        }

        return match ($complexity) {
            'low' => 0.8,
            'high' => 1.25,
            'very_high' => 1.5,
            default => 1.0,
        };
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{low:float, mid:float, high:float, justification:array<string, mixed>}
     */
    private function entrepreneur(array $inputs): array
    {
        $stage = (string) ($inputs['stage'] ?? 'pre_revenue');
        $range = match ($stage) {
            'idea' => ['low' => 750.0, 'mid' => 1500.0, 'high' => 2250.0],
            'growth' => ['low' => 2000.0, 'mid' => 3500.0, 'high' => 5000.0],
            default => ['low' => 1200.0, 'mid' => 2400.0, 'high' => 3600.0],
        };

        return [
            ...$range,
            'justification' => [
                'method' => FeeMethod::Entrepreneur->value,
                'basis' => 'Distinct lower-entry entrepreneur structure for early-stage founders.',
                'stage' => $stage,
                'lower_entry_path' => true,
                'payment_scope' => 'proposal_only_no_payment_collection',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{low:float, mid:float, high:float, justification:array<string, mixed>}
     */
    private function governanceReview(array $inputs): array
    {
        $band = $this->governanceReviewBand($inputs);
        $annualOperatingBudget = $this->annualOperatingBudget($inputs);
        $schedule = $this->governanceReviewSchedule();
        $range = $schedule[$band];
        $conversionPercent = $this->boundedPercent($inputs['conversion_credit_percent'] ?? 50);

        return [
            'low' => $range['low'],
            'mid' => $range['mid'],
            'high' => $range['high'],
            'justification' => [
                'method' => FeeMethod::GovernanceReview->value,
                'proposal_variant' => FeeMethod::GovernanceReview->value,
                'basis' => 'Fixed-fee Governance Review by NPO size band, with no retainer structure.',
                'fixed_fee' => true,
                'retainer_structure' => null,
                'size_band' => $band,
                'annual_operating_budget' => $annualOperatingBudget,
                'fixed_fee_schedule' => [
                    'small' => ['low' => 1500.0, 'mid' => 1500.0, 'high' => 1500.0],
                    'medium' => ['low' => 1800.0, 'mid' => 2000.0, 'high' => 2200.0],
                    'large' => ['low' => 2200.0, 'mid' => 2350.0, 'high' => 2500.0],
                ],
                'selected_range' => $range,
                'services' => [[
                    'name' => 'Governance Review fixed-fee engagement',
                    'fee_method' => FeeMethod::GovernanceReview->value,
                    'size_band' => $band,
                    'line_total' => $range['mid'],
                ]],
                'conversion_credit' => [
                    'percent' => $conversionPercent,
                    'amount_low' => round($range['low'] * ($conversionPercent / 100), 2),
                    'amount_mid' => round($range['mid'] * ($conversionPercent / 100), 2),
                    'amount_high' => round($range['high'] * ($conversionPercent / 100), 2),
                    'creditable_to' => 'first_retainer_month',
                    'advisor_discretion' => true,
                ],
                'payment_scope' => 'proposal_signoff_and_payment_authority',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{low:float, mid:float, high:float, justification:array<string, mixed>}
     */
    private function npoRetainer(array $inputs, ?NpoEngagement $engagement): array
    {
        if (! $engagement instanceof NpoEngagement) {
            throw new InvalidArgumentException('NPO retainer fee calculations require a full NPO engagement.');
        }

        $annualOperatingBudget = $this->annualOperatingBudget($inputs);
        $budgetBand = $this->npoBudgetBand($inputs, $annualOperatingBudget);
        $bandConfig = $this->npoRetainerSchedule()[$budgetBand];
        $smeTier = (string) ($bandConfig['sme_tier'] ?? 'foundation');
        $smeMonthly = $this->smeMonthlyRate($smeTier, $inputs);
        $discountSnapshot = $this->serviceRates->npoRetainerDiscountSnapshot();
        $discountRate = $discountSnapshot['discount_rate'];
        $socialEnterpriseRule = $this->socialEnterpriseRateRule($engagement, $inputs);
        $discountApplies = $socialEnterpriseRule['basis'] !== 'commercial_primary';
        $monthlyFee = $discountApplies
            ? round($smeMonthly * (1 - $discountRate), 2)
            : $smeMonthly;
        $retainerMonths = max(1, (int) ($inputs['retainer_months'] ?? $bandConfig['default_months'] ?? 12));
        $addonCount = max(0, (int) ($inputs['bespoke_accountability_reports'] ?? 0));
        $addonUnit = $this->nonNegativeNumber(
            $inputs['bespoke_accountability_report_fee'] ?? config('fees.npo.bespoke_accountability_report_addon', 650),
            'Bespoke accountability report fee',
        );
        $addonTotal = round($addonCount * $addonUnit, 2);
        $nominalMid = round(($monthlyFee * $retainerMonths) + $addonTotal, 2);
        $proBono = (bool) ($inputs['pro_bono'] ?? false);
        $proBonoYear = (int) ($inputs['pro_bono_year'] ?? now()->year);

        if ($proBono) {
            $this->assertProBonoCapacity($proBonoYear);
        }

        $low = $proBono ? 0.0 : round($nominalMid * 0.9, 2);
        $mid = $proBono ? 0.0 : $nominalMid;
        $high = $proBono ? 0.0 : round($nominalMid * 1.1, 2);

        return [
            'low' => $low,
            'mid' => $mid,
            'high' => $high,
            'justification' => [
                'method' => FeeMethod::NpoRetainer->value,
                'proposal_variant' => FeeMethod::NpoRetainer->value,
                'basis' => 'NPO retainer calculated from the configured SME tier rate with the admin retainer discount unless the social enterprise is commercial-primary.',
                'annual_operating_budget' => $annualOperatingBudget,
                'budget_band' => $budgetBand,
                'sme_tier' => $smeTier,
                'sme_monthly_rate' => $smeMonthly,
                'npo_discount_rate' => $discountRate,
                'npo_retainer_discount_percent' => $discountSnapshot['discount_percent'],
                'npo_retainer_discount_source' => $discountSnapshot['discount_source'],
                'service_rate_setting_id' => $discountSnapshot['service_rate_setting_id'],
                'service_rate_effective_from' => $discountSnapshot['effective_from'],
                'npo_discount_applied' => $discountApplies,
                'monthly_retainer_fee' => $monthlyFee,
                'retainer_months' => $retainerMonths,
                'bespoke_accountability_report_addon' => [
                    'count' => $addonCount,
                    'unit_fee' => $addonUnit,
                    'total' => $addonTotal,
                ],
                'pro_bono' => [
                    'flagged' => $proBono,
                    'year' => $proBono ? $proBonoYear : null,
                    'max_per_year' => (int) config('fees.npo.pro_bono.max_per_year', 2),
                    'tracked_separately' => $proBono,
                    'full_functionality' => $proBono,
                    'nominal_value' => $proBono ? $nominalMid : null,
                ],
                'social_enterprise_rate_rule' => $socialEnterpriseRule,
                'services' => [[
                    'name' => 'NPO advisory retainer',
                    'fee_method' => FeeMethod::NpoRetainer->value,
                    'budget_band' => $budgetBand,
                    'monthly_fee' => $monthlyFee,
                    'months' => $retainerMonths,
                    'line_total' => round($monthlyFee * $retainerMonths, 2),
                ], [
                    'name' => 'Bespoke accountability report add-on',
                    'fee_method' => FeeMethod::NpoRetainer->value,
                    'quantity' => $addonCount,
                    'line_total' => $addonTotal,
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function governanceReviewBand(array $inputs): string
    {
        $band = strtolower(trim((string) ($inputs['size_band'] ?? '')));

        if ($band !== '') {
            return match ($band) {
                'small', 'medium', 'large' => $band,
                default => throw new InvalidArgumentException('Governance Review size band must be small, medium, or large.'),
            };
        }

        $budget = $this->annualOperatingBudget($inputs);

        if ($budget === null) {
            throw new InvalidArgumentException('Governance Review fee requires a size band or annual operating budget.');
        }

        if ($budget <= 500000.0) {
            return 'small';
        }

        return $budget <= 2000000.0 ? 'medium' : 'large';
    }

    /**
     * @return array<string, array{low:float, mid:float, high:float}>
     */
    private function governanceReviewSchedule(): array
    {
        return [
            'small' => ['low' => 1500.0, 'mid' => 1500.0, 'high' => 1500.0],
            'medium' => ['low' => 1800.0, 'mid' => 2000.0, 'high' => 2200.0],
            'large' => ['low' => 2200.0, 'mid' => 2350.0, 'high' => 2500.0],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function annualOperatingBudget(array $inputs): ?float
    {
        if (! array_key_exists('annual_operating_budget', $inputs) || $inputs['annual_operating_budget'] === null || $inputs['annual_operating_budget'] === '') {
            return null;
        }

        return $this->nonNegativeNumber($inputs['annual_operating_budget'], 'Annual operating budget');
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function npoBudgetBand(array $inputs, ?float $annualOperatingBudget): string
    {
        $explicit = strtolower(trim((string) ($inputs['budget_band'] ?? '')));

        if ($explicit !== '') {
            if (! array_key_exists($explicit, $this->npoRetainerSchedule())) {
                throw new InvalidArgumentException('NPO retainer budget band is not configured.');
            }

            return $explicit;
        }

        if ($annualOperatingBudget === null) {
            throw new InvalidArgumentException('NPO retainer fee requires a budget band or annual operating budget.');
        }

        foreach ($this->npoRetainerSchedule() as $band => $config) {
            $max = $config['max_budget'] ?? null;

            if ($max === null || $annualOperatingBudget <= (float) $max) {
                return (string) $band;
            }
        }

        return 'large';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function npoRetainerSchedule(): array
    {
        $schedule = config('fees.npo.budget_bands', []);

        return is_array($schedule) ? $schedule : [];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function smeMonthlyRate(string $tier, array $inputs): float
    {
        if (is_numeric($inputs['sme_monthly_rate'] ?? null)) {
            return $this->nonNegativeNumber($inputs['sme_monthly_rate'], 'SME monthly rate');
        }

        $rate = data_get(config('fees.sme.retainer_monthly', []), $tier);

        if (! is_numeric($rate)) {
            throw new InvalidArgumentException("SME retainer tier [{$tier}] is not configured.");
        }

        return $this->nonNegativeNumber($rate, 'SME monthly rate');
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{basis:string, rationale:?string, engagement_social_enterprise:bool}
     */
    private function socialEnterpriseRateRule(NpoEngagement $engagement, array $inputs): array
    {
        $isSocialEnterprise = $engagement->sub_type === NpoEngagementSubType::SocialEnterprise
            || (bool) $engagement->social_enterprise;

        if (! $isSocialEnterprise) {
            return [
                'basis' => 'standard_npo',
                'rationale' => null,
                'engagement_social_enterprise' => false,
            ];
        }

        $basis = strtolower(trim((string) ($inputs['social_enterprise_rate_basis'] ?? 'mission_primary')));

        if (! in_array($basis, ['mission_primary', 'commercial_primary'], true)) {
            throw new InvalidArgumentException('Social enterprise rate basis must be mission_primary or commercial_primary.');
        }

        $rationale = trim((string) ($inputs['social_enterprise_rate_rationale'] ?? ''));

        if ($rationale === '') {
            throw new InvalidArgumentException('Social enterprise rate rule requires an advisor-recorded rationale.');
        }

        return [
            'basis' => $basis,
            'rationale' => $rationale,
            'engagement_social_enterprise' => true,
        ];
    }

    private function assertProBonoCapacity(int $year): void
    {
        $max = max(0, (int) config('fees.npo.pro_bono.max_per_year', 2));
        $used = FeeCalculation::query()
            ->where('method', FeeMethod::NpoRetainer->value)
            ->get()
            ->filter(fn (FeeCalculation $calculation): bool => (bool) data_get($calculation->justification, 'pro_bono.flagged', false)
                && (int) data_get($calculation->justification, 'pro_bono.year') === $year)
            ->count();

        if ($used >= $max) {
            throw new InvalidArgumentException("Pro-bono NPO provision limit reached for {$year}.");
        }
    }

    private function boundedPercent(mixed $value): float
    {
        return max(0.0, min(100.0, $this->nonNegativeNumber($value, 'Conversion credit percent')));
    }

    private function boundedRate(mixed $value, string $label): float
    {
        $rate = $this->nonNegativeNumber($value, $label);

        if ($rate > 1.0 && $rate <= 100.0) {
            $rate = $rate / 100;
        }

        return max(0.0, min(1.0, $rate));
    }

    private function roiRatio(float $improvementPv, float $fee): float
    {
        if ($fee <= 0) {
            return 0.0;
        }

        return round($improvementPv / $fee, 4);
    }

    private function nonNegativeNumber(mixed $value, string $label): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("{$label} must be numeric.");
        }

        return max(0.0, round((float) $value, 2));
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

    private function npoEngagement(Client $client, mixed $value, FeeMethod $method): ?NpoEngagement
    {
        $requireGovernanceReview = $method === FeeMethod::GovernanceReview;
        $requireFullNpo = $method === FeeMethod::NpoRetainer;
        $requested = $value instanceof NpoEngagement || (is_string($value) && $value !== '');

        if ($value instanceof NpoEngagement) {
            $engagement = $value;
        } elseif (is_string($value) && $value !== '') {
            $engagement = NpoEngagement::query()
                ->where('client_id', $client->getKey())
                ->find($value);
        } elseif ($value !== null && $value !== '') {
            throw new InvalidArgumentException('NPO engagement id must be a UUID string.');
        } else {
            $engagement = null;
        }

        if (! $engagement instanceof NpoEngagement) {
            if ($requested) {
                throw new InvalidArgumentException('NPO engagement must belong to the fee calculation client.');
            }

            if ($requireGovernanceReview) {
                throw new InvalidArgumentException('Governance Review fee calculations require a governance-review NPO engagement.');
            }

            if ($requireFullNpo) {
                throw new InvalidArgumentException('NPO retainer fee calculations require a full NPO engagement.');
            }

            return null;
        }

        if ((string) $engagement->client_id !== (string) $client->getKey()) {
            throw new InvalidArgumentException('NPO engagement must belong to the fee calculation client.');
        }

        if ($requireGovernanceReview && $engagement->sub_type !== NpoEngagementSubType::GovernanceReview) {
            throw new InvalidArgumentException('Governance Review fee calculations require a governance-review NPO engagement.');
        }

        if ($requireFullNpo && ! in_array($engagement->sub_type, [NpoEngagementSubType::StandardNpo, NpoEngagementSubType::SocialEnterprise], true)) {
            throw new InvalidArgumentException('NPO retainer fee calculations require a standard NPO or social-enterprise engagement.');
        }

        return $engagement;
    }
}
