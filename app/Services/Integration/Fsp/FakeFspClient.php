<?php

declare(strict_types=1);

namespace App\Services\Integration\Fsp;

use App\Services\Integration\Fixtures\FixtureRepository;
use App\Services\Integration\Fsp\Contracts\FspClient;
use App\Support\FspNumber;

final class FakeFspClient implements FspClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    public function lookup(string $fspNumber): array
    {
        return $this->withBadge($this->fixtures->find('fsp', $this->normalise($fspNumber)), 'stub');
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackLookup(string $fspNumber): array
    {
        return $this->withBadge($this->fixtures->find('fsp', $this->normalise($fspNumber)), 'stub_live_fallback', degraded: true);
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

    private function normalise(string $fspNumber): string
    {
        return FspNumber::normalise($fspNumber);
    }
}
