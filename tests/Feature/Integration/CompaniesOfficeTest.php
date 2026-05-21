<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use Tests\TestCase;

final class CompaniesOfficeTest extends TestCase
{
    public function test_stub_returns_canned_company_profile_and_directors(): void
    {
        $client = app(CompaniesOfficeClient::class);

        $profile = $client->companyProfile('9429000000000');
        $directors = $client->directorsForCompany('9429000000000');

        $this->assertSame('9999999', $profile['company_number']);
        $this->assertSame('Future Shift Advisory Test Limited', $profile['company_name']);
        $this->assertSame('Registered', $profile['status']);
        $this->assertSame('stub', $profile['source_badge']);
        $this->assertFalse($profile['degraded']);
        $this->assertSame(['Aroha Campbell', 'James Patel'], array_column($directors, 'name'));
    }
}
