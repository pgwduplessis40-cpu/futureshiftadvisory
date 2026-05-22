<?php

declare(strict_types=1);

namespace App\Services\Integration\StatsNz;

use App\Services\Integration\Fixtures\FixtureRepository;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;

final class FakeStatsNzClient implements StatsNzClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function indicators(): array
    {
        return $this->records('stub');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fallbackIndicators(): array
    {
        return $this->records('stub_live_fallback', degraded: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function records(string $badge, bool $degraded = false): array
    {
        $record = $this->fixtures->find('stats-nz-economic', 'current');
        $indicators = $record['indicators'] ?? [];

        if (! is_array($indicators)) {
            return [];
        }

        return array_values(array_map(
            fn (array $indicator): array => [
                ...$indicator,
                'source' => 'stats_nz',
                'source_badge' => $badge,
                'degraded' => $degraded || (bool) ($indicator['degraded'] ?? false),
            ],
            array_filter($indicators, 'is_array'),
        ));
    }
}
