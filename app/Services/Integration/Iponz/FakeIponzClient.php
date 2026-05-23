<?php

declare(strict_types=1);

namespace App\Services\Integration\Iponz;

use App\Services\Integration\Iponz\Contracts\IponzClient;

final class FakeIponzClient implements IponzClient
{
    public function intellectualProperty(string $name, ?string $nzbn = null): array
    {
        return [
            [
                'iponz_reference' => 'IPONZ-STUB-'.substr(hash('crc32b', $name.(string) $nzbn), 0, 8),
                'owner_name' => $name,
                'nzbn' => $nzbn,
                'asset_type' => 'trade_mark_search',
                'status' => 'fixture_review_required',
                'source_badge' => 'stub',
            ],
        ];
    }
}
