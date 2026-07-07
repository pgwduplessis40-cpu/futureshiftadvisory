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
use App\Models\ImprovementOpportunity;
use App\Models\RiskCost;
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

    public function test_statutory_penalty_defaults_to_one_off_cash_flow_unless_overridden(): void
    {
        [$client, $finding] = $this->clientAndFinding(AnalysisModule::Compliance);
        $risk = [
            'title' => 'Statutory penalty exposure',
            'financial_impact' => 0,
            'probability' => 1,
            'duration_years' => 5,
            'statutory_penalty_range' => ['low' => 100000, 'high' => 100000],
            'analysis_finding_id' => $finding->id,
            'source_reference' => "analysis_finding:{$finding->id}",
        ];

        $oneOff = app(RiskCostPv::class)->rank($client, [$risk], DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Advisor selected risk PV rate.',
        ]);

        $this->assertSame('one_off', $oneOff[0]->recurrence);
        $this->assertSame(1, $oneOff[0]->cash_flow_years);
        $this->assertSame(90909.09, round($oneOff[0]->pv_of_cost, 2));
        $this->assertCount(1, $oneOff[0]->pvCalculation->inputs['cash_flows']);

        $recurring = app(RiskCostPv::class)->rank($client, [[...$risk, 'recurrence' => 'recurring']], DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Advisor selected risk PV rate.',
        ]);

        $this->assertSame('recurring', $recurring[0]->recurrence);
        $this->assertSame(5, $recurring[0]->cash_flow_years);
        $this->assertGreaterThan($oneOff[0]->pv_of_cost, $recurring[0]->pv_of_cost);
        $this->assertCount(5, $recurring[0]->pvCalculation->inputs['cash_flows']);
        $this->assertSame(1, RiskCost::query()->where('client_id', $client->getKey())->active()->count());
    }

    public function test_re_ranking_same_improvement_supersedes_prior_active_row(): void
    {
        [$client, $finding] = $this->clientAndFinding(AnalysisModule::Financial);
        $input = [[
            'title' => 'Margin improvement programme',
            'annual_benefit' => 30000,
            'duration_years' => 2,
            'analysis_finding_id' => $finding->id,
            'source_reference' => "analysis_finding:{$finding->id}",
        ]];

        $first = app(ImprovementPv::class)->rank($client, $input, DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Test rate.',
        ]);
        $activeTotal = round((float) ImprovementOpportunity::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->sum('pv_of_impact'), 2);

        $second = app(ImprovementPv::class)->rank($client, $input, DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Test rate.',
        ]);

        $this->assertNotSame($first[0]->id, $second[0]->id);
        $this->assertSame(2, ImprovementOpportunity::query()->where('client_id', $client->getKey())->count());
        $this->assertSame(1, ImprovementOpportunity::query()->where('client_id', $client->getKey())->active()->count());
        $this->assertSame(1, ImprovementOpportunity::query()->where('client_id', $client->getKey())->whereNotNull('superseded_at')->count());
        $this->assertSame($activeTotal, round((float) ImprovementOpportunity::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->sum('pv_of_impact'), 2));
    }

    public function test_re_ranking_same_risk_supersedes_prior_active_row(): void
    {
        [$client, $finding] = $this->clientAndFinding(AnalysisModule::Compliance);
        $input = [[
            'title' => 'Compliance penalty exposure',
            'financial_impact' => 50000,
            'probability' => 0.4,
            'duration_years' => 2,
            'analysis_finding_id' => $finding->id,
            'source_reference' => "analysis_finding:{$finding->id}",
        ]];

        $first = app(RiskCostPv::class)->rank($client, $input, DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Test rate.',
        ]);
        $activeTotal = round((float) RiskCost::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->sum('pv_of_cost'), 2);

        $second = app(RiskCostPv::class)->rank($client, $input, DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Test rate.',
        ]);

        $this->assertNotSame($first[0]->id, $second[0]->id);
        $this->assertSame(2, RiskCost::query()->where('client_id', $client->getKey())->count());
        $this->assertSame(1, RiskCost::query()->where('client_id', $client->getKey())->active()->count());
        $this->assertSame(1, RiskCost::query()->where('client_id', $client->getKey())->whereNotNull('superseded_at')->count());
        $this->assertSame($activeTotal, round((float) RiskCost::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->sum('pv_of_cost'), 2));
    }

    public function test_re_ranking_risk_with_new_finding_and_stable_source_key_supersedes_prior_active_row(): void
    {
        [$client, $firstFinding] = $this->clientAndFinding(AnalysisModule::Compliance);
        $secondFinding = $this->findingForClient($client, AnalysisModule::Compliance);
        $input = [
            'title' => 'Compliance penalty exposure',
            'financial_impact' => 50000,
            'probability' => 0.4,
            'duration_years' => 2,
            'source_fingerprint_key' => 'dd_risk:stable-compliance-penalty-exposure',
        ];

        $first = app(RiskCostPv::class)->rank($client, [[
            ...$input,
            'analysis_finding_id' => $firstFinding->id,
            'source_reference' => "analysis_finding:{$firstFinding->id}",
        ]], DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Test rate.',
        ]);
        $activeTotal = round((float) RiskCost::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->sum('pv_of_cost'), 2);

        $second = app(RiskCostPv::class)->rank($client, [[
            ...$input,
            'analysis_finding_id' => $secondFinding->id,
            'source_reference' => "analysis_finding:{$secondFinding->id}",
        ]], DiscountMethod::AdvisorConfigured, [
            'rate' => 0.1,
            'rationale' => 'Test rate.',
        ]);

        $this->assertNotSame($first[0]->id, $second[0]->id);
        $this->assertSame(2, RiskCost::query()->where('client_id', $client->getKey())->count());
        $this->assertSame(1, RiskCost::query()->where('client_id', $client->getKey())->active()->count());
        $this->assertSame(1, RiskCost::query()->where('client_id', $client->getKey())->whereNotNull('superseded_at')->count());
        $this->assertSame($activeTotal, round((float) RiskCost::query()
            ->where('client_id', $client->getKey())
            ->active()
            ->sum('pv_of_cost'), 2));
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

        $finding = $this->findingForClient($client, $module);

        return [$client, $finding];
    }

    private function findingForClient(Client $client, AnalysisModule $module): AnalysisFinding
    {
        $run = AnalysisRun::query()->create([
            'client_id' => $client->id,
            'module' => $module,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Prescriptive->value],
            'data_quality_snapshot' => ['level' => Client::DATA_QUALITY_LOW],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return AnalysisFinding::query()->create([
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
    }
}
