<?php

declare(strict_types=1);

namespace Tests\Feature\Dd;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdEngagement;
use App\Models\Questionnaire;
use App\Models\User;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DdDisclaimer;
use App\Services\Dd\DdOnboarding;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class DdOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_dd_onboarding_creates_engagement_with_conflict_gate_questionnaire_and_disclaimer(): void
    {
        [$advisor, $client] = $this->ddClientWithAdvisor();
        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );

        $engagement = app(DdOnboarding::class)->start(
            buyer: $client,
            advisor: $advisor,
            conflict: $conflict,
            targetName: 'Target Bakery Limited',
            targetDetails: [
                'nzbn' => '9429000000999',
                'vendor_name' => 'Vendor Person',
                'industry' => 'Food manufacturing',
                'asking_price' => 1250000,
                'notes' => 'Vendor supplied initial teaser only.',
            ],
        );

        $this->assertSame(DdEngagement::STATUS_IN_PROGRESS, $engagement->status);
        $this->assertSame($client->id, $engagement->client_id);
        $this->assertSame('Target Bakery Limited', $engagement->target_name);
        $this->assertSame('acquisition_target_only', $engagement->target_details['data_scope']);
        $this->assertSame('Food manufacturing', $engagement->target_details['industry']);
        $this->assertNotEquals($engagement->target_name, $client->legal_name);
        $this->assertStringContainsString('not legal, tax, accounting, investment', DdDisclaimer::STANDARD);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.engagement_started',
            'subject_id' => $engagement->id,
        ]);

        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::DUE_DILIGENCE)
            ->published()
            ->firstOrFail();

        $this->assertSame('Due Diligence Questionnaire', $questionnaire->title);
        $this->assertGreaterThan(0, $questionnaire->sections()->count());
    }

    public function test_stale_or_wrong_conflict_blocks_dd_onboarding(): void
    {
        [$advisor, $client] = $this->ddClientWithAdvisor('blocked-dd-advisor@example.test');
        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::CLIENT_CREATION,
            existingRelationship: false,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A fresh due diligence conflict declaration is required before DD onboarding.');

        app(DdOnboarding::class)->start($client, $advisor, $conflict, 'Wrong Conflict Target', []);
    }

    public function test_advisor_client_show_surfaces_acquisition_target_tab_payload(): void
    {
        [$advisor, $client] = $this->ddClientWithAdvisor('dd-tab-advisor@example.test');
        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );
        app(DdOnboarding::class)->start($client, $advisor, $conflict, 'Target Panel Limited', [
            'vendor_name' => 'Panel Vendor',
            'industry' => 'Services',
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.due_diligence.target_name', 'Target Panel Limited')
                ->where('client.due_diligence.questionnaire.set', QuestionnaireSet::DUE_DILIGENCE->value)
                ->where('client.due_diligence.standard_advisory_deferred', true)
                ->where('client.due_diligence.acquisition_target_tab', true));
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function ddClientWithAdvisor(string $advisorEmail = 'dd-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Buyer Holdings Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        return [$advisor, $client];
    }
}
