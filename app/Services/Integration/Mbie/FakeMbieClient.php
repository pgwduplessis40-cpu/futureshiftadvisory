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
}
