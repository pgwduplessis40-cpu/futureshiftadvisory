<?php

declare(strict_types=1);

namespace App\Services\Pv;

use App\Enums\DiscountMethod;
use App\Models\Client;
use App\Models\EconomicIndicator;
use App\Models\IndustryWaccData;
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
        if (array_key_exists('rate', $options) || array_key_exists('industry_wacc', $options)) {
            $rate = $this->rate($options['rate'] ?? $options['industry_wacc']);
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

        $industryCode = $this->industryCode($client, $options);
        $wacc = IndustryWaccData::query()
            ->where('industry_code', $industryCode)
            ->whereNull('superseded_at')
            ->latest('quarter')
            ->latest('fetched_at')
            ->first();

        if (! $wacc instanceof IndustryWaccData) {
            $rate = self::DEFAULT_INDUSTRY_WACC;

            return new DiscountRateResult(
                method: DiscountMethod::IndustryWacc,
                rate: $rate,
                rationale: (string) ($options['rationale'] ?? "Default industry WACC assumption selected for {$client->legal_name}."),
                sourceAttributions: [
                    [
                        'claim' => 'Industry WACC default was used because no active industry WACC reference row was available.',
                        'source_reference' => 'industry_wacc:default',
                    ],
                ],
            );
        }

        return new DiscountRateResult(
            method: DiscountMethod::IndustryWacc,
            rate: round($wacc->wacc_rate, 6),
            rationale: (string) ($options['rationale'] ?? sprintf(
                'Industry WACC for %s uses %s %.2f%% from %s.',
                $client->legal_name,
                $wacc->industry_code,
                $wacc->wacc_rate * 100,
                strtoupper($wacc->source),
            )),
            sourceAttributions: [
                [
                    'claim' => 'Industry WACC discount rate uses the active industry WACC reference feed.',
                    'source_reference' => "industry_wacc_data:{$wacc->id}",
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

    /**
     * @param  array<string, mixed>  $options
     */
    private function industryCode(Client $client, array $options): string
    {
        $candidate = $options['industry_code'] ?? null;
        if (is_string($candidate) && trim($candidate) !== '') {
            return strtoupper(trim($candidate));
        }

        $sources = is_array($client->registry_sources) ? $client->registry_sources : [];

        foreach (['industry_code', 'industry', 'sector'] as $key) {
            $value = $sources[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return strtoupper(trim($value));
            }
        }

        return 'M6962';
    }
}
