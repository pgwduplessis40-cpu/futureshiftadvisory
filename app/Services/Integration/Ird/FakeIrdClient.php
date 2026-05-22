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

    public function legislativeChanges(): array
    {
        return [
            [
                'source' => 'ird',
                'title' => 'Payroll and employer obligations watch',
                'statute' => 'Tax Administration Act 1994',
                'change_key' => 'ird-employer-obligations-2026',
                'effective_date' => '2026-07-01',
                'summary' => 'Review payroll settings and employer filing obligations for affected clients.',
                'source_url' => 'https://www.ird.govt.nz/employing-staff',
            ],
        ];
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
