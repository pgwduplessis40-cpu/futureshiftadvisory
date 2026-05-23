<?php

declare(strict_types=1);

namespace Tests\Feature\Dd;

use App\Enums\EngagementType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdOnboarding;
use App\Services\Dd\PlanBuilder as DdPlanBuilder;
use App\Services\Dd\Workstreams\DdWorkstreamRunner;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class DdPlanBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_business_plans_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON business_plans, plan_phases, plan_sections, entrepreneur_profiles FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_dd_findings_auto_populate_shared_business_plan_sections(): void
    {
        [$advisor, $engagement] = $this->ddEngagement();
        $this->completeWorkstreams($engagement, $advisor);

        $plan = app(DdPlanBuilder::class)->buildFromWorkstreams($engagement, $advisor);

        $this->assertSame(BusinessPlan::SOURCE_DUE_DILIGENCE, $plan->source_type);
        $this->assertSame($engagement->id, $plan->dd_engagement_id);
        $this->assertCount(5, $plan->phases);
        $this->assertTrue($plan->phases->every(fn ($phase): bool => $phase->sections->isNotEmpty()));
        $this->assertGreaterThan(8, $plan->sections()->count());
        $this->assertGreaterThan(0, $plan->sections()->whereNotNull('source_analysis_finding_id')->count());

        $section = $plan->sections()->whereNotNull('source_analysis_finding_id')->firstOrFail();
        $this->assertInstanceOf(PlanSection::class, $section);
        $this->assertSame(PlanSection::STATUS_COMPLETE, $section->completeness_status);
        $this->assertStringContainsString('double-weighted', $section->body);
    }

    public function test_completeness_gate_blocks_acquisition_proceeding_until_required_phases_are_present(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('incomplete-plan-dd-advisor@example.test');

        try {
            app(DdPlanBuilder::class)->markAcquisitionProceeding($engagement, $advisor);
            $this->fail('Expected incomplete DD plan to block acquisition proceeding.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Business plan is incomplete', $e->getMessage());
        }

        $this->assertSame(DdEngagement::STATUS_IN_PROGRESS, $engagement->refresh()->status);
        $plan = BusinessPlan::query()->where('dd_engagement_id', $engagement->id)->firstOrFail();
        $this->assertSame(BusinessPlan::STATUS_DRAFT, $plan->status);
        $this->assertNull($plan->founding_advisory_payload);
    }

    public function test_complete_dd_plan_marks_acquisition_proceeding_and_builds_founding_advisory_payload(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('complete-plan-dd-advisor@example.test');
        $this->completeWorkstreams($engagement, $advisor);

        $plan = app(DdPlanBuilder::class)->markAcquisitionProceeding($engagement, $advisor);

        $this->assertSame(BusinessPlan::STATUS_FOUNDING, $plan->status);
        $this->assertNotNull($plan->completed_at);
        $this->assertSame(DdEngagement::STATUS_ACQUISITION_PROCEEDING, $engagement->refresh()->status);
        $this->assertIsArray($plan->founding_advisory_payload);
        $this->assertSame($plan->id, $plan->founding_advisory_payload['business_plan_id']);
        $this->assertCount(5, $plan->founding_advisory_payload['phases']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.plan_founding_advisory_ready',
            'subject_id' => $plan->id,
        ]);
    }

    public function test_business_plan_tables_are_isolated_by_buyer_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Business-plan RLS assertions require Postgres.');
        }

        [, $engagementA] = $this->ddEngagement('plan-rls-a@example.test');
        [, $engagementB] = $this->ddEngagement('plan-rls-b@example.test');

        $planA = app(DdPlanBuilder::class)->buildFromWorkstreams($engagementA);
        $planB = app(DdPlanBuilder::class)->buildFromWorkstreams($engagementB);

        app(RequestContext::class)->apply('advisor', [(string) $engagementA->client_id]);

        $visiblePlanIds = $this->withRlsRole(fn (): array => DB::table('business_plans')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $visiblePhasePlanIds = $this->withRlsRole(fn (): array => DB::table('plan_phases')
            ->pluck('business_plan_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all());

        $visibleSectionPlanIds = $this->withRlsRole(fn (): array => DB::table('plan_sections')
            ->pluck('business_plan_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all());

        foreach ([$visiblePlanIds, $visiblePhasePlanIds, $visibleSectionPlanIds] as $visibleIds) {
            $this->assertContains($planA->id, $visibleIds);
            $this->assertNotContains($planB->id, $visibleIds);
        }
    }

    public function test_business_plan_owner_constraint_rejects_missing_and_dual_owners(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Business-plan owner constraint requires Postgres.');
        }

        [$advisor, $engagement] = $this->ddEngagement('plan-owner-xor@example.test');
        $profile = EntrepreneurProfile::query()->create([
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Plan Owner Founder',
            'email' => 'plan-owner-founder@example.test',
            'stage' => 'onboarding',
        ]);

        $this->assertQueryFails(fn (): bool => DB::table('business_plans')->insert([
            'title' => 'Ownerless plan',
            'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
            'status' => BusinessPlan::STATUS_DRAFT,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->assertQueryFails(fn (): bool => DB::table('business_plans')->insert([
            'client_id' => $engagement->client_id,
            'entrepreneur_profile_id' => $profile->id,
            'dd_engagement_id' => $engagement->id,
            'title' => 'Dual owner plan',
            'source_type' => BusinessPlan::SOURCE_DUE_DILIGENCE,
            'status' => BusinessPlan::STATUS_DRAFT,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddEngagement(string $advisorEmail = 'plan-dd-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => '9429000000111',
            'legal_name' => 'Buyer Holdings Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );

        $engagement = app(DdOnboarding::class)->start(
            buyer: $client,
            advisor: $advisor,
            conflict: $conflict,
            targetName: 'Target Supplies Limited',
            targetDetails: [
                'nzbn' => '9429000000999',
                'industry' => 'Distribution',
            ],
        );

        return [$advisor, $engagement];
    }

    private function completeWorkstreams(DdEngagement $engagement, User $advisor): void
    {
        foreach (array_keys(DataRoom::WORKSTREAMS) as $workstream) {
            $this->dataRoomItem($engagement, $workstream);
        }

        app(DdWorkstreamRunner::class)->runAll($engagement, $advisor);
    }

    private function dataRoomItem(DdEngagement $engagement, string $workstream): DdDataRoomItem
    {
        $document = Document::query()->create([
            'client_id' => $engagement->client_id,
            'category' => Document::CATEGORY_DD_ARTIFACT,
            'original_filename' => "{$workstream}.txt",
            'stored_path' => 'dd-plan-builder/'.Str::uuid().".{$workstream}.txt",
            'byte_size' => 128,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $workstream),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $engagement->client_id,
            'claim_source' => 'dd_plan_fixture',
            'context_hash' => hash('sha256', $document->id.$workstream),
            'claim_text' => "The {$workstream} evidence supports the DD plan.",
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
            'confidence' => 0.9300,
            'verified_at' => now(),
        ]);

        return DdDataRoomItem::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'document_id' => $document->id,
            'workstream' => $workstream,
            'folder' => 'general',
            'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
            'source' => DdDataRoomItem::SOURCE_GUEST_UPLOAD,
        ]);
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON business_plans, plan_phases, plan_sections, entrepreneur_profiles TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT business_plan_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT business_plan_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT business_plan_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function assertQueryFails(callable $callback): void
    {
        DB::statement('SAVEPOINT business_plan_owner_xor_probe');

        try {
            $callback();
            $this->fail('Expected business plan owner constraint to reject the insert.');
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT business_plan_owner_xor_probe');
            $this->assertStringContainsString('business_plans_owner_xor', $e->getMessage());
        } finally {
            DB::statement('RELEASE SAVEPOINT business_plan_owner_xor_probe');
        }
    }
}
