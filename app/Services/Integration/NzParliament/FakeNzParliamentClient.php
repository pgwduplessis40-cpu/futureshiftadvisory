<?php

declare(strict_types=1);

namespace App\Services\Integration\NzParliament;

use App\Services\Integration\NzParliament\Contracts\NzParliamentClient;

final class FakeNzParliamentClient implements NzParliamentClient
{
    public function legislativeChanges(): array
    {
        return [
            [
                'source' => 'nz_parliament',
                'title' => 'Employment Relations amendment watch',
                'statute' => 'Employment Relations Act 2000',
                'change_key' => 'era-watch-2026',
                'effective_date' => '2026-07-01',
                'summary' => 'Monitor proposed changes that may affect employment-agreement and consultation obligations.',
                'source_url' => 'https://www.parliament.nz/en/pb/bills-and-laws/',
            ],
        ];
    }
}
