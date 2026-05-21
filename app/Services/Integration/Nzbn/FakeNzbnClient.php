<?php

declare(strict_types=1);

namespace App\Services\Integration\Nzbn;

use App\Services\Integration\Fixtures\FixtureRepository;
use App\Services\Integration\Nzbn\Contracts\NzbnClient;

final class FakeNzbnClient implements NzbnClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    public function lookupByNzbn(string $nzbn): array
    {
        return $this->withBadge($this->fixtures->find('nzbn', $nzbn), 'stub');
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackLookupByNzbn(string $nzbn): array
    {
        return $this->withBadge($this->fixtures->find('nzbn', $nzbn), 'stub_live_fallback', degraded: true);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function withBadge(array $record, string $badge, bool $degraded = false): array
    {
        return [
            ...$record,
            'source_badge' => $badge,
            'degraded' => $degraded || (bool) ($record['degraded'] ?? false),
        ];
    }
}
