<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Services\Integration\CompaniesEntityRoleSearch\Contracts\CompaniesEntityRoleSearchClient;
use App\Services\Integration\CompaniesEntityRoleSearch\FakeCompaniesEntityRoleSearchClient;
use App\Services\Integration\CompaniesEntityRoleSearch\FallbackCompaniesEntityRoleSearchClient;
use App\Services\Integration\CompaniesEntityRoleSearch\LiveCompaniesEntityRoleSearchClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CompaniesEntityRoleSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('integrations.companies_entity_role_search.live', false);
        Config::set('integrations.companies_entity_role_search.api_key', null);
        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);

        $this->forgetClients();
    }

    public function test_stub_returns_canned_role_search_results(): void
    {
        $result = app(CompaniesEntityRoleSearchClient::class)->search('Smith', 'DIR');

        $this->assertSame('stub', $result['source_badge']);
        $this->assertFalse($result['degraded']);
        $this->assertSame('Belinda Jane Smith', $result['items'][0]['name']);
    }

    public function test_live_search_uses_mbie_subscription_header_and_query_contract(): void
    {
        Config::set('integrations.companies_entity_role_search.live', true);
        Config::set('integrations.companies_entity_role_search.api_key', 'role-search-key');
        Config::set('integrations.companies_entity_role_search.base_url', 'https://api.business.govt.nz/sandbox/companies-entity-role-search/v3');
        $this->forgetClients();

        Http::fake(fn () => Http::response([
            'items' => [
                ['name' => 'Smith Belinda', 'roleType' => 'DIR'],
            ],
        ], 200));

        $result = app(CompaniesEntityRoleSearchClient::class)->search('Smith Belinda', 'DIR', true, 2, 10);

        $this->assertSame('live', $result['source_badge']);
        $this->assertSame('Smith Belinda', $result['items'][0]['name']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Ocp-Apim-Subscription-Key', 'role-search-key')
            && str_starts_with($request->url(), 'https://api.business.govt.nz/sandbox/companies-entity-role-search/v3')
            && str_contains($request->url(), 'name=Smith%20Belinda')
            && str_contains($request->url(), 'role-type=DIR'));
    }

    private function forgetClients(): void
    {
        app()->forgetInstance(CompaniesEntityRoleSearchClient::class);
        app()->forgetInstance(FakeCompaniesEntityRoleSearchClient::class);
        app()->forgetInstance(LiveCompaniesEntityRoleSearchClient::class);
        app()->forgetInstance(FallbackCompaniesEntityRoleSearchClient::class);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);
    }
}
