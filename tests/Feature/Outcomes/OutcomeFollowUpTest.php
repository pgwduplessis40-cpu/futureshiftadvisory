<?php

declare(strict_types=1);

namespace Tests\Feature\Outcomes;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Enums\FeeMethod;
use App\Enums\FindingSeverity;
use App\Enums\ProposalStatus;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\ConversionOutcome;
use App\Models\DdEngagement;
use App\Models\DdOutcomeRecord;
use App\Models\EntrepreneurProfile;
use App\Models\FeeCalculation;
use App\Models\OutcomeFollowUp;
use App\Models\PlanAssessment;
use App\Models\Proposal;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Outcomes\OutcomeFollowUpService;
use App\Support\RequestContext;
use Database\Seeders\FoundingRatingFrameworkValuesSeeder;
use Database\Seeders\RatingFrameworkSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OutcomeFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('security.mfa_required', false);
        config()->set('privacy.min_cohort', 1);

        $this->seed(RoleSeeder::class);
        $this->seed(RatingFrameworkSeeder::class);
        $this->seed(FoundingRatingFrameworkValuesSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_due_entrepreneur_follow_up_records_conversion_outcome_for_learning(): void
    {
        [$entrepreneur, $assessment] = $this->entrepreneurAssessmentFixture();
        $service = app(OutcomeFollowUpService::class);

        $service->scheduleDue(now());

        $followUp = OutcomeFollowUp::query()
            ->where('plan_assessment_id', $assessment->getKey())
            ->firstOrFail();
        $this->assertSame(1, OutcomeFollowUp::query()->where('plan_assessment_id', $assessment->getKey())->count());

        $service->scheduleDue(now());

        $this->assertSame(1, OutcomeFollowUp::query()->where('plan_assessment_id', $assessment->getKey())->count());

        $this->actingAs($entrepreneur)
            ->post(route('portal.outcome-follow-ups.submit', $followUp), [
                'status' => 'growth',
                'still_trading' => true,
                'revenue_direction' => 'up',
                'revenue_growth_percent' => 24.5,
                'implemented_recommendations' => 4,
                'total_recommendations' => 5,
                'comments' => 'Revenue lifted after implementing the pricing and channel recommendations.',
            ])
            ->assertRedirect('/portal/entrepreneur');

        $followUp->refresh();
        $outcome = ConversionOutcome::query()->firstOrFail();

        $this->assertSame(OutcomeFollowUp::STATUS_COMPLETED, $followUp->status);
        $this->assertSame($outcome->getKey(), $followUp->conversion_outcome_id);
        $this->assertSame('post_engagement_follow_up', $outcome->outcome_signal['source']);
        $this->assertSame('growth', $outcome->outcome_signal['status']);
        $this->assertSame(0.8, $outcome->outcome_signal['implementation_rate']);
        $this->assertSame($assessment->getKey(), $outcome->plan_assessment_id);
        $this->assertIsNumeric($outcome->outcome_signal['success_score']);
    }

    public function test_due_diligence_follow_up_records_dd_outcome(): void
    {
        [$clientUser, $engagement] = $this->ddFixture();
        $followUp = OutcomeFollowUp::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->getKey(),
            'subject_type' => OutcomeFollowUp::SUBJECT_DUE_DILIGENCE,
            'cadence_month' => 6,
            'status' => OutcomeFollowUp::STATUS_PENDING,
            'engagement_completed_at' => now()->subMonths(6),
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($clientUser)
            ->post(route('portal.outcome-follow-ups.submit', $followUp), [
                'status' => 'completed_acquisition',
                'still_trading' => true,
                'revenue_direction' => 'flat',
                'revenue_growth_percent' => null,
                'recorded_price' => 1_250_000,
                'implemented_recommendations' => 2,
                'total_recommendations' => 3,
                'comments' => 'Completed with a smaller earnout than expected.',
            ])
            ->assertRedirect('/portal');

        $record = DdOutcomeRecord::query()->firstOrFail();

        $this->assertSame(OutcomeFollowUp::STATUS_COMPLETED, $followUp->refresh()->status);
        $this->assertSame($record->getKey(), $followUp->dd_outcome_record_id);
        $this->assertSame(1_250_000.0, (float) $record->recorded_price);
        $this->assertSame('successful', $record->actual_outcome['status']);
        $this->assertSame('post_engagement_follow_up', $record->actual_outcome['source']);
    }

    public function test_follow_up_records_proposal_focus_area_outcomes_by_analysis_finding(): void
    {
        [$clientUser, $engagement] = $this->ddFixture();
        $client = $engagement->client()->firstOrFail();
        $finding = $this->analysisFinding($client);
        $proposal = $this->signedProposalWithFocusArea($client, $finding);
        $followUp = OutcomeFollowUp::query()->create([
            'client_id' => $client->getKey(),
            'dd_engagement_id' => $engagement->getKey(),
            'subject_type' => OutcomeFollowUp::SUBJECT_DUE_DILIGENCE,
            'cadence_month' => 6,
            'status' => OutcomeFollowUp::STATUS_PENDING,
            'engagement_completed_at' => now()->subMonths(6),
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($clientUser)
            ->get(route('portal.outcome-follow-ups.show', $followUp))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('followUp.focus_area_outcomes.0.proposal_id', $proposal->id)
                ->where('followUp.focus_area_outcomes.0.analysis_finding_id', $finding->id)
                ->where('followUp.focus_area_outcomes.0.status', 'not_started'));

        $this->actingAs($clientUser)
            ->post(route('portal.outcome-follow-ups.submit', $followUp), [
                'status' => 'completed_acquisition',
                'still_trading' => true,
                'revenue_direction' => 'up',
                'revenue_growth_percent' => 12.5,
                'recorded_price' => 1_400_000,
                'implemented_recommendations' => 1,
                'total_recommendations' => 2,
                'focus_area_outcomes' => [
                    [
                        'proposal_id' => $proposal->id,
                        'analysis_finding_id' => $finding->id,
                        'module' => AnalysisModule::Financial->value,
                        'title' => $finding->title,
                        'status' => 'implemented',
                        'notes' => 'Implemented the cash cadence and reporting recommendation.',
                    ],
                    [
                        'proposal_id' => $proposal->id,
                        'analysis_finding_id' => null,
                        'module' => AnalysisModule::Systems->value,
                        'title' => 'Systems handoff',
                        'status' => 'not_started',
                        'notes' => '',
                    ],
                ],
                'comments' => 'The signed proposal focus areas are now tracked item by item.',
            ])
            ->assertRedirect('/portal');

        $record = DdOutcomeRecord::query()->firstOrFail();

        $this->assertSame($finding->id, $record->actual_outcome['focus_area_outcomes'][0]['analysis_finding_id']);
        $this->assertSame('implemented', $record->actual_outcome['focus_area_outcomes'][0]['status']);
        $this->assertSame([$finding->id], $record->actual_outcome['implemented_analysis_finding_ids']);
        $this->assertSame(0.5, $record->actual_outcome['focus_area_implementation_rate']);
    }

    public function test_outcome_follow_up_scheduler_is_registered_daily(): void
    {
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression(
            '/15\s+5\s+\*\s+\*\s+\*\s+php artisan outcomes:schedule-follow-ups/',
            Artisan::output(),
        );
    }

    /**
     * @return array{0: User, 1: PlanAssessment}
     */
    private function entrepreneurAssessmentFixture(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'outcome-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Outcome Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ADVISORY_READY,
            'concept_summary' => 'Outcome follow-up fixture.',
        ]);

        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'title' => 'Outcome Founder Plan',
            'status' => BusinessPlan::STATUS_FINALISED,
            'current_phase' => 5,
            'created_by_user_id' => $advisor->getKey(),
            'completed_at' => now()->subMonths(7),
        ]);

        $framework = RatingFramework::query()
            ->with('criteria')
            ->where('status', RatingFramework::STATUS_PUBLISHED)
            ->latest('version')
            ->firstOrFail();

        $assessment = PlanAssessment::query()->create([
            'business_plan_id' => $plan->getKey(),
            'round' => 1,
            'rating_framework_id' => $framework->getKey(),
            'ai_scores' => $framework->criteria
                ->map(fn ($criterion): array => [
                    'criterion_id' => $criterion->id,
                    'criterion_number' => $criterion->number,
                    'criterion_name' => $criterion->name,
                    'score' => 76,
                    'rationale' => 'Outcome follow-up fixture score.',
                    'attributions' => [
                        ['claim' => 'Fixture score', 'source_reference' => 'test:outcome-follow-up'],
                    ],
                ])
                ->values()
                ->all(),
            'advisor_scores' => [],
            'mentor_notes' => [],
            'document_support' => [],
            'overall_grade' => $framework->gradeFor(76),
            'finalised_at' => now()->subMonths(7),
            'finalised_by_user_id' => $advisor->getKey(),
        ]);

        return [$entrepreneur, $assessment];
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddFixture(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'email' => 'outcome-buyer@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'legal_name' => 'Outcome Buyer Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $clientUser->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        $conflict = ConflictDeclaration::query()->create([
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'declaration' => [
                'declared' => true,
                'referral_type' => 'due_diligence',
                'existing_relationship' => false,
            ],
            'declared_at' => now()->subMonths(7),
        ]);

        $engagement = DdEngagement::query()->create([
            'client_id' => $client->getKey(),
            'target_name' => 'Outcome Target Limited',
            'target_details' => ['industry' => 'Manufacturing'],
            'status' => DdEngagement::STATUS_ACQUISITION_PROCEEDING,
            'recommendation' => DdEngagement::RECOMMENDATION_PROCEED,
            'conflict_declaration_id' => $conflict->getKey(),
            'created_by_user_id' => $advisor->getKey(),
            'disclaimer_acknowledged_at' => now()->subMonths(7),
        ]);

        return [$clientUser, $engagement];
    }

    private function analysisFinding(Client $client): AnalysisFinding
    {
        $run = AnalysisRun::query()->create([
            'client_id' => $client->getKey(),
            'module' => AnalysisModule::Financial,
            'status' => AnalysisRun::STATUS_COMPLETED,
            'framework_lenses' => [],
            'data_quality_snapshot' => [],
            'tokens_in' => 0,
            'tokens_out' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return AnalysisFinding::query()->create([
            'analysis_run_id' => $run->getKey(),
            'client_id' => $client->getKey(),
            'lens' => AnalysisLens::Prescriptive,
            'severity' => FindingSeverity::High,
            'title' => 'Cash runway pressure',
            'body' => 'Improve weekly cash cadence and margin reporting.',
            'attributions' => [[
                'claim' => 'Fixture finding for outcome attribution.',
                'source_reference' => 'test:outcome-focus-area',
            ]],
            'document_support' => AnalysisFinding::DOCUMENT_SUPPORT_NONE,
        ]);
    }

    private function signedProposalWithFocusArea(Client $client, AnalysisFinding $finding): Proposal
    {
        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 8000,
            'suggested_mid' => 10000,
            'suggested_high' => 12000,
            'improvement_pv_total' => 25000,
            'risk_cost_pv_total' => 3000,
            'roi_ratio' => 2.5,
            'justification' => ['services' => [['name' => 'Outcome fixture', 'line_total' => 10000]]],
        ]);

        $proposal = Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => [
                'summary' => 'Outcome attribution fixture.',
                'focus_areas' => [[
                    'analysis_finding_id' => $finding->id,
                    'module' => AnalysisModule::Financial->value,
                    'lens' => AnalysisLens::Prescriptive->value,
                    'severity' => FindingSeverity::High->value,
                    'title' => $finding->title,
                    'body' => $finding->body,
                ]],
            ],
            'services' => [],
            'pv_summary' => [],
            'roi_ratio' => 2.5,
            'acceptance_terms' => [],
            'released_at' => now()->subMonths(7),
        ]);

        return Proposal::allowSignoffStatusTransition(function () use ($proposal, $client): Proposal {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now()->subMonths(7),
            ])->save();

            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => now()->subMonths(7),
                'signed_by_user_id' => $client->primary_contact_user_id,
            ])->save();

            return $proposal->refresh();
        });
    }
}
