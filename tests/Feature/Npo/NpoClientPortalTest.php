<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\ClientTeamMember;
use App\Models\Funder;
use App\Models\LearningUpdate;
use App\Models\NpoEngagement;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Goals\GoalTracker;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Npo\FunderRegistry;
use App\Services\Npo\NpoValueCalculator;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class NpoClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_npo_portal_widgets_are_scoped_to_the_current_engagement(): void
    {
        Carbon::setTestNow('2026-05-27 09:00:00');
        [$advisor, $user, $client, $engagement] = $this->portalClient();
        $otherEngagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $funder = $this->funder('Portal Fund');
        $otherFunder = $this->funder('Other Engagement Fund');
        $this->record($client, $engagement, $funder, [
            'grant_amount' => 120000,
            'reporting_deadline' => now()->addDays(45)->toDateString(),
        ]);
        $this->record($client, $otherEngagement, $otherFunder, [
            'grant_amount' => 900000,
            'reporting_deadline' => now()->addDays(10)->toDateString(),
        ]);
        $tracker = app(GoalTracker::class);
        $goal = $tracker->createGoal($client, ['title' => 'Delivery plan'], $advisor);
        $tracker->createMilestone($goal, [
            'title' => 'Current engagement milestone',
            'npo_engagement_id' => $engagement->id,
            'pv_of_impact' => 1000,
        ], $advisor);
        $tracker->createMilestone($goal, [
            'title' => 'Other engagement milestone',
            'npo_engagement_id' => $otherEngagement->id,
            'pv_of_impact' => 2000,
        ], $advisor);
        app(NpoValueCalculator::class)->calculateCostPerBeneficiary($engagement, [
            'programme_expenditure' => 60000,
            'beneficiary_count' => 100,
            'programme_type' => 'community_services',
        ], $advisor);
        $this->submittedQuestionnaire($client, $engagement, $user);

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('npoPortal.engagement_id', $engagement->id)
                ->where('npoPortal.funding.summary.active_records', 1)
                ->where('npoPortal.funding.records.0.funder_name', 'Portal Fund')
                ->where('npoPortal.accountability_reports_due.0.funder_name', 'Portal Fund')
                ->where('goals.goals.0.milestones.0.title', 'Current engagement milestone')
                ->where('npoPortal.milestone_progress.cost_per_beneficiary.cost_per_beneficiary', 600)
                ->where('npoPortal.questionnaire_completion.completed', true)
                ->where('npoImpactMetricStoreUrl', route('portal.npo-impact-metrics.store', absolute: false)));
    }

    public function test_impact_metric_entry_feeds_funder_accountability_reports(): void
    {
        Carbon::setTestNow('2026-05-27 09:00:00');
        [$advisor, $user, $client, $engagement] = $this->portalClient(
            'portal-impact-advisor@example.test',
            'portal-impact-primary@example.test',
            'Portal Impact Trust',
        );
        $record = $this->record($client, $engagement, $this->funder('Impact Funder'));

        $this->actingAsMfa($user)
            ->postJson(route('portal.npo-impact-metrics.store'), [
                'metric_label' => 'Meals served',
                'value' => 80,
                'unit' => 'meals',
                'platform_value' => 90,
                'period_end' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('metric.metric_key', 'meals_served')
            ->assertJsonPath('metric.value', 80);

        $report = app(ReportComposer::class)->composeFunderAccountability($engagement, $record, $advisor);
        $section = $report->sections->firstWhere('key', 'impact_metrics');

        $this->assertNotNull($section);
        $this->assertStringContainsString('Meals served: 80 meals', $section->body);
        $this->assertSame('meals_served', $section->metadata['metrics'][0]['metric_key']);
    }

    /**
     * @return array{0: User, 1: User, 2: Client, 3: NpoEngagement}
     */
    private function portalClient(
        string $advisorEmail = 'portal-npo-advisor@example.test',
        string $clientEmail = 'portal-npo-primary@example.test',
        string $clientName = 'Portal NPO Trust',
    ): array {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $user = User::factory()->withTwoFactor()->create([
            'email' => $clientEmail,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'primary_contact_user_id' => $user->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        foreach ([[$advisor, 'lead_advisor'], [$user, 'primary_contact']] as [$member, $role]) {
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $member->getKey(),
                'role' => $role,
                'granted_modules' => [EngagementType::NPO->value],
            ]);
        }

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);

        return [$advisor, $user, $client, $engagement];
    }

    private function funder(string $name): Funder
    {
        return app(FunderRegistry::class)->upsertFromLearningUpdate(
            $this->learningUpdate(),
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function record(Client $client, NpoEngagement $engagement, Funder $funder, array $overrides = []): ClientFunderRecord
    {
        return ClientFunderRecord::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'funder_id' => $funder->id,
            'grant_name' => 'Community grant',
            'grant_amount' => 50000,
            'currency' => 'NZD',
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->addYear()->toDateString(),
            'conditions' => [],
            'renewal_probability' => 75,
            'history' => [['event' => 'created']],
            ...$overrides,
        ]);
    }

    private function submittedQuestionnaire(Client $client, NpoEngagement $engagement, User $user): QuestionnaireResponse
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => 'standard_npo',
            'version' => 'portal-test-'.substr((string) $engagement->id, 0, 8),
            'title' => 'Standard NPO',
            'published_at' => now(),
        ]);

        return QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->id,
        ]);
    }

    private function learningUpdate(): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES,
            'source' => ['type' => 'npo_portal_test'],
            'summary' => 'Update funder registry',
            'proposed_change' => ['action' => 'update_funder_registry'],
            'impact_scope' => ['surface' => 'funder_registry'],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.8,
            'evidence' => ['source' => 'test'],
            'status' => LearningUpdate::STATUS_APPROVED,
        ]);
    }
}
