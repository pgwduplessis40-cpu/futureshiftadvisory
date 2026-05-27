<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Models\BusinessHealthSnapshot;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\NpoEngagement;
use App\Models\NpoSocialEnterpriseScorecard;
use App\Models\NpoTensionAnalysis;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Npo\NpoHealthScorer;
use App\Services\Npo\SocialEnterpriseAssessment;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class SocialEnterpriseAssessmentTest extends TestCase
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

    public function test_social_enterprise_blended_score_divides_weighted_total_by_100(): void
    {
        Carbon::setTestNow('2026-05-27 11:00:00');
        [, $client, $engagement] = $this->socialEnterpriseClient(commercialWeight: 40, missionWeight: 60);
        $this->businessHealth($client, 70);
        $this->npoHealth($engagement, 80);

        $scorecard = app(SocialEnterpriseAssessment::class)->score($engagement);

        $this->assertSame(70, $scorecard->commercial_score);
        $this->assertSame(80, $scorecard->mission_score);
        $this->assertSame(40, $scorecard->commercial_weight);
        $this->assertSame(60, $scorecard->mission_weight);
        $this->assertEqualsWithDelta(76.0, $scorecard->blended_score, 0.01);
    }

    public function test_ai_tension_analysis_uses_fake_ai_and_is_review_gated(): void
    {
        Carbon::setTestNow('2026-05-27 11:00:00');
        [$advisor, $client, $engagement] = $this->socialEnterpriseClient();
        $this->businessHealth($client, 68);
        $this->npoHealth($engagement, 82);
        app(SocialEnterpriseAssessment::class)->score($engagement);

        $analysis = app(SocialEnterpriseAssessment::class)->analyseTensions($engagement, $advisor);

        $this->assertSame(NpoTensionAnalysis::REVIEW_PENDING, $analysis->review_status);
        $this->assertFalse($analysis->reviewed());
        $this->assertSame('fake-ai-client', $analysis->ai_response['model']);
        $this->assertGreaterThanOrEqual(1, count($analysis->tensions));
        $this->assertLessThanOrEqual(5, count($analysis->tensions));
        $this->assertNotEmpty($analysis->tensions[0]['data_points']);

        $reviewed = app(SocialEnterpriseAssessment::class)->markTensionAnalysisReviewed($analysis, $advisor);

        $this->assertTrue($reviewed->reviewed());
        $this->assertSame($advisor->id, $reviewed->reviewed_by_user_id);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.npo_social_enterprise.scorecard.commercial_score', 68)
                ->where('client.npo_social_enterprise.tension_analysis.review_status', NpoTensionAnalysis::REVIEW_REVIEWED)
                ->where('client.npo_social_enterprise.tension_analysis.is_releasable', true));
    }

    public function test_tension_without_evidencing_data_points_is_rejected(): void
    {
        [, $client, $engagement] = $this->socialEnterpriseClient();
        $scorecard = NpoSocialEnterpriseScorecard::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'commercial_score' => 70,
            'mission_score' => 80,
            'commercial_weight' => 50,
            'mission_weight' => 50,
            'blended_score' => 75,
            'commercial_axes' => [],
            'mission_axes' => [],
            'source_attributions' => [],
            'calculated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('data points');

        NpoTensionAnalysis::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'npo_social_enterprise_scorecard_id' => $scorecard->id,
            'review_status' => NpoTensionAnalysis::REVIEW_PENDING,
            'tensions' => [
                [
                    'type' => NpoTensionAnalysis::TYPE_GROWTH_TENSION,
                    'title' => 'Unsupported growth claim',
                    'commercial_implication' => 'Commercial claim.',
                    'mission_implication' => 'Mission claim.',
                    'strategic_options' => ['Option'],
                    'advisor_recommended_path' => 'Review.',
                    'data_points' => [],
                ],
            ],
            'ai_response' => [],
            'source_attributions' => [],
            'generated_at' => now(),
        ]);
    }

    public function test_tension_count_is_capped_at_five(): void
    {
        [, $client, $engagement] = $this->socialEnterpriseClient();
        $scorecard = NpoSocialEnterpriseScorecard::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'commercial_score' => 70,
            'mission_score' => 80,
            'commercial_weight' => 50,
            'mission_weight' => 50,
            'blended_score' => 75,
            'commercial_axes' => [],
            'mission_axes' => [],
            'source_attributions' => [],
            'calculated_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between 1 and 5');

        NpoTensionAnalysis::query()->create([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'npo_social_enterprise_scorecard_id' => $scorecard->id,
            'review_status' => NpoTensionAnalysis::REVIEW_PENDING,
            'tensions' => array_fill(0, 6, [
                'type' => NpoTensionAnalysis::TYPE_KPI_MISALIGNMENT,
                'title' => 'Too many tensions',
                'commercial_implication' => 'Commercial.',
                'mission_implication' => 'Mission.',
                'strategic_options' => ['Option'],
                'advisor_recommended_path' => 'Review.',
                'data_points' => [['label' => 'Score', 'source_reference' => 'test:score']],
            ]),
            'ai_response' => [],
            'source_attributions' => [],
            'generated_at' => now(),
        ]);
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function socialEnterpriseClient(int $commercialWeight = 50, int $missionWeight = 50): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => fake()->unique()->safeEmail(),
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Social Enterprise Trust',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
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
            'sub_type' => NpoEngagementSubType::SocialEnterprise,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'social_enterprise' => true,
            'social_enterprise_type' => NpoSocialEnterpriseType::CrossSubsidy,
            'commercial_weight' => $commercialWeight,
            'mission_weight' => $missionWeight,
        ]);

        return [$advisor, $client, $engagement];
    }

    private function businessHealth(Client $client, int $score): void
    {
        $batchId = (string) Str::uuid();

        foreach (BusinessHealthSnapshot::dimensions() as $dimension) {
            BusinessHealthSnapshot::query()->create([
                'client_id' => $client->id,
                'assessment_batch_id' => $batchId,
                'dimension' => $dimension,
                'score' => $score,
                'contributing_finding_ids' => [],
                'module_run_states' => [],
                'dimension_run_state' => BusinessHealthSnapshot::STATE_SCORED,
                'captured_at' => now(),
                'source_attributions' => [['claim' => 'Fixture commercial score', 'source_reference' => 'test:business-health']],
            ]);
        }
    }

    private function npoHealth(NpoEngagement $engagement, int $score): void
    {
        app(NpoHealthScorer::class)->recordAssessment($engagement, [
            'mission_strategy' => $score,
            'service_operations' => $score,
            'governance_compliance' => $score,
            'financial_sustainability' => $score,
            'people_capability' => $score,
            'impact_measurement' => $score,
            'funding_resilience' => $score,
        ]);
    }
}
