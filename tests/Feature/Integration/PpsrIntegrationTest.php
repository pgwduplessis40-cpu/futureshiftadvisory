<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Services\Integration\Ppsr\Contracts\PpsrClient;
use App\Services\Integration\Ppsr\FakePpsrClient;
use App\Services\Integration\Ppsr\FallbackPpsrClient;
use App\Services\Integration\Ppsr\LivePpsrClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PpsrIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('integrations.ppsr.live', false);
        Config::set('integrations.ppsr.api_key', null);
        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);

        $this->forgetClients();
    }

    public function test_stub_returns_canned_security_interests(): void
    {
        $records = app(PpsrClient::class)->securityInterests('9429000000000');

        $this->assertSame('stub', $records[0]['source_badge']);
        $this->assertSame('9429000000000', $records[0]['debtor_nzbn']);
    }

    public function test_live_security_interest_search_posts_debtor_organisation_payload(): void
    {
        Config::set('integrations.ppsr.live', true);
        Config::set('integrations.ppsr.api_key', 'ppsr-key');
        Config::set('integrations.ppsr.base_url', 'https://api.business.govt.nz/sandbox/ppsr');
        Config::set('integrations.ppsr.security_interests_path', 'financing-statements-search');
        $this->forgetClients();

        Http::fake(fn () => Http::response([
            'items' => [
                [
                    'registration_id' => 'PPSR-LIVE-1',
                    'status' => 'current',
                ],
            ],
        ], 200));

        $records = app(PpsrClient::class)->securityInterests('9429000000000');

        $this->assertSame('PPSR-LIVE-1', $records[0]['registration_id']);
        $this->assertSame('live', $records[0]['source_badge']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.business.govt.nz/sandbox/ppsr/financing-statements-search'
            && $request->hasHeader('Ocp-Apim-Subscription-Key', 'ppsr-key')
            && $request['searchBy'] === 'debtorOrganisation'
            && $request['searchByDebtorOrganisation']['nzbn'] === '9429000000000');
    }

    private function forgetClients(): void
    {
        app()->forgetInstance(PpsrClient::class);
        app()->forgetInstance(FakePpsrClient::class);
        app()->forgetInstance(LivePpsrClient::class);
        app()->forgetInstance(FallbackPpsrClient::class);
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);
    }
}
