<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\Permission;
use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\LearningUpdate;
use App\Models\ServiceActivation;
use App\Models\Survey;
use App\Models\SurveyAssignment;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Surveys\SurveyLibrary;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ServiceImprovementSurveyTest extends TestCase
{
    use DatabaseTransactions;

    private Survey $survey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        TermsVersion::query()->delete();
        app(RequestContext::class)->apply('system', []);

        $admin = $this->superAdmin();
        $this->survey = app(SurveyLibrary::class)->ensureServiceImprovement($admin);
        $this->survey->forceFill([
            'status' => SurveyStatus::Published->value,
            'published_at' => now(),
            'published_by_user_id' => $admin->getKey(),
        ])->save();
    }

    public function test_super_admin_can_issue_service_survey_and_client_submission_becomes_a_learning_signal(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient();
        $admin = $this->superAdmin('service-survey-issuer@example.test');
        $activation = $this->closedService($client);

        $this->actingAsMfa($admin)
            ->post(route('admin.service-surveys.store', $activation), [
                'survey_id' => $this->survey->id,
            ])
            ->assertRedirect();

        $assignment = SurveyAssignment::query()->with('survey.questions')->sole();

        $this->assertSame($activation->id, $assignment->service_activation_id);
        $this->assertSame($client->id, $assignment->client_id);
        $this->assertSame([], $assignment->deliverable_snapshot);
        $this->assertSame('Explore buying a business', $assignment->service_snapshot['service_label']);
        $this->assertSame('Due diligence review', $assignment->service_snapshot['package_label']);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.surveys.show', $assignment))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/surveys/Show')
                ->where('assignment.service.service_label', 'Explore buying a business'));

        $answers = $this->answersFor($assignment);
        $serviceFitQuestion = $assignment->survey->questions->firstWhere('key', 'service_fit');
        $this->assertInstanceOf(SurveyQuestion::class, $serviceFitQuestion);
        $answers[$serviceFitQuestion->id] = [
            'value' => 3,
            'comment' => 'The advice was useful, but more time to apply it between sessions would improve the fit.',
        ];

        $this->actingAsMfa($clientUser)
            ->post(route('portal.surveys.submit', $assignment), [
                'answers' => $answers,
            ])
            ->assertRedirect(route('portal.surveys.index', absolute: false));

        $response = SurveyResponse::query()->with('answers.question')->sole();

        $this->assertSame(SurveyAssignmentStatus::Completed, $assignment->refresh()->status);
        $this->assertSame('Reduce the time between our workshop and the written follow-up.', $response->answers
            ->firstWhere('question.key', 'improve_next_time')?->value['value']);
        $this->assertSame('The advice was useful, but more time to apply it between sessions would improve the fit.', $response->answers
            ->firstWhere('question.key', 'service_fit')?->value['comment']);
        $this->assertDatabaseHas('learning_updates', [
            'layer_id' => LayerCadenceRegistry::LAYER_SERVICE_ACTIVATION,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    public function test_advisor_general_survey_action_cannot_issue_a_service_improvement_survey(): void
    {
        [, $client] = $this->clientUserWithClient('service-survey-general-client@example.test');
        $admin = $this->superAdmin('service-survey-general-admin@example.test');

        $this->actingAsMfa($admin)
            ->post(route('advisor.clients.survey-assignments.store', $client), [
                'survey_id' => $this->survey->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('survey_assignments', 0);
    }

    public function test_builtin_service_survey_upgrade_publishes_v1_1_and_preserves_the_v1_0_template(): void
    {
        $this->survey->delete();

        $legacy = Survey::query()->create([
            'key' => SurveyLibrary::SERVICE_IMPROVEMENT_KEY,
            'version' => SurveyLibrary::DEFAULT_VERSION,
            'type' => 'service_improvement',
            'title' => 'Service improvement survey',
            'status' => SurveyStatus::Published->value,
        ]);
        $legacy->questions()->create([
            'order' => 1,
            'type' => 'likert',
            'key' => 'service_fit',
            'prompt' => 'How well did this service meet the need you engaged us for?',
            'help_text' => null,
            'options' => [
                ['value' => 1, 'label' => 'Very poor'],
                ['value' => 5, 'label' => 'Excellent'],
            ],
            'required' => true,
        ]);

        $updated = app(SurveyLibrary::class)->ensureServiceImprovement($this->superAdmin('service-survey-upgrade@example.test'));

        $this->assertSame(SurveyLibrary::SERVICE_IMPROVEMENT_VERSION, $updated->version);
        $this->assertSame(SurveyStatus::Published, $updated->status);
        $this->assertNotNull($updated->published_at);
        $this->assertSame(
            'Consider how well this completed service addressed the need you engaged us for.',
            $updated->questions->firstWhere('key', 'service_fit')?->help_text,
        );
        $this->assertSame(SurveyStatus::Archived, $legacy->refresh()->status);
        $this->assertNull($legacy->questions()->firstOrFail()->help_text);
    }

    public function test_admin_survey_pages_do_not_reconcile_builtin_templates_on_get(): void
    {
        Survey::query()->delete();
        $admin = $this->superAdmin('service-survey-readonly-admin@example.test');

        $this->actingAsMfa($admin)
            ->get(route('admin.surveys.index'))
            ->assertOk();

        $this->actingAsMfa($admin)
            ->get(route('admin.service-surveys.index'))
            ->assertOk();

        $this->assertDatabaseCount('surveys', 0);
    }

    public function test_builtin_survey_templates_are_reconciled_by_explicit_command(): void
    {
        Survey::query()->delete();

        $this->artisan('fsa:ensure-built-in-surveys')
            ->assertSuccessful();

        $this->assertDatabaseHas('surveys', [
            'key' => SurveyLibrary::CLIENT_EXPERIENCE_KEY,
            'version' => SurveyLibrary::DEFAULT_VERSION,
            'type' => 'general_experience',
        ]);
        $this->assertDatabaseHas('surveys', [
            'key' => SurveyLibrary::SERVICE_IMPROVEMENT_KEY,
            'version' => SurveyLibrary::SERVICE_IMPROVEMENT_VERSION,
            'type' => 'service_improvement',
            'status' => SurveyStatus::Published->value,
        ]);
        $this->assertSame(8, Survey::query()
            ->where('key', SurveyLibrary::SERVICE_IMPROVEMENT_KEY)
            ->where('version', SurveyLibrary::SERVICE_IMPROVEMENT_VERSION)
            ->firstOrFail()
            ->questions()
            ->count());
    }

    public function test_super_admin_can_issue_service_survey_to_an_advisory_ready_entrepreneur(): void
    {
        $admin = $this->superAdmin('entrepreneur-service-survey-admin@example.test');
        $entrepreneurUser = User::factory()->withTwoFactor()->create([
            'email' => 'entrepreneur-service-survey@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneurUser->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneurUser->getKey(),
            'assigned_advisor_id' => $admin->getKey(),
            'name' => 'Entrepreneur Service Survey',
            'email' => $entrepreneurUser->email,
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => 'entrepreneur_combo',
        ]);

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/entrepreneurs/Show')
                ->where(
                    'entrepreneur.service_feedback_survey.action_url',
                    route('admin.service-surveys.entrepreneurs.store', $profile, absolute: false),
                ));

        $this->actingAsMfa($admin)
            ->post(route('admin.service-surveys.entrepreneurs.store', $profile))
            ->assertRedirect();

        $assignment = SurveyAssignment::query()->with('survey.questions')->sole();

        $this->assertNull($assignment->client_id);
        $this->assertSame(SurveyLibrary::SERVICE_IMPROVEMENT_VERSION, $assignment->survey->version);
        $this->assertSame($profile->id, $assignment->entrepreneur_profile_id);
        $this->assertNull($assignment->service_activation_id);
        $this->assertSame('entrepreneur_profile', $assignment->service_snapshot['source']);
        $this->assertSame('Entrepreneur advisory service', $assignment->service_snapshot['service_label']);

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('entrepreneur.service_feedback_survey.action_url', null)
                ->where(
                    'entrepreneur.service_feedback_survey.unavailable_reason',
                    'A service feedback survey is already awaiting a response.',
                ));

        $this->actingAsMfa($entrepreneurUser)
            ->get(route('portal.entrepreneur.surveys.show', $assignment))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/entrepreneur/surveys/Show')
                ->where('assignment.service.service_label', 'Entrepreneur advisory service'));
    }

    public function test_super_admin_can_issue_an_idea_validation_service_survey_after_builder_gate_approval(): void
    {
        $admin = $this->superAdmin('idea-validation-service-survey-admin@example.test');
        $entrepreneurUser = User::factory()->withTwoFactor()->create([
            'email' => 'idea-validation-service-survey@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneurUser->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneurUser->getKey(),
            'assigned_advisor_id' => $admin->getKey(),
            'name' => 'Idea Validation Service Survey',
            'email' => $entrepreneurUser->email,
            'stage' => EntrepreneurStage::BUILDING_PHASE_1,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => 'entrepreneur_combo',
        ]);
        $ideaValidation = IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'evaluated_by_user_id' => $entrepreneurUser->getKey(),
            'problem' => 'Local owners need a clear way to validate viable business ideas.',
            'target_customer' => 'New founders testing service businesses in their communities.',
            'solution' => 'A structured idea validation process with advisor feedback and evidence checks.',
            'value_proposition' => 'Founders gain confidence and a clear next step before investing in a business plan.',
            'demand_signal' => 'Interviews and paid pilots demonstrate demand for the planned service.',
            'revenue_model' => 'Fixed-fee validation workshops followed by a paid business plan package.',
            'ai_evaluation' => ['summary' => 'Approved for builder access.'],
            'viability_alerts' => [],
            'evaluated_at' => now()->subDay(),
            'advisor_gate_passed_at' => now()->subHour(),
            'advisor_gate_passed_by_user_id' => $admin->getKey(),
            'advisor_gate_note' => 'The validation evidence is complete and the founder may begin planning.',
        ]);

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/entrepreneurs/Show')
                ->where(
                    'entrepreneur.service_feedback_survey.action_url',
                    route('admin.service-surveys.entrepreneurs.store', $profile, absolute: false),
                )
                ->where('entrepreneur.service_feedback_survey.service_label', 'Idea Validation'));

        $this->actingAsMfa($admin)
            ->post(route('admin.service-surveys.entrepreneurs.store', $profile))
            ->assertRedirect();

        $assignment = SurveyAssignment::query()->with('survey.questions')->sole();

        $this->assertSame($profile->id, $assignment->entrepreneur_profile_id);
        $this->assertSame('idea_validation', $assignment->service_snapshot['source']);
        $this->assertSame($ideaValidation->id, $assignment->service_snapshot['idea_validation_id']);
        $this->assertSame('Idea Validation', $assignment->service_snapshot['service_label']);
        $this->assertSame($ideaValidation->advisor_gate_passed_at?->toIso8601String(), $assignment->service_snapshot['completed_at']);

        $this->actingAsMfa($entrepreneurUser)
            ->get(route('portal.entrepreneur.surveys.show', $assignment))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/entrepreneur/surveys/Show')
                ->where('assignment.service.service_label', 'Idea Validation'));
    }

    public function test_super_admin_can_issue_an_idea_validation_service_survey_when_the_default_template_is_unpublished(): void
    {
        $this->survey->forceFill([
            'status' => SurveyStatus::Draft->value,
            'published_at' => null,
            'published_by_user_id' => null,
        ])->save();

        $admin = $this->superAdmin('idea-validation-unpublished-survey-admin@example.test');
        $entrepreneurUser = User::factory()->withTwoFactor()->create([
            'email' => 'idea-validation-unpublished-survey@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneurUser->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneurUser->getKey(),
            'assigned_advisor_id' => $admin->getKey(),
            'name' => 'Idea Validation Unpublished Survey',
            'email' => $entrepreneurUser->email,
            'stage' => EntrepreneurStage::BUILDING_PHASE_1,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => 'entrepreneur_combo',
        ]);
        $ideaValidation = IdeaValidation::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'evaluated_by_user_id' => $entrepreneurUser->getKey(),
            'problem' => 'Founders need a clear way to validate ideas.',
            'target_customer' => 'Early stage founders.',
            'solution' => 'A structured idea validation process.',
            'value_proposition' => 'A clear decision before planning investment.',
            'demand_signal' => 'Paid discovery calls show demand.',
            'revenue_model' => 'Fixed fee validation workshops.',
            'ai_evaluation' => ['summary' => 'Approved for builder access.'],
            'viability_alerts' => [],
            'evaluated_at' => now()->subDay(),
            'advisor_gate_passed_at' => now()->subHour(),
            'advisor_gate_passed_by_user_id' => $admin->getKey(),
            'advisor_gate_note' => 'The validation evidence is complete.',
        ]);

        $this->actingAsMfa($admin)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where(
                    'entrepreneur.service_feedback_survey.action_url',
                    route('admin.service-surveys.entrepreneurs.store', $profile, absolute: false),
                )
                ->where('entrepreneur.service_feedback_survey.unavailable_reason', null)
                ->where('entrepreneur.service_feedback_survey.service_label', 'Idea Validation'));

        $this->actingAsMfa($admin)
            ->post(route('admin.service-surveys.entrepreneurs.store', $profile))
            ->assertRedirect();

        $assignment = SurveyAssignment::query()->with('survey')->sole();

        $this->assertSame($profile->id, $assignment->entrepreneur_profile_id);
        $this->assertSame('idea_validation', $assignment->service_snapshot['source']);
        $this->assertSame($ideaValidation->id, $assignment->service_snapshot['idea_validation_id']);
        $this->assertSame(SurveyStatus::Published, $assignment->survey->status);
        $this->assertNotNull($assignment->survey->published_at);
    }

    public function test_super_admin_with_multiple_roles_can_issue_a_service_survey_to_an_assigned_entrepreneur(): void
    {
        $manager = User::factory()->superAdmin()->withTwoFactor()->create([
            'email' => 'multi-role-service-survey-admin@example.test',
        ]);
        $manager->assignRole(User::TYPE_ADVISOR);
        $manager->assignRole(User::TYPE_SUPER_ADMIN);

        $entrepreneurUser = User::factory()->withTwoFactor()->create([
            'email' => 'multi-role-service-survey-entrepreneur@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneurUser->assignRole(User::TYPE_ENTREPRENEUR);

        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneurUser->getKey(),
            'assigned_advisor_id' => $manager->getKey(),
            'name' => 'Multi-role Service Survey Entrepreneur',
            'email' => $entrepreneurUser->email,
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => 'entrepreneur_combo',
        ]);

        $this->actingAsMfa($manager)
            ->get(route('advisor.entrepreneurs.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where(
                    'entrepreneur.service_feedback_survey.action_url',
                    route('admin.service-surveys.entrepreneurs.store', $profile, absolute: false),
                ));

        $this->actingAsMfa($manager)
            ->post(route('admin.service-surveys.entrepreneurs.store', $profile))
            ->assertRedirect();

        $this->assertDatabaseHas('survey_assignments', [
            'entrepreneur_profile_id' => $profile->getKey(),
            'activated_by_user_id' => $manager->getKey(),
        ]);
    }

    public function test_advisor_cannot_issue_an_entrepreneur_service_survey(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'unprivileged-service-survey-advisor@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Restricted Service Survey Entrepreneur',
            'email' => 'restricted-service-survey-entrepreneur@example.test',
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'intended_service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'intended_package_scope' => 'entrepreneur_combo',
        ]);

        $this->actingAsMfa($advisor)
            ->post(route('admin.service-surveys.entrepreneurs.store', $profile))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_issue_service_survey(): void
    {
        [, $client] = $this->clientUserWithClient('service-survey-advisor-client@example.test');
        $activation = $this->closedService($client);
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'service-survey-advisor@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->post(route('admin.service-surveys.store', $activation), [
                'survey_id' => $this->survey->id,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0:User,1:Client}
     */
    private function clientUserWithClient(string $email = 'service-survey-client@example.test'): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => 'Service Survey Client '.fake()->unique()->company(),
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        app(RequestContext::class)->apply('system', []);

        return [$user, $client];
    }

    private function closedService(Client $client): ServiceActivation
    {
        return ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'client_label' => 'Business purchase review',
            'status' => ServiceActivation::STATUS_CLOSED,
            'selected_package_snapshot' => [
                'package_name' => 'Due diligence review',
                'client_label' => 'Due diligence review',
            ],
            'closed_at' => now(),
        ]);
    }

    private function superAdmin(string $email = 'service-survey-admin@example.test'): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create([
            'email' => $email,
        ]);
        $user->assignRole(User::TYPE_SUPER_ADMIN);
        $user->givePermissionTo(Permission::SURVEYS_MANAGE->value);

        return $user;
    }

    /**
     * @return array<string, array{value:string|int}>
     */
    private function answersFor(SurveyAssignment $assignment): array
    {
        $assignment->loadMissing('survey.questions');
        $answers = [];

        /** @var SurveyQuestion $question */
        foreach ($assignment->survey->questions as $question) {
            $answers[$question->id] = match ($question->type?->value) {
                'nps' => ['value' => 8],
                'text' => ['value' => match ($question->key) {
                    'most_valuable' => 'The practical decision checklist.',
                    'improve_next_time' => 'Reduce the time between our workshop and the written follow-up.',
                    default => 'No further issues.',
                }],
                default => ['value' => 4],
            };
        }

        return $answers;
    }
}
