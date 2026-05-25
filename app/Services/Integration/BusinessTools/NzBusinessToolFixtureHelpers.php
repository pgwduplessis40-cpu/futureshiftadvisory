<?php

declare(strict_types=1);

namespace App\Services\Integration\BusinessTools;

use App\Models\NzToolConnection;
use App\Services\Integration\Fixtures\FixtureRepository;

trait NzBusinessToolFixtureHelpers
{
    public function __construct(private readonly FixtureRepository $fixtures) {}

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        return $this->withBadge($this->fixture('token'), 'stub');
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function businessSnapshot(NzToolConnection $connection, array $token): array
    {
        return $this->withBadge($this->fixture('snapshot'), 'stub');
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(NzToolConnection $connection, array $token): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackToken(): array
    {
        return $this->withBadge($this->fixture('token'), 'stub_live_fallback', degraded: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackSnapshot(): array
    {
        return $this->withBadge($this->fixture('snapshot'), 'stub_live_fallback', degraded: true);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function fixture(string $key): array;

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function withBadge(array $record, string $badge, bool $degraded = false): array
    {
        return [
            ...$record,
            'source' => $this->providerName(),
            'source_badge' => $badge,
            'degraded' => $degraded || (bool) ($record['degraded'] ?? false),
        ];
    }

    abstract protected function providerName(): string;
}
