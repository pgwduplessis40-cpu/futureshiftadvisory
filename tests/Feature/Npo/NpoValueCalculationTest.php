<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\ClientTeamMember;
use App\Models\FinancialSnapshot;
use App\Models\Funder;
use App\Models\LearningUpdate;
use App\Models\NpoEngagement;
use App\Models\NpoValueCalculation;
use App\Models\User;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Npo\FunderRegistry;
use App\Services\Npo\NpoHealthScorer;
use App\Services\Npo\NpoValueCalculator;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class NpoValueCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cost_per_beneficiary_uses_layer_36_benchmark_and_mandatory_uncertainty(): void
    {
        Carbon::setTestNow('2026-05-27 10:00:00');
        [, , $engagement] = $this->npoClient();
        $benchmark = $this->learningUpdate(LayerCadenceRegistry::LAYER_NPO_COST_PER_BENEFICIARY_BENCHMARKS, [
            'action' => 'update_cpb_benchmarks',
            'benchmarks' => [
                [
                    'programme_type' => 'food_rescue',
                    'size_band' => 'small',
                    'cost_per_beneficiary' => 500,
                    'sample_size' => 5,
                ],
            ],
        ]);
        $benchmark->forceFill(['status' => LearningUpdate::STATUS_IMPLEMENTED])->save();

        $calculation = app(NpoValueCalculator::class)->calculateCostPerBeneficiary($engagement, [
            'programme_type' => 'food_rescue',
            'programme_expenditure' => 60000,
            'beneficiary_count' => 80,
            'theory_of_change' => 'Food rescue reduces household food insecurity by redirecting surplus meals.',
            'stakeholder_evidence' => ['beneficiary interviews', 'funder outcome report'],
            'external_verification_source' => 'Independent impact review 2026',
        ]);

        $this->assertSame(NpoValueCalculation::TYPE_COST_PER_BENEFICIARY, $calculation->type);
        $this->assertSame(NpoHealthScorer::DIMENSION_FINANCIAL_SUSTAINABILITY, $calculation->dimension_number);
        $this->assertSame('high_cost', $calculation->rating);
        $this->assertSame($benchmark->id, $calculation->benchmark_config['learning_update_id']);
        $this->assertEqualsWithDelta(750.0, $calculation->result['cost_per_beneficiary'], 0.01);
        $this->assertEqualsWithDelta(20000.0, $calculation->projection_mid, 0.01);
        $this->assertEqualsWithDelta(17000.0, $calculation->projection_low, 0.01);
        $this->assertEqualsWithDelta(23000.0, $calculation->projection_high, 0.01);
        $this->assertSame(0.15, $calculation->result['projections'][0]['uncertainty']['rate']);
        $this->assertSame('externally_verified', $calculation->result['impact_governance']['verification_status']);
        $this->assertSame('captured', $calculation->result['impact_governance']['theory_of_change_status']);
        $this->assertSame('captured', $calculation->result['impact_governance']['stakeholder_involvement_status']);
        $this->assertStringContainsString('mission delivery', $calculation->result['mission_framing']);
        $this->assertStringContainsString('+/-15%', $calculation->stable_assumption_disclosure);
    }

    public function test_cost_per_beneficiary_records_baseline_when_comparable_sample_is_not_ready(): void
    {
        Carbon::setTestNow('2026-05-27 10:00:00');
        [, , $engagement] = $this->npoClient();
        $benchmark = $this->learningUpdate(LayerCadenceRegistry::LAYER_NPO_COST_PER_BENEFICIARY_BENCHMARKS, [
            'action' => 'update_cpb_benchmarks',
            'benchmarks' => [
                [
                    'programme_type' => 'food_rescue',
                    'size_band' => 'small',
                    'cost_per_beneficiary' => 500,
                    'sample_size' => 4,
                ],
            ],
        ]);
        $benchmark->forceFill(['status' => LearningUpdate::STATUS_IMPLEMENTED])->save();

        $calculation = app(NpoValueCalculator::class)->calculateCostPerBeneficiary($engagement, [
            'programme_type' => 'food_rescue',
            'programme_expenditure' => 60000,
            'beneficiary_count' => 80,
        ]);

        $this->assertEqualsWithDelta(750.0, $calculation->result['cost_per_beneficiary'], 0.01);
        $this->assertNull($calculation->result['benchmark_cost_per_beneficiary']);
        $this->assertNull($calculation->result['variance_to_benchmark']);
        $this->assertSame('benchmark_pending', $calculation->rating);
        $this->assertSame(0.0, $calculation->projection_mid);
        $this->assertSame('benchmark_pending_baseline', $calculation->result['projections'][0]['key']);
        $this->assertSame(0.15, $calculation->result['projections'][0]['uncertainty']['rate']);
        $this->assertSame('internal_estimate_unverified', $calculation->result['impact_governance']['verification_status']);
        $this->assertStringContainsString('No official external NZ cost-per-beneficiary benchmark is available', $calculation->result['mission_framing']);
        $this->assertStringContainsString('pending a sufficient comparable sample', $calculation->source_attributions[0]['claim']);
    }

    public function test_funding_risk_rates_concentration_runway_and_layer_37_thresholds(): void
    {
        Carbon::setTestNow('2026-05-27 10:00:00');
        [, $client, $engagement] = $this->npoClient();
        $this->financialSnapshot($client, revenue: 200000, operatingExpenses: 120000, unrestrictedReserves: 30000);
        $anchor = $this->funder('Anchor Fund');
        $secondary = $this->funder('Secondary Fund');
        $this->record($client, $engagement, $anchor, grantAmount: 80000, renewalProbability: 25);
        $this->record($client, $engagement, $secondary, grantAmount: 20000, renewalProbability: 75);

        $defaultCalculation = app(NpoValueCalculator::class)->calculateFundingRisk($engagement);

        $this->assertSame(NpoValueCalculation::TYPE_FUNDING_RISK, $defaultCalculation->type);
        $this->assertSame(NpoHealthScorer::DIMENSION_SERVICE_OPERATIONS, $defaultCalculation->dimension_number);
        $this->assertSame('medium', $defaultCalculation->result['concentration']['rating']);
        $this->assertSame('high', $defaultCalculation->result['runway']['rating']);
        $this->assertSame('high', $defaultCalculation->rating);
        $this->assertEqualsWithDelta(0.4, $defaultCalculation->result['concentration']['largest_funder_to_revenue_ratio'], 0.0001);
        $this->assertEqualsWithDelta(3.0, $defaultCalculation->result['runway']['months'], 0.01);
        $this->assertEqualsWithDelta(65000.0, $defaultCalculation->result['renewal_weighted_scenario']['weighted_value_at_risk'], 0.01);
        $this->assertEqualsWithDelta(95000.0, $defaultCalculation->projection_mid, 0.01);
        $this->assertEqualsWithDelta(80750.0, $defaultCalculation->projection_low, 0.01);
        $this->assertEqualsWithDelta(109250.0, $defaultCalculation->projection_high, 0.01);

        $this->learningUpdate(LayerCadenceRegistry::LAYER_NPO_FUNDING_CONCENTRATION_THRESHOLDS, [
            'action' => 'update_funding_concentration_thresholds',
            'thresholds' => ['high' => 50, 'medium' => 30],
        ]);
        [, $configuredClient, $configuredEngagement] = $this->npoClient('configured-value-advisor@example.test', 'Configured Threshold Trust');
        $this->financialSnapshot($configuredClient, revenue: 200000, operatingExpenses: 120000, unrestrictedReserves: 120000);
        $configuredFunder = $this->funder('Configured Anchor Fund');
        $this->record($configuredClient, $configuredEngagement, $configuredFunder, grantAmount: 90000, renewalProbability: 90);

        $configured = app(NpoValueCalculator::class)->calculateFundingRisk($configuredEngagement);

        $this->assertEqualsWithDelta(0.45, $configured->result['concentration']['largest_funder_to_revenue_ratio'], 0.0001);
        $this->assertSame('medium', $configured->result['concentration']['rating']);
        $this->assertSame('low', $configured->result['runway']['rating']);
        $this->assertSame('medium', $configured->rating);
        $this->assertEqualsWithDelta(0.5, $configured->benchmark_config['concentration']['high_above'], 0.0001);
    }

    public function test_value_calculations_cannot_persist_without_uncertainty_ranges(): void
    {
        [, $client, $engagement] = $this->npoClient();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('+/-15%');

        NpoValueCalculation::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'type' => NpoValueCalculation::TYPE_COST_PER_BENEFICIARY,
            'dimension_number' => NpoHealthScorer::DIMENSION_FINANCIAL_SUSTAINABILITY,
            'rating' => 'watch',
            'projection_mid' => 1000,
            'projection_low' => 850,
            'projection_high' => 1150,
            'inputs' => ['programme_expenditure' => 1000, 'beneficiary_count' => 1],
            'result' => ['mission_framing' => 'Missing uncertainty range.'],
            'benchmark_config' => [],
            'source_attributions' => [],
            'stable_assumption_disclosure' => 'Stable assumptions disclosed.',
            'calculated_at' => now(),
        ]);
    }

    public function test_client_profile_surfaces_latest_mission_framed_calculations(): void
    {
        Carbon::setTestNow('2026-05-27 10:00:00');
        [$advisor, $client, $engagement] = $this->npoClient();
        $this->financialSnapshot($client, revenue: 200000, operatingExpenses: 120000, unrestrictedReserves: 60000);
        $funder = $this->funder('Profile Value Fund');
        $this->record($client, $engagement, $funder, grantAmount: 50000, renewalProbability: 50);

        app(NpoValueCalculator::class)->calculateCostPerBeneficiary($engagement, [
            'programme_type' => 'community_services',
            'programme_expenditure' => 120000,
            'beneficiary_count' => 100,
        ]);
        app(NpoValueCalculator::class)->calculateFundingRisk($engagement);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->has('client.npo_values.calculations', 2)
                ->where('client.npo_values.calculations.0.projections.0.uncertainty.rate', 0.15)
                ->where('client.npo_values.calculations.0.impact_governance.verification_status', 'internal_estimate_unverified')
                ->where('client.npo_values.calculations', fn (mixed $calculations): bool => collect($calculations)
                    ->pluck('type')
                    ->sort()
                    ->values()
                    ->all() === [
                        NpoValueCalculation::TYPE_COST_PER_BENEFICIARY,
                        NpoValueCalculation::TYPE_FUNDING_RISK,
                    ]));
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(string $advisorEmail = 'npo-value-advisor@example.test', string $clientName = 'NPO Value Trust'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);

        return [$advisor, $client, $engagement];
    }

    private function financialSnapshot(Client $client, float $revenue, float $operatingExpenses, float $unrestrictedReserves): FinancialSnapshot
    {
        $connection = AccountingConnection::query()->create([
            'client_id' => $client->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'external_tenant_id' => 'tenant-'.$client->id,
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => 'test-token',
            'token_envelope_meta' => [],
            'scopes' => ['financials.read'],
            'connected_at' => now(),
        ]);

        return FinancialSnapshot::query()->create([
            'client_id' => $client->id,
            'accounting_connection_id' => $connection->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'source' => 'xero_fixture',
            'source_badge' => 'Xero',
            'degraded' => false,
            'profit_and_loss' => [
                'revenue' => $revenue,
                'operating_expenses' => $operatingExpenses,
            ],
            'balance_sheet' => [
                'unrestricted_reserves' => $unrestrictedReserves,
            ],
            'cash_flow' => [
                'operating_cash_flow' => 0,
            ],
            'metrics' => [],
            'pulled_at' => now(),
        ]);
    }

    private function funder(string $name): Funder
    {
        return app(FunderRegistry::class)->upsertFromLearningUpdate(
            $this->learningUpdate(LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES, [
                'action' => 'update_funder_registry',
            ]),
            [
                'name' => $name,
                'type' => Funder::TYPE_PHILANTHROPIC,
                'funding_windows' => [],
                'criteria' => ['region' => 'Aotearoa'],
                'reporting_requirements' => ['six_month_report' => true],
                'renewal_intelligence' => ['renewal_weight' => 0.7],
                'last_verified_at' => now()->toIso8601String(),
            ],
        );
    }

    private function record(
        Client $client,
        NpoEngagement $engagement,
        Funder $funder,
        float $grantAmount,
        int $renewalProbability,
    ): ClientFunderRecord {
        /** @var ClientFunderRecord $record */
        $record = ClientFunderRecord::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'funder_id' => $funder->id,
            'grant_name' => 'Community grant',
            'grant_amount' => $grantAmount,
            'currency' => 'NZD',
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->addYear()->toDateString(),
            'conditions' => [],
            'renewal_probability' => $renewalProbability,
            'history' => [['event' => 'created']],
        ]);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $proposedChange
     */
    private function learningUpdate(int $layerId, array $proposedChange): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => $layerId,
            'source' => ['type' => 'npo_value_calculation_test'],
            'summary' => 'Update NPO value calculation configuration',
            'proposed_change' => $proposedChange,
            'impact_scope' => [
                'surface' => 'npo_value_calculations',
                'tenant_scope' => 'global',
            ],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.8,
            'evidence' => ['source' => 'test'],
            'status' => LearningUpdate::STATUS_APPROVED,
        ]);
    }
}
