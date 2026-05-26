<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Enums\NpoTiritiMode;
use App\Events\NpoEngagementWeightingChanged;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Npo\NpoEngagementConfiguration;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class NpoEngagementConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_decision_guide_suggests_standalone_on_any_yes_and_woven_on_all_no(): void
    {
        $configuration = app(NpoEngagementConfiguration::class);

        $this->assertSame(NpoTiritiMode::Standalone, $configuration->suggestTiritiMode([
            NpoEngagementConfiguration::GUIDE_GOVERNANCE_OBLIGATION => false,
            NpoEngagementConfiguration::GUIDE_MANA_WHENUA_RELATIONSHIP => true,
            NpoEngagementConfiguration::GUIDE_TIRITI_OUTCOMES => false,
        ]));

        $this->assertSame(NpoTiritiMode::Woven, $configuration->suggestTiritiMode([
            NpoEngagementConfiguration::GUIDE_GOVERNANCE_OBLIGATION => false,
            NpoEngagementConfiguration::GUIDE_MANA_WHENUA_RELATIONSHIP => false,
            NpoEngagementConfiguration::GUIDE_TIRITI_OUTCOMES => false,
        ]));
    }

    public function test_advisor_configures_full_engagement_and_weighting_change_is_audited_and_emits_hook(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient();
        Event::fake([NpoEngagementWeightingChanged::class]);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.npo-engagements.configuration.update', $engagement), [
                'legal_structure' => NpoLegalStructure::CommunityTrustOrFoundation->value,
                'tiriti_decision_guide' => [
                    NpoEngagementConfiguration::GUIDE_GOVERNANCE_OBLIGATION => false,
                    NpoEngagementConfiguration::GUIDE_MANA_WHENUA_RELATIONSHIP => false,
                    NpoEngagementConfiguration::GUIDE_TIRITI_OUTCOMES => false,
                ],
                'tiriti_mode' => NpoTiritiMode::Woven->value,
                'social_enterprise' => true,
                'social_enterprise_type' => NpoSocialEnterpriseType::FeeForService->value,
                'commercial_weight' => 55,
                'mission_weight' => 45,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $engagement = $engagement->refresh();
        $this->assertSame(NpoLegalStructure::CommunityTrustOrFoundation, $engagement->legal_structure);
        $this->assertSame(NpoTiritiMode::Woven, $engagement->tiriti_mode);
        $this->assertTrue($engagement->social_enterprise);
        $this->assertSame(NpoSocialEnterpriseType::FeeForService, $engagement->social_enterprise_type);
        $this->assertSame(55, $engagement->commercial_weight);
        $this->assertSame(45, $engagement->mission_weight);

        Event::assertDispatched(
            NpoEngagementWeightingChanged::class,
            fn (NpoEngagementWeightingChanged $event): bool => $event->npoEngagementId === $engagement->id
                && $event->clientId === $client->id
                && $event->commercialWeight === 55
                && $event->missionWeight === 45,
        );

        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo_engagement.configuration_updated',
            'subject_id' => $engagement->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo_engagement.weighting_changed',
            'subject_id' => $engagement->id,
        ]);
    }

    public function test_social_enterprise_weights_must_sum_to_100(): void
    {
        [$advisor, , $engagement] = $this->npoClient('npo-config-weighting@example.test');

        $this->actingAsMfa($advisor)
            ->from(route('advisor.clients.show', $engagement->client))
            ->patch(route('advisor.npo-engagements.configuration.update', $engagement), [
                'legal_structure' => NpoLegalStructure::RegisteredCharity->value,
                'tiriti_decision_guide' => [
                    NpoEngagementConfiguration::GUIDE_GOVERNANCE_OBLIGATION => true,
                    NpoEngagementConfiguration::GUIDE_MANA_WHENUA_RELATIONSHIP => false,
                    NpoEngagementConfiguration::GUIDE_TIRITI_OUTCOMES => false,
                ],
                'tiriti_mode' => NpoTiritiMode::Standalone->value,
                'social_enterprise' => true,
                'social_enterprise_type' => NpoSocialEnterpriseType::TradingEnterprise->value,
                'commercial_weight' => 70,
                'mission_weight' => 40,
            ])
            ->assertRedirect(route('advisor.clients.show', $engagement->client, absolute: false))
            ->assertSessionHasErrors('mission_weight');
    }

    public function test_client_show_exposes_full_npo_configuration_payload(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient('npo-config-show@example.test');

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.npo_configuration.id', $engagement->id)
                ->where('client.npo_configuration.legal_structure', NpoLegalStructure::RegisteredCharity->value)
                ->where('client.npo_configuration.tiriti_suggested_mode', NpoTiritiMode::Woven->value)
                ->where('client.npo_configuration.update_url', route('advisor.npo-engagements.configuration.update', $engagement, absolute: false))
                ->has('client.npo_configuration.tiriti_decision_questions', 3)
                ->has('client.npo_configuration.social_enterprise_type_options', 4));
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(
        string $advisorEmail = 'npo-config-advisor@example.test',
        string $clientName = 'Full NPO Configuration Trust',
    ): array {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_LOW,
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
            'isa_2022_reregistered' => null,
        ]);

        return [$advisor, $client, $engagement];
    }
}
