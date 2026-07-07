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
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
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
        $this->assertStringContainsString('improvement present-value calculation', $prescriptive->body);
        $this->assertStringNotContainsString('WO-42', $prescriptive->body);
        $this->assertNotNull($prescriptive->pv_link_id);
        $this->assertDatabaseHas('improvement_opportunities', [
            'id' => $prescriptive->pv_link_id,
            'analysis_finding_id' => $prescriptive->id,
            'title' => 'Financial margin and cash-conversion uplift',
        ]);
    }

    public function test_structured_financial_ai_findings_are_persisted_with_attributions(): void
    {
        $client = $this->clientWithQuestionnaire();
        $connection = $this->connection($client);
        $this->snapshot($client, $connection);
        $this->app->instance(AiClient::class, new StructuredFinancialAiClient);

        $run = app(FinancialAnalysisRunner::class)->run($client);

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertTrue($run->findings->contains(
            fn (AnalysisFinding $finding): bool => $finding->title === 'AI working-capital interpretation'
                && str_contains($finding->body, 'collection timing is constraining cash'),
        ));
        $this->assertTrue($run->findings->contains(
            fn (AnalysisFinding $finding): bool => $finding->title === 'AI pricing and collections action'
                && collect($finding->attributions)->contains(
                    fn (array $attribution): bool => str_starts_with($attribution['source_reference'], 'financial_snapshot:'),
                ),
        ));
        $this->assertSame(4, $run->findings->count());
    }

    public function test_structured_financial_ai_finding_without_attribution_is_dropped_and_surfaced(): void
    {
        $client = $this->clientWithQuestionnaire();
        $connection = $this->connection($client);
        $this->snapshot($client, $connection);
        $this->app->instance(AiClient::class, new MissingStructuredFinancialAttributionAiClient);

        $run = app(AnalysisRunner::class)->run($client, app(FinancialAnalysis::class));

        $this->assertSame(AnalysisRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, data_get($run->metadata, 'dropped_findings.missing_attribution'));
        $this->assertSame('Uncited AI interpretation', data_get($run->metadata, 'dropped_findings.items.0.title'));
        $this->assertFalse($run->findings->contains('title', 'Uncited AI interpretation'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'analysis.finding_dropped_missing_attribution',
        ]);
    }

    public function test_financial_analysis_surfaces_multi_period_trend_and_missing_industry_benchmark(): void
    {
        $client = $this->clientWithQuestionnaire();
        $connection = $this->connection($client);
        $this->snapshot($client, $connection);
        $this->olderSnapshot($client, $connection);

        $run = app(FinancialAnalysisRunner::class)->run($client);

        $diagnostic = $run->findings->firstWhere('lens', AnalysisLens::Diagnostic);
        $this->assertInstanceOf(AnalysisFinding::class, $diagnostic);
        $this->assertStringContainsString('Multi-period trend across 2 snapshots', $diagnostic->body);
        $this->assertStringContainsString('revenue moved from NZD 200,000 to NZD 250,000', $diagnostic->body);

        $predictive = $run->findings->firstWhere('lens', AnalysisLens::Predictive);
        $this->assertInstanceOf(AnalysisFinding::class, $predictive);
        $this->assertStringContainsString('Industry benchmark warning', $predictive->body);
        $this->assertStringContainsString('No verified industry financial benchmark is configured', $predictive->body);
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

    private function olderSnapshot(Client $client, AccountingConnection $connection): FinancialSnapshot
    {
        return FinancialSnapshot::query()->create([
            'client_id' => $client->getKey(),
            'accounting_connection_id' => $connection->getKey(),
            'provider' => $connection->provider,
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'source' => AccountingConnection::PROVIDER_XERO,
            'source_badge' => 'stub',
            'degraded' => false,
            'correlation_id' => null,
            'profit_and_loss' => [
                'revenue' => 200000,
                'gross_profit' => 108000,
                'operating_expenses' => 99000,
                'net_profit' => 9000,
            ],
            'balance_sheet' => [
                'assets' => 360000,
                'liabilities' => 230000,
                'equity' => 130000,
            ],
            'cash_flow' => [
                'operating_cash_flow' => 7000,
                'investing_cash_flow' => -6000,
                'financing_cash_flow' => -4000,
                'closing_cash' => 52000,
            ],
            'metrics' => [
                'gross_margin' => 0.54,
                'net_margin' => 0.045,
                'current_ratio' => 1.09,
            ],
            'pulled_at' => now()->subMonth(),
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

class StructuredFinancialAiClient implements AiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        $source = collect($prompt->sourceReferences)
            ->first(fn (string $reference): bool => str_contains($reference, 'profit_and_loss.revenue'))
            ?? $prompt->sourceReferences[0]
            ?? 'client:unknown';

        return AiResponse::fromStructuredPayload(
            payload: [
                'text' => 'Structured financial interpretation.',
                'attributions' => [[
                    'claim' => 'Structured financial response uses the supplied financial source references.',
                    'source_reference' => $source,
                ]],
                'uncertainty' => Uncertainty::Medium->value,
                'metadata' => [
                    'findings' => [
                        [
                            'lens' => AnalysisLens::Diagnostic->value,
                            'severity' => 'medium',
                            'title' => 'AI working-capital interpretation',
                            'body' => 'The connected data indicates collection timing is constraining cash conversion despite positive reported profit.',
                            'attributions' => [[
                                'claim' => 'Collection timing interpretation is grounded in the supplied financial snapshot references.',
                                'source_reference' => $source,
                            ]],
                            'confidence' => 0.78,
                        ],
                        [
                            'lens' => AnalysisLens::Prescriptive->value,
                            'severity' => 'high',
                            'title' => 'AI pricing and collections action',
                            'body' => 'Prioritise price discipline and collections cadence before adding more overhead.',
                            'attributions' => [[
                                'claim' => 'Prescriptive action is grounded in the supplied financial snapshot references.',
                                'source_reference' => $source,
                            ]],
                            'uncertainty' => Uncertainty::Medium->value,
                        ],
                    ],
                ],
            ],
            model: 'fake-financial-structured',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
        );
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }
}

final class MissingStructuredFinancialAttributionAiClient extends StructuredFinancialAiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        $source = $prompt->sourceReferences[0] ?? 'client:unknown';

        return AiResponse::fromStructuredPayload(
            payload: [
                'text' => 'Structured financial interpretation with one uncited row.',
                'attributions' => [[
                    'claim' => 'Top-level response is attributed so row-level dropping can be tested.',
                    'source_reference' => $source,
                ]],
                'uncertainty' => Uncertainty::Medium->value,
                'metadata' => [
                    'findings' => [
                        [
                            'lens' => AnalysisLens::Diagnostic->value,
                            'severity' => 'medium',
                            'title' => 'Uncited AI interpretation',
                            'body' => 'This row lacks row-level source attribution and must not be persisted.',
                            'attributions' => [],
                        ],
                    ],
                ],
            ],
            model: 'fake-financial-structured',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
        );
    }
}
