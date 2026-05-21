<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Services\Portal\OnboardingWizard;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_user_reaches_portal_dashboard_after_auth_gates(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/Dashboard')
                ->where('client.id', $client->id)
                ->where('progress.completed', 0)
                ->where('currentStep', OnboardingWizard::STEP_WELCOME)
            );
    }

    public function test_wizard_step_order_is_enforced_server_side(): void
    {
        $this->seed(RoleSeeder::class);
        [$user] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_WELCOME,
            ], absolute: false));
    }

    public function test_wizard_state_persists_between_steps(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WELCOME]), [
                'acknowledged' => true,
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_IDENTITY,
            ], absolute: false));

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_IDENTITY]), [
                'name' => 'Client Owner',
                'email' => 'client.owner@example.com',
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_BUSINESS_SNAPSHOT,
            ], absolute: false));

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_BUSINESS_SNAPSHOT]), [
                'snapshot_confirmed' => true,
            ]);

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_GOALS]), [
                'primary_goal' => 'Improve cash visibility before growth funding.',
                'success_measure' => 'Weekly reporting pack is trusted by the leadership team.',
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_QUESTIONNAIRE,
            ], absolute: false));

        $state = $client->refresh()->onboarding_wizard_state;

        $this->assertSame(5, $state['current_step']);
        $this->assertContains(OnboardingWizard::STEP_GOALS, $state['completed_steps']);
        $this->assertSame(
            'Improve cash visibility before growth funding.',
            $state['steps'][OnboardingWizard::STEP_GOALS]['primary_goal'],
        );

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_GOALS]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/onboarding/Step')
                ->where('step.slug', OnboardingWizard::STEP_GOALS)
                ->where('stepData.primary_goal', 'Improve cash visibility before growth funding.')
                ->where('progress.completed', 4)
            );
    }

    public function test_standard_advisory_engagement_uses_phase_one_questionnaire_path(): void
    {
        $this->seed(RoleSeeder::class);
        [$user] = $this->clientUserWithClient(EngagementType::STANDARD_ADVISORY);

        $this->advanceToQuestionnaire($user);

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/onboarding/Step')
                ->where('questionnaire.set', 'standard_advisory')
                ->where('questionnaire.available', true)
                ->where('questionnaire.phase', 'Phase 1')
            );
    }

    public function test_due_diligence_questionnaire_is_gated_to_phase_three(): void
    {
        $this->seed(RoleSeeder::class);
        [$user] = $this->clientUserWithClient(EngagementType::DUE_DILIGENCE);

        $this->advanceToQuestionnaire($user);

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/onboarding/Step')
                ->where('questionnaire.set', 'dd_specific')
                ->where('questionnaire.available', false)
                ->where('questionnaire.phase', 'Phase 3')
            );
    }

    public function test_review_submit_completes_the_wizard(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();

        $this->advanceToQuestionnaire($user);

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), [
                'questionnaire_set_acknowledged' => true,
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_DOCUMENTS]), [
                'documents_acknowledged' => true,
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_REVIEW]), [
                'review_confirmed' => true,
            ])
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $state = $client->refresh()->onboarding_wizard_state;

        $this->assertCount(7, $state['completed_steps']);
        $this->assertNotNull($state['submitted_at']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'portal.onboarding_step_saved',
            'client_id' => $client->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(
        EngagementType $engagementType = EngagementType::STANDARD_ADVISORY,
    ): array {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Client Owner',
            'email' => 'client.owner@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => $engagementType,
            'nzbn' => '9429000000000',
            'legal_name' => 'Future Shift Advisory Test Limited',
            'trading_name' => 'Future Shift',
            'entity_type' => 'NZ Limited Company',
            'gst_registered' => true,
            'filing_status' => 'registered',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [$engagementType->value],
        ]);

        return [$user, $client];
    }

    private function advanceToQuestionnaire(User $user): void
    {
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WELCOME]), [
                'acknowledged' => true,
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_IDENTITY]), [
                'name' => 'Client Owner',
                'email' => 'client.owner@example.com',
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_BUSINESS_SNAPSHOT]), [
                'snapshot_confirmed' => true,
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_GOALS]), [
                'primary_goal' => 'Improve cash visibility before growth funding.',
                'success_measure' => 'Trusted weekly reporting pack.',
            ]);
    }
}
