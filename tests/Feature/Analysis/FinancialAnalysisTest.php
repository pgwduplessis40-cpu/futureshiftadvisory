<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\AccountingConnection;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\EconomicIndicator;
use App\Models\FinancialSnapshot;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\FinancialAnalysisRunner;
use App\Services\Analysis\Modules\FinancialAnalysis;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FinancialAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_financial_module_runs_on_the_spine_with_snapshot_and_economic_context(): void
    {
        $client = $this->clientWithQuestionnaire();
        $connection = $this->connection($client);
        $snapshot = $this->snapshot($client, $connection);
        $ocr = $this->indicator(EconomicIndicator::OCR, 'OCR', 5.5, 'percent');
        $this->indicator(EconomicIndicator::CPI_ANNUAL, 'CPI annual', 4.1, 'percent');

        $run = app(FinancialAnalysisRunner::class)->run($client);

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('financial', $run->module->value);
        $this->assertSame(AnalysisLens::values(), $run->framework_lenses);
        $this->assertCount(4, $run->findings);

        $descriptive = $run->findings->firstWhere('lens', AnalysisLens::Descriptive);
        $this->assertInstanceOf(AnalysisFinding::class, $descriptive);
        $this->assertStringContainsString('NZD 250,000', $descriptive->body);
        $this->assertTrue(collect($descriptive->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "financial_snapshot:{$snapshot->id}:profit_and_loss.revenue",
        ));
        $this->assertTrue(collect($descriptive->attributions)->contains(
            fn (array $attribution): bool => $attribution['source_reference'] === "economic_indicator:{$ocr->id}:ocr",
        ));
        $this->assertSame(AnalysisFinding::DOCUMENT_SUPPORT_NONE, $descriptive->document_support);

        $prescriptive = $run->findings->firstWhere('lens', AnalysisLens::Prescriptive);
        $this->assertInstanceOf(AnalysisFinding::class, $prescriptive);
        $this->assertNotNull($prescriptive->pv_link_id);
        $this->assertDatabaseHas('improvement_opportunities', [
            'id' => $prescriptive->pv_link_id,
            'analysis_finding_id' => $prescriptive->id,
            'title' => 'Financial margin and cash-conversion uplift',
        ]);
    }

    public function test_financial_module_uses_questionnaire_fallback_with_disclaimer_when_no_accounting_connection_exists(): void
    {
        $client = $this->clientWithQuestionnaire();

        $run = app(AnalysisRunner::class)->run($client, app(FinancialAnalysis::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertCount(4, $run->findings);
        $this->assertDatabaseCount('improvement_opportunities', 0);

        $finding = $run->findings->firstWhere('lens', AnalysisLens::Descriptive);
        $this->assertInstanceOf(AnalysisFinding::class, $finding);
        $this->assertStringContainsString('questionnaire fallback', $finding->title);
        $this->assertStringContainsString('No connected accounting snapshot is available', (string) $finding->data_quality_disclaimer);
        $this->assertTrue(collect($finding->attributions)->contains(
            fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'questionnaire_response:'),
        ));
    }

    private function clientWithQuestionnaire(): Client
    {
        $user = User::factory()->create();

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => 'Financial Analysis Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        [$questionnaire, $question] = $this->questionnaireWithQuestion();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);

        $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Revenue is growing but debtor days and wage pressure are constraining cash flow.',
            'attached_document_ids' => [],
        ]);

        return $client;
    }

    /**
     * @return array{0: Questionnaire, 1: QuestionnaireQuestion}
     */
    private function questionnaireWithQuestion(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo44-'.Str::lower(Str::random(8)),
            'title' => 'WO-44 Financial Analysis Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Financial context',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'What is the current financial position?',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    private function connection(Client $client): AccountingConnection
    {
        return AccountingConnection::query()->create([
            'client_id' => $client->getKey(),
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'wo44-xero-tenant',
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'test-token-envelope',
            'token_envelope_meta' => ['cipher' => 'test'],
            'scopes' => ['accounting.reports.read'],
            'connected_at' => now()->subDays(4),
        ]);
    }

    private function snapshot(Client $client, AccountingConnection $connection): FinancialSnapshot
    {
        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => $connection->provider,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'source' => AccountingConnection::PROVIDER_XERO,
            'source_badge' => 'stub',
            'degraded' => false,
            'correlation_id' => null,
            'profit_and_loss' => [
                'revenue' => 250000,
                'gross_profit' => 145000,
                'operating_expenses' => 118000,
                'net_profit' => 27000,
            ],
            'balance_sheet' => [
                'assets' => 400000,
                'liabilities' => 245000,
                'equity' => 155000,
            ],
            'cash_flow' => [
                'operating_cash_flow' => 15000,
                'investing_cash_flow' => -8000,
                'financing_cash_flow' => -5000,
                'closing_cash' => 60000,
            ],
            'metrics' => [
                'gross_margin' => 0.58,
                'net_margin' => 0.108,
                'current_ratio' => 1.18,
            ],
            'pulled_at' => now(),
        ]);
    }

    private function indicator(string $indicator, string $label, float $value, string $unit): EconomicIndicator
    {
        return EconomicIndicator::query()->create([
            'indicator' => $indicator,
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'period_date' => '2026-04-30',
            'source' => 'fixture',
            'source_badge' => 'stub',
            'degraded' => false,
            'correlation_id' => null,
            'fetched_at' => now(),
            'payload' => [],
        ]);
    }
}
