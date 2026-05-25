<?php

declare(strict_types=1);

namespace App\Services\Integration\Mbie;

use App\Services\Integration\Fixtures\FixtureRepository;
use App\Services\Integration\Mbie\Contracts\MbieClient;

final class FakeMbieClient implements MbieClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function wageRates(): array
    {
        return $this->records('stub');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fallbackWageRates(): array
    {
        return $this->records('stub_live_fallback', degraded: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function valuationMultiples(): array
    {
        return $this->multipleRecords('stub');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fallbackValuationMultiples(): array
    {
        return $this->multipleRecords('stub_live_fallback', degraded: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function industryWaccRates(): array
    {
        return $this->waccRecords('stub');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fallbackIndustryWaccRates(): array
    {
        return $this->waccRecords('stub_live_fallback', degraded: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function records(string $badge, bool $degraded = false): array
    {
        $record = $this->fixtures->find('mbie-economic', 'current');
        $rates = $record['wage_rates'] ?? [];

        if (! is_array($rates)) {
            return [];
        }

        return array_values(array_map(
            fn (array $rate): array => [
                ...$rate,
                'source' => 'mbie',
                'source_badge' => $badge,
                'degraded' => $degraded || (bool) ($rate['degraded'] ?? false),
            ],
            array_filter($rates, 'is_array'),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function multipleRecords(string $badge, bool $degraded = false): array
    {
        $record = $this->fixtures->find('valuation-multiples', 'current');
        $multiples = $record['valuation_multiples'] ?? [];

        if (! is_array($multiples)) {
            return [];
        }

        return array_values(array_map(
            fn (array $multiple): array => [
                ...$multiple,
                'source' => (string) ($multiple['source'] ?? 'mbie'),
                'source_badge' => $badge,
                'degraded' => $degraded || (bool) ($multiple['degraded'] ?? false),
            ],
            array_filter($multiples, 'is_array'),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function waccRecords(string $badge, bool $degraded = false): array
    {
        $record = $this->fixtures->find('industry-wacc', 'current');
        $rates = $record['industry_wacc_rates'] ?? [];

        if (! is_array($rates)) {
            return [];
        }

        return array_values(array_map(
            fn (array $rate): array => [
                ...$rate,
                'source' => (string) ($rate['source'] ?? 'mbie'),
                'source_badge' => $badge,
                'degraded' => $degraded || (bool) ($rate['degraded'] ?? false),
            ],
            array_filter($rates, 'is_array'),
        ));
    }
}
