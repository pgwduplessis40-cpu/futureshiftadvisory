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
}
