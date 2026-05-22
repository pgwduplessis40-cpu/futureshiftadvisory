<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Models\Client;
use App\Models\EconomicIndicator;
use InvalidArgumentException;

final class DiscountRateResolver
{
    private const DEFAULT_RISK_PREMIUM = 0.06;

    private const DEFAULT_INDUSTRY_WACC = 0.12;

    /**
     * @param  array<string, mixed>  $options
     */
    public function resolve(Client $client, DiscountMethod $method, array $options = []): DiscountRateResult
    {
        return match ($method) {
            DiscountMethod::OcrLinked => $this->ocrLinked($client, $options),
            DiscountMethod::IndustryWacc => $this->industryWacc($client, $options),
            DiscountMethod::AdvisorConfigured => $this->configured($method, $options, 'advisor_configured_rate'),
            DiscountMethod::ClientInputted => $this->configured($method, $options, 'client_inputted_rate'),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function ocrLinked(Client $client, array $options): DiscountRateResult
    {
        $ocr = EconomicIndicator::query()
            ->where('indicator', EconomicIndicator::OCR)
            ->latest('period_date')
            ->latest('fetched_at')
            ->first();

        if (! $ocr instanceof EconomicIndicator) {
            throw new InvalidArgumentException('OCR-linked discount rate requires an OCR economic indicator.');
        }

        $riskPremium = $this->rate($options['risk_premium'] ?? self::DEFAULT_RISK_PREMIUM);
        $ocrRate = $ocr->value / 100;
        $rate = $ocrRate + $riskPremium;

        return new DiscountRateResult(
            method: DiscountMethod::OcrLinked,
            rate: round($rate, 6),
            rationale: sprintf(
                'OCR-linked rate for %s uses OCR %.2f%% plus %.2f%% risk premium.',
                $client->legal_name,
                $ocr->value,
                $riskPremium * 100,
            ),
            sourceAttributions: [
                [
                    'claim' => 'OCR-linked discount rate uses the latest OCR indicator.',
                    'source_reference' => "economic_indicator:{$ocr->id}",
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function industryWacc(Client $client, array $options): DiscountRateResult
    {
        $rate = $this->rate($options['rate'] ?? $options['industry_wacc'] ?? self::DEFAULT_INDUSTRY_WACC);
        $source = (string) ($options['source_reference'] ?? 'advisor:industry_wacc_assumption');

        return new DiscountRateResult(
            method: DiscountMethod::IndustryWacc,
            rate: $rate,
            rationale: (string) ($options['rationale'] ?? "Industry WACC assumption selected for {$client->legal_name}."),
            sourceAttributions: [
                [
                    'claim' => 'Industry WACC discount rate supplied as an advisor-reviewed assumption.',
                    'source_reference' => $source,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function configured(DiscountMethod $method, array $options, string $defaultSource): DiscountRateResult
    {
        if (! array_key_exists('rate', $options)) {
            throw new InvalidArgumentException("{$method->value} discount rate requires a rate option.");
        }

        $rate = $this->rate($options['rate']);
        $rationale = trim((string) ($options['rationale'] ?? ''));

        if ($rationale === '') {
            throw new InvalidArgumentException("{$method->value} discount rate requires a rationale.");
        }

        return new DiscountRateResult(
            method: $method,
            rate: $rate,
            rationale: $rationale,
            sourceAttributions: [
                [
                    'claim' => "{$method->value} discount rate was explicitly supplied with rationale.",
                    'source_reference' => (string) ($options['source_reference'] ?? $defaultSource),
                ],
            ],
        );
    }

    private function rate(mixed $value): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Discount rate must be numeric.');
        }

        $rate = (float) $value;

        if ($rate <= 0 || $rate >= 1) {
            throw new InvalidArgumentException('Discount rate must be a decimal between 0 and 1.');
        }

        return round($rate, 6);
    }
}
