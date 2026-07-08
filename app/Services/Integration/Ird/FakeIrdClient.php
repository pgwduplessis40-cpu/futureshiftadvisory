<?php

declare(strict_types=1);

namespace App\Services\Integration\Ird;

use App\Services\Integration\Ird\Contracts\IrdClient;

final class FakeIrdClient implements IrdClient
{
    public function gstStatus(string $nzbn): array
    {
        return IrdGatewayPolicy::gstStatusPayload($nzbn);
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
        return [
            ...IrdGatewayPolicy::gstStatusPayload($nzbn),
            'source_badge' => 'stub_live_fallback_regulatory_deferred',
            'degraded' => true,
        ];
    }
}
