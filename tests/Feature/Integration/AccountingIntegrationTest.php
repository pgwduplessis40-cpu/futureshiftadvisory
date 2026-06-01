<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Enums\EngagementType;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FinancialSnapshot;
use App\Models\IntegrationCall;
use App\Models\User;
use App\Services\Accounting\AccountingClientResolver;
use App\Services\Accounting\AccountingConnector;
use App\Services\Accounting\FinancialSnapshotPuller;
use App\Services\Integration\Figured\Contracts\FiguredClient;
use App\Services\Integration\Figured\FakeFiguredClient;
use App\Services\Integration\Figured\FallbackFiguredClient;
use App\Services\Integration\Figured\LiveFiguredClient;
use App\Services\Integration\Myob\Contracts\MyobClient;
use App\Services\Integration\Myob\FakeMyobClient;
use App\Services\Integration\Myob\FallbackMyobClient;
use App\Services\Integration\Myob\LiveMyobClient;
use App\Services\Integration\QuickBooks\Contracts\QuickBooksClient;
use App\Services\Integration\QuickBooks\FakeQuickBooksClient;
use App\Services\Integration\QuickBooks\FallbackQuickBooksClient;
use App\Services\Integration\QuickBooks\LiveQuickBooksClient;
use App\Services\Integration\Resilience\ResilientHttp;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Integration\Sage\Contracts\SageClient;
use App\Services\Integration\Sage\FakeSageClient;
use App\Services\Integration\Sage\FallbackSageClient;
use App\Services\Integration\Sage\LiveSageClient;
use App\Services\Integration\Workflowmax\Contracts\WorkflowmaxClient;
use App\Services\Integration\Workflowmax\FakeWorkflowmaxClient;
use App\Services\Integration\Workflowmax\FallbackWorkflowmaxClient;
use App\Services\Integration\Workflowmax\LiveWorkflowmaxClient;
use App\Services\Integration\Xero\Contracts\XeroClient;
use App\Services\Integration\Xero\FakeXeroClient;
use App\Services\Integration\Xero\FallbackXeroClient;
use App\Services\Integration\Xero\LiveXeroClient;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AccountingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Cache::flush();

        foreach (array_keys(AccountingConnection::providerLabels()) as $provider) {
            Config::set("integrations.accounting.{$provider}.live", false);
            Config::set("integrations.accounting.{$provider}.client_secret", null);
            Config::set("integrations.accounting.{$provider}.authorize_url", "https://{$provider}.example.test/oauth");
        }

        Config::set('integrations.retry.attempts', 1);
        Config::set('integrations.retry.base_delay_ms', 0);
        Config::set('integrations.retry.max_delay_ms', 0);
        $this->forgetAccountingClients();
    }

    public function test_oauth_callback_stores_encrypted_token_envelope(): void
    {
        [$advisor, $client] = $this->advisorAndClient();
        $state = $this->connectState($advisor, $client, AccountingConnection::PROVIDER_XERO);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.accounting.callback', [
                $client,
                AccountingConnection::PROVIDER_XERO,
                'code' => 'fixture-code',
                'state' => $state,
            ]))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        /** @var AccountingConnection $connection */
        $connection = AccountingConnection::query()->firstOrFail();

        $this->assertSame(AccountingConnection::PROVIDER_XERO, $connection->provider);
        $this->assertSame(AccountingConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame('xero-tenant-fixture', $connection->external_tenant_id);
        $this->assertStringNotContainsString('xero-access-token-fixture', $connection->token_envelope);
        $this->assertSame(KeyEnvelope::ALG_V1, $connection->token_envelope_meta['alg']);

        $token = json_decode(app(KeyEnvelope::class)->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('xero-access-token-fixture', $token['access_token']);
        $this->assertSame(['accounting.reports.read', 'offline_access'], $connection->scopes);
        $this->assertDatabaseHas('audit_events', ['action' => 'accounting_connection.connected']);
    }

    public function test_new_accounting_providers_connect_pull_and_revoke_through_fixture_clients(): void
    {
        $providers = [
            AccountingConnection::PROVIDER_SAGE => ['tenant' => 'sage-tenant-fixture', 'token' => 'sage-access-token-fixture', 'ratio' => 1.8],
            AccountingConnection::PROVIDER_FIGURED => ['tenant' => 'figured-farm-fixture', 'token' => 'figured-access-token-fixture', 'ratio' => 1.72],
            AccountingConnection::PROVIDER_WORKFLOWMAX => ['tenant' => 'workflowmax-account-fixture', 'token' => 'workflowmax-access-token-fixture', 'ratio' => 1.85],
        ];

        foreach ($providers as $provider => $expectations) {
            [$advisor, $client] = $this->advisorAndClient("{$provider}-accounting@example.test");
            $state = $this->connectState($advisor, $client, $provider);

            $this->actingAsMfa($advisor)
                ->get(route('advisor.clients.accounting.callback', [
                    $client,
                    $provider,
                    'code' => 'fixture-code',
                    'state' => $state,
                ]))
                ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

            /** @var AccountingConnection $connection */
            $connection = AccountingConnection::query()
                ->where('client_id', $client->getKey())
                ->where('provider', $provider)
                ->firstOrFail();

            $this->assertSame($expectations['tenant'], $connection->external_tenant_id);
            $this->assertStringNotContainsString((string) $expectations['token'], $connection->token_envelope);
            $token = json_decode(app(KeyEnvelope::class)->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($expectations['token'], $token['access_token']);

            $this->actingAsMfa($advisor)
                ->post(route('advisor.clients.accounting.pull', [$client, $connection]))
                ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

            /** @var FinancialSnapshot $snapshot */
            $snapshot = FinancialSnapshot::query()
                ->where('accounting_connection_id', $connection->getKey())
                ->firstOrFail();
            $this->assertSame($provider, $snapshot->provider);
            $this->assertSame($provider, $snapshot->source);
            $this->assertSame('stub', $snapshot->source_badge);
            $this->assertSame($expectations['ratio'], $snapshot->metrics['current_ratio']);

            $this->actingAsMfa($advisor)
                ->patch(route('advisor.clients.accounting.revoke', [$client, $connection]))
                ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

            $this->assertSame(AccountingConnection::STATUS_REVOKED, $connection->refresh()->status);
        }
    }

    public function test_new_accounting_providers_live_mode_with_config_credentials_records_resilience_fallback(): void
    {
        foreach ([AccountingConnection::PROVIDER_SAGE, AccountingConnection::PROVIDER_FIGURED, AccountingConnection::PROVIDER_WORKFLOWMAX] as $provider) {
            Config::set("integrations.accounting.{$provider}.live", true);
            Config::set("integrations.accounting.{$provider}.client_id", "{$provider}-test-client");
            Config::set("integrations.accounting.{$provider}.client_secret", "{$provider}-test-secret");
            $this->forgetAccountingClients();

            [$advisor, $client] = $this->advisorAndClient("{$provider}-live-accounting@example.test");
            $state = $this->connectState($advisor, $client, $provider);
            Http::fake(fn () => Http::response(['error' => 'missing credential'], 401));

            $this->actingAsMfa($advisor)
                ->get(route('advisor.clients.accounting.callback', [
                    $client,
                    $provider,
                    'code' => 'fixture-code',
                    'state' => $state,
                ]))
                ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

            $this->assertDatabaseHas('integration_calls', [
                'service' => $provider,
                'status' => IntegrationCall::STATUS_FAILURE,
                'attempt' => 1,
            ]);
            $this->assertDatabaseHas('integration_calls', [
                'service' => $provider,
                'status' => IntegrationCall::STATUS_FALLBACK,
                'attempt' => 1,
            ]);

            /** @var AccountingConnection $connection */
            $connection = AccountingConnection::query()
                ->where('client_id', $client->getKey())
                ->where('provider', $provider)
                ->firstOrFail();
            $token = json_decode(app(KeyEnvelope::class)->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('stub_live_fallback', $token['source_badge']);
            $this->assertTrue($token['degraded']);

            Config::set("integrations.accounting.{$provider}.live", false);
        }
    }

    public function test_accounting_resolver_accepts_all_spec_named_providers(): void
    {
        $resolver = app(AccountingClientResolver::class);

        $this->assertTrue(AccountingConnection::validProvider(AccountingConnection::PROVIDER_SAGE));
        $this->assertTrue(AccountingConnection::validProvider(AccountingConnection::PROVIDER_FIGURED));
        $this->assertTrue(AccountingConnection::validProvider(AccountingConnection::PROVIDER_WORKFLOWMAX));
        $this->assertInstanceOf(FallbackSageClient::class, $resolver->client(AccountingConnection::PROVIDER_SAGE));
        $this->assertInstanceOf(FallbackFiguredClient::class, $resolver->client(AccountingConnection::PROVIDER_FIGURED));
        $this->assertInstanceOf(FallbackWorkflowmaxClient::class, $resolver->client(AccountingConnection::PROVIDER_WORKFLOWMAX));
    }

    public function test_snapshot_pull_persists_append_only_fixture_payload(): void
    {
        [$advisor, $client] = $this->advisorAndClient();
        $connection = $this->connectedXero($advisor, $client);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.accounting.pull', [$client, $connection]))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        /** @var FinancialSnapshot $snapshot */
        $snapshot = FinancialSnapshot::query()->firstOrFail();
        $this->assertSame(AccountingConnection::PROVIDER_XERO, $snapshot->provider);
        $this->assertSame('xero', $snapshot->source);
        $this->assertSame('stub', $snapshot->source_badge);
        $this->assertFalse($snapshot->degraded);
        $this->assertSame(125000, $snapshot->profit_and_loss['revenue']);
        $this->assertSame(0.608, $snapshot->metrics['gross_margin']);
        $this->assertNotNull($connection->refresh()->last_snapshot_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'financial_snapshot.pulled']);

        $this->expectException(QueryException::class);
        $snapshot->forceFill(['source_badge' => 'tampered'])->save();
    }

    public function test_live_mode_with_config_credentials_logs_resilient_http_fallback(): void
    {
        Config::set('integrations.accounting.xero.live', true);
        Config::set('integrations.accounting.xero.client_id', 'xero-test-client');
        Config::set('integrations.accounting.xero.client_secret', 'xero-test-secret');
        $this->forgetAccountingClients();

        [$advisor, $client] = $this->advisorAndClient();
        $state = $this->connectState($advisor, $client, AccountingConnection::PROVIDER_XERO);
        Http::fake(fn () => Http::response(['error' => 'missing credential'], 401));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.accounting.callback', [
                $client,
                AccountingConnection::PROVIDER_XERO,
                'code' => 'fixture-code',
                'state' => $state,
            ]))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('integration_calls', [
            'service' => AccountingConnection::PROVIDER_XERO,
            'status' => IntegrationCall::STATUS_FAILURE,
            'attempt' => 1,
        ]);
        $this->assertDatabaseHas('integration_calls', [
            'service' => AccountingConnection::PROVIDER_XERO,
            'status' => IntegrationCall::STATUS_FALLBACK,
            'attempt' => 1,
        ]);

        /** @var AccountingConnection $connection */
        $connection = AccountingConnection::query()->firstOrFail();
        $token = json_decode(app(KeyEnvelope::class)->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('stub_live_fallback', $token['source_badge']);
        $this->assertTrue($token['degraded']);
    }

    public function test_revoke_marks_connection_revoked_without_deleting_snapshots(): void
    {
        [$advisor, $client] = $this->advisorAndClient();
        $connection = $this->connectedXero($advisor, $client);

        app(FinancialSnapshotPuller::class)->pull($connection, $advisor);
        $this->assertDatabaseCount('financial_snapshots', 1);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.clients.accounting.revoke', [$client, $connection]))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $connection->refresh();
        $this->assertSame(AccountingConnection::STATUS_REVOKED, $connection->status);
        $this->assertNotNull($connection->revoked_at);
        $this->assertSame((string) $advisor->getKey(), (string) $connection->revoked_by_user_id);
        $this->assertDatabaseCount('financial_snapshots', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'accounting_connection.revoked']);
    }

    public function test_client_show_exposes_accounting_payload(): void
    {
        [$advisor, $client] = $this->advisorAndClient();
        $connection = $this->connectedXero($advisor, $client);

        app(FinancialSnapshotPuller::class)->pull($connection, $advisor);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.accounting.providers.0.provider', AccountingConnection::PROVIDER_XERO)
                ->where('client.accounting.providers.0.connected', true)
                ->where('client.accounting.connections.0.provider', AccountingConnection::PROVIDER_XERO)
                ->where('client.accounting.connections.0.latest_snapshot.source_badge', 'stub')
                ->where('client.accounting.connections.0.latest_snapshot.metrics.current_ratio', 1.9)
            );
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

    private function connectedXero(User $advisor, Client $client): AccountingConnection
    {
        $state = $this->connectState($advisor, $client, AccountingConnection::PROVIDER_XERO);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.accounting.callback', [
                $client,
                AccountingConnection::PROVIDER_XERO,
                'code' => 'fixture-code',
                'state' => $state,
            ]))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        /** @var AccountingConnection $connection */
        $connection = AccountingConnection::query()->firstOrFail();

        return $connection;
    }

    private function connectState(User $advisor, Client $client, string $provider): string
    {
        $response = $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.accounting.connect', [$client, $provider]))
            ->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertIsString($location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('https', parse_url($location, PHP_URL_SCHEME));
        $this->assertSame("{$provider}.example.test", parse_url($location, PHP_URL_HOST));
        $this->assertArrayHasKey('state', $query);

        return (string) $query['state'];
    }

    private function forgetAccountingClients(): void
    {
        foreach ([
            XeroClient::class,
            FakeXeroClient::class,
            LiveXeroClient::class,
            FallbackXeroClient::class,
            MyobClient::class,
            FakeMyobClient::class,
            LiveMyobClient::class,
            FallbackMyobClient::class,
            QuickBooksClient::class,
            FakeQuickBooksClient::class,
            LiveQuickBooksClient::class,
            FallbackQuickBooksClient::class,
            SageClient::class,
            FakeSageClient::class,
            LiveSageClient::class,
            FallbackSageClient::class,
            FiguredClient::class,
            FakeFiguredClient::class,
            LiveFiguredClient::class,
            FallbackFiguredClient::class,
            WorkflowmaxClient::class,
            FakeWorkflowmaxClient::class,
            LiveWorkflowmaxClient::class,
            FallbackWorkflowmaxClient::class,
            RetryPolicy::class,
            ResilientHttp::class,
            AccountingClientResolver::class,
            AccountingConnector::class,
            FinancialSnapshotPuller::class,
        ] as $abstract) {
            app()->forgetInstance($abstract);
        }
    }
}
