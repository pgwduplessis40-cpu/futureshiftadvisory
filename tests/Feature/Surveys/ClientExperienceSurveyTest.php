<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\Permission;
use App\Enums\ReportType;
use App\Enums\SurveyAssignmentStatus;
use App\Enums\SurveyStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\EntrepreneurProfile;
use App\Models\LearningUpdate;
use App\Models\Report;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClientExperienceSurveyTest extends TestCase
{
    use RefreshDatabase;

    private Survey $survey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        TermsVersion::query()->delete();
        app(RequestContext::class)->apply('system', []);

        $admin = $this->superAdmin();
        $this->survey = app(SurveyLibrary::class)->ensureDefault($admin);
        $this->survey->forceFill([
            'status' => SurveyStatus::Published->value,
            'published_at' => now(),
            'published_by_user_id' => $admin->getKey(),
        ])->save();
    }

    public function test_admin_can_activate_snapshot_and_client_can_submit_response(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient();
        $admin = $this->superAdmin('survey-admin-activate@example.test');
        $report = $this->reviewedReport($client);

        $this->actingAsMfa($admin)
            ->post(route('advisor.clients.survey-assignments.store', $client), [
                'survey_id' => $this->survey->id,
            ])
            ->assertRedirect(route('advisor.clients.surveys', $client, absolute: false));

        $assignment = SurveyAssignment::query()->with('survey.questions')->sole();

        $this->assertSame($client->id, $assignment->client_id);
        $this->assertSame(SurveyAssignmentStatus::Pending, $assignment->status);
        $this->assertSame('report', $assignment->deliverable_snapshot[0]['source_type']);
        $this->assertSame($report->id, $assignment->deliverable_snapshot[0]['source_id']);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.surveys.show', $assignment))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/surveys/Show')
                ->where('assignment.id', $assignment->id)
                ->has('assignment.deliverables', 1));

        $this->actingAsMfa($clientUser)
            ->post(route('portal.surveys.submit', $assignment), [
                'answers' => $this->answersFor($assignment, [
                    'overall_experience' => 2,
                    'recommendation' => 4,
                    'objectives_met' => 2,
                    'met_objective' => false,
                ]),
            ])
            ->assertRedirect(route('portal.surveys.index', absolute: false));

        $response = SurveyResponse::query()->with('answers')->sole();

        $this->assertSame($assignment->id, $response->survey_assignment_id);
        $this->assertSame($clientUser->id, $response->submitted_by_user_id);
        $this->assertSame(4, $response->nps_score);
        $this->assertSame(SurveyAssignmentStatus::Completed, $assignment->refresh()->status);
        $this->assertSame(6, $response->answers->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'survey_response.submitted']);
        $this->assertDatabaseHas('learning_updates', [
            'layer_id' => LayerCadenceRegistry::LAYER_CLIENT_EXPERIENCE_SURVEY,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
    }

    public function test_assignment_snapshot_is_immutable_after_activation(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient('snapshot-client@example.test');
        $admin = $this->superAdmin('snapshot-admin@example.test');
        $this->reviewedReport($client, 'Original report');

        $this->actingAsMfa($admin)
            ->post(route('advisor.clients.survey-assignments.store', $client), [
                'survey_id' => $this->survey->id,
            ]);

        $assignment = SurveyAssignment::query()->sole();
        $this->reviewedReport($client, 'Later report');
        $this->cleanDocument($client);

        $assignment->refresh();

        $this->assertCount(1, $assignment->deliverable_snapshot);
        $this->assertSame('Original report', $assignment->deliverable_snapshot[0]['title']);

        $this->actingAsMfa($clientUser)
            ->post(route('portal.surveys.submit', $assignment), [
                'answers' => $this->answersFor($assignment),
            ])
            ->assertRedirect();

        $this->assertSame(1, SurveyResponse::query()->firstOrFail()->answers()->whereNotNull('anchor_ref')->where('answer_key', 'received')->count());
    }

    public function test_completed_and_cancelled_assignments_cannot_be_submitted_again(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient('closed-client@example.test');
        $admin = $this->superAdmin('closed-admin@example.test');
        $this->reviewedReport($client);

        $this->actingAsMfa($admin)
            ->post(route('advisor.clients.survey-assignments.store', $client), [
                'survey_id' => $this->survey->id,
            ]);

        $assignment = SurveyAssignment::query()->sole();

        $this->actingAsMfa($clientUser)
            ->post(route('portal.surveys.submit', $assignment), [
                'answers' => $this->answersFor($assignment),
            ])
            ->assertRedirect();

        $this->actingAsMfa($clientUser)
            ->post(route('portal.surveys.submit', $assignment), [
                'answers' => $this->answersFor($assignment),
            ])
            ->assertForbidden();

        $this->actingAsMfa($admin)
            ->post(route('advisor.clients.survey-assignments.store', $client), [
                'survey_id' => $this->survey->id,
            ]);

        $cancelled = SurveyAssignment::query()
            ->where('status', SurveyAssignmentStatus::Pending->value)
            ->latest()
            ->firstOrFail();

        $this->actingAsMfa($admin)
            ->patch(route('advisor.survey-assignments.cancel', $cancelled))
            ->assertRedirect();

        $this->actingAsMfa($clientUser)
            ->post(route('portal.surveys.submit', $cancelled), [
                'answers' => $this->answersFor($cancelled),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('survey_responses', 1);
    }

    public function test_advisor_results_surface_assignments_and_scores(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient('results-client@example.test');
        $admin = $this->superAdmin('results-admin@example.test');
        $this->reviewedReport($client);

        $this->actingAsMfa($admin)
            ->post(route('advisor.clients.survey-assignments.store', $client), [
                'survey_id' => $this->survey->id,
            ]);

        $assignment = SurveyAssignment::query()->sole();

        $this->actingAsMfa($clientUser)
            ->post(route('portal.surveys.submit', $assignment), [
                'answers' => $this->answersFor($assignment),
            ]);

        $this->actingAsMfa($admin)
            ->get(route('advisor.clients.surveys', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/surveys/Results')
                ->where('subject.name', $client->legal_name)
                ->where('results.summary.assignments', 1)
                ->where('results.summary.completed', 1)
                ->where('results.items.0.response.nps_score', 8));
    }

    public function test_client_portal_dashboard_surfaces_pending_survey_click_through(): void
    {
        [$clientUser, $client] = $this->clientUserWithClient('dashboard-survey-client@example.test');
        $admin = $this->superAdmin('dashboard-survey-admin@example.test');
        $this->reviewedReport($client);

        $this->actingAsMfa($admin)
            ->post(route('advisor.clients.survey-assignments.store', $client), [
                'survey_id' => $this->survey->id,
            ]);

        $assignment = SurveyAssignment::query()->sole();

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('surveys.total_open', 1)
                ->where('surveys.items.0.id', $assignment->id)
                ->where('surveys.items.0.status', SurveyAssignmentStatus::Pending->value)
                ->where('surveys.items.0.url', route('portal.surveys.show', $assignment, absolute: false)));
    }

    public function test_entrepreneur_dashboard_surfaces_pending_survey_click_through(): void
    {
        $advisor = $this->superAdmin('dashboard-survey-entrepreneur-advisor@example.test');
        [$entrepreneur, $profile] = $this->entrepreneurUserWithProfile($advisor);

        $assignment = SurveyAssignment::query()->create([
            'survey_id' => $this->survey->id,
            'entrepreneur_profile_id' => $profile->getKey(),
            'status' => SurveyAssignmentStatus::Pending->value,
            'activated_by_user_id' => $advisor->getKey(),
            'activated_at' => now(),
            'deliverable_snapshot' => [],
        ]);

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/entrepreneur/Dashboard')
                ->where('surveys.total_open', 1)
                ->where('surveys.items.0.id', $assignment->id)
                ->where('surveys.items.0.status', SurveyAssignmentStatus::Pending->value)
                ->where('surveys.items.0.url', route('portal.entrepreneur.surveys.show', $assignment, absolute: false)));
    }

    /**
     * @return array{0:User,1:Client}
     */
    private function clientUserWithClient(string $email = 'survey-client@example.test'): array
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
            'legal_name' => 'Survey Client '.fake()->unique()->company(),
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

    private function superAdmin(string $email = 'survey-admin@example.test'): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create([
            'email' => $email,
        ]);
        $user->assignRole(User::TYPE_SUPER_ADMIN);
        $user->givePermissionTo(Permission::SURVEYS_MANAGE->value);

        return $user;
    }

    /**
     * @return array{0:User,1:EntrepreneurProfile}
     */
    private function entrepreneurUserWithProfile(User $advisor): array
    {
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'dashboard-survey-entrepreneur@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Dashboard Survey Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Testing dashboard survey prompts.',
        ]);

        return [$entrepreneur, $profile];
    }

    private function reviewedReport(Client $client, string $title = 'Client report'): Report
    {
        return Report::query()->create([
            'client_id' => $client->getKey(),
            'type' => ReportType::Client,
            'title' => $title,
            'pdf_path' => "reports/{$client->id}.pdf",
            'pdf_byte_size' => 1200,
            'generated_at' => now(),
            'metadata' => [],
            'review_status' => 'reviewed',
            'reviewed_at' => now(),
        ]);
    }

    private function cleanDocument(Client $client): Document
    {
        return Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => 'Additional context.pdf',
            'stored_path' => 'documents/additional-context.pdf',
            'byte_size' => 1234,
            'mime_type' => 'application/pdf',
            'sha256' => str_repeat('a', 64),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function answersFor(SurveyAssignment $assignment, array $overrides = []): array
    {
        $assignment->loadMissing('survey.questions');
        $answers = [];

        /** @var SurveyQuestion $question */
        foreach ($assignment->survey->questions as $question) {
            if ($question->key === 'overall_experience') {
                $answers[$question->id] = ['value' => $overrides['overall_experience'] ?? 4];
            } elseif ($question->key === 'recommendation') {
                $answers[$question->id] = ['value' => $overrides['recommendation'] ?? 8];
            } elseif ($question->key === 'objectives_met') {
                $answers[$question->id] = ['value' => $overrides['objectives_met'] ?? 4];
            } elseif ($question->key === 'deliverable_feedback') {
                $answers[$question->id] = [
                    'anchors' => collect($assignment->deliverable_snapshot)
                        ->map(fn (array $deliverable): array => [
                            'source_type' => $deliverable['source_type'],
                            'source_id' => $deliverable['source_id'],
                            'received' => $overrides['received'] ?? true,
                            'accessible' => $overrides['accessible'] ?? true,
                            'met_objective' => $overrides['met_objective'] ?? true,
                        ])
                        ->values()
                        ->all(),
                ];
            }
        }

        return $answers;
    }
}
