<?php

declare(strict_types=1);

namespace App\Services\Integration\Ppsr;

use App\Services\Integration\Ppsr\Contracts\PpsrClient;

final class FakePpsrClient implements PpsrClient
{
    public function securityInterests(string $nzbn): array
    {
        return [
            [
                'registration_id' => 'PPSR-STUB-'.substr(hash('crc32b', $nzbn), 0, 8),
                'debtor_nzbn' => $nzbn,
                'collateral_type' => 'all_present_and_after_acquired_property',
                'secured_party' => 'Fixture Bank Limited',
                'status' => 'current',
                'source_badge' => 'stub',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fallbackSecurityInterests(string $nzbn): array
    {
        return collect($this->securityInterests($nzbn))
            ->map(fn (array $record): array => [
                ...$record,
                'source_badge' => 'stub_live_fallback',
                'degraded' => true,
            ])
            ->all();
    }
}
