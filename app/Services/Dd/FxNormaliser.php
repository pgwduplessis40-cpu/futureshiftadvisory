<?php

declare(strict_types=1);

namespace App\Services\Dd;

use App\Models\BusinessValuation;
use App\Models\ExchangeRate;
use InvalidArgumentException;

final class FxNormaliser
{
    /**
     * @return array{
     *     source_currency:string,
     *     normalised_currency:string,
     *     exchange_rate_id:?string,
     *     source_to_nzd_rate:float,
     *     rate_timestamp:?string,
     *     normalised_values:array<string, mixed>,
     *     sensitivity:array<string, mixed>,
     *     source_attributions:array<int, array{claim:string, source_reference:string}>
     * }
     */
    public function normalise(BusinessValuation $valuation, string $sourceCurrency): array
    {
        $sourceCurrency = strtoupper($sourceCurrency ?: 'NZD');

        if ($sourceCurrency === 'NZD') {
            return $this->payload($valuation, $sourceCurrency, null, 1.0, now());
        }

        $rate = ExchangeRate::query()
            ->where('base_currency', 'NZD')
            ->where('quote_currency', $sourceCurrency)
            ->latest('rate_date')
            ->latest('fetched_at')
            ->first();

        if (! $rate instanceof ExchangeRate || $rate->rate <= 0) {
            throw new InvalidArgumentException("No RBNZ exchange rate exists for NZD/{$sourceCurrency}.");
        }

        return $this->payload(
            valuation: $valuation,
            sourceCurrency: $sourceCurrency,
            exchangeRate: $rate,
            sourceToNzdRate: round(1 / $rate->rate, 8),
            rateTimestamp: $rate->fetched_at,
        );
    }

    /**
     * @return array{
     *     source_currency:string,
     *     normalised_currency:string,
     *     exchange_rate_id:?string,
     *     source_to_nzd_rate:float,
     *     rate_timestamp:?string,
     *     normalised_values:array<string, mixed>,
     *     sensitivity:array<string, mixed>,
     *     source_attributions:array<int, array{claim:string, source_reference:string}>
     * }
     */
    private function payload(
        BusinessValuation $valuation,
        string $sourceCurrency,
        ?ExchangeRate $exchangeRate,
        float $sourceToNzdRate,
        mixed $rateTimestamp,
    ): array {
        $normalised = $this->normalisedValues($valuation, $sourceToNzdRate);

        return [
            'source_currency' => $sourceCurrency,
            'normalised_currency' => 'NZD',
            'exchange_rate_id' => $exchangeRate?->id,
            'source_to_nzd_rate' => $sourceToNzdRate,
            'rate_timestamp' => $rateTimestamp?->toIso8601String(),
            'normalised_values' => $normalised,
            'sensitivity' => [
                'minus_10_percent_rate' => $this->normalisedValues($valuation, round($sourceToNzdRate * 0.9, 8)),
                'base_rate' => $normalised,
                'plus_10_percent_rate' => $this->normalisedValues($valuation, round($sourceToNzdRate * 1.1, 8)),
            ],
            'source_attributions' => $this->sourceAttributions($sourceCurrency, $exchangeRate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalisedValues(BusinessValuation $valuation, float $sourceToNzdRate): array
    {
        return [
            'sde_value' => $this->range($valuation->sde_value, $sourceToNzdRate),
            'ebitda_value' => $this->range($valuation->ebitda_value, $sourceToNzdRate),
            'dcf_value' => $this->range($valuation->dcf_value, $sourceToNzdRate),
            'reconciled' => [
                'low' => $this->money($valuation->reconciled_low, $sourceToNzdRate),
                'mid' => $this->money($valuation->reconciled_mid, $sourceToNzdRate),
                'high' => $this->money($valuation->reconciled_high, $sourceToNzdRate),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $range
     * @return array<string, mixed>
     */
    private function range(?array $range, float $sourceToNzdRate): array
    {
        $range ??= [];
        $converted = $range;

        foreach (['low', 'mid', 'high', 'input', 'terminal_value', 'cash_flow_pv'] as $key) {
            if (isset($range[$key]) && is_numeric($range[$key])) {
                $converted[$key] = $this->money((float) $range[$key], $sourceToNzdRate);
            }
        }

        return $converted;
    }

    private function money(float $value, float $sourceToNzdRate): float
    {
        return round($value * $sourceToNzdRate, 2);
    }

    /**
     * @return array<int, array{claim:string, source_reference:string}>
     */
    private function sourceAttributions(string $sourceCurrency, ?ExchangeRate $exchangeRate): array
    {
        if (! $exchangeRate instanceof ExchangeRate) {
            return [[
                'claim' => 'DD valuation is already denominated in NZD.',
                'source_reference' => 'exchange_rate:native-nzd',
            ]];
        }

        return [[
            'claim' => "DD valuation converted from {$sourceCurrency} to NZD using the latest RBNZ exchange-rate row.",
            'source_reference' => "exchange_rate:{$exchangeRate->id}:NZD/{$sourceCurrency}",
        ]];
    }
}
