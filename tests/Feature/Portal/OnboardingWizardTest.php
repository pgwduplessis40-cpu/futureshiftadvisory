<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\Questionnaire;
use App\Models\User;
use App\Models\WebsiteUrlConfirmation;
use App\Services\Portal\OnboardingWizard;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use App\Support\RequestContext;
use Database\Seeders\PostAcquisitionGapQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StandardAdvisoryQuestionnaireSeeder;
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
                'step' => OnboardingWizard::STEP_GOALS,
            ], absolute: false));

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_GOALS]), [
                'primary_goal' => 'Improve cash visibility before growth funding.',
                'success_measure' => 'Weekly reporting pack is trusted by the leadership team.',
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_WEBSITE,
            ], absolute: false));

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WEBSITE]), [
                'website_skipped' => true,
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_QUESTIONNAIRE,
            ], absolute: false));

        $state = $client->refresh()->onboarding_wizard_state;

        $this->assertSame(4, $state['current_step']);
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
                ->where('progress.completed', 3)
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

    public function test_client_website_submission_is_visible_to_the_advisor_for_confirmation(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient(EngagementType::STANDARD_ADVISORY);

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WELCOME]), [
                'acknowledged' => true,
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_GOALS]), [
                'primary_goal' => 'Improve cash visibility before growth funding.',
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WEBSITE]), [
                'website_url' => 'example.com',
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_QUESTIONNAIRE,
            ], absolute: false));

        $this->assertDatabaseHas('website_url_confirmations', [
            'client_id' => $client->getKey(),
            'root_url' => 'https://example.com/',
            'status' => WebsiteUrlConfirmation::STATUS_PENDING_ADVISOR_REVIEW,
        ]);

        $summary = app(StandardAdvisoryWorkflow::class)->clientSummary($client->refresh());

        $this->assertSame('awaiting_confirmation', data_get($summary, 'website_audit.status'));
        $this->assertSame('https://example.com/', data_get($summary, 'website_audit.candidates.0.url'));
        $this->assertSame('client', data_get($summary, 'website_audit.candidates.0.source'));
    }

    public function test_legacy_onboarding_state_skips_retired_internal_steps(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();
        $client->forceFill([
            'onboarding_wizard_state' => [
                'current_step' => 2,
                'completed_steps' => [
                    OnboardingWizard::STEP_WELCOME,
                    OnboardingWizard::STEP_IDENTITY,
                ],
            ],
        ])->save();

        $wizard = app(OnboardingWizard::class);
        $state = $wizard->state($client);

        $this->assertSame(2, $state['current_step']);
        $this->assertSame([OnboardingWizard::STEP_WELCOME], $state['completed_steps']);
        $this->assertSame(OnboardingWizard::STEP_GOALS, $wizard->currentStepSlug($client));
        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_IDENTITY]))
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_GOALS,
            ], absolute: false));
    }

    public function test_every_client_engagement_uses_only_client_facing_onboarding_steps(): void
    {
        $this->seed(RoleSeeder::class);
        $wizard = app(OnboardingWizard::class);
        $expectedSteps = [
            OnboardingWizard::STEP_WELCOME,
            OnboardingWizard::STEP_GOALS,
            OnboardingWizard::STEP_WEBSITE,
            OnboardingWizard::STEP_QUESTIONNAIRE,
            OnboardingWizard::STEP_DOCUMENTS,
            OnboardingWizard::STEP_REVIEW,
        ];

        foreach (EngagementType::cases() as $engagementType) {
            [, $client] = $this->clientUserWithClient($engagementType);
            $steps = array_column($wizard->navigation($client), 'slug');

            $this->assertSame($expectedSteps, $steps);
            $this->assertNotContains(OnboardingWizard::STEP_IDENTITY, $steps);
            $this->assertNotContains(
                OnboardingWizard::STEP_BUSINESS_SNAPSHOT,
                $steps,
            );
        }
    }

    public function test_questionnaire_draft_persists_without_completing_the_onboarding_step(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(StandardAdvisoryQuestionnaireSeeder::class);
        [$user, $client] = $this->clientUserWithClient(EngagementType::STANDARD_ADVISORY);
        $this->advanceToQuestionnaire($user);

        $questionnaire = Questionnaire::query()
            ->forSet('standard_advisory')
            ->published()
            ->firstOrFail();
        $questionId = (string) $questionnaire->sections()
            ->firstOrFail()
            ->questions()
            ->firstOrFail()
            ->getKey();

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.questionnaire.draft'), [
                'answers' => [
                    $questionId => [
                        'value' => 'A partially completed questionnaire answer.',
                        'attached_document_ids' => [],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['saved_at']);

        $state = $client->refresh()->onboarding_wizard_state;
        $this->assertSame(
            'A partially completed questionnaire answer.',
            data_get($state, "drafts.questionnaire.payload.answers.{$questionId}.value"),
        );
        $this->assertNotContains(OnboardingWizard::STEP_QUESTIONNAIRE, $state['completed_steps']);

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where("questionnaire.answers.{$questionId}.value", 'A partially completed questionnaire answer.')
                ->has('questionnaire.draft_saved_at')
            );
    }

    public function test_due_diligence_questionnaire_requires_dd_engagement(): void
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

    public function test_due_diligence_questionnaire_is_available_for_active_dd_engagement(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient(EngagementType::DUE_DILIGENCE);
        $this->createDdEngagement($client, $user);

        $this->advanceToQuestionnaire($user);

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/onboarding/Step')
                ->where('questionnaire.set', 'dd_specific')
                ->where('questionnaire.available', true)
                ->where('questionnaire.phase', 'Phase 3')
            );
    }

    public function test_post_acquisition_gap_questionnaire_is_available_for_completion(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PostAcquisitionGapQuestionnaireSeeder::class);
        [$user] = $this->clientUserWithClient(EngagementType::POST_ACQUISITION_ADVISORY);

        $this->advanceToQuestionnaire($user);

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/onboarding/Step')
                ->where('questionnaire.set', 'post_acquisition_gap')
                ->where('questionnaire.available', true)
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
        Document::query()->create([
            'client_id' => $client->id,
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => 'supporting-evidence.pdf',
            'stored_path' => 'documents/testing/supporting-evidence.pdf',
            'byte_size' => 1200,
            'mime_type' => 'application/pdf',
            'sha256' => str_repeat('a', 64),
            'uploaded_by_user_id' => $user->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
            'scanner_payload' => [],
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

        $this->assertCount(6, $state['completed_steps']);
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
            'email' => "client.owner+{$engagementType->value}@example.com",
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
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_GOALS]), [
                'primary_goal' => 'Improve cash visibility before growth funding.',
                'success_measure' => 'Trusted weekly reporting pack.',
            ]);
        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_WEBSITE]), [
                'website_skipped' => true,
            ]);
    }

    private function createDdEngagement(Client $client, User $user): DdEngagement
    {
        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $client->getKey(),
            'advisor_id' => $user->getKey(),
            'declaration' => [
                'referral_type' => 'direct',
                'existing_relationship' => false,
            ],
            'declared_at' => now(),
        ]);

        return DdEngagement::query()->create([
            'client_id' => $client->getKey(),
            'target_name' => 'Target Acquisition Limited',
            'target_details' => [
                'industry' => 'Manufacturing',
                'nzbn' => '9429000000099',
            ],
            'status' => DdEngagement::STATUS_IN_PROGRESS,
            'conflict_declaration_id' => $conflict->getKey(),
            'created_by_user_id' => $user->getKey(),
            'disclaimer_acknowledged_at' => now(),
        ]);
    }
}
