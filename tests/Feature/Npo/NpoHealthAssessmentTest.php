<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\NpoSocialEnterpriseType;
use App\Enums\NpoTiritiMode;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoDimensionScore;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Npo\GovernanceReviewConversion;
use App\Services\Npo\NpoEngagementConfiguration;
use App\Services\Npo\NpoHealthScorer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class NpoHealthAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_mode_a_scores_eight_dimensions_with_dimension_three_highest_weight(): void
    {
        [, , $engagement] = $this->npoClient(mode: NpoTiritiMode::Standalone);

        $scores = app(NpoHealthScorer::class)->recordAssessment($engagement, $this->fixtureScores(includeTiriti: true));
        $byDimension = $scores->keyBy('dimension_number');

        $this->assertCount(8, $scores);
        $this->assertSame(69, $scores->first()?->health_score);
        $this->assertSame(20, $byDimension[NpoHealthScorer::DIMENSION_GOVERNANCE_COMPLIANCE]->advisor_weight);
        $this->assertSame(15, $byDimension[NpoHealthScorer::DIMENSION_FUNDING_RESILIENCE]->advisor_weight);
        $this->assertSame(NpoTiritiMode::Standalone, $byDimension[NpoHealthScorer::DIMENSION_TE_TIRITI]->tiriti_mode);
    }

    public function test_mode_b_scores_seven_dimensions_and_folds_tiriti_criteria_into_parent_dimensions(): void
    {
        [, , $engagement] = $this->npoClient(mode: NpoTiritiMode::Woven);

        $scores = app(NpoHealthScorer::class)->recordAssessment($engagement, $this->fixtureScores(includeTiriti: false));
        $byDimension = $scores->keyBy('dimension_number');

        $this->assertCount(7, $scores);
        $this->assertFalse($byDimension->has(NpoHealthScorer::DIMENSION_TE_TIRITI));
        $this->assertSame(65, $scores->first()?->health_score);
        $this->assertContains(
            '[TIRITI] governance obligations and board accountability',
            $byDimension[NpoHealthScorer::DIMENSION_GOVERNANCE_COMPLIANCE]->mode_b_criteria_contributions,
        );
    }

    public function test_governance_review_findings_prepopulate_dimension_three_and_backfill_is_idempotent(): void
    {
        [$advisor, $client, $source] = $this->npoClient(
            advisorEmail: 'npo-health-prefill@example.test',
            subType: NpoEngagementSubType::GovernanceReview,
        );
        $converted = $this->convertedEngagement($client, $source, $advisor);
        $this->governanceFinding($source, FindingSeverity::High, 'Board skills gap');
        $this->governanceFinding($source, FindingSeverity::Medium, 'COI register incomplete');

        $scorer = app(NpoHealthScorer::class);
        $this->assertSame(1, $scorer->backfillGovernanceDimension($advisor));
        $this->assertSame(0, $scorer->backfillGovernanceDimension($advisor));

        $score = NpoDimensionScore::query()
            ->where('npo_engagement_id', $converted->id)
            ->where('source', NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION)
            ->first();
        $again = $scorer->prepopulateGovernanceDimension($converted, $advisor);

        $this->assertInstanceOf(NpoDimensionScore::class, $score);
        $this->assertSame($score?->id, $again?->id);
        $this->assertSame(NpoHealthScorer::DIMENSION_GOVERNANCE_COMPLIANCE, $score?->dimension_number);
        $this->assertSame(67, $score?->score);
        $this->assertCount(2, $score?->findings ?? []);
        $this->assertSame(1, NpoDimensionScore::query()
            ->where('npo_engagement_id', $converted->id)
            ->where('source', NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION)
            ->count());
    }

    public function test_conversion_triggers_dimension_three_prepopulation(): void
    {
        [$advisor, , $source] = $this->npoClient(
            advisorEmail: 'npo-health-convert@example.test',
            subType: NpoEngagementSubType::GovernanceReview,
        );
        $this->governanceFinding($source, FindingSeverity::High, 'Board renewal overdue');

        $converted = app(GovernanceReviewConversion::class)->convert($source, $advisor);

        $this->assertDatabaseHas('npo_dimension_scores', [
            'npo_engagement_id' => $converted->id,
            'dimension_number' => NpoHealthScorer::DIMENSION_GOVERNANCE_COMPLIANCE,
            'source' => NpoDimensionScore::SOURCE_GOVERNANCE_REVIEW_PREPOPULATION,
            'source_npo_engagement_id' => $source->id,
        ]);
    }

    public function test_weighting_change_recomputes_historical_score_context_and_audits(): void
    {
        [$advisor, , $engagement] = $this->npoClient(mode: NpoTiritiMode::Woven);
        app(NpoHealthScorer::class)->recordAssessment($engagement, $this->fixtureScores(includeTiriti: false), actor: $advisor);

        app(NpoEngagementConfiguration::class)->configure($engagement, $advisor, [
            'legal_structure' => NpoLegalStructure::RegisteredCharity->value,
            'tiriti_decision_guide' => [
                NpoEngagementConfiguration::GUIDE_GOVERNANCE_OBLIGATION => false,
                NpoEngagementConfiguration::GUIDE_MANA_WHENUA_RELATIONSHIP => false,
                NpoEngagementConfiguration::GUIDE_TIRITI_OUTCOMES => false,
            ],
            'tiriti_mode' => NpoTiritiMode::Woven->value,
            'social_enterprise' => true,
            'social_enterprise_type' => NpoSocialEnterpriseType::CrossSubsidy->value,
            'commercial_weight' => 40,
            'mission_weight' => 60,
        ]);

        $row = NpoDimensionScore::query()
            ->where('npo_engagement_id', $engagement->id)
            ->firstOrFail();

        $this->assertSame(40, data_get($row->scoring_context, 'social_weighting.commercial_weight'));
        $this->assertSame(60, data_get($row->scoring_context, 'social_weighting.mission_weight'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.dimension_scores.recomputed_for_weighting_change',
            'subject_id' => $engagement->id,
        ]);
    }

    public function test_advisor_and_portal_payloads_include_npo_health_radar(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient(mode: NpoTiritiMode::Standalone);
        app(NpoHealthScorer::class)->recordAssessment($engagement, $this->fixtureScores(includeTiriti: true), actor: $advisor);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.npo_health.health_score', 69)
                ->has('client.npo_health.axes', 8));

        $portalUser = $this->clientUserFor($client);

        $this->actingAsMfa($portalUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('npoHealth.health_score', 69)
                ->has('npoHealth.axes', 8));
    }

    /**
     * @return array<int|string, int>
     */
    private function fixtureScores(bool $includeTiriti): array
    {
        $scores = [
            'mission_strategy' => 80,
            'service_operations' => 70,
            'governance_compliance' => 60,
            'financial_sustainability' => 90,
            'people_capability' => 50,
            'impact_measurement' => 75,
            'funding_resilience' => 40,
        ];

        if ($includeTiriti) {
            $scores['te_tiriti'] = 100;
        }

        return $scores;
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(
        string $advisorEmail = 'npo-health-advisor@example.test',
        NpoTiritiMode $mode = NpoTiritiMode::Woven,
        NpoEngagementSubType $subType = NpoEngagementSubType::StandardNpo,
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
            'legal_name' => 'NPO Health Trust',
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
            'sub_type' => $subType,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'tiriti_mode' => $subType === NpoEngagementSubType::GovernanceReview ? null : $mode,
            'isa_2022_reregistered' => null,
            'created_by_user_id' => $advisor->getKey(),
            'updated_by_user_id' => $advisor->getKey(),
        ]);

        return [$advisor, $client, $engagement];
    }

    private function convertedEngagement(Client $client, NpoEngagement $source, User $advisor): NpoEngagement
    {
        return NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => $source->legal_structure,
            'tiriti_mode' => NpoTiritiMode::Woven,
            'converted_from_npo_engagement_id' => $source->id,
            'created_by_user_id' => $advisor->getKey(),
            'updated_by_user_id' => $advisor->getKey(),
        ]);
    }

    private function governanceFinding(NpoEngagement $engagement, FindingSeverity $severity, string $title): GovernanceReviewFinding
    {
        return GovernanceReviewFinding::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'finding_key' => str($title)->slug('_')->toString(),
            'category' => 'governance',
            'severity' => $severity,
            'title' => $title,
            'body' => "Finding body for {$title}.",
            'criteria' => ['dimension' => 'governance'],
            'evidence' => ['source' => 'board pack'],
            'attributions' => [
                ['claim' => $title, 'source_reference' => 'governance:test'],
            ],
            'uncertainty' => Uncertainty::Low,
            'status' => GovernanceReviewFinding::STATUS_REVIEWED,
            'reviewed_at' => now(),
        ]);
    }

    private function clientUserFor(Client $client): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => 'npo-health-portal@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        return $user;
    }
}
