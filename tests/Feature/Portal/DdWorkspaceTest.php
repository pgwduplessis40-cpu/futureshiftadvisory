<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\Questionnaire;
use App\Models\ServiceActivation;
use App\Models\User;
use Database\Seeders\DdSpecificQuestionnaireV2Seeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DdWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireV2Seeder::class);
    }

    public function test_added_due_diligence_workspace_uses_dd_specific_questionnaire(): void
    {
        [$buyer, $client] = $this->entrepreneurClientWithDdWorkspace();

        $this->actingAsMfa($buyer)
            ->get(route('portal.dd-plan.show', ['client' => $client->getKey()]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('portal/dd/BusinessPlan')
                ->where('client.id', $client->getKey())
                ->where('workspaces.active_key', 'due_diligence')
                ->where('questionnaire.schema.set', QuestionnaireSet::DUE_DILIGENCE->value)
                ->where('questionnaire.schema.title', 'Buying a Business Questions')
                ->where('questionnaire.submitted', false)
                ->missing('onboardingUrl'));
    }

    public function test_due_diligence_questionnaire_submit_counts_for_dd_readiness(): void
    {
        [$buyer, $client] = $this->entrepreneurClientWithDdWorkspace();
        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::DUE_DILIGENCE)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();

        $this->actingAsMfa($buyer)
            ->from(route('portal.dd-plan.show', ['client' => $client->getKey()], absolute: false))
            ->post(route('portal.dd-plan.questionnaire.store', ['client' => $client->getKey()]), [
                'answers' => $this->answersFor($questionnaire),
            ])
            ->assertRedirect(route('portal.dd-plan.show', ['client' => $client->getKey()], absolute: false))
            ->assertSessionHas('status', 'dd-questionnaire-submitted');

        $this->assertDatabaseHas('questionnaire_responses', [
            'client_id' => $client->getKey(),
            'questionnaire_id' => $questionnaire->getKey(),
        ]);

        $this->actingAsMfa($buyer)
            ->get(route('portal.dd-plan.show', ['client' => $client->getKey()]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('readiness.questionnaire_submitted', true)
                ->where('questionnaire.submitted', true)
                ->where('client.engagement_type_label', EngagementType::DUE_DILIGENCE->label()));
    }

    /**
     * @return array{0: User, 1: Client, 2: DdEngagement}
     */
    private function entrepreneurClientWithDdWorkspace(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $buyer = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $buyer->assignRole(User::TYPE_ENTREPRENEUR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::ENTREPRENEUR_MODULE,
            'legal_name' => 'Rodney and Janya Limited',
            'trading_name' => 'Rodney and Janya',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $buyer->getKey(),
            'created_by_user_id' => $advisor->getKey(),
            'onboarding_wizard_state' => [
                'completed_steps' => ['welcome', 'goals', 'website', 'documents', 'review'],
            ],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $buyer->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => ['portal', 'entrepreneur_module', 'dd'],
        ]);

        EntrepreneurProfile::query()->create([
            'user_id' => $buyer->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'client_id' => $client->getKey(),
            'name' => $buyer->name ?: 'Rodney and Janya',
            'email' => $buyer->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Original entrepreneur service with added due diligence workspace.',
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => ['portal', 'entrepreneur_module', 'dd'],
        ]);

        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'declaration' => ['referral_type' => 'due_diligence'],
            'declared_at' => now(),
        ]);

        $engagement = DdEngagement::query()->create([
            'client_id' => $client->getKey(),
            'target_name' => 'Main Street Bikes Limited',
            'target_details' => [
                'industry' => 'Retail',
                'client_capability' => [
                    'mode' => 'guided',
                    'support_level' => 'guided',
                ],
            ],
            'status' => DdEngagement::STATUS_IN_PROGRESS,
            'conflict_declaration_id' => $conflict->getKey(),
            'created_by_user_id' => $advisor->getKey(),
            'disclaimer_acknowledged_at' => now(),
        ]);

        ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'client_label' => 'Explore buying a business',
            'status' => ServiceActivation::STATUS_ACTIVE,
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_completed_at' => now(),
            'accepted_at' => now(),
            'accepted_by_user_id' => $buyer->getKey(),
            'related_dd_engagement_id' => $engagement->getKey(),
            'intake' => [
                'target_name' => $engagement->target_name,
                'dd_experience' => 'first_time',
                'preferred_guidance' => 'guided',
            ],
            'metadata' => ['source' => 'test'],
        ]);

        return [$buyer, $client, $engagement];
    }

    /**
     * @return array<string, array{value:mixed, attached_document_ids:array<int, string>}>
     */
    private function answersFor(Questionnaire $questionnaire): array
    {
        $answers = [];

        foreach ($questionnaire->sections as $section) {
            foreach ($section->questions as $question) {
                if (! $question->required) {
                    continue;
                }

                $answers[(string) $question->getKey()] = [
                    'value' => match ($question->type) {
                        QuestionnaireQuestionType::SINGLE_SELECT => $question->options[0]['value'],
                        QuestionnaireQuestionType::MULTI_SELECT => [$question->options[0]['value']],
                        QuestionnaireQuestionType::NUMBER,
                        QuestionnaireQuestionType::CURRENCY => 250000,
                        QuestionnaireQuestionType::DATE => now()->addMonth()->format('Y-m-d'),
                        QuestionnaireQuestionType::FILE_ATTACH => null,
                        default => 'Test DD answer for '.$question->prompt,
                    },
                    'attached_document_ids' => [],
                ];
            }
        }

        return $answers;
    }
}
