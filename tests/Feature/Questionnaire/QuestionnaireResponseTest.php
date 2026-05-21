<?php

declare(strict_types=1);

namespace Tests\Feature\Questionnaire;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Questionnaire;
use App\Models\QuestionnaireAnswer;
use App\Models\User;
use App\Services\Portal\OnboardingWizard;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuestionnaireResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_questionnaire_submit_enforces_conditional_logic_and_records_attached_documents(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();
        [$questionnaire, $control, $dependent, $fileAttach] = $this->conditionalQuestionnaire();
        $documentId = (string) Str::uuid();

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), [
                'answers' => [
                    $control => ['value' => 'no', 'attached_document_ids' => []],
                    $fileAttach => ['value' => null, 'attached_document_ids' => [$documentId]],
                ],
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_DOCUMENTS,
            ], absolute: false));

        $this->assertDatabaseHas('questionnaire_responses', [
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
        ]);
        $this->assertDatabaseMissing('questionnaire_answers', [
            'question_id' => $dependent,
        ]);

        $fileAnswer = QuestionnaireAnswer::query()
            ->where('question_id', $fileAttach)
            ->firstOrFail();
        $this->assertSame([$documentId], $fileAnswer->attached_document_ids);

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), [
                'answers' => [
                    $control => ['value' => 'yes', 'attached_document_ids' => []],
                ],
            ])
            ->assertSessionHasErrors("answers.{$dependent}.value");

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), [
                'answers' => [
                    $control => ['value' => 'yes', 'attached_document_ids' => []],
                    $dependent => ['value' => 'Three full-time staff.', 'attached_document_ids' => []],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('questionnaire_answers', [
            'question_id' => $dependent,
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'questionnaire.submitted']);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Client Owner',
            'email' => 'client.owner@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000000',
            'legal_name' => 'Future Shift Advisory Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
            'onboarding_wizard_state' => [
                'current_step' => 5,
                'completed_steps' => [
                    OnboardingWizard::STEP_WELCOME,
                    OnboardingWizard::STEP_IDENTITY,
                    OnboardingWizard::STEP_BUSINESS_SNAPSHOT,
                    OnboardingWizard::STEP_GOALS,
                ],
                'steps' => [],
            ],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }

    /**
     * @return array{0: Questionnaire, 1: string, 2: string, 3: string}
     */
    private function conditionalQuestionnaire(): array
    {
        $control = (string) Str::uuid();
        $dependent = (string) Str::uuid();
        $fileAttach = (string) Str::uuid();

        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => '99',
            'title' => 'Conditional Test Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'People',
            'help_text' => null,
        ]);

        $section->questions()->create([
            'id' => $control,
            'order' => 1,
            'type' => QuestionnaireQuestionType::SINGLE_SELECT,
            'prompt' => 'Do you employ staff?',
            'options' => [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'no', 'label' => 'No'],
            ],
            'required' => true,
        ]);
        $section->questions()->create([
            'id' => $dependent,
            'order' => 2,
            'type' => QuestionnaireQuestionType::LONG_TEXT,
            'prompt' => 'Describe the team.',
            'conditional_logic' => [
                'when' => $control,
                'equals' => 'yes',
                'show' => $dependent,
            ],
            'required' => true,
        ]);
        $section->questions()->create([
            'id' => $fileAttach,
            'order' => 3,
            'type' => QuestionnaireQuestionType::FILE_ATTACH,
            'prompt' => 'Attach HR records.',
            'required' => false,
        ]);

        return [$questionnaire, $control, $dependent, $fileAttach];
    }
}
