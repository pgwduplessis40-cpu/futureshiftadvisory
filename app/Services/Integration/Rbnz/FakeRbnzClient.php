<?php

declare(strict_types=1);

namespace App\Services\Integration\Rbnz;

use App\Services\Integration\Fixtures\FixtureRepository;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;

final class FakeRbnzClient implements RbnzClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    /**
     * @return array<string, mixed>
     */
    public function ocr(): array
    {
        $record = $this->fixtures->find('rbnz-economic', 'current.ocr');

        return $this->withBadge($record, 'stub');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exchangeRates(): array
    {
        $record = $this->fixtures->find('rbnz-economic', 'current');
        $rates = $record['exchange_rates'] ?? [];

        if (! is_array($rates)) {
            return [];
        }

        return array_values(array_map(
            fn (array $rate): array => $this->withBadge($rate, 'stub'),
            array_filter($rates, 'is_array'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackOcr(): array
    {
        return $this->withBadge($this->fixtures->find('rbnz-economic', 'current.ocr'), 'stub_live_fallback', degraded: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fallbackExchangeRates(): array
    {
        return array_values(array_map(
            fn (array $rate): array => $this->withBadge($rate, 'stub_live_fallback', degraded: true),
            array_filter((array) ($this->fixtures->find('rbnz-economic', 'current')['exchange_rates'] ?? []), 'is_array'),
        ));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function withBadge(array $record, string $badge, bool $degraded = false): array
    {
        return [
            ...$record,
            'source' => 'rbnz',
            'source_badge' => $badge,
            'degraded' => $degraded || (bool) ($record['degraded'] ?? false),
        ];
    }
}
