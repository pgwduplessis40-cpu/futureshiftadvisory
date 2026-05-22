<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Pv\ImprovementPv;
use App\Services\Pv\RiskCostPv;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImprovementAndRiskPvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RequestContext::class)->apply('system', []);
    }

    public function test_improvement_opportunities_are_ranked_by_pv_and_link_to_findings(): void
    {
        [$client, $finding] = $this->clientAndFinding(AnalysisModule::Operational);

        $ranked = app(ImprovementPv::class)->rank($client, [
            [
                'title' => 'Automate invoice follow-up',
                'annual_benefit' => 40000,
                'duration_years' => 3,
                'analysis_finding_id' => $finding->id,
                'source_reference' => "analysis_finding:{$finding->id}",
            ],
            [
                'title' => 'Renegotiate freight contracts',
                'annual_benefit' => 20000,
                'duration_years' => 2,
            ],
        ], DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Advisor selected improvement PV rate.',
        ]);

        $this->assertCount(2, $ranked);
        $this->assertSame('Automate invoice follow-up', $ranked[0]->title);
        $this->assertSame(1, $ranked[0]->rank);
        $this->assertSame($finding->id, $ranked[0]->analysis_finding_id);
        $this->assertGreaterThan($ranked[1]->pv_of_impact, $ranked[0]->pv_of_impact);
        $this->assertDatabaseHas('improvement_opportunities', [
            'analysis_finding_id' => $finding->id,
            'rank' => 1,
        ]);
    }

    public function test_risk_costs_apply_statutory_ranges_and_rank_by_pv_cost(): void
    {
        [$client, $finding] = $this->clientAndFinding(AnalysisModule::Compliance);

        $ranked = app(RiskCostPv::class)->rank($client, [
            [
                'title' => 'Holidays Act remediation exposure',
                'financial_impact' => 10000,
                'probability' => 0.5,
                'duration_years' => 2,
                'statutory_penalty_range' => ['low' => 50000, 'high' => 150000],
                'analysis_finding_id' => $finding->id,
                'source_reference' => "analysis_finding:{$finding->id}",
            ],
            [
                'title' => 'Supplier contract dispute',
                'financial_impact' => 60000,
                'probability' => 0.3,
                'duration_years' => 1,
            ],
        ], DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Advisor selected risk PV rate.',
        ]);

        $this->assertCount(2, $ranked);
        $this->assertSame('Holidays Act remediation exposure', $ranked[0]->title);
        $this->assertSame(1, $ranked[0]->rank);
        $this->assertSame($finding->id, $ranked[0]->analysis_finding_id);
        $this->assertEquals(['low' => 50000.0, 'high' => 150000.0], $ranked[0]->statutory_penalty_range);
        $this->assertSame(100000.0, $ranked[0]->applied_impact);
        $this->assertSame(50000.0, $ranked[0]->annual_expected_cost);
        $this->assertGreaterThan($ranked[1]->pv_of_cost, $ranked[0]->pv_of_cost);
    }

    /**
     * @return array{0: Client, 1: AnalysisFinding}
     */
    private function clientAndFinding(AnalysisModule $module): array
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'PV Type Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);

        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => $module,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Prescriptive->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $finding = AnalysisFinding::query()->create([
            'analysis_run_id' => $run->id,
            'client_id' => $client->id,
            'lens' => AnalysisLens::Prescriptive,
            'severity' => FindingSeverity::High,
            'title' => 'PV source finding',
            'body' => 'This finding has measurable financial impact.',
            'attributions' => [
                ['claim' => 'PV source finding', 'source_reference' => 'questionnaire:pv-source'],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
            'uncertainty' => Uncertainty::Medium,
            'bias_signals' => [],
        ]);

        return [$client, $finding];
    }
}
