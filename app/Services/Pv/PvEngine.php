<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\PvCalculation;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class PvEngine implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['pv.dcf', 'pv.terminal_value'];
    }

    public function __construct(
        private readonly DiscountRateResolver $discountRates,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<int|string, int|float>  $cashFlows
     */
    public function presentValue(array $cashFlows, float $discountRate): float
    {
        $this->assertRate($discountRate);
        $total = 0.0;

        foreach ($cashFlows as $period => $amount) {
            if (! is_numeric($amount)) {
                throw new InvalidArgumentException('Cash flow amounts must be numeric.');
            }

            $periodNumber = is_numeric($period) ? (int) $period : count($cashFlows);
            $periodNumber = max(1, $periodNumber);
            $total += (float) $amount / ((1 + $discountRate) ** $periodNumber);
        }

        return round($total, 2);
    }

    /**
     * @param  array<int|string, int|float>  $cashFlows
     * @return array<int, array{period:int, amount:float, present_value:float}>
     */
    public function discountedCashFlows(array $cashFlows, float $discountRate): array
    {
        $this->assertRate($discountRate);
        $rows = [];

        foreach ($cashFlows as $period => $amount) {
            if (! is_numeric($amount)) {
                throw new InvalidArgumentException('Cash flow amounts must be numeric.');
            }

            $periodNumber = is_numeric($period) ? (int) $period : count($rows) + 1;
            $periodNumber = max(1, $periodNumber);
            $rows[] = [
                'period' => $periodNumber,
                'amount' => round((float) $amount, 2),
                'present_value' => round((float) $amount / ((1 + $discountRate) ** $periodNumber), 2),
            ];
        }

        return $rows;
    }

    public function terminalValue(float $terminalCashFlow, float $discountRate, float $growthRate, int $period): float
    {
        $this->assertRate($discountRate);

        if ($growthRate < 0 || $growthRate >= $discountRate) {
            throw new InvalidArgumentException('Growth rate must be non-negative and below the discount rate.');
        }

        if ($period < 1) {
            throw new InvalidArgumentException('Terminal value period must be at least one.');
        }

        $futureTerminalValue = ($terminalCashFlow * (1 + $growthRate)) / ($discountRate - $growthRate);

        return round($futureTerminalValue / ((1 + $discountRate) ** $period), 2);
    }

    /**
     * @param  array<int|string, int|float>  $cashFlows
     * @param  array<string, mixed>  $discountOptions
     */
    public function calculate(
        Client $client,
        PvType $type,
        DiscountMethod $discountMethod,
        array $cashFlows,
        array $discountOptions = [],
        ?CarbonInterface $asAt = null,
    ): PvCalculation {
        $asAt ??= now();
        $rate = $this->discountRates->resolve($client, $discountMethod, $discountOptions);
        $discounted = $this->discountedCashFlows($cashFlows, $rate->rate);
        $presentValue = $this->presentValue($cashFlows, $rate->rate);

        $calculation = PvCalculation::query()->create([
            'client_id' => $client->getKey(),
            'type' => $type,
            'discount_method' => $discountMethod,
            'discount_rate' => $rate->rate,
            'discount_rate_rationale' => $rate->rationale,
            'inputs' => [
                'cash_flows' => $this->normaliseCashFlows($cashFlows),
                'discount_options' => $discountOptions,
            ],
            'result' => [
                'present_value' => $presentValue,
                'discounted_cash_flows' => $discounted,
            ],
            'as_at' => $asAt,
            'created_by_user_id' => Auth::id(),
            'source_attributions' => $rate->sourceAttributions,
        ]);

        $this->audit->record(
            action: 'pv_calculation.created',
            subject: $calculation,
            after: [
                'type' => $type->value,
                'discount_method' => $discountMethod->value,
                'discount_rate' => $rate->rate,
                'present_value' => $presentValue,
            ],
        );

        return $calculation;
    }

    /**
     * @param  array<int|string, int|float>  $cashFlows
     * @return array<int, array{period:int, amount:float}>
     */
    private function normaliseCashFlows(array $cashFlows): array
    {
        $rows = [];

        foreach ($cashFlows as $period => $amount) {
            $rows[] = [
                'period' => is_numeric($period) ? max(1, (int) $period) : count($rows) + 1,
                'amount' => round((float) $amount, 2),
            ];
        }

        return $rows;
    }

    private function assertRate(float $discountRate): void
    {
        if ($discountRate <= 0 || $discountRate >= 1) {
            throw new InvalidArgumentException('Discount rate must be a decimal between 0 and 1.');
        }
    }
}
