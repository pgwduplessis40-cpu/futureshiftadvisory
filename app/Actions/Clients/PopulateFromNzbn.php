<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\Ird\Contracts\IrdClient;
use App\Services\Integration\Nzbn\Contracts\NzbnClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Concurrency;

final class PopulateFromNzbn
{
    public function __construct(
        private readonly NzbnClient $nzbn,
        private readonly CompaniesOfficeClient $companiesOffice,
        private readonly IrdClient $ird,
    ) {}

    /**
     * @return array{
     *     lookup_key:string,
     *     nzbn:array<string, mixed>,
     *     companies_office:array<string, mixed>,
     *     ird:array<string, mixed>,
     *     summary:array<string, mixed>,
     *     source_badges:array<string, string>,
     *     degraded:bool
     * }
     */
    public function handle(string $nzbn): array
    {
        /** @var array{nzbn:array<string, mixed>, companies_office:array<string, mixed>, ird:array<string, mixed>} $results */
        $results = Concurrency::driver('sync')->run([
            'nzbn' => fn (): array => $this->nzbn->lookupByNzbn($nzbn),
            'companies_office' => fn (): array => $this->companiesOffice->companyProfile($nzbn),
            'ird' => fn (): array => $this->ird->gstStatus($nzbn),
        ]);

        $nzbnRecord = $results['nzbn'];
        $companyRecord = $results['companies_office'];
        $irdRecord = $results['ird'];

        return [
            'lookup_key' => $nzbn,
            'nzbn' => $nzbnRecord,
            'companies_office' => $companyRecord,
            'ird' => $irdRecord,
            'summary' => [
                'legal_name' => Arr::get($nzbnRecord, 'entity_name') ?: Arr::get($companyRecord, 'company_name'),
                'entity_type' => Arr::get($nzbnRecord, 'entity_type'),
                'status' => Arr::get($nzbnRecord, 'status') ?: Arr::get($companyRecord, 'status'),
                'address' => Arr::get($nzbnRecord, 'registered_address'),
                'gst_registered' => Arr::get($irdRecord, 'verification_status') === null
                    ? (bool) Arr::get($irdRecord, 'gst_registered', false)
                    : null,
                'gst_registration_status' => Arr::get($irdRecord, 'verification_label', 'Not verified with IRD'),
                'ird_verification_status' => Arr::get($irdRecord, 'verification_status'),
                'ird_regulatory_note' => Arr::get($irdRecord, 'regulatory_note'),
                'directors' => Arr::get($companyRecord, 'directors', []),
                'filing_status' => Arr::get($companyRecord, 'status'),
            ],
            'source_badges' => [
                'nzbn' => (string) Arr::get($nzbnRecord, 'source_badge', 'unknown'),
                'companies_office' => (string) Arr::get($companyRecord, 'source_badge', 'unknown'),
                'ird' => (string) Arr::get($irdRecord, 'source_badge', 'unknown'),
            ],
            'degraded' => (bool) Arr::get($nzbnRecord, 'degraded', false)
                || (bool) Arr::get($companyRecord, 'degraded', false)
                || (bool) Arr::get($irdRecord, 'degraded', false),
        ];
    }
}
