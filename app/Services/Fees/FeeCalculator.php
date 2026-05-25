<?php

declare(strict_types=1);

namespace App\Services\Fees;

use App\Enums\FeeMethod;
use App\Models\Client;
use App\Models\FeeCalculation;
use App\Models\FinancialSnapshot;
use App\Models\ImprovementOpportunity;
use App\Models\RiskCost;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class FeeCalculator implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['fees.hours_based', 'fees.outcome_based', 'fees.entrepreneur'];
    }

    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array{created_by_user_id?: int|string|null}  $options
     */
    public function calculate(Client $client, FeeMethod $method, array $inputs = [], array $options = []): FeeCalculation
    {
        $pv = $this->pvTotals($client);
        $result = match ($method) {
            FeeMethod::HoursBased => $this->hoursBased($inputs),
            FeeMethod::OutcomeBased => $this->outcomeBased($client, $inputs, $pv),
            FeeMethod::Entrepreneur => $this->entrepreneur($inputs),
        };

        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
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
            'suggested_mid' => $calculation->suggested_mid,
            'improvement_pv_total' => $calculation->improvement_pv_total,
            'risk_cost_pv_total' => $calculation->risk_cost_pv_total,
            'roi_ratio' => $calculation->roi_ratio,
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
                ->sum('pv_of_impact'), 2),
            'risk' => round((float) RiskCost::query()
                ->where('client_id', $client->getKey())
                ->sum('pv_of_cost'), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{low:float, mid:float, high:float, justification:array<string, mixed>}
     */
    private function hoursBased(array $inputs): array
    {
        $services = $this->services($inputs);
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
     * @return array<int, array{name:string, hours:float, rate:float, line_total:float}>
     */
    private function services(array $inputs): array
    {
        $rawServices = is_array($inputs['services'] ?? null) ? $inputs['services'] : [];

        if ($rawServices === []) {
            $rawServices = [[
                'name' => $inputs['service_name'] ?? 'Advisory service',
                'hours' => $inputs['hours'] ?? 0,
                'rate' => $inputs['hourly_rate'] ?? $inputs['rate'] ?? 0,
            ]];
        }

        return array_values(array_map(function (array $service): array {
            $hours = $this->nonNegativeNumber($service['hours'] ?? 0, 'Service hours');
            $rate = $this->nonNegativeNumber($service['rate'] ?? 0, 'Service rate');

            return [
                'name' => (string) ($service['name'] ?? 'Advisory service'),
                'hours' => $hours,
                'rate' => $rate,
                'line_total' => round($hours * $rate, 2),
            ];
        }, array_filter($rawServices, 'is_array')));
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
}
