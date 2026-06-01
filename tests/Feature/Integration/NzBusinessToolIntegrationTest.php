<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\IntegrationCall;
use App\Models\NzToolConnection;
use App\Models\User;
use App\Services\Integration\Cin7\Contracts\Cin7Client;
use App\Services\Integration\Cin7\FakeCin7Client;
use App\Services\Integration\Cin7\FallbackCin7Client;
use App\Services\Integration\Cin7\LiveCin7Client;
use App\Services\Integration\EmploymentHero\Contracts\EmploymentHeroClient;
use App\Services\Integration\EmploymentHero\FakeEmploymentHeroClient;
use App\Services\Integration\EmploymentHero\FallbackEmploymentHeroClient;
use App\Services\Integration\EmploymentHero\LiveEmploymentHeroClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Integration\Tradify\Contracts\TradifyClient;
use App\Services\Integration\Tradify\FakeTradifyClient;
use App\Services\Integration\Tradify\FallbackTradifyClient;
use App\Services\Integration\Tradify\LiveTradifyClient;
use App\Services\NzTools\NzToolClientResolver;
use App\Services\NzTools\NzToolConnector;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NzBusinessToolIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Cache::flush();

        foreach (array_keys(NzToolConnection::providerLabels()) as $provider) {
            Config::set("integrations.business_tools.{$provider}.live", false);
            Config::set("integrations.business_tools.{$provider}.client_secret", null);
            Config::set("integrations.business_tools.{$provider}.authorize_url", "https://{$provider}.example.test/oauth");
        }

        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        $this->forgetNzToolClients();
    }

    public function test_connection_stores_encrypted_token_and_syncs_fixture_snapshot(): void
    {
        [$advisor, $client] = $this->advisorAndClient();
        $state = $this->connectState($advisor, $client, NzToolConnection::PROVIDER_EMPLOYMENT_HERO);

        $connection = app(NzToolConnector::class)->connectFromCallback(
            client: $client,
            user: $advisor,
            provider: NzToolConnection::PROVIDER_EMPLOYMENT_HERO,
            code: 'fixture-code',
            state: $state,
        );

        $this->assertSame(NzToolConnection::PROVIDER_EMPLOYMENT_HERO, $connection->provider);
        $this->assertSame(NzToolConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('eh-tenant-fixture', $connection->external_tenant_id);
        $this->assertStringNotContainsString('employment-hero-access-token-fixture', $connection->token_envelope);
        $this->assertSame(KeyEnvelope::ALG_V1, $connection->token_envelope_meta['alg']);
        $this->assertSame(['employees.read', 'leave.read', 'payroll.read'], $connection->scopes);
        $this->assertDatabaseHas('audit_events', ['action' => 'nz_tool_connection.connected']);

        $token = json_decode(app(KeyEnvelope::class)->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('employment-hero-access-token-fixture', $token['access_token']);

        $snapshot = app(NzToolConnector::class)->sync($connection, $advisor);

        $this->assertSame(24, $snapshot['employee_count']);
        $this->assertSame('stub', $snapshot['source_badge']);
        $this->assertSame(112, $connection->refresh()->last_sync_payload['leave_liability_hours']);
        $this->assertNotNull($connection->last_synced_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'nz_tool_connection.synced']);
    }

    public function test_live_mode_with_config_credentials_records_resilience_fallback(): void
    {
        Config::set('integrations.business_tools.cin7.live', true);
        Config::set('integrations.business_tools.cin7.client_id', 'cin7-test-client');
        Config::set('integrations.business_tools.cin7.client_secret', 'cin7-test-secret');
        $this->forgetNzToolClients();

        [$advisor, $client] = $this->advisorAndClient();
        $state = $this->connectState($advisor, $client, NzToolConnection::PROVIDER_CIN7);
        Http::fake(fn () => Http::response(['error' => 'missing credential'], 401));

        $connection = app(NzToolConnector::class)->connectFromCallback(
            client: $client,
            user: $advisor,
            provider: NzToolConnection::PROVIDER_CIN7,
            code: 'fixture-code',
            state: $state,
        );

        Http::assertSentCount(1);
        $this->assertDatabaseHas('integration_calls', [
            'service' => NzToolConnection::PROVIDER_CIN7,
            'status' => IntegrationCall::STATUS_FAILURE,
            'attempt' => 1,
        ]);
        $this->assertDatabaseHas('integration_calls', [
            'service' => NzToolConnection::PROVIDER_CIN7,
            'status' => IntegrationCall::STATUS_FALLBACK,
            'attempt' => 1,
        ]);

        $token = json_decode(app(KeyEnvelope::class)->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('stub_live_fallback', $token['source_badge']);
        $this->assertTrue($token['degraded']);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function advisorAndClient(): array
    {
        app(RequestContext::class)->apply('system', []);

        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'Future Shift Advisory Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'registry_sources' => ['nzbn' => 'stub'],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function connectState(User $advisor, Client $client, string $provider): string
    {
        $location = app(NzToolConnector::class)->authorizeUrl($client, $advisor, $provider);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('https', parse_url($location, PHP_URL_SCHEME));
        $this->assertSame("{$provider}.example.test", parse_url($location, PHP_URL_HOST));
        $this->assertArrayHasKey('state', $query);

        return (string) $query['state'];
    }

    private function forgetNzToolClients(): void
    {
        foreach ([
            EmploymentHeroClient::class,
            FakeEmploymentHeroClient::class,
            LiveEmploymentHeroClient::class,
            FallbackEmploymentHeroClient::class,
            Cin7Client::class,
            FakeCin7Client::class,
            LiveCin7Client::class,
            FallbackCin7Client::class,
            TradifyClient::class,
            FakeTradifyClient::class,
            LiveTradifyClient::class,
            FallbackTradifyClient::class,
            RetryPolicy::class,
            ResilientHttp::class,
            NzToolClientResolver::class,
            NzToolConnector::class,
        ] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }
}
