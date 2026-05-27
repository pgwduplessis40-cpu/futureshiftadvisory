<?php

declare(strict_types=1);

namespace App\Services\Integration\NpoFunders;

use App\Services\Integration\Fixtures\FixtureRepository;
use App\Services\Integration\NpoFunders\Contracts\NpoFunderSourceClient;

final class FakeNpoFunderSourceClient implements NpoFunderSourceClient
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    public function fetch(string $source): array
    {
        return $this->withBadge(
            $this->fixtures->find('npo-funders', $this->normaliseSource($source)),
            'stub',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackSource(string $source): array
    {
        return $this->withBadge(
            $this->fixtures->find('npo-funders', $this->normaliseSource($source)),
            'stub_live_fallback',
            degraded: true,
        );
    }

    private function normaliseSource(string $source): string
    {
        return strtolower(trim($source));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withBadge(array $payload, string $badge, bool $degraded = false): array
    {
        return [
            ...$payload,
            'source_badge' => $badge,
            'degraded' => $degraded || (bool) ($payload['degraded'] ?? false),
        ];
    }
}
