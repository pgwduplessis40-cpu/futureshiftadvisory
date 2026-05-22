<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\EngagementType;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\FinancialAlert;
use App\Models\FinancialSnapshot;
use App\Models\User;
use App\Services\Accounting\AccountingClientResolver;
use App\Services\Accounting\FinancialSnapshotPuller;
use App\Services\Accounting\HealthMonitor;
use App\Services\Integration\Xero\Contracts\XeroClient;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class FinancialMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Config::set('integrations.accounting.monitoring.enabled', true);
        Config::set('integrations.accounting.monitoring.net_profit_drop_threshold', 0.2);
        Config::set('integrations.accounting.monitoring.revenue_drop_threshold', 0.15);
        Config::set('integrations.accounting.monitoring.cash_flow_drop_threshold', 0.2);
        Config::set('integrations.accounting.monitoring.gross_margin_drop_points', 0.1);
        Config::set('integrations.accounting.monitoring.current_ratio_floor', 1.2);
    }

    public function test_deterioration_between_consecutive_snapshots_raises_cited_financial_alert(): void
    {
        Mail::fake();
        [$advisor, $client, $connection] = $this->clientConnectionAndAdvisor();
        $previous = $this->snapshot($client, $connection, '2026-03-31', netProfit: 50000);
        $this->bindXeroSnapshot($this->snapshotPayload('2026-04-30', netProfit: 30000));

        $this->artisan('financial-monitoring:run', ['--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('financial_snapshots', 2);
        $alert = FinancialAlert::query()->firstOrFail();
        $current = FinancialSnapshot::query()
            ->where('id', '!=', $previous->id)
            ->firstOrFail();

        $this->assertSame($client->id, $alert->client_id);
        $this->assertSame('net_profit', $alert->metric);
        $this->assertSame(FinancialAlert::CATEGORY_PROFITABILITY, $alert->category);
        $this->assertSame(50000.0, $alert->previous_value);
        $this->assertSame(30000.0, $alert->current_value);
        $this->assertEqualsWithDelta(-0.4, (float) $alert->change_percent, 0.0001);
        $this->assertSame($previous->id, $alert->citation['previous']['snapshot_id']);
        $this->assertSame($current->id, $alert->citation['current']['snapshot_id']);
        $this->assertEqualsWithDelta(50000.0, (float) $alert->citation['previous']['value'], 0.0001);
        $this->assertEqualsWithDelta(30000.0, (float) $alert->citation['current']['value'], 0.0001);
        $this->assertSame("financial_snapshot:{$previous->id}:profit_and_loss.net_profit", $alert->citation['previous']['source_reference']);
        $this->assertStringContainsString('$50,000.00', $alert->detail);
        $this->assertStringContainsString('$30,000.00', $alert->detail);

        $notification = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $advisor->id)
            ->first();
        $this->assertNotNull($notification);
        $this->assertSame('financial.alert.created', $notification->type);
        $this->assertSame('normal', $notification->urgency);

        $decision = $this->decision($notification->channel_decision);
        $this->assertSame(CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY, $decision['preference_channel']);
        $this->assertFalse($decision['mail_now']);
        $this->assertFalse($decision['bypassed_preference']);
        $this->assertNotContains('mail', $decision['channels']);
        Mail::assertNothingSent();
    }

    public function test_stable_snapshot_pair_does_not_raise_false_alert(): void
    {
        [$advisor, $client, $connection] = $this->clientConnectionAndAdvisor();
        $this->snapshot($client, $connection, '2026-03-31', netProfit: 50000);
        $this->bindXeroSnapshot($this->snapshotPayload('2026-04-30', netProfit: 50000));

        $this->artisan('financial-monitoring:run', ['--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('financial_snapshots', 2);
        $this->assertDatabaseCount('financial_alerts', 0);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $advisor->id,
            'type' => 'financial.alert.created',
        ]);
    }

    public function test_monitoring_command_is_gated_by_feature_flag(): void
    {
        Config::set('integrations.accounting.monitoring.enabled', false);
        [$advisor, $client, $connection] = $this->clientConnectionAndAdvisor();
        $this->snapshot($client, $connection, '2026-03-31', netProfit: 50000);
        $this->bindXeroSnapshot($this->snapshotPayload('2026-04-30', netProfit: 30000));

        $this->artisan('financial-monitoring:run')
            ->expectsOutput('Continuous financial monitoring is disabled.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('financial_snapshots', 1);
        $this->assertDatabaseCount('financial_alerts', 0);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $advisor->id,
            'type' => 'financial.alert.created',
        ]);
    }

    /**
     * @return array{0: User, 1: Client, 2: AccountingConnection}
     */
    private function clientConnectionAndAdvisor(): array
    {
        app(RequestContext::class)->apply('system', []);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $advisor->communicationPreference()->create([
            'channel' => CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            'frequency' => CommunicationPreference::FREQUENCY_WEEKLY,
            'timezone' => 'Pacific/Auckland',
        ]);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'Monitoring Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $tokenEnvelope = app(KeyEnvelope::class)->encrypt(json_encode([
            'access_token' => 'monitoring-token',
            'external_tenant_id' => 'xero-monitoring-tenant',
        ], JSON_THROW_ON_ERROR));

        $connection = AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'xero-monitoring-tenant',
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => $tokenEnvelope,
            'token_envelope_meta' => app(KeyEnvelope::class)->inspect($tokenEnvelope),
            'scopes' => ['accounting.reports.read', 'offline_access'],
            'connected_by_user_id' => $advisor->getKey(),
            'connected_at' => now()->subDays(7),
        ]);

        return [$advisor, $client, $connection];
    }

    private function snapshot(
        Client $client,
        AccountingConnection $connection,
        string $periodEnd,
        float $netProfit,
    ): FinancialSnapshot {
        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => $connection->provider,
            'period_start' => substr($periodEnd, 0, 8).'01',
            'period_end' => $periodEnd,
            'source' => $connection->provider,
            'source_badge' => 'stub',
            'degraded' => false,
            'correlation_id' => null,
            'profit_and_loss' => [
                'revenue' => 100000,
                'gross_profit' => 70000,
                'operating_expenses' => 20000,
                'net_profit' => $netProfit,
            ],
            'balance_sheet' => [
                'assets' => 250000,
                'liabilities' => 100000,
                'equity' => 150000,
            ],
            'cash_flow' => [
                'operating_cash_flow' => 25000,
                'investing_cash_flow' => -4000,
                'financing_cash_flow' => -2000,
                'closing_cash' => 75000,
            ],
            'metrics' => [
                'gross_margin' => 0.7,
                'net_margin' => $netProfit / 100000,
                'current_ratio' => 1.8,
            ],
            'pulled_at' => now()->subDays($periodEnd === '2026-03-31' ? 2 : 1),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPayload(string $periodEnd, float $netProfit): array
    {
        return [
            'period_start' => substr($periodEnd, 0, 8).'01',
            'period_end' => $periodEnd,
            'source' => AccountingConnection::PROVIDER_XERO,
            'source_badge' => 'stub',
            'degraded' => false,
            'profit_and_loss' => [
                'revenue' => 100000,
                'gross_profit' => 70000,
                'operating_expenses' => 20000,
                'net_profit' => $netProfit,
            ],
            'balance_sheet' => [
                'assets' => 250000,
                'liabilities' => 100000,
                'equity' => 150000,
            ],
            'cash_flow' => [
                'operating_cash_flow' => 25000,
                'investing_cash_flow' => -4000,
                'financing_cash_flow' => -2000,
                'closing_cash' => 75000,
            ],
            'metrics' => [
                'gross_margin' => 0.7,
                'net_margin' => $netProfit / 100000,
                'current_ratio' => 1.8,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bindXeroSnapshot(array $payload): void
    {
        $this->app->instance(XeroClient::class, new SnapshotXeroClient($payload));

        foreach ([AccountingClientResolver::class, FinancialSnapshotPuller::class, HealthMonitor::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }
}

final class SnapshotXeroClient implements XeroClient
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(private readonly array $snapshot) {}

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        return [
            'access_token' => 'monitoring-token',
            'external_tenant_id' => 'xero-monitoring-tenant',
        ];
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    public function financialSnapshot(AccountingConnection $connection, array $token): array
    {
        return $this->snapshot;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function revoke(AccountingConnection $connection, array $token): void
    {
        //
    }
}
