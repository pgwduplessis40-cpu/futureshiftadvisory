<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboards;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FinancialSnapshot;
use App\Models\User;
use App\Services\Dashboards\EconomicExposureMapper;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EconomicExposureMapperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_supported_and_unsupported_exposure_rules_are_conservative(): void
    {
        $debt = $this->client('Debt Exposed Limited');
        $clear = $this->client('Debt Clear Limited');
        $missingKey = $this->client('Missing Debt Key Limited');
        $missingSnapshot = $this->client('Missing Snapshot Limited');
        $suspended = $this->client('Suspended Limited', ClientStatus::SUSPENDED);

        $this->snapshot($debt, ['metrics' => ['interest_bearing_debt' => 250000]]);
        $this->snapshot($clear, ['metrics' => ['interest_bearing_debt' => 0]]);
        $this->snapshot($missingKey, ['metrics' => ['gross_margin' => 0.5]]);
        $this->snapshot($suspended, ['metrics' => ['interest_bearing_debt' => 100000]]);

        $mapper = app(EconomicExposureMapper::class);

        $cpi = $mapper->forKey('cpi');
        $ocr = $mapper->forKey('ocr');
        $wage = $mapper->forIndicator('minimum_wage');
        $fx = $mapper->forExchangeRate('NZD', 'USD');

        $this->assertTrue($cpi['supported']);
        $this->assertSame(4, $cpi['exposed_count']);
        $this->assertNotContains($suspended->id, $cpi['client_ids']);
        $this->assertTrue($ocr['supported']);
        $this->assertSame([$debt->id], $ocr['client_ids']);
        $this->assertSame(1, $ocr['exposed_count']);
        $this->assertSame(2, $ocr['unknown_count']);
        $this->assertSame(1, $ocr['not_exposed_count']);
        $this->assertFalse($wage['supported']);
        $this->assertSame('classification_not_captured', $wage['reason']);
        $this->assertNull($wage['exposed_count']);
        $this->assertFalse($fx['supported']);
        $this->assertSame('classification_not_captured', $fx['reason']);
        $this->assertNull($fx['drill_url']);

        $this->assertNotContains($missingSnapshot->id, $ocr['client_ids']);
    }

    public function test_clients_index_filters_supported_exposure_without_scope_leakage(): void
    {
        $advisor = $this->advisor('economic-exposure-filter@example.test');
        $otherAdvisor = $this->advisor('other-economic-exposure-filter@example.test');
        $debt = $this->client('Scoped Debt Limited', advisor: $advisor);
        $clear = $this->client('Scoped Clear Limited', advisor: $advisor);
        $outside = $this->client('Outside Debt Limited', advisor: $otherAdvisor);

        $this->snapshot($debt, ['metrics' => ['interest_bearing_debt' => 250000]]);
        $this->snapshot($clear, ['metrics' => ['interest_bearing_debt' => 0]]);
        $this->snapshot($outside, ['metrics' => ['interest_bearing_debt' => 999999]]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index', ['exposed_to' => 'ocr']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Index')
                ->where('exposureFilter.key', 'ocr')
                ->where('exposureFilter.exposed_count', 1)
                ->has('clients', 1)
                ->where('clients.0.id', $debt->id)
                ->where('clients.0.legal_name', 'Scoped Debt Limited'));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.index', ['exposed_to' => 'fx']))
            ->assertNotFound();
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function client(
        string $name,
        ClientStatus $status = ClientStatus::ACTIVE,
        ?User $advisor = null,
    ): Client {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'status' => $status,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor?->getKey(),
        ]);

        if ($advisor instanceof User) {
            ClientTeamMember::query()->create([
                'client_id' => $client->getKey(),
                'user_id' => $advisor->getKey(),
                'role' => 'lead_advisor',
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }

        return $client;
    }

    /**
     * @param  array{balance_sheet?:array<string, mixed>, cash_flow?:array<string, mixed>, metrics?:array<string, mixed>}  $payload
     */
    private function snapshot(Client $client, array $payload): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'economic-exposure-'.$client->getKey(),
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'encrypted-token',
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'source' => 'xero',
            'source_badge' => 'fixture',
            'degraded' => false,
            'profit_and_loss' => [],
            'balance_sheet' => $payload['balance_sheet'] ?? [],
            'cash_flow' => $payload['cash_flow'] ?? [],
            'metrics' => $payload['metrics'] ?? [],
            'pulled_at' => now(),
        ]);
    }
}
