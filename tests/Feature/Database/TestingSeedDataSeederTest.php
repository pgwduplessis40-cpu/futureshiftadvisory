<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

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
use App\Models\Proposal;
use App\Models\PvCalculation;
use App\Models\QuestionnaireQuestion;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\TestingSeedDataSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TestingSeedDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_seed_data_is_comprehensive_and_idempotent(): void
    {
        $this->seed(TestingSeedDataSeeder::class);

        $tables = [
            'users',
            'clients',
            'documents',
            'questionnaire_responses',
            'analysis_findings',
            'proposals',
            'business_plans',
            'dd_engagements',
            'npo_engagements',
            'npo_dimension_scores',
            'client_funder_records',
            'npo_value_calculations',
            'npo_impact_metrics',
            'bulk_communications',
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
        $this->assertDatabaseHas('mfa_factors', [
            'user_id' => $advisor->id,
            'type' => 'totp',
        ]);
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

    private function assertAtLeast(int $minimum, string $table): void
    {
        $this->assertGreaterThanOrEqual($minimum, DB::table($table)->count(), "Expected [{$table}] to have seed coverage.");
    }
}
