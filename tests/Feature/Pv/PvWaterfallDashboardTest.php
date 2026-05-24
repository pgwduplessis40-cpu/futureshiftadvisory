<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\PvType;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ImprovementOpportunity;
use App\Models\PvCalculation;
use App\Models\RiskCost;
use App\Models\User;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Pv\PvWaterfallReportChart;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PvWaterfallDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_waterfall_data_reconciles_current_improvements_risks_and_target(): void
    {
        $client = $this->client();
        $this->pvFixture($client, current: 100000, improvement: 25000, risk: 10000);

        $payload = app(PvWaterfallBuilder::class)->forClient($client);

        $this->assertSame(100000.0, $payload['current_pv']);
        $this->assertSame(25000.0, $payload['improvement_pv']);
        $this->assertSame(10000.0, $payload['risk_mitigation_pv']);
        $this->assertSame(135000.0, $payload['target_pv']);
        $this->assertSame('Current PV', $payload['waterfall'][0]['label']);
        $this->assertSame('Automation upside', $payload['waterfall'][1]['label']);
        $this->assertSame(125000.0, $payload['waterfall'][1]['end']);
        $this->assertSame(0.12, $payload['waterfall'][1]['discount_rate']);
        $this->assertSame('Risk mitigation value', $payload['waterfall'][2]['label']);
        $this->assertSame(135000.0, $payload['waterfall'][3]['end']);
    }

    public function test_waterfall_caps_recommendations_with_remainders_and_finding_drills(): void
    {
        $client = $this->client();
        $this->valuationFixture($client, 100000);
        $finding = $this->analysisFinding($client);

        foreach (range(1, 9) as $rank) {
            $this->opportunityFixture(
                $client,
                title: "Improvement {$rank}",
                value: $rank * 1000,
                rank: $rank,
                finding: $rank === 1 ? $finding : null,
            );
            $this->riskFixture(
                $client,
                title: "Risk mitigation {$rank}",
                value: $rank * 500,
                rank: $rank,
            );
        }

        $payload = app(PvWaterfallBuilder::class)->forClient($client);
        $steps = collect($payload['waterfall']);
        $improvementSteps = $steps->where('recommendation_type', 'improvement')->values();
        $riskSteps = $steps->where('recommendation_type', 'risk_mitigation')->values();

        $this->assertSame(45000.0, $payload['improvement_pv']);
        $this->assertSame(22500.0, $payload['risk_mitigation_pv']);
        $this->assertSame(167500.0, $payload['target_pv']);
        $this->assertCount(9, $improvementSteps);
        $this->assertCount(9, $riskSteps);
        $this->assertSame(45000.0, round((float) $improvementSteps->sum('value'), 2));
        $this->assertSame(22500.0, round((float) $riskSteps->sum('value'), 2));
        $this->assertSame('Improvement 1', $improvementSteps[0]['label']);
        $this->assertSame($finding->id, $improvementSteps[0]['source_finding_id']);
        $this->assertSame(
            route('advisor.clients.show', [
                'client' => $client,
                'focus' => 'analysis',
                'highlight' => $finding->id,
            ], absolute: false),
            $improvementSteps[0]['drill_url'],
        );
        $this->assertSame('Other improvements (1)', $improvementSteps[8]['label']);
        $this->assertTrue($improvementSteps[8]['is_remainder']);
        $this->assertNull($improvementSteps[8]['drill_url']);
        $this->assertSame('Other risk mitigation (1)', $riskSteps[8]['label']);
        $this->assertSame(167500.0, $steps->last()['end']);
    }

    public function test_advisor_dashboard_surfaces_pv_baseline_target_and_waterfall(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $client = $this->client($advisor);
        $this->pvFixture($client, current: 100000, improvement: 25000, risk: 10000);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('pvWaterfall.summary.clients', 1)
                ->where('pvWaterfall.summary.current_pv', 100000)
                ->where('pvWaterfall.summary.target_pv', 135000)
                ->where('pvWaterfall.clients.0.client_id', $client->id)
                ->where('pvWaterfall.clients.0.waterfall.1.label', 'Automation upside')
                ->where('pvWaterfall.clients.0.waterfall.3.end', 135000));
    }

    public function test_server_side_waterfall_chart_renders_for_reports(): void
    {
        $client = $this->client();
        $this->pvFixture($client, current: 100000, improvement: 25000, risk: 10000);

        $payload = app(PvWaterfallBuilder::class)->forClient($client);
        $html = app(PvWaterfallReportChart::class)->render($payload['waterfall']);

        $this->assertStringContainsString('PV waterfall', $html);
        $this->assertStringContainsString('Current PV', $html);
        $this->assertStringContainsString('Automation upside', $html);
        $this->assertStringContainsString('Risk mitigation value', $html);
        $this->assertStringContainsString('NZD 135,000', $html);
    }

    private function client(?User $advisor = null): Client
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => '9429000000000',
            'legal_name' => 'PV Waterfall Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
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

    private function pvFixture(Client $client, float $current, float $improvement, float $risk): void
    {
        $this->valuationFixture($client, $current);
        $this->opportunityFixture($client, 'Automation upside', $improvement, 1);
        $this->riskFixture($client, 'Risk mitigation value', $risk, 1);
    }

    private function valuationFixture(Client $client, float $current): void
    {
        BusinessValuation::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::BusinessValuation, $current)->getKey(),
            'sde_value' => ['low' => 90000, 'mid' => $current, 'high' => 110000],
            'ebitda_value' => ['low' => 90000, 'mid' => $current, 'high' => 110000],
            'dcf_value' => ['low' => 90000, 'mid' => $current, 'high' => 110000],
            'reconciled_low' => $current * 0.9,
            'reconciled_mid' => $current,
            'reconciled_high' => $current * 1.1,
            'adjustments' => [],
            'source_attributions' => [
                ['claim' => 'PV fixture', 'source_reference' => 'test:pv-fixture'],
            ],
            'as_at' => now(),
        ]);
    }

    private function opportunityFixture(
        Client $client,
        string $title,
        float $value,
        int $rank,
        ?AnalysisFinding $finding = null,
    ): void {
        ImprovementOpportunity::query()->create([
            'client_id' => $client->getKey(),
            'analysis_finding_id' => $finding?->id,
            'pv_calculation_id' => $this->pvCalculation($client, PvType::ImprovementOpportunity, $value)->getKey(),
            'title' => $title,
            'annual_benefit' => $value,
            'duration_years' => 1,
            'pv_of_impact' => $value,
            'rank' => $rank,
            'source_attributions' => [
                ['claim' => 'Improvement fixture', 'source_reference' => 'test:improvement'],
            ],
        ]);
    }

    private function riskFixture(Client $client, string $title, float $value, int $rank): void
    {
        RiskCost::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::RiskCost, $value)->getKey(),
            'title' => $title,
            'financial_impact' => $value,
            'probability' => 1,
            'duration_years' => 1,
            'applied_impact' => $value,
            'annual_expected_cost' => $value,
            'pv_of_cost' => $value,
            'rank' => $rank,
            'source_attributions' => [
                ['claim' => 'Risk fixture', 'source_reference' => 'test:risk'],
            ],
        ]);
    }

    private function analysisFinding(Client $client): AnalysisFinding
    {
        $run = AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => AnalysisModule::Financial,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [AnalysisLens::Diagnostic->value],
            'data_quality_snapshot' => [],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->getKey(),
            'client_id' => $client->getKey(),
            'lens' => AnalysisLens::Diagnostic,
            'severity' => FindingSeverity::High,
            'title' => 'PV source finding',
            'body' => 'Improvement opportunity source.',
            'attributions' => [
                ['claim' => 'PV source fixture', 'source_reference' => 'test:pv-finding'],
            ],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
        ]);
    }

    private function pvCalculation(Client $client, PvType $type, float $presentValue): PvCalculation
    {
        return PvCalculation::query()->create([
            'client_id' => $client->getKey(),
            'type' => $type,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'Fixture rate.',
            'inputs' => ['cash_flows' => []],
            'result' => ['present_value' => $presentValue],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'Fixture PV', 'source_reference' => 'test:pv'],
            ],
        ]);
    }
}
