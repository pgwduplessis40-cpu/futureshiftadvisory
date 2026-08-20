<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\FeeCalculation;
use App\Models\FoundingAdvisoryEngagement;
use App\Models\PlanAssessment;
use App\Models\Proposal;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Entrepreneurs\AdvisorEntrepreneurCapacity;
use App\Services\Entrepreneurs\AdvisoryConversion;
use App\Services\Entrepreneurs\FoundingAdvisoryService;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdvisoryConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_conversion_prepopulates_founding_advisory_client_and_immutable_baseline_from_entrepreneur_data(): void
    {
        [$advisor, $profile, $plan] = $this->entrepreneurPlan('convert-founder@example.test');
        $profile->forceFill([
            'pilot_fee_waiver_enabled' => true,
            'pilot_fee_waiver_starts_at' => now()->subDay(),
            'pilot_fee_waiver_expires_at' => now()->addMonth(),
            'pilot_fee_waiver_reason' => 'Founder pilot conversion.',
            'pilot_fee_waiver_approved_by_user_id' => $advisor->getKey(),
            'pilot_fee_waiver_approved_at' => now(),
        ])->save();

        $client = app(AdvisoryConversion::class)->convert($profile, $advisor, $plan);

        $this->assertSame(EngagementType::FOUNDING_ADVISORY, $client->engagement_type);
        $this->assertSame($profile->name, $client->legal_name);
        $this->assertSame($profile->user_id, $client->primary_contact_user_id);
        $this->assertSame('entrepreneur', $client->registry_sources['source']);
        $this->assertSame($profile->id, $client->registry_sources['entrepreneur_profile_id']);
        $this->assertSame($plan->id, $client->registry_sources['business_plan_id']);
        $this->assertSame('Retail', data_get($client->registry_sources, 'founding_advisory_payload.industry'));
        $this->assertSame($client->id, $plan->refresh()->client_id);
        $this->assertSame(BusinessPlan::STATUS_FOUNDING, $plan->status);
        $this->assertSame($client->id, $profile->refresh()->client_id);
        $this->assertTrue($client->pilot_fee_waiver_enabled);
        $this->assertSame('Founder pilot conversion.', $client->pilot_fee_waiver_reason);
        $this->assertSame($advisor->getKey(), $client->pilot_fee_waiver_approved_by_user_id);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $profile->user_id,
            'role' => 'primary_contact',
        ]);
        $this->assertDatabaseHas('founding_advisory_engagements', [
            'client_id' => $client->id,
            'entrepreneur_profile_id' => $profile->id,
            'business_plan_id' => $plan->id,
            'status' => 'advisory_ready',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.advisory_converted',
            'subject_id' => $client->id,
        ]);
    }

    public function test_advisor_conversion_requires_current_readiness_signal_for_latest_plan(): void
    {
        [$advisor, $profile, $readyPlan] = $this->entrepreneurPlan('stale-readiness-founder@example.test');
        $assessment = $this->finalisedAssessmentFor($readyPlan, $advisor, 88);
        AdvisoryReadinessSignal::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'business_plan_id' => $readyPlan->id,
            'plan_assessment_id' => $assessment->id,
            'score' => 88,
            'surfaced_at' => now()->subMinute(),
            'advisor_notified_at' => now()->subMinute(),
        ]);
        $latestPlan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Business plan: '.$profile->name.' revised',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_SUBMITTED,
            'current_phase' => 5,
            'founding_advisory_payload' => [
                'industry' => 'Retail',
                'validated_customer' => 'Revised customer segment',
            ],
            'created_by_user_id' => $advisor->id,
        ]);
        $latestPlan->forceFill(['updated_at' => now()->addMinute()])->save();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.entrepreneurs.convert', $profile))
            ->assertRedirect(route('advisor.entrepreneurs.show', $profile))
            ->assertSessionHas('status', 'entrepreneur-not-advisory-ready');

        $this->assertDatabaseCount('clients', 0);
        $this->assertNull($latestPlan->refresh()->client_id);
    }

    public function test_capacity_counts_all_active_entrepreneur_stages_and_blocks_at_limit(): void
    {
        $advisor = $this->advisor('capacity-wo92@example.test');
        $this->profiles($advisor, 24, EntrepreneurStage::BUILDING_PHASE_3);

        $summary = app(AdvisorEntrepreneurCapacity::class)->summary($advisor);

        $this->assertSame(24, $summary['active_count']);
        $this->assertTrue($summary['warning']);
        $this->assertFalse($summary['blocked']);

        $this->profiles($advisor, 6, EntrepreneurStage::ADVISORY_READY, 24);
        $summary = app(AdvisorEntrepreneurCapacity::class)->summary($advisor);

        $this->assertSame(30, $summary['active_count']);
        $this->assertTrue($summary['blocked']);

        $this->expectException(ValidationException::class);
        app(AdvisorEntrepreneurCapacity::class)->ensureCanAdd($advisor);
    }

    public function test_signed_founding_proposal_creates_a_draft_rolling_roadmap_and_replans_without_overwriting_history(): void
    {
        [$advisor, $profile, $plan] = $this->entrepreneurPlan('founding-roadmap@example.test');
        $client = app(AdvisoryConversion::class)->convert($profile, $advisor, $plan);
        $engagement = FoundingAdvisoryEngagement::query()->where('client_id', $client->id)->firstOrFail();
        $feeCalculation = FeeCalculation::query()->create([
            'client_id' => $client->id,
            'method' => FeeMethod::Entrepreneur->value,
            'inputs' => [],
            'suggested_low' => 1000,
            'suggested_mid' => 1500,
            'suggested_high' => 2000,
            'justification' => [],
            'created_by_user_id' => $advisor->id,
        ]);
        $proposal = Proposal::query()->create([
            'client_id' => $client->id,
            'fee_calculation_id' => $feeCalculation->id,
            'status' => ProposalStatus::Draft,
            'version' => 1,
            'scope' => [],
            'services' => [],
            'pv_summary' => [],
            'acceptance_terms' => [],
            'created_by_user_id' => $advisor->id,
        ]);
        $founding = app(FoundingAdvisoryService::class);
        $founding->attachProposal($client, $proposal, $advisor);

        $draft = $founding->activateSignedProposal($proposal, $profile->user);

        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft->status);
        $this->assertCount(3, data_get($draft->agenda, 'horizons', []));
        $this->assertSame('committed', data_get($draft->agenda, 'horizons.0.commitment'));
        $this->assertSame('provisional', data_get($draft->agenda, 'horizons.1.commitment'));
        $this->assertSame('indicative', data_get($draft->agenda, 'horizons.2.commitment'));

        $founding->publish($draft, $advisor);
        $nextDraft = $founding->draftReplan($engagement->refresh(), [
            'reason' => 'First paid work changed the delivery sequence.',
            'sales_pipeline' => 'Two qualified opportunities are progressing.',
            'cash_funding' => 'Opening cash is lower than forecast.',
        ], $advisor);

        $this->assertSame(2, $nextDraft->version);
        $this->assertSame('draft', $nextDraft->status);
        $this->assertDatabaseHas('founding_roadmap_versions', [
            'id' => $draft->id,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('strategic_plan_milestones', [
            'strategic_plan_id' => $draft->strategic_plan_id,
            'owner' => 'client',
        ]);
    }

    public function test_dd_built_plan_hands_off_to_new_advisory_client(): void
    {
        [$advisor, $plan] = $this->ddPlan();

        $client = app(AdvisoryConversion::class)->handoffDdPlan($plan, $advisor);

        $this->assertSame(EngagementType::STANDARD_ADVISORY, $client->engagement_type);
        $this->assertSame('Sourced from DD Business Plan', $client->registry_sources['source_label']);
        $this->assertSame($plan->dd_engagement_id, $client->registry_sources['dd_engagement_id']);
        $this->assertSame($plan->id, $client->registry_sources['business_plan_id']);
        $this->assertSame($client->id, $plan->refresh()->client_id);
        $this->assertSame(BusinessPlan::STATUS_FOUNDING, $plan->status);
        $this->assertDatabaseHas('client_team', [
            'client_id' => $client->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.dd_plan_handoff_converted',
            'subject_id' => $client->id,
        ]);
    }

    /**
     * @return array{0: User, 1: EntrepreneurProfile, 2: BusinessPlan}
     */
    private function entrepreneurPlan(string $email): array
    {
        $advisor = $this->advisor('advisor-'.$email);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Converted Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'concept_summary' => 'Founder has validated retail concept.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Business plan: '.$profile->name,
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 5,
            'founding_advisory_payload' => [
                'industry' => 'Retail',
                'validated_customer' => 'Regional retail operators',
            ],
            'created_by_user_id' => $advisor->id,
            'completed_at' => now(),
        ]);

        return [$advisor, $profile->refresh()->load('user', 'advisoryReadinessSignals'), $plan];
    }

    private function finalisedAssessmentFor(BusinessPlan $plan, User $advisor, int $score): PlanAssessment
    {
        $framework = RatingFramework::query()->create([
            'version' => 1,
            'status' => RatingFramework::STATUS_PUBLISHED,
            'production_ready' => true,
            'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
            'published_at' => now(),
            'published_by_user_id' => $advisor->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return PlanAssessment::query()->create([
            'business_plan_id' => $plan->id,
            'round' => 1,
            'rating_framework_id' => $framework->id,
            'ai_scores' => [[
                'criterion_id' => 'fixture-market-proof',
                'criterion_number' => 1,
                'criterion_name' => 'Market proof',
                'score' => $score,
                'rationale' => 'Fixture readiness score.',
            ]],
            'advisor_scores' => [],
            'mentor_notes' => [],
            'document_support' => [],
            'overall_grade' => $framework->gradeFor($score),
            'finalised_at' => now(),
            'finalised_by_user_id' => $advisor->getKey(),
        ]);
    }

    /**
     * @return array{0: User, 1: BusinessPlan}
     */
    private function ddPlan(): array
    {
        $advisor = $this->advisor('dd-plan-handoff@example.test');
        $buyer = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'legal_name' => 'Buyer Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->id,
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $buyer->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);
        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $buyer->id,
            'advisor_id' => $advisor->id,
            'declaration' => ['conflict' => false],
            'declared_at' => now(),
        ]);
        $engagement = DdEngagement::query()->create([
            'client_id' => $buyer->id,
            'target_name' => 'Target Retail Limited',
            'target_details' => [
                'nzbn' => '9429000000000',
                'industry' => 'Retail',
            ],
            'status' => DdEngagement::STATUS_ACQUISITION_PROCEEDING,
            'conflict_declaration_id' => $conflict->id,
            'created_by_user_id' => $advisor->id,
            'disclaimer_acknowledged_at' => now(),
        ]);
        $plan = BusinessPlan::query()->create([
            'client_id' => $buyer->id,
            'dd_engagement_id' => $engagement->id,
            'title' => 'Acquisition plan: '.$engagement->target_name,
            'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
            'status' => BusinessPlan::STATUS_FOUNDING,
            'current_phase' => 5,
            'founding_advisory_payload' => [
                'business_plan_id' => 'fixture',
                'industry' => 'Retail',
                'phases' => [],
            ],
            'created_by_user_id' => $advisor->id,
            'completed_at' => now(),
        ]);

        return [$advisor, $plan->refresh()->load('ddEngagement.client')];
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function profiles(User $advisor, int $count, EntrepreneurStage $stage, int $offset = 0): void
    {
        for ($i = 0; $i < $count; $i++) {
            EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->id,
                'name' => 'Capacity Founder '.($offset + $i),
                'email' => 'capacity-'.($offset + $i).'@example.test',
                'stage' => $stage,
            ]);
        }
    }
}
