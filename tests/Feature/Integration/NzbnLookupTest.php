<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\IntegrationCall;
use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\Ird\Contracts\IrdClient;
use App\Services\Integration\Nzbn\Contracts\NzbnClient;
use App\Services\Integration\Nzbn\FakeNzbnClient;
use App\Services\Integration\Nzbn\FallbackNzbnClient;
use App\Services\Integration\Nzbn\LiveNzbnClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NzbnLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('integrations.nzbn.live', false);
        Config::set('integrations.nzbn.api_key', null);
        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);

        $this->forgetNzbnClients();
        app()->forgetInstance(RetryPolicy::class);
        app()->forgetInstance(ResilientHttp::class);
    }

    public function test_stub_returns_canned_nzbn_data(): void
    {
        $result = app(NzbnClient::class)->lookupByNzbn('9429000000000');

        $this->assertSame('9429000000000', $result['nzbn']);
        $this->assertSame('Future Shift Advisory Test Limited', $result['entity_name']);
        $this->assertSame('Registered', $result['status']);
        $this->assertSame('stub', $result['source_badge']);
        $this->assertFalse($result['degraded']);
    }

    public function test_live_mode_without_credential_degrades_through_resilience_layer(): void
    {
        Config::set('integrations.nzbn.live', true);
        Config::set('integrations.nzbn.api_key', null);
        $this->forgetNzbnClients();

        Http::fake(fn () => Http::response(['error' => 'missing credential'], 401));

        $result = app(NzbnClient::class)->lookupByNzbn('9429000000000');

        $this->assertSame('Future Shift Advisory Test Limited', $result['entity_name']);
        $this->assertSame('stub_live_fallback', $result['source_badge']);
        $this->assertTrue($result['degraded']);
        $this->assertArrayHasKey('correlation_id', $result);
        Http::assertSentCount(1);

        $this->assertDatabaseHas('integration_calls', [
            'service' => 'nzbn',
            'status' => IntegrationCall::STATUS_FAILURE,
            'attempt' => 1,
        ]);
        $this->assertDatabaseHas('integration_calls', [
            'service' => 'nzbn',
            'status' => IntegrationCall::STATUS_FALLBACK,
            'attempt' => 1,
        ]);
    }

    public function test_live_mode_without_credential_uses_cached_data_before_stub_fallback(): void
    {
        Config::set('integrations.nzbn.live', true);
        Config::set('integrations.nzbn.api_key', null);
        Cache::put('integration:nzbn:9429000000000', [
            'status_code' => 200,
            'json' => [
                'nzbn' => '9429000000000',
                'entity_name' => 'Cached Registry Limited',
                'status' => 'Registered',
            ],
            'body' => '{"entity_name":"Cached Registry Limited"}',
        ]);
        $this->forgetNzbnClients();

        Http::fake(fn () => Http::response(['error' => 'missing credential'], 401));

        $result = app(NzbnClient::class)->lookupByNzbn('9429000000000');

        $this->assertSame('Cached Registry Limited', $result['entity_name']);
        $this->assertSame('cached', $result['source_badge']);
        $this->assertFalse($result['degraded']);
        $this->assertArrayHasKey('correlation_id', $result);
        Http::assertSentCount(1);

        $this->assertDatabaseHas('integration_calls', [
            'service' => 'nzbn',
            'status' => IntegrationCall::STATUS_CACHED,
            'attempt' => 1,
        ]);
    }

    public function test_named_integration_scaffolds_have_interface_and_stub_files(): void
    {
        $scaffolds = [
            ['Nzbn/Contracts/NzbnClient.php', 'Nzbn/FakeNzbnClient.php'],
            ['CompaniesOffice/Contracts/CompaniesOfficeClient.php', 'CompaniesOffice/FakeCompaniesOfficeClient.php'],
            ['Ird/Contracts/IrdClient.php', 'Ird/FakeIrdClient.php'],
            ['Fsp/Contracts/FspClient.php', 'Fsp/FakeFspClient.php'],
            ['Ppsr/Contracts/PpsrClient.php', 'Ppsr/FakePpsrClient.php'],
            ['Linz/Contracts/LinzClient.php', 'Linz/FakeLinzClient.php'],
            ['Iponz/Contracts/IponzClient.php', 'Iponz/FakeIponzClient.php'],
            ['StatsNz/Contracts/StatsNzClient.php', 'StatsNz/FakeStatsNzClient.php'],
            ['Rbnz/Contracts/RbnzClient.php', 'Rbnz/FakeRbnzClient.php'],
            ['Mbie/Contracts/MbieClient.php', 'Mbie/FakeMbieClient.php'],
            ['NzParliament/Contracts/NzParliamentClient.php', 'NzParliament/FakeNzParliamentClient.php'],
            ['WorkSafe/Contracts/WorkSafeClient.php', 'WorkSafe/FakeWorkSafeClient.php'],
            ['Stripe/Contracts/StripeClient.php', 'Stripe/FakeStripeClient.php'],
            ['Windcave/Contracts/WindcaveClient.php', 'Windcave/FakeWindcaveClient.php'],
            ['Xero/Contracts/XeroClient.php', 'Xero/FakeXeroClient.php'],
            ['Myob/Contracts/MyobClient.php', 'Myob/FakeMyobClient.php'],
            ['QuickBooks/Contracts/QuickBooksClient.php', 'QuickBooks/FakeQuickBooksClient.php'],
            ['SesSendGrid/Contracts/SesSendGridClient.php', 'SesSendGrid/FakeSesSendGridClient.php'],
            ['Whisper/Contracts/WhisperClient.php', 'Whisper/FakeWhisperClient.php'],
            ['GoogleCalendar/Contracts/GoogleCalendarClient.php', 'GoogleCalendar/FakeGoogleCalendarClient.php'],
            ['MicrosoftGraph/Contracts/MicrosoftGraphClient.php', 'MicrosoftGraph/FakeMicrosoftGraphClient.php'],
        ];

        $this->assertGreaterThanOrEqual(20, count($scaffolds));

        foreach ($scaffolds as [$interface, $stub]) {
            $this->assertFileExists($this->integrationPath($interface));
            $this->assertFileExists($this->integrationPath($stub));
        }

        $this->assertInstanceOf(NzbnClient::class, app(NzbnClient::class));
        $this->assertInstanceOf(CompaniesOfficeClient::class, app(CompaniesOfficeClient::class));
        $this->assertInstanceOf(IrdClient::class, app(IrdClient::class));
    }

    private function integrationPath(string $path): string
    {
        return base_path('app/Services/Integration/'.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    private function forgetNzbnClients(): void
    {
        app()->forgetInstance(NzbnClient::class);
        app()->forgetInstance(FakeNzbnClient::class);
        app()->forgetInstance(LiveNzbnClient::class);
        app()->forgetInstance(FallbackNzbnClient::class);
    }
}
