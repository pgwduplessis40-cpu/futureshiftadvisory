<?php

declare(strict_types=1);

namespace App\Services\Integration\Ird;

use App\Services\Integration\Fixtures\FixtureRepository;
use App\Services\Integration\Ird\Contracts\IrdClient;

final class FakeIrdClient implements IrdClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    public function gstStatus(string $nzbn): array
    {
        return $this->withBadge($this->fixtures->find('ird', $nzbn), 'stub');
    }

    public function fallbackGstStatus(string $nzbn): array
    {
        return $this->withBadge($this->fixtures->find('ird', $nzbn), 'stub_live_fallback', degraded: true);
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
