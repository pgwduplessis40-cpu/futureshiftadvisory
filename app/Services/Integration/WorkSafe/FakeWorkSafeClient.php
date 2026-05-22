<?php

declare(strict_types=1);

namespace App\Services\Integration\WorkSafe;

use App\Services\Integration\WorkSafe\Contracts\WorkSafeClient;

final class FakeWorkSafeClient implements WorkSafeClient
{
    public function legislativeChanges(): array
    {
        return [
            [
                'source' => 'worksafe',
                'title' => 'Health and safety guidance refresh',
                'statute' => 'Health and Safety at Work Act 2015',
                'change_key' => 'hswa-guidance-refresh-2026',
                'effective_date' => '2026-06-15',
                'summary' => 'Review client health and safety policies against updated WorkSafe guidance.',
                'source_url' => 'https://www.worksafe.govt.nz/laws-and-regulations/',
            ],
        ];
    }
}
