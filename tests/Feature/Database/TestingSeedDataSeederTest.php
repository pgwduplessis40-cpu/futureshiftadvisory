<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\EngagementType;
use App\Http\Controllers\Portal\EntrepreneurPlanWorkspace;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\FeeCalculation;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoDimensionScore;
use App\Models\NpoEngagement;
use App\Models\NpoSocialEnterpriseScorecard;
use App\Models\NpoTensionAnalysis;
use App\Models\NpoValueCalculation;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\PvCalculation;
use App\Models\QuestionnaireQuestion;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\StrategicBudget;
use App\Models\Template;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurJourney;
use App\Services\Pdf\PdfRenderer;
use App\Services\Portal\OnboardingWizard;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\StandardAdvisory\StandardAdvisoryWorkflow;
use App\Services\Storage\KeyEnvelope;
use Database\Seeders\TestingSeedDataSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TestingSeedDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('secure_local');
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".$html;
            }
        });
    }

    public function test_testing_seed_data_is_comprehensive_and_idempotent(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $tables = [
            'users',
            'clients',
            'documents',
            'questionnaire_responses',
            'analysis_findings',
            'pv_calculations',
            'business_valuations',
            'improvement_opportunities',
            'risk_costs',
            'templates',
            'proposals',
            'founding_advisory_engagements',
            'service_rate_packages',
            'service_activations',
            'business_plans',
            'dd_engagements',
            'npo_engagements',
            'npo_dimension_scores',
            'client_funder_records',
            'npo_value_calculations',
            'npo_impact_metrics',
            'bulk_communications',
            'entrepreneur_profiles',
            'idea_validations',
            'idea_validation_purchases',
            'entrepreneur_plan_budget_purchases',
            'advisor_client_transfer_requests',
        ];

        $countsAfterFirstRun = collect($tables)
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();

        $this->seed(TestingSeedDataSeeder::class);

        foreach ($countsAfterFirstRun as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "The [{$table}] seed records should be idempotent.");
        }

        $advisor = DB::table('users')->where('email', 'seed.advisor@futureshiftadvisory.test')->first();
        $this->assertNotNull($advisor);
        $this->assertDatabaseHas('users', [
            'email' => 'seed.advisor@futureshiftadvisory.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $advisor->id,
            'channel' => 'both',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $advisor->id,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'mfa_enabled_at' => null,
            'mfa_method' => null,
        ]);
        $this->assertDatabaseMissing('mfa_factors', ['user_id' => $advisor->id]);
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => DB::table('roles')->where('name', User::TYPE_ADVISOR)->value('id'),
            'model_type' => User::class,
            'model_id' => $advisor->id,
        ]);

        $advisoryClient = DB::table('clients')->where('nzbn', '9429000000010')->first();
        $this->assertNotNull($advisoryClient);
        $this->assertSame(1, DB::table('clients')->where('nzbn', '9429000000010')->count());
        $this->assertDatabaseHas('client_team', [
            'client_id' => $advisoryClient->id,
            'user_id' => $advisor->id,
            'role' => 'lead_advisor',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'seed.receiving.advisor@futureshiftadvisory.test',
            'user_type' => User::TYPE_ADVISOR,
        ]);
        $this->assertDatabaseHas('advisor_client_transfer_requests', [
            'client_id' => DB::table('clients')->where('nzbn', '9429000000133')->value('id'),
            'status' => 'pending',
        ]);

        $this->assertAtLeast(6, 'clients');
        $this->assertAtLeast(6, 'documents');
        $this->assertAtLeast(4, 'document_verifications');
        $this->assertAtLeast(2, 'questionnaire_responses');
        $this->assertGreaterThan(10, DB::table('questionnaire_answers')->count());
        $this->assertAtLeast(1, 'document_expiry_reminders');

        $this->assertAtLeast(1, 'accounting_connections');
        $this->assertAtLeast(2, 'financial_snapshots');
        $this->assertAtLeast(1, 'financial_alerts');
        $this->assertAtLeast(3, 'analysis_findings');
        $this->assertAtLeast(1, 'red_flags');
        $this->assertAtLeast(1, 'business_valuations');
        $this->assertAtLeast(1, 'improvement_opportunities');
        $this->assertAtLeast(1, 'risk_costs');
        $this->assertPvWaterfallSeedCoverage();

        $this->assertAtLeast(1, 'goals');
        $this->assertAtLeast(1, 'milestones');
        $this->assertAtLeast(1, 'milestone_actions');
        $this->assertAtLeast(1, 'proof_of_completion');
        $this->assertAtLeast(1, 'fee_calculations');
        $this->assertAtLeast(1, 'proposals');
        $this->assertAtLeast(1, 'consents');
        $this->assertAtLeast(1, 'payment_authorities');
        $this->assertAtLeast(1, 'payment_schedules');
        $this->assertAtLeast(1, 'payments');
        $this->assertAtLeast(1, 'receipts');
        $this->assertAtLeast(6, 'service_rate_packages');
        $this->assertAtLeast(3, 'service_activations');
        $this->assertSeededServiceActivationPricingFlow();
        $this->assertSeedClientPersonasStayScoped();
        $this->assertSouthernLightsPlanBudgetSubmitFixture();
        $this->assertSeededProposalTemplate();
        $this->assertSeededProposalSignoffFlow();
        $this->assertWebsiteAuditDemoFixture();
        $this->assertStandardAdvisoryReviewReadyFixture();

        $this->assertDatabaseHas('entrepreneur_profiles', [
            'email' => 'seed.entrepreneur@futureshiftadvisory.test',
            'stage' => 'advisory_ready',
        ]);
        $this->assertAtLeast(1, 'readiness_assessments');
        $this->assertAtLeast(1, 'idea_validations');
        $this->assertAtLeast(1, 'business_plans');
        $this->assertAtLeast(5, 'plan_phases');
        $this->assertAtLeast(4, 'plan_sections');
        $this->assertAtLeast(1, 'plan_assessments');
        $this->assertAtLeast(1, 'plan_revisions');
        $this->assertAtLeast(1, 'advisory_readiness_signals');
        $this->assertAtLeast(2, 'outcome_follow_ups');
        $this->assertSeededIdeaValidationTestScenarios();
        $this->assertSeededPaidPlanBudgetTestScenarios();
        $this->assertSeededAdvisoryServiceTestScenarios();

        $this->assertAtLeast(2, 'panel_members');
        $this->assertAtLeast(2, 'panel_agreements');
        $this->assertAtLeast(1, 'coach_referral_authorisations');
        $this->assertAtLeast(2, 'referrals');
        $this->assertAtLeast(2, 'referral_messages');
        $this->assertAtLeast(1, 'reverse_referrals');

        $this->assertAtLeast(1, 'dd_engagements');
        $this->assertAtLeast(1, 'dd_guest_links');
        $this->assertAtLeast(2, 'dd_data_room_items');
        $this->assertAtLeast(4, 'dd_workstreams');
        $this->assertAtLeast(1, 'dd_valuations');
        $this->assertAtLeast(1, 'dd_risk_register');
        $this->assertAtLeast(3, 'dd_integration_plans');
        $this->assertAtLeast(1, 'post_acquisition_migrations');

        $npoClient = DB::table('clients')->where('nzbn', '9429000000072')->first();
        $this->assertNotNull($npoClient);
        $this->assertDatabaseHas('clients', [
            'nzbn' => '9429000000072',
            'engagement_type' => 'npo',
            'legal_name' => 'Aroha Community Trust',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'seed.npo.primary@futureshiftadvisory.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'seed.npo.board@futureshiftadvisory.test',
            'user_type' => User::TYPE_NPO_BOARD_MEMBER,
        ]);
        $this->assertAtLeast(3, 'npo_engagements');
        $this->assertAtLeast(2, 'npo_board_members');
        $this->assertAtLeast(3, 'governance_review_findings');
        $this->assertAtLeast(15, 'npo_dimension_scores');
        $this->assertAtLeast(2, 'npo_compliance_alerts');
        $this->assertAtLeast(3, 'funders');
        $this->assertAtLeast(3, 'client_funder_records');
        $this->assertAtLeast(4, 'client_funder_alerts');
        $this->assertAtLeast(2, 'npo_value_calculations');
        $this->assertAtLeast(1, 'npo_social_enterprise_scorecards');
        $this->assertAtLeast(1, 'npo_tension_analyses');
        $this->assertAtLeast(4, 'npo_impact_metrics');
        $this->assertAtLeast(4, 'reports');
        $this->assertAtLeast(1, 'npo_funder_report_links');
        $this->assertAtLeast(1, 'npo_funder_report_sessions');
        $this->assertGreaterThanOrEqual(
            2,
            DB::table('questionnaire_responses')->whereNotNull('npo_engagement_id')->count(),
            'Expected NPO-scoped questionnaire responses.',
        );
        $this->assertGreaterThanOrEqual(
            5,
            DB::table('documents')->whereNotNull('npo_engagement_id')->count(),
            'Expected NPO-scoped documents.',
        );

        $this->assertAtLeast(1, 'message_threads');
        $this->assertAtLeast(1, 'messages');
        $this->assertAtLeast(1, 'wellbeing_checkins');
        $this->assertAtLeast(1, 'coaching_signals');
        $this->assertAtLeast(1, 'coach_referral_suggestions');
        $this->assertAtLeast(1, 'voice_notes');
        $this->assertAtLeast(1, 'call_logs');
        $this->assertAtLeast(1, 'testimonials');
        $this->assertAtLeast(1, 'meetings');
        $this->assertAtLeast(1, 'pre_meeting_briefs');
        $this->assertAtLeast(1, 'industry_briefings');
        $this->assertAtLeast(1, 'practice_health_snapshots');
        $this->assertAtLeast(1, 'offboarding_records');
        $this->assertAtLeast(1, 'bulk_communications');
        $this->assertAtLeast(3, 'bulk_communication_recipients');
    }

    public function test_advisory_proposal_fixture_shows_the_founder_handoff_and_signoff(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $entrepreneur = User::query()
            ->where('email', 'seed.idea-advisory.proposal@futureshiftadvisory.test')
            ->firstOrFail();
        $proposal = Proposal::query()
            ->whereHas('foundingAdvisoryEngagement.entrepreneurProfile', fn ($query) => $query
                ->where('email', 'seed.idea-advisory.proposal@futureshiftadvisory.test'))
            ->firstOrFail();

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/entrepreneur/Dashboard')
                ->where('journey.state', 'advisory_proposal')
                ->where('journey.active_service', 'Advisory')
                ->where('journey.next.label', 'Review advisory proposal')
                ->where('journey.next.url', route('portal.proposals.signoff.show', $proposal, absolute: false))
                ->where('foundingAdvisory.status', 'proposal_sent')
                ->where('foundingAdvisory.proposal.id', $proposal->getKey())
                ->where('foundingAdvisory.proposal.signoff_url', route('portal.proposals.signoff.show', $proposal, absolute: false)));

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.proposals.signoff.show', $proposal))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/ProposalSignoff')
                ->where('proposal.id', $proposal->getKey()));
    }

    /**
     * Guards against seeded values that are not valid enum backing values.
     * The raw count assertions above use DB::table() and never cast, so an
     * out-of-range enum (e.g. analysis_runs.module = 'strategic_diagnostic')
     * slips through and only blows up when the app hydrates the model (a 500
     * on /dashboard). This test hydrates every enum-cast seeded model through
     * Eloquent — toArray() forces each cast — so an invalid backing value
     * throws a ValueError here instead of in production.
     *
     * @return array<int, class-string<Model>>
     */
    public static function enumCastModels(): array
    {
        return [
            [AnalysisRun::class],
            [AnalysisFinding::class],
            [Client::class],
            [EntrepreneurProfile::class],
            [FeeCalculation::class],
            [GovernanceReviewFinding::class],
            [NpoDimensionScore::class],
            [NpoEngagement::class],
            [NpoSocialEnterpriseScorecard::class],
            [NpoTensionAnalysis::class],
            [NpoValueCalculation::class],
            [Proposal::class],
            [PvCalculation::class],
            [QuestionnaireQuestion::class],
            [Report::class],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('enumCastModels')]
    public function test_seeded_records_hydrate_through_their_enum_casts(string $model): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $records = $model::query()->get();
        $this->assertGreaterThan(0, $records->count(), "Expected seeded [{$model}] records to hydrate.");

        foreach ($records as $record) {
            // Throws ValueError if any enum-cast column holds a value that is
            // not a valid backing value for its enum (mirrors app hydration).
            $this->assertIsArray($record->toArray());
        }
    }

    public function test_testing_seed_data_keeps_dd_and_post_acquisition_personas_separate(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $this->assertSeedClientPersonasStayScoped();
    }

    public function test_testing_seed_data_reconciles_fixture_statuses_despite_lifecycle_guard(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        DB::table('clients')
            ->where('nzbn', '9429000000172')
            ->update(['status' => 'paused']);

        $this->seed(TestingSeedDataSeeder::class);

        $this->assertDatabaseHas('clients', [
            'nzbn' => '9429000000172',
            'status' => 'active',
        ]);
    }

    public function test_testing_seed_data_includes_a_standard_advisory_client_ready_for_review(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $this->assertStandardAdvisoryReviewReadyFixture();
    }

    public function test_testing_seed_data_includes_dedicated_standard_advisory_start_and_submit_ready_clients(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $this->assertDedicatedStandardAdvisoryClientFixtures();
    }

    public function test_seed_buyer_portal_resolves_to_southern_lights_and_rejects_post_acquisition_client(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $buyer = User::query()
            ->where('email', 'seed.buyer.primary@futureshiftadvisory.test')
            ->firstOrFail();
        $southernLights = Client::query()
            ->where('nzbn', '9429000000027')
            ->firstOrFail();
        $kauriKitchens = Client::query()
            ->where('nzbn', '9429000000034')
            ->firstOrFail();

        $this->actingAsMfa($buyer)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('client.id', $southernLights->getKey())
                ->where('client.legal_name', 'Southern Lights Holdings Limited')
                ->where('client.engagement_type', EngagementType::DUE_DILIGENCE->value)
            );

        $this->actingAsMfa($buyer)
            ->get(route('portal.dashboard', ['client' => $kauriKitchens->getKey()]))
            ->assertNotFound();
    }

    public function test_testing_seed_data_reconciles_retired_dd_post_acquisition_cross_links(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $buyer = DB::table('users')
            ->where('email', 'seed.buyer.primary@futureshiftadvisory.test')
            ->first();
        $advisor = DB::table('users')
            ->where('email', 'seed.advisor@futureshiftadvisory.test')
            ->first();
        $kauriKitchens = DB::table('clients')
            ->where('nzbn', '9429000000034')
            ->first();
        $packageId = DB::table('service_rate_packages')
            ->where('service_type', ServiceRatePackage::SERVICE_DUE_DILIGENCE)
            ->where('package_scope', ServiceRatePackage::SCOPE_DD_1M_3M)
            ->value('id');

        $this->assertNotNull($buyer);
        $this->assertNotNull($advisor);
        $this->assertNotNull($kauriKitchens);
        $this->assertNotNull($packageId);

        DB::table('client_team')->insert([
            'client_id' => $kauriKitchens->id,
            'user_id' => $buyer->id,
            'role' => 'primary_contact',
            'granted_modules' => json_encode(['portal', 'documents', 'post_acquisition'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('service_activations')->insert([
            'client_id' => $kauriKitchens->id,
            'requested_by_user_id' => $buyer->id,
            'advisor_id' => $advisor->id,
            'approved_by_user_id' => $advisor->id,
            'service_rate_package_id' => $packageId,
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'client_label' => 'Explore buying a business',
            'status' => ServiceActivation::STATUS_PACKAGE_SELECTED,
            'intake' => json_encode(['fixture' => 'retired-dd-post-acquisition-cross-link'], JSON_THROW_ON_ERROR),
            'selected_package_snapshot' => json_encode(['fixture' => 'retired-dd-post-acquisition-cross-link'], JSON_THROW_ON_ERROR),
            'payment_status' => ServiceActivation::PAYMENT_DEPOSIT_PENDING,
            'metadata' => json_encode([
                'fixture' => true,
                'fixture_key' => 'service_activation_dd_deposit_due',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            1,
            DB::table('client_team')
                ->where('client_id', $kauriKitchens->id)
                ->where('user_id', $buyer->id)
                ->count(),
        );
        $this->assertSame(
            1,
            DB::table('service_activations')
                ->where('client_id', $kauriKitchens->id)
                ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->count(),
        );

        $this->seed(TestingSeedDataSeeder::class);

        $this->assertSeedClientPersonasStayScoped();
    }

    private function assertAtLeast(int $minimum, string $table): void
    {
        $this->assertGreaterThanOrEqual($minimum, DB::table($table)->count(), "Expected [{$table}] to have seed coverage.");
    }

    private function assertSeededIdeaValidationTestScenarios(): void
    {
        $starter = DB::table('entrepreneur_profiles')
            ->where('email', 'seed.idea.start@futureshiftadvisory.test')
            ->first();
        $review = DB::table('entrepreneur_profiles')
            ->where('email', 'seed.idea.review@futureshiftadvisory.test')
            ->first();
        $approved = DB::table('entrepreneur_profiles')
            ->where('email', 'seed.idea.approved@futureshiftadvisory.test')
            ->first();
        $cancellation = DB::table('entrepreneur_profiles')
            ->where('email', 'seed.idea.cancel@futureshiftadvisory.test')
            ->first();
        $checkout = DB::table('idea_validation_purchases')
            ->join('users', 'users.id', '=', 'idea_validation_purchases.user_id')
            ->where('users.email', 'seed.idea.checkout@futureshiftadvisory.test')
            ->select('idea_validation_purchases.*')
            ->first();

        $this->assertNotNull($starter);
        $this->assertNotNull($review);
        $this->assertNotNull($approved);
        $this->assertNotNull($cancellation);
        $this->assertNotNull($checkout, sprintf(
            'Expected one pending Idea Validation checkout fixture, found %d. Current Idea Validation service rates: %d. Published terms versions: %d.',
            DB::table('idea_validation_purchases')->count(),
            DB::table('service_rate_packages')->where('package_scope', ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION)->count(),
            DB::table('terms_versions')->whereNotNull('published_at')->count(),
        ));
        $this->assertSame('idea_validation', $starter->stage);
        $this->assertSame('idea_validation', $review->stage);
        $this->assertSame('building_phase1', $approved->stage);
        $this->assertSame('idea_validation', $starter->intended_package_scope);
        $this->assertSame('idea_validation', $review->intended_package_scope);
        $this->assertSame('idea_validation', $approved->intended_package_scope);
        $this->assertSame('idea_validation', $cancellation->intended_package_scope);
        $this->assertSame('payment_pending', $checkout->status);
        $this->assertNull($checkout->payment_id);
        $this->assertDatabaseMissing('idea_validations', [
            'entrepreneur_profile_id' => $starter->id,
        ]);
        $this->assertDatabaseHas('idea_validations', [
            'entrepreneur_profile_id' => $review->id,
            'revision_number' => 1,
            'advisor_gate_passed_at' => null,
        ]);
        $this->assertDatabaseHas('idea_validations', [
            'entrepreneur_profile_id' => $approved->id,
            'revision_number' => 1,
            'advisor_gate_passed_by_user_id' => DB::table('users')
                ->where('email', 'seed.advisor@futureshiftadvisory.test')
                ->value('id'),
        ]);
        $this->assertDatabaseMissing('idea_validations', [
            'entrepreneur_profile_id' => $cancellation->id,
        ]);
        $this->assertDatabaseHas('service_activations', [
            'related_entrepreneur_profile_id' => $cancellation->id,
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'status' => ServiceActivation::STATUS_ACTIVE,
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_reference' => 'pi_seed_idea_validation_cancellation',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'seed.idea.cancel@futureshiftadvisory.test',
            'suspended_at' => null,
        ]);
    }

    private function assertSeededPaidPlanBudgetTestScenarios(): void
    {
        $package = DB::table('service_rate_packages')
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->where('package_scope', ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();

        $this->assertNotNull($package, 'Expected an active Business Plan & Budget Service Rate.');
        $expectedGross = round((float) $package->fixed_fee * 1.15, 2);

        foreach ([
            'start' => ['stage' => 'building_phase1', 'plan_status' => 'building', 'assessment_finalised' => false],
            'financials' => ['stage' => 'building_phase5', 'plan_status' => 'building', 'assessment_finalised' => false],
            'review' => ['stage' => 'submitted', 'plan_status' => 'submitted', 'assessment_finalised' => false],
            'approved' => ['stage' => 'advisory_ready', 'plan_status' => 'finalised', 'assessment_finalised' => true],
        ] as $state => $expectation) {
            $profile = DB::table('entrepreneur_profiles')
                ->where('email', "seed.plan-budget.{$state}@futureshiftadvisory.test")
                ->first();
            $this->assertNotNull($profile, "Expected the paid BP&B {$state} profile.");
            $this->assertSame($expectation['stage'], $profile->stage);
            $this->assertSame(ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET, $profile->intended_package_scope);
            $this->assertNotNull($profile->client_id, "Expected the paid BP&B {$state} profile to have a client record.");

            $purchase = DB::table('entrepreneur_plan_budget_purchases')
                ->where('entrepreneur_profile_id', $profile->id)
                ->first();
            $this->assertNotNull($purchase, "Expected the paid BP&B {$state} purchase.");
            $this->assertSame('paid', $purchase->status);
            $this->assertNotNull($purchase->paid_at);
            $this->assertNotNull($purchase->activated_at);
            $this->assertNotNull($purchase->approved_idea_validation_id);
            $this->assertSame((float) $package->fixed_fee, (float) $purchase->amount_ex_gst);
            $this->assertSame($expectedGross, (float) $purchase->amount_including_gst);

            $payment = DB::table('payments')->where('id', $purchase->payment_id)->first();
            $this->assertNotNull($payment, "Expected the paid BP&B {$state} payment record.");
            $this->assertSame('succeeded', $payment->status);
            $this->assertSame($expectedGross, (float) $payment->amount);

            $idea = DB::table('idea_validations')->where('id', $purchase->approved_idea_validation_id)->first();
            $this->assertNotNull($idea, "Expected the paid BP&B {$state} approved idea validation.");
            $this->assertNotNull($idea->advisor_gate_passed_at);

            $plan = DB::table('business_plans')
                ->where('entrepreneur_profile_id', $profile->id)
                ->where('source_type', 'entrepreneur')
                ->first();
            $this->assertNotNull($plan, "Expected the paid BP&B {$state} workspace.");
            $this->assertSame($expectation['plan_status'], $plan->status);

            $modelProfile = EntrepreneurProfile::query()->findOrFail($profile->id);
            $workspace = app(EntrepreneurPlanWorkspace::class);
            $journey = app(EntrepreneurJourney::class)->payload(
                $modelProfile,
                $workspace->packageAccess($modelProfile),
                $workspace->latestPlan($modelProfile),
            );
            $this->assertSame('Business Plan & Budget', $journey['active_service']);
            $this->assertTrue($journey['includes_plan_budget']);
            $this->assertSame(
                match ($state) {
                    'review' => 'plan_budget_review',
                    'approved' => 'plan_budget_assessed',
                    default => 'plan_budget_building',
                },
                $journey['state'],
            );
            $this->assertSame(
                $expectation['assessment_finalised']
                    ? 'Feedback ready'
                    : ($state === 'review' ? 'Awaiting advisor review' : 'Not started'),
                $journey['assessment']['status_label'],
            );
            $this->assertSame(
                $state === 'approved',
                $journey['advisory']['available'],
                "The paid BP&B {$state} fixture has an unexpected advisory-request state.",
            );

            if ($state === 'financials') {
                $this->assertSame(18, $journey['plan']['completion']['total']);
                $this->assertSame(17, $journey['plan']['completion']['completed']);
                $this->assertSame(94, $journey['plan']['completion']['percent']);

                $budget = DB::table('entrepreneur_budgets')
                    ->where('business_plan_id', $plan->id)
                    ->first();
                $this->assertNotNull($budget, 'Expected the Financials fixture to have a budget.');
                $this->assertSame('partial', $budget->status);
                $this->assertSame([], json_decode((string) $budget->funding_sources, true));
            }

            $assessment = DB::table('plan_assessments')
                ->where('business_plan_id', $plan->id)
                ->orderByDesc('round')
                ->first();
            if ($expectation['assessment_finalised']) {
                $this->assertNotNull($assessment, 'Expected an approved BP&B assessment.');
                $this->assertNotNull($assessment->finalised_at);
                $this->assertDatabaseHas('advisory_readiness_signals', [
                    'entrepreneur_profile_id' => $profile->id,
                    'business_plan_id' => $plan->id,
                    'plan_assessment_id' => $assessment->id,
                ]);
            } else {
                $this->assertNull($assessment, "The BP&B {$state} fixture should not have a finalised assessment.");
            }
        }
    }

    private function assertSeededAdvisoryServiceTestScenarios(): void
    {
        foreach ([
            'start' => [
                'email' => 'seed.idea-advisory.start@futureshiftadvisory.test',
                'founding_engagement' => false,
                'journey_state' => 'plan_budget_assessed',
                'active_service' => 'Business Plan & Budget',
                'advisory_available' => true,
            ],
            'proposal' => [
                'email' => 'seed.idea-advisory.proposal@futureshiftadvisory.test',
                'founding_engagement' => true,
                'journey_state' => 'advisory_proposal',
                'active_service' => 'Advisory',
                'advisory_available' => false,
            ],
        ] as $state => $expectation) {
            $profile = DB::table('entrepreneur_profiles')
                ->where('email', $expectation['email'])
                ->first();
            $this->assertNotNull($profile, "Expected the advisory {$state} profile.");
            $this->assertSame('advisory_ready', $profile->stage);

            $purchase = DB::table('entrepreneur_plan_budget_purchases')
                ->where('entrepreneur_profile_id', $profile->id)
                ->first();
            $this->assertNotNull($purchase, "Expected the advisory {$state} BP&B purchase.");
            $this->assertSame('paid', $purchase->status);

            $plan = DB::table('business_plans')
                ->where('entrepreneur_profile_id', $profile->id)
                ->where('source_type', 'entrepreneur')
                ->first();
            $this->assertNotNull($plan, "Expected the advisory {$state} BP&B workspace.");

            $assessment = DB::table('plan_assessments')
                ->where('business_plan_id', $plan->id)
                ->whereNotNull('finalised_at')
                ->orderByDesc('round')
                ->first();
            $this->assertNotNull($assessment, "Expected the advisory {$state} finalised BP&B assessment.");

            $this->assertDatabaseHas('advisory_readiness_signals', [
                'entrepreneur_profile_id' => $profile->id,
                'business_plan_id' => $plan->id,
                'plan_assessment_id' => $assessment->id,
            ]);

            $modelProfile = EntrepreneurProfile::query()->findOrFail($profile->id);
            $workspace = app(EntrepreneurPlanWorkspace::class);
            $journey = app(EntrepreneurJourney::class)->payload(
                $modelProfile,
                $workspace->packageAccess($modelProfile),
                $workspace->latestPlan($modelProfile),
            );
            $this->assertSame($expectation['journey_state'], $journey['state']);
            $this->assertSame($expectation['active_service'], $journey['active_service']);
            $this->assertSame($expectation['advisory_available'], $journey['advisory']['available']);

            $engagement = DB::table('founding_advisory_engagements')
                ->where('entrepreneur_profile_id', $profile->id)
                ->first();

            if (! $expectation['founding_engagement']) {
                $this->assertSame('finalised', $plan->status);
                $this->assertNull($engagement, 'The request-ready fixture must not bypass the client advisory-request step.');

                continue;
            }

            $this->assertSame('founding', $plan->status);
            $this->assertNotNull($engagement, 'Expected a Founding Advisory engagement for the released-proposal fixture.');
            $this->assertSame('proposal_sent', $engagement->status);
            $this->assertNotNull($engagement->proposal_id);

            $proposal = DB::table('proposals')->where('id', $engagement->proposal_id)->first();
            $this->assertNotNull($proposal, 'Expected the Founding Advisory proposal.');
            $this->assertSame('released', $proposal->status);
            $this->assertNotNull($proposal->released_at);
            $this->assertNull($proposal->recalled_at);
            $this->assertSame('Review advisory proposal', $journey['next']['label']);
            $this->assertSame(
                route('portal.proposals.signoff.show', $proposal->id, absolute: false),
                $journey['next']['url'],
            );

            $client = DB::table('clients')->where('id', $engagement->client_id)->first();
            $this->assertNotNull($client, 'Expected the converted Founding Advisory client.');
            $this->assertSame(EngagementType::FOUNDING_ADVISORY->value, $client->engagement_type);
        }
    }

    private function assertPvWaterfallSeedCoverage(): void
    {
        $pvClientIds = DB::table('clients')
            ->whereIn('nzbn', [
                '9429000000096',
                '9429000000102',
                '9429000000119',
                '9429000000126',
            ])
            ->pluck('id')
            ->all();

        $this->assertCount(4, $pvClientIds, 'Expected four seeded PV waterfall test clients.');
        $this->assertGreaterThanOrEqual(6, DB::table('business_valuations')->count());
        $this->assertGreaterThanOrEqual(17, DB::table('improvement_opportunities')->count());
        $this->assertGreaterThanOrEqual(9, DB::table('risk_costs')->count());

        $payload = app(PvWaterfallBuilder::class)->forClients($pvClientIds);
        $clients = collect($payload['clients']);
        $summit = $clients->firstWhere('client_name', 'Summit SaaS Limited');
        $bay = $clients->firstWhere('client_name', 'Bay Micro Tools Limited');

        $this->assertSame(4, $payload['summary']['clients']);
        $this->assertNotNull($bay);
        $this->assertSame(180000.0, $bay['current_pv']);
        $this->assertSame(243000.0, $bay['target_pv']);
        $this->assertNotNull($summit);
        $this->assertSame(9500000.0, $summit['current_pv']);
        $this->assertSame(15148000.0, $summit['target_pv']);
        $this->assertTrue(
            collect($summit['waterfall'])->contains(fn (array $step): bool => ($step['is_remainder'] ?? false) === true),
            'Expected Summit SaaS to exercise the PV waterfall remainder step.',
        );
    }

    private function assertSeededProposalTemplate(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            return;
        }

        $template = DB::table('templates')
            ->where('category', Template::CATEGORY_PROPOSAL)
            ->where('status', Template::STATUS_ACTIVE)
            ->first();

        $this->assertNotNull($template, 'Expected testing seed data to include an active proposal template.');

        $structure = json_decode((string) $template->structure, true);
        $this->assertSame('uploaded_file', $structure['source_kind'] ?? null);
        $this->assertNotEmpty($structure['uploaded_file']['stored_path'] ?? null);
    }

    private function assertSeededServiceActivationPricingFlow(): void
    {
        $this->assertDatabaseHas('service_rate_packages', [
            'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
            'package_scope' => ServiceRatePackage::SCOPE_DD_300K_1M,
            'fixed_fee' => '8500.00',
            'deposit_percent' => '50.00',
        ]);

        $this->assertDatabaseHas('service_rate_packages', [
            'service_type' => ServiceRatePackage::SERVICE_DUE_DILIGENCE,
            'package_scope' => ServiceRatePackage::SCOPE_DD_1M_3M,
            'fixed_fee' => '14500.00',
            'deposit_percent' => '25.00',
        ]);

        $this->assertDatabaseHas('service_rate_packages', [
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_COMBO,
            'fixed_fee' => '4450.00',
            'deposit_percent' => '100.00',
        ]);

        $this->assertDatabaseHas('service_rate_packages', [
            'service_type' => ServiceRatePackage::SERVICE_DD_PLAN_BUDGET,
            'package_scope' => ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON,
            'fixed_fee' => '2400.00',
            'deposit_percent' => '100.00',
            'purchase_price_min' => null,
            'purchase_price_max' => null,
        ]);

        $balancePending = DB::table('service_activations')
            ->where('payment_status', ServiceActivation::PAYMENT_BALANCE_PENDING)
            ->first();

        $this->assertNotNull($balancePending, 'Expected a seeded activation with bank-transfer balance pending.');
        $this->assertNotNull($balancePending->deposit_paid_at);
        $this->assertNull($balancePending->payment_completed_at);

        $snapshot = json_decode((string) $balancePending->selected_package_snapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(50.0, (float) data_get($snapshot, 'payment_split.deposit_percent'));
        $this->assertSame(4250.0, (float) data_get($snapshot, 'payment_split.card_deposit_amount'));
        $this->assertSame(4250.0, (float) data_get($snapshot, 'payment_split.bank_transfer_amount'));

        $this->assertDatabaseHas('service_activations', [
            'payment_status' => ServiceActivation::PAYMENT_DEPOSIT_PENDING,
            'deposit_paid_at' => null,
            'payment_completed_at' => null,
        ]);

        $this->assertDatabaseHas('service_activations', [
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'payment_status' => ServiceActivation::PAYMENT_PENDING,
            'payment_completed_at' => null,
        ]);

        foreach ([
            'seed.dd.guided@futureshiftadvisory.test' => 'guided',
            'seed.dd.experience@futureshiftadvisory.test' => 'experienced',
        ] as $email => $mode) {
            $persona = DB::table('users')
                ->join('clients', 'clients.primary_contact_user_id', '=', 'users.id')
                ->join('dd_engagements', 'dd_engagements.client_id', '=', 'clients.id')
                ->join('service_activations', 'service_activations.related_dd_engagement_id', '=', 'dd_engagements.id')
                ->where('users.email', $email)
                ->where('service_activations.status', ServiceActivation::STATUS_ACTIVE)
                ->select('dd_engagements.target_details')
                ->first();

            $this->assertNotNull($persona, "Expected seeded DD persona [{$email}] to have an active DD workspace.");
            $targetDetails = json_decode((string) $persona->target_details, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($mode, data_get($targetDetails, 'client_capability.mode'));
        }
    }

    private function assertSeedClientPersonasStayScoped(): void
    {
        $ddBuyer = DB::table('users')
            ->where('email', 'seed.buyer.primary@futureshiftadvisory.test')
            ->first();
        $postAcquisitionBuyer = DB::table('users')
            ->where('email', 'seed.postacquisition.primary@futureshiftadvisory.test')
            ->first();
        $depositPendingBuyer = DB::table('users')
            ->where('email', 'seed.dd.deposit@futureshiftadvisory.test')
            ->first();
        $southernLights = DB::table('clients')
            ->where('nzbn', '9429000000027')
            ->first();
        $kauriKitchens = DB::table('clients')
            ->where('nzbn', '9429000000034')
            ->first();
        $depositPendingDd = DB::table('clients')
            ->where('nzbn', '9429000000164')
            ->first();

        $this->assertNotNull($ddBuyer, 'Expected the seeded DD buyer persona.');
        $this->assertNotNull($postAcquisitionBuyer, 'Expected the seeded post-acquisition buyer persona.');
        $this->assertNotNull($depositPendingBuyer, 'Expected the seeded DD deposit-pending persona.');
        $this->assertNotNull($southernLights, 'Expected the seeded DD client.');
        $this->assertNotNull($kauriKitchens, 'Expected the seeded post-acquisition client.');
        $this->assertNotNull($depositPendingDd, 'Expected the seeded DD deposit-pending client.');
        $this->assertNotSame((string) $ddBuyer->id, (string) $postAcquisitionBuyer->id);
        $this->assertNotSame((string) $ddBuyer->id, (string) $depositPendingBuyer->id);
        $this->assertNotSame((string) $postAcquisitionBuyer->id, (string) $depositPendingBuyer->id);
        $this->assertSame((string) $ddBuyer->id, (string) $southernLights->primary_contact_user_id);
        $this->assertSame((string) $postAcquisitionBuyer->id, (string) $kauriKitchens->primary_contact_user_id);
        $this->assertSame((string) $depositPendingBuyer->id, (string) $depositPendingDd->primary_contact_user_id);
        $this->assertSame(
            1,
            DB::table('client_team')
                ->where('client_id', $southernLights->id)
                ->where('user_id', $ddBuyer->id)
                ->count(),
            'The DD buyer should only be assigned to the DD client team.',
        );
        $this->assertSame(
            0,
            DB::table('client_team')
                ->where('client_id', $kauriKitchens->id)
                ->where('user_id', $ddBuyer->id)
                ->count(),
            'The DD buyer must not also be assigned to the post-acquisition client.',
        );
        $this->assertSame(
            1,
            DB::table('client_team')
                ->where('client_id', $kauriKitchens->id)
                ->where('user_id', $postAcquisitionBuyer->id)
                ->count(),
            'The post-acquisition buyer should be assigned to the post-acquisition client team.',
        );
        $this->assertSame(
            0,
            DB::table('service_activations')
                ->where('client_id', $kauriKitchens->id)
                ->where('service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->count(),
            'The post-acquisition client must not carry a due-diligence activation fixture.',
        );
        $this->assertDatabaseHas('service_activations', [
            'client_id' => $depositPendingDd->id,
            'requested_by_user_id' => $depositPendingBuyer->id,
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'payment_status' => ServiceActivation::PAYMENT_DEPOSIT_PENDING,
        ]);
        $this->assertSame(
            0,
            DB::table('service_activations')
                ->join('clients', 'clients.id', '=', 'service_activations.client_id')
                ->where('service_activations.service_type', ServiceActivation::SERVICE_DUE_DILIGENCE)
                ->whereNotIn('clients.engagement_type', [
                    EngagementType::DUE_DILIGENCE->value,
                    EngagementType::ENTREPRENEUR_MODULE->value,
                ])
                ->count(),
            'Seeded due-diligence activations may attach to DD clients or entrepreneur DD add-ons, but not unrelated client journeys.',
        );

        $wizardState = json_decode((string) $kauriKitchens->onboarding_wizard_state, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(4, (int) ($wizardState['journey_version'] ?? 0));
        $this->assertSame(3, (int) ($wizardState['current_step'] ?? 0));
        $this->assertSame([
            OnboardingWizard::STEP_WELCOME,
            OnboardingWizard::STEP_QUESTIONNAIRE,
        ], $wizardState['completed_steps'] ?? []);
        $this->assertNotContains(OnboardingWizard::STEP_GOALS, $wizardState['completed_steps'] ?? []);
        $this->assertNotContains(OnboardingWizard::STEP_WEBSITE, $wizardState['completed_steps'] ?? []);
        $this->assertNotContains(OnboardingWizard::STEP_IDENTITY, $wizardState['completed_steps'] ?? []);
        $this->assertNotContains(OnboardingWizard::STEP_BUSINESS_SNAPSHOT, $wizardState['completed_steps'] ?? []);
    }

    private function assertSouthernLightsPlanBudgetSubmitFixture(): void
    {
        $client = DB::table('clients')->where('nzbn', '9429000000027')->first();
        $this->assertNotNull($client, 'Expected Southern Lights seed client.');

        $activation = DB::table('service_activations')
            ->where('client_id', $client->id)
            ->where('service_type', ServiceActivation::SERVICE_DD_PLAN_BUDGET)
            ->first();

        $this->assertNotNull($activation, 'Expected Southern Lights to have active fixed-fee BP&B access.');
        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->status);
        $this->assertSame(ServiceActivation::PAYMENT_PAID, $activation->payment_status);

        $snapshot = json_decode((string) $activation->selected_package_snapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(ServiceRatePackage::SCOPE_DD_PLAN_BUDGET_ADD_ON, data_get($snapshot, 'package_scope'));
        $this->assertSame(2400.0, (float) data_get($snapshot, 'fixed_fee'));
        $this->assertNull(data_get($snapshot, 'quote_context'));

        $document = DB::table('documents')
            ->where('client_id', $client->id)
            ->where('original_filename', 'target-management-accounts.xlsx')
            ->first();

        $this->assertNotNull($document, 'Expected Southern Lights management accounts upload.');
        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $document->id,
            'context_hash' => hash('sha256', 'seed-verification-dd-plan-budget-management-accounts'),
            'outcome' => 'verified',
        ]);

        $budget = DB::table('strategic_budgets')
            ->where('client_id', $client->id)
            ->where('pathway', StrategicBudget::PATHWAY_DUE_DILIGENCE)
            ->first();

        $this->assertNotNull($budget, 'Expected Southern Lights DD Business Plan & Budget fixture.');
        $this->assertSame(StrategicBudget::STATUS_CLIENT_WORKING_DRAFT, $budget->status);
        $this->assertNull($budget->submitted_at);
        $this->assertNull($budget->business_plan_submitted_at);
        $this->assertNull($budget->accepted_snapshot_at);

        $sourceFinancials = json_decode((string) $budget->source_financials, true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) data_get($sourceFinancials, 'unlocked'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($sourceFinancials, 'count'));

        $planSections = json_decode((string) $budget->business_plan_sections, true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(8, collect($planSections)->filter(fn (array $section): bool => trim((string) ($section['answer'] ?? '')) !== ''));
        $financialDrivers = collect($planSections)
            ->flatMap(fn (array $section): array => (array) ($section['financial_drivers'] ?? []))
            ->keyBy('key');

        foreach (['implementation_costs', 'monthly_fixed_costs', 'revenue_forecast', 'funding_sources'] as $column) {
            $rows = json_decode((string) $budget->{$column}, true, flags: JSON_THROW_ON_ERROR);
            $this->assertNotEmpty($rows, "Expected seeded [{$column}] rows for submit-for-review testing.");

            foreach ($rows as $row) {
                $driverKey = (string) ($row['plan_financial_driver_key'] ?? '');
                $driver = $financialDrivers->get($driverKey);

                $this->assertNotSame('', $driverKey, "Expected seeded [{$column}] row to link back to a plan financial driver.");
                $this->assertIsArray($driver, "Expected seeded [{$column}] row to link to an existing plan financial driver.");
                $this->assertSame($column, $driver['category']);
                $this->assertSame((float) $row['amount'], (float) $driver['amount']);
                $this->assertSame((float) ($row['quantity'] ?? 1), (float) $driver['quantity']);
                $this->assertSame((int) ($row['month'] ?? 1), (int) $driver['month']);
            }
        }
    }

    private function assertSeededProposalSignoffFlow(): void
    {
        $proposal = DB::table('proposals')
            ->join('clients', 'clients.id', '=', 'proposals.client_id')
            ->where('clients.nzbn', '9429000000010')
            ->select('proposals.id', 'proposals.signature_evidence_path', 'proposals.signature_evidence_sha256_envelope', 'proposals.signature_evidence_byte_size')
            ->first();

        $this->assertNotNull($proposal);

        $steps = DB::table('proposal_signoff_steps')
            ->where('proposal_id', $proposal->id)
            ->pluck('step')
            ->all();

        foreach (ProposalSignoffStep::orderedSteps() as $step) {
            $this->assertContains($step, $steps, "Expected seeded proposal to include signoff step [{$step}].");
        }

        $this->assertSame(
            0,
            DB::table('proposal_signoff_steps')
                ->where('proposal_id', $proposal->id)
                ->whereIn('step', ['released', 'client_signed', 'payment_authorised'])
                ->count(),
            'Seeded proposal should not use legacy signoff step names.',
        );

        $this->assertSame('seed/proposals/harbour-hive-signature.pdf', $proposal->signature_evidence_path);
        $this->assertGreaterThan(0, $proposal->signature_evidence_byte_size);
        $this->assertTrue(
            Storage::disk('secure_local')->exists($proposal->signature_evidence_path),
            'Seeded signed proposal should have retrievable signed PDF evidence.',
        );
        $signatureEvidence = Storage::disk('secure_local')->get($proposal->signature_evidence_path);
        $this->assertIsString($signatureEvidence);
        $compactSignatureEvidence = preg_replace('/\s+/', '', $signatureEvidence) ?? '';

        $this->assertStringContainsString('UPLOADED PROPOSAL TEMPLATE', $signatureEvidence);
        $this->assertStringContainsString('proposal-signature-stamp', $signatureEvidence);
        $this->assertStringContainsString('proposal-signature-certificate', $signatureEvidence);
        $this->assertStringContainsString('Signed proposal certificate', $signatureEvidence);
        $this->assertStringContainsString('Signedby</dt><dd>SeedClientPrincipal', $compactSignatureEvidence);
        $this->assertStringContainsString('Collectiondate</dt><dd>1stofeachmonth', $compactSignatureEvidence);
        $this->assertStringNotContainsString('Future Shift Advisory Proposal v1 - Signed', $signatureEvidence);
        $this->assertStringNotContainsString('Future Shift Advisory Proposal - Signed', $signatureEvidence);
        $this->assertSame(
            hash('sha256', $signatureEvidence),
            app(KeyEnvelope::class)->decrypt((string) $proposal->signature_evidence_sha256_envelope),
        );

        $authority = DB::table('payment_authorities')
            ->where('proposal_id', $proposal->id)
            ->where('gateway', 'stripe')
            ->first();

        $this->assertNotNull($authority);

        $tokenPayload = json_decode(
            app(KeyEnvelope::class)->decrypt((string) $authority->gateway_token_envelope),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('pm_seed_harbour_hive', $tokenPayload['token'] ?? null);
        $this->assertSame('cus_seed_harbour_hive', $tokenPayload['customer_ref'] ?? null);

        $schedule = DB::table('payment_schedules')
            ->where('proposal_id', $proposal->id)
            ->first();

        $this->assertNotNull($schedule);
        $this->assertSame(PaymentSchedule::CADENCE_MONTHLY_RETAINER, $schedule->cadence);
        $this->assertSame(1, (int) $schedule->collection_day);
    }

    private function assertStandardAdvisoryReviewReadyFixture(): void
    {
        $client = Client::query()
            ->where('nzbn', '9429000000172')
            ->first();

        $this->assertNotNull($client, 'Expected the advisor-review-ready Standard Advisory client.');
        $this->assertSame(EngagementType::STANDARD_ADVISORY, $client->engagement_type);

        $wizard = app(OnboardingWizard::class);
        $state = $wizard->state($client);
        $this->assertSame(100, $wizard->progress($client)['percentage']);
        $this->assertNotNull($state['submitted_at']);

        $readiness = app(StandardAdvisoryWorkflow::class)->readiness($client);
        $this->assertTrue($readiness['questionnaire_submitted']);
        $this->assertSame(1, $readiness['document_count']);
        $this->assertSame(0, $readiness['blocking_verification_count']);
        $this->assertTrue($readiness['can_run_analysis']);
        $this->assertSame('green', $readiness['analysis_readiness']['level']);
        $this->assertSame('ready_for_analysis', $readiness['status']);
    }

    private function assertDedicatedStandardAdvisoryClientFixtures(): void
    {
        $startUser = User::query()
            ->where('email', 'seed.client.advisory.start@futureshiftadvisory.test')
            ->firstOrFail();
        $startClient = Client::query()->where('nzbn', '9429000000246')->firstOrFail();
        $reviewUser = User::query()
            ->where('email', 'seed.client.advisory.review@futureshiftadvisory.test')
            ->firstOrFail();
        $reviewClient = Client::query()->where('nzbn', '9429000000253')->firstOrFail();
        $wizard = app(OnboardingWizard::class);

        $this->assertSame(EngagementType::STANDARD_ADVISORY, $startClient->engagement_type);
        $this->assertSame((string) $startUser->getKey(), (string) $startClient->primary_contact_user_id);
        $this->assertSame(0, $wizard->progress($startClient)['percentage']);
        $this->assertSame(OnboardingWizard::STEP_WELCOME, $wizard->currentStepSlug($startClient));
        $this->assertNull($wizard->state($startClient)['submitted_at']);

        $this->assertSame(EngagementType::STANDARD_ADVISORY, $reviewClient->engagement_type);
        $this->assertSame((string) $reviewUser->getKey(), (string) $reviewClient->primary_contact_user_id);
        $this->assertSame(83, $wizard->progress($reviewClient)['percentage']);
        $this->assertSame(OnboardingWizard::STEP_REVIEW, $wizard->currentStepSlug($reviewClient));
        $this->assertNull($wizard->state($reviewClient)['submitted_at']);
        $this->assertTrue($wizard->canAccess($reviewClient, OnboardingWizard::STEP_REVIEW));

        $readiness = app(StandardAdvisoryWorkflow::class)->readiness($reviewClient);
        $this->assertTrue($readiness['questionnaire_submitted']);
        $this->assertSame(1, $readiness['document_count']);
        $this->assertSame(0, $readiness['blocking_verification_count']);
    }

    private function assertWebsiteAuditDemoFixture(): void
    {
        $client = DB::table('clients')->where('nzbn', '9429000000133')->first();
        $this->assertNotNull($client, 'Expected the Website Review Demo client.');
        $this->assertTrue((bool) $client->pilot_fee_waiver_enabled);
        $this->assertNotNull($client->pilot_fee_waiver_expires_at);
        $this->assertSame('open', DB::table('pilot_fee_waiver_programs')
            ->where('key', 'pilot-fee-waiver')
            ->value('status'));

        $document = DB::table('documents')
            ->where('client_id', $client->id)
            ->where('stored_path', 'seed/documents/website-audit-financial-statements')
            ->first();
        $this->assertNotNull($document, 'Expected Website Review Demo financial statements.');
        $this->assertStringContainsString(
            'website-review-demo-financial-statements.pdf',
            Storage::disk('secure_local')->get($document->stored_path),
        );

        $proposal = DB::table('proposals')
            ->join('fee_calculations', 'fee_calculations.id', '=', 'proposals.fee_calculation_id')
            ->where('proposals.client_id', $client->id)
            ->where('fee_calculations.method', 'integration')
            ->where('proposals.status', 'released')
            ->select('proposals.scope', 'proposals.pricing_terms')
            ->first();
        $this->assertNotNull($proposal, 'Expected a released integration proposal for Website Review Demo.');

        $scope = json_decode((string) $proposal->scope, true, flags: JSON_THROW_ON_ERROR);
        $hosting = data_get($scope, 'integration_quote_pack.hosting');
        $this->assertSame(true, data_get($hosting, 'enabled'));
        $this->assertSame(41.32, (float) data_get($hosting, 'monthly_fee'));
        $this->assertArrayNotHasKey('monthly_cost', $hosting);
        $this->assertArrayNotHasKey('markup_percent', $hosting);

        $pricingTerms = json_decode((string) $proposal->pricing_terms, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('pilot_fee_waiver', data_get($pricingTerms, 'treatment'));
        $this->assertTrue((bool) data_get($pricingTerms, 'fee_active'));
        $this->assertTrue((bool) data_get($pricingTerms, 'payment_required'));
        $this->assertSame(0, data_get($pricingTerms, 'payable_fee.mid'));
        $this->assertSame(41.32, (float) data_get($pricingTerms, 'hosting.monthly_fee'));
    }
}
