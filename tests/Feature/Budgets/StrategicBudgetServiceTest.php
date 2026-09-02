<?php

declare(strict_types=1);

namespace Tests\Feature\Budgets;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Exceptions\StrategicBudgetRevisionConflict;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\FinancialSnapshot;
use App\Models\Questionnaire;
use App\Models\QuestionnaireAnswer;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\QuestionnaireSection;
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
        ], $actor, $budget->revision);

        $this->assertContains('financial_snapshot_discrepancy', collect($budget->flags)->pluck('key')->all());
        $this->assertStringContainsString('latest accounting snapshot', collect($budget->flags)->firstWhere('key', 'financial_snapshot_discrepancy')['message']);
    }

    public function test_update_rejects_a_stale_budget_revision_without_overwriting_the_newer_draft(): void
    {
        $client = $this->client();
        $actor = User::factory()->create();
        $document = $this->financialDocument($client, 'Management Accounts.pdf');
        DocumentVerification::query()->create([
            'document_id' => $document->getKey(),
            'client_id' => $client->getKey(),
            'context_hash' => hash('sha256', 'stale-budget-revision'),
            'claim_text' => 'Management accounts verified for budget reliance.',
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.97,
            'verified_at' => now(),
        ]);

        $staleTab = app(StrategicBudgetService::class)->ensureForClient($client);
        $saved = app(StrategicBudgetService::class)->update($staleTab, [
            'horizon_months' => 12,
            'monthly_fixed_costs' => [['label' => 'Rent', 'amount' => 1250.10, 'confidence' => 'known']],
        ], $actor, $staleTab->revision);

        $this->assertSame(2, $saved->revision);

        try {
            app(StrategicBudgetService::class)->update($staleTab, [
                'horizon_months' => 12,
                'monthly_fixed_costs' => [['label' => 'Stale rent', 'amount' => 10, 'confidence' => 'guess']],
            ], $actor, $staleTab->revision);
            $this->fail('A stale tab must not overwrite the newer budget revision.');
        } catch (StrategicBudgetRevisionConflict) {
            // Expected: the database row was locked and its revision changed.
        }

        $this->assertSame('Rent', StrategicBudget::query()->findOrFail($saved->getKey())->monthly_fixed_costs[0]['label']);
    }

    public function test_post_acquisition_source_drafts_gather_onboarding_questionnaire_and_evidence_for_plan_sections(): void
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::POST_ACQUISITION_ADVISORY->value,
            'legal_name' => 'Kauri Kitchens Limited',
            'trading_name' => 'Kauri Kitchens',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'onboarding_wizard_state' => [
                'steps' => [
                    'goals' => [
                        'primary_goal' => 'Stabilise the acquired operation before expanding.',
                        'success_measure' => 'First 100-day plan is funded and customer handover is complete.',
                    ],
                    'website' => [
                        'website_url' => 'https://kaurikitchens.example.nz',
                        'website_skipped' => false,
                    ],
                ],
            ],
        ]);
        $document = $this->financialDocument($client, 'target-management-accounts.xlsx');
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::POST_ACQUISITION_GAP,
            'version' => 'test-v1',
            'title' => 'Post-acquisition Gap Questionnaire',
            'published_at' => now(),
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->getKey(),
            'order' => 1,
            'title' => 'DD Handoff Gaps',
        ]);
        $currentPosition = $this->question($section, 1, 'Confirm acquired business details from DD.');
        $risk = $this->question($section, 2, 'Review inherited due diligence risks.');
        $evidence = $this->question($section, 3, 'Confirm migrated DD document set.');
        $actions = $this->question($section, 4, 'What post-close action must improve first?');
        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
            'submitted_at' => now(),
        ]);

        QuestionnaireAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $currentPosition->getKey(),
            'value' => ['value' => 'Kauri Kitchens has been acquired and needs a post-close operating baseline.'],
        ]);
        QuestionnaireAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $risk->getKey(),
            'value' => ['value' => 'Customer concentration and undocumented systems remain the highest DD risks.'],
        ]);
        QuestionnaireAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $evidence->getKey(),
            'value' => ['value' => 'Management accounts and customer contracts are the core source evidence.'],
            'attached_document_ids' => [(string) $document->getKey()],
        ]);
        QuestionnaireAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $actions->getKey(),
            'value' => ['value' => 'Assign handover owners and schedule customer-retention calls.'],
        ]);

        $budget = app(StrategicBudgetService::class)->ensureForClient($client);
        $sourceDrafts = collect($budget->business_plan_source_drafts)->keyBy('key');

        $this->assertStringContainsString('Stabilise the acquired operation', (string) data_get($sourceDrafts->get('goals'), 'body'));
        $this->assertSame(route('portal.onboarding.step', ['step' => 'goals'], absolute: false), data_get($sourceDrafts->get('goals'), 'source_url'));
        $this->assertStringContainsString('https://kaurikitchens.example.nz', (string) data_get($sourceDrafts->get('current_position'), 'body'));
        $this->assertStringContainsString('post-close operating baseline', (string) data_get($sourceDrafts->get('current_position'), 'body'));
        $this->assertStringContainsString('Customer concentration', (string) data_get($sourceDrafts->get('risks'), 'body'));
        $this->assertSame(route('portal.onboarding.step', ['step' => 'questionnaire'], absolute: false), data_get($sourceDrafts->get('risks'), 'source_url'));
        $this->assertStringContainsString('handover owners', (string) data_get($sourceDrafts->get('action_priorities'), 'body'));
        $this->assertStringContainsString('1 document(s) are available', (string) data_get($sourceDrafts->get('evidence_documents'), 'body'));
        $this->assertStringContainsString('customer contracts', (string) data_get($sourceDrafts->get('evidence_documents'), 'body'));
        $this->assertSame(route('portal.onboarding.step', ['step' => 'documents'], absolute: false), data_get($sourceDrafts->get('evidence_documents'), 'source_url'));
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

    private function question(QuestionnaireSection $section, int $order, string $prompt): QuestionnaireQuestion
    {
        return QuestionnaireQuestion::query()->create([
            'questionnaire_section_id' => $section->getKey(),
            'order' => $order,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => $prompt,
            'required' => true,
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
