<?php

declare(strict_types=1);

namespace Tests\Feature\Budgets;

use App\Enums\EngagementType;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\FinancialSnapshot;
use App\Models\StrategicBudget;
use App\Models\User;
use App\Services\Budgets\StrategicBudgetService;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StrategicBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_budget_unlock_requires_verified_financial_document(): void
    {
        $client = $this->client();
        $document = $this->financialDocument($client, 'FY26 Profit and Loss.pdf');

        $locked = app(StrategicBudgetService::class)->ensureForClient($client);

        $this->assertSame(StrategicBudget::STATUS_LOCKED, $locked->status);
        $this->assertFalse((bool) data_get($locked->source_financials, 'unlocked'));

        DocumentVerification::query()->create([
            'document_id' => $document->getKey(),
            'client_id' => $client->getKey(),
            'context_hash' => hash('sha256', 'verified-financials'),
            'claim_text' => 'Document is a P&L suitable for budget reliance.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.98,
            'verified_at' => now(),
        ]);

        $unlocked = app(StrategicBudgetService::class)->ensureForClient($client);

        $this->assertSame(StrategicBudget::STATUS_SYSTEM_DRAFT, $unlocked->status);
        $this->assertTrue((bool) data_get($unlocked->source_financials, 'unlocked'));
        $this->assertSame('verified', data_get($unlocked->source_financials, 'items.0.verification_status'));
    }

    public function test_latest_financial_snapshot_discrepancy_creates_budget_warning(): void
    {
        $client = $this->client();
        $actor = User::factory()->create();
        $document = $this->financialDocument($client, 'Management Accounts.pdf');
        DocumentVerification::query()->create([
            'document_id' => $document->getKey(),
            'client_id' => $client->getKey(),
            'context_hash' => hash('sha256', 'snapshot-discrepancy'),
            'claim_text' => 'Management accounts verified for budget reliance.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.97,
            'verified_at' => now(),
        ]);
        $this->financialSnapshot($client, revenue: 120_000);

        $budget = app(StrategicBudgetService::class)->ensureForClient($client);
        $budget = app(StrategicBudgetService::class)->update($budget, [
            'horizon_months' => 12,
            'assumptions' => [
                'revenue_growth_percent' => 0,
                'cost_inflation_percent' => 0,
                'target_gross_profit_percent' => 50,
                'target_net_profit_before_tax_percent' => 10,
                'target_net_profit_after_tax_percent' => 7,
            ],
            'implementation_costs' => [
                ['label' => 'Setup', 'amount' => 2_000, 'confidence' => 'known'],
            ],
            'monthly_fixed_costs' => [
                ['label' => 'Rent', 'amount' => 1_000, 'confidence' => 'known'],
            ],
            'revenue_forecast' => [
                ['label' => 'Sales', 'amount' => 2_000, 'month' => 1, 'confidence' => 'known'],
            ],
            'funding_sources' => [
                ['label' => 'Founder cash', 'amount' => 5_000, 'confidence' => 'known'],
            ],
        ], $actor);

        $this->assertContains('financial_snapshot_discrepancy', collect($budget->flags)->pluck('key')->all());
        $this->assertStringContainsString('latest accounting snapshot', collect($budget->flags)->firstWhere('key', 'financial_snapshot_discrepancy')['message']);
    }

    private function client(): Client
    {
        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Strategic Budget Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
    }

    private function financialDocument(Client $client, string $filename): Document
    {
        return Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_FINANCIAL_STATEMENT,
            'original_filename' => $filename,
            'stored_path' => 'budget/'.Str::uuid().'.pdf',
            'byte_size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', $filename),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
    }

    private function financialSnapshot(Client $client, float $revenue): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'budget-fixture',
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'test-token',
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => now()->subYear()->startOfYear()->toDateString(),
            'period_end' => now()->subYear()->endOfYear()->toDateString(),
            'source' => 'xero',
            'source_badge' => 'Actual',
            'degraded' => false,
            'profit_and_loss' => [
                'revenue' => $revenue,
                'gross_profit' => $revenue * 0.5,
                'net_profit' => $revenue * 0.1,
            ],
            'balance_sheet' => [],
            'cash_flow' => [],
            'metrics' => [],
            'pulled_at' => now(),
        ]);
    }
}
