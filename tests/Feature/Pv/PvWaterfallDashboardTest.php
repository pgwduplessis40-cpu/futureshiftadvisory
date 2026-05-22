<?php

declare(strict_types=1);

namespace Tests\Feature\Pv;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\PvType;
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
        $this->assertSame(125000.0, $payload['waterfall'][1]['end']);
        $this->assertSame(135000.0, $payload['waterfall'][3]['end']);
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
                ->where('pvWaterfall.clients.0.waterfall.1.key', 'improvements')
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
        $this->assertStringContainsString('Improvements', $html);
        $this->assertStringContainsString('Risk mitigation', $html);
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
        $valuationCalculation = $this->pvCalculation($client, PvType::BusinessValuation, $current);

        BusinessValuation::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $valuationCalculation->getKey(),
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

        ImprovementOpportunity::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::ImprovementOpportunity, $improvement)->getKey(),
            'title' => 'Automation upside',
            'annual_benefit' => $improvement,
            'duration_years' => 1,
            'pv_of_impact' => $improvement,
            'rank' => 1,
            'source_attributions' => [
                ['claim' => 'Improvement fixture', 'source_reference' => 'test:improvement'],
            ],
        ]);

        RiskCost::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $this->pvCalculation($client, PvType::RiskCost, $risk)->getKey(),
            'title' => 'Risk mitigation value',
            'financial_impact' => $risk,
            'probability' => 1,
            'duration_years' => 1,
            'applied_impact' => $risk,
            'annual_expected_cost' => $risk,
            'pv_of_cost' => $risk,
            'rank' => 1,
            'source_attributions' => [
                ['claim' => 'Risk fixture', 'source_reference' => 'test:risk'],
            ],
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
