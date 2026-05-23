<?php

declare(strict_types=1);

namespace App\Services\Integration\Linz;

use App\Services\Integration\Linz\Contracts\LinzClient;

final class FakeLinzClient implements LinzClient
{
    public function titleInterests(string $nzbn, ?string $address = null): array
    {
        return [
            [
                'title_reference' => 'LINZ-STUB-'.substr(hash('crc32b', $nzbn.$address), 0, 8),
                'nzbn' => $nzbn,
                'address' => $address,
                'interest_type' => 'lease_or_property_interest_review_required',
                'status' => 'fixture_pending_confirmation',
                'source_badge' => 'stub',
            ],
        ];
    }
}
