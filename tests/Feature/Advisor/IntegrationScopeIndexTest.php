<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\IntegrationScope;
use App\Models\Proposal;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IntegrationScopeIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_sees_only_scopes_for_accessible_clients(): void
    {
        $advisor = $this->advisor('scope-advisor@example.test');
        $otherAdvisor = $this->advisor('other-scope-advisor@example.test');
        $accessibleClient = $this->clientFor($advisor, 'Accessible Systems Limited');
        $otherClient = $this->clientFor($otherAdvisor, 'Other Systems Limited');

        $this->scopeFor($accessibleClient, $advisor, 'M', 8_000);
        $this->scopeFor($otherClient, $otherAdvisor, 'XL', 45_000);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.integration-scopes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/integration-scopes/Index')
                ->has('scopes', 1)
                ->where('scopes.0.client_name', 'Accessible Systems Limited')
                ->where('scopes.0.complexity_band', 'M')
                ->where('scopes.0.quoted_fee', 8_000)
                ->has('clients', 1)
                ->where('clients.0.name', 'Accessible Systems Limited'));
    }

    public function test_scope_show_exposes_the_quote_calculation_and_proposal_builder_link(): void
    {
        $advisor = $this->advisor('scope-quote-advisor@example.test');
        $client = $this->clientFor($advisor, 'Quote Ready Systems Limited');
        $scope = $this->scopeFor($client, $advisor, 'XL', 45_000);

        $this->integrationFeeCalculationFor($client, $scope, $advisor);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.integration-scopes.show', $scope))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/integration-scopes/Show')
                ->where('scope.fee_calculations.0.suggested_low', 45_000)
                ->where('scope.fee_calculations.0.suggested_mid', 45_000)
                ->where('scope.fee_calculations.0.suggested_high', 45_000)
                ->where(
                    'urls.clientProposals',
                    route('advisor.clients.show', $client, absolute: false).'#section-proposals',
                ));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where(
                    'client.fee_calculations.0.proposal_scope_summary',
                    'Design, build, test, and commission the agreed systems integrations. Systems in scope: Xero, Field Service Board. Connections in scope: Field Service Board to Xero (one way). The scoped outcome targets 199 annual hours returned to the team and NZD 8,043 in annual savings. Delivery model: In-house.',
                ));
    }

    public function test_client_hides_used_calculations_and_briefs_integration_proposals(): void
    {
        $advisor = $this->advisor('scope-brief-advisor@example.test');
        $client = $this->clientFor($advisor, 'Brief Ready Systems Limited');
        $scope = $this->scopeFor($client, $advisor, 'XL', 45_000);
        $calculation = $this->integrationFeeCalculationFor($client, $scope, $advisor);

        Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => 'draft',
            'version' => 3,
            'scope' => [
                'summary' => 'Systems integration delivery proposal.',
                'integration_quote_pack' => [
                    'systems' => $scope->systems,
                    'connections' => $scope->connections,
                    'delivery_mode' => $scope->delivery_mode,
                ],
            ],
            'services' => [],
            'pv_summary' => [],
            'roi_ratio' => 2.67,
            'acceptance_terms' => [],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->has('client.fee_calculations', 0)
                ->where('client.proposals.0.version', 3)
                ->where('client.proposals.0.fee_method_label', 'Integration')
                ->where(
                    'client.proposals.0.brief',
                    'Systems integration: Xero, Field Service Board. 1 scoped connection. In-house.',
                ));
    }

    public function test_integration_proposal_generation_skips_referral_consents(): void
    {
        $advisor = $this->advisor('scope-generation-advisor@example.test');
        $client = $this->clientFor($advisor, 'Consent Free Systems Limited');
        $scope = $this->scopeFor($client, $advisor, 'XL', 45_000);
        $calculation = $this->integrationFeeCalculationFor($client, $scope, $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.proposals.store', $client), [
                'fee_calculation_id' => $calculation->getKey(),
                'scope_summary' => 'Integration delivery for the scoped systems.',
                'budget_override_category' => 'advisor_judgement',
                'budget_override_notes' => 'Fixture confirms an integration proposal can be drafted before budget approval.',
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHasNoErrors();

        $proposal = Proposal::query()->firstOrFail();

        $this->assertSame('draft', $proposal->status->value);
        $this->assertSame('integration', $proposal->acceptance_terms['proposal_variant']);
        $this->assertFalse($proposal->acceptance_terms['referral_consents_required']);
        $this->assertDatabaseCount('consents', 0);
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

    private function clientFor(User $advisor, string $name): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000001',
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    private function scopeFor(Client $client, User $advisor, string $band, int $quotedFee): IntegrationScope
    {
        return IntegrationScope::query()->create([
            'client_id' => $client->getKey(),
            'status' => IntegrationScope::STATUS_COMPLETE,
            'systems' => [
                ['id' => 'xero', 'name' => 'Xero'],
                ['id' => 'field-service', 'name' => 'Field Service Board'],
            ],
            'connections' => [
                [
                    'from_system' => 'field-service',
                    'to_system' => 'xero',
                    'direction' => 'one_way',
                ],
            ],
            'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
            'computed' => [
                'complexity_band' => $band,
                'annual_hours_wasted' => 199.33,
                'annual_savings' => 8_043,
                'quoted_fee' => $quotedFee,
            ],
            'created_by_user_id' => $advisor->getKey(),
        ]);
    }

    private function integrationFeeCalculationFor(Client $client, IntegrationScope $scope, User $advisor): FeeCalculation
    {
        return FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'integration_scope_id' => $scope->getKey(),
            'method' => FeeMethod::Integration,
            'inputs' => ['integration_scope_id' => $scope->getKey()],
            'suggested_low' => 45_000,
            'suggested_mid' => 45_000,
            'suggested_high' => 45_000,
            'improvement_pv_total' => 120_000,
            'risk_cost_pv_total' => 0,
            'roi_ratio' => 2.67,
            'justification' => ['method' => FeeMethod::Integration->value],
            'created_by_user_id' => $advisor->getKey(),
        ]);
    }
}
