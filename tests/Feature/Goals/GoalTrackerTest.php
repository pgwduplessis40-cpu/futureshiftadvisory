<?php

declare(strict_types=1);

namespace Tests\Feature\Goals;

use App\Enums\EngagementType;
use App\Enums\PvType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Milestone;
use App\Models\ProofOfCompletion;
use App\Models\User;
use App\Services\Goals\GoalTracker;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GoalTrackerTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_goals_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        Queue::fake();

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
                DB::statement('REVOKE SELECT ON goals, milestones, milestone_actions, proof_of_completion FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_goal_tracker_links_pv_tracks_actions_and_surfaces_dashboard_progress(): void
    {
        [$advisor, $client] = $this->clientWithTeam();
        $clientUser = $this->clientUserFor($client);
        $tracker = app(GoalTracker::class);

        $goal = $tracker->createGoal($client, [
            'title' => 'Raise recurring advisory value',
            'description' => 'Lift stable recurring revenue.',
            'annual_benefit' => 12000,
            'duration_years' => 2,
        ], $advisor)->load('pvTargetCalculation');

        $milestone = $tracker->createMilestone($goal, [
            'title' => 'Launch monthly management reporting',
            'recommendation_ref' => 'WO-65-test-rec-1',
            'annual_impact' => 6000,
            'duration_years' => 2,
            'due_date' => now()->addMonth()->toDateString(),
        ], $advisor)->load('pvOfImpactCalculation');

        $action = $tracker->createAction($milestone, [
            'title' => 'Configure board pack template',
            'owner_user_id' => $advisor->getKey(),
            'priority' => 'high',
            'due_date' => now()->addWeeks(2)->toDateString(),
        ], $advisor);

        $document = $this->document($client, 'The board pack template is live and was accepted by the client.', 'milestone-proof.txt', $advisor);
        $proof = $tracker->completeWithProof($milestone, $document, [
            'claim' => 'The board pack template is live and accepted.',
        ], $advisor)->load('documentVerification');

        $this->assertSame(PvType::GoalTarget, $goal->pvTargetCalculation?->type);
        $this->assertSame(PvType::MilestoneImpact, $milestone->pvOfImpactCalculation?->type);
        $this->assertGreaterThan(0, $goal->pv_target);
        $this->assertGreaterThan(0, $milestone->pv_of_impact);
        $this->assertSame('high', $action->priority);
        $this->assertSame(ProofOfCompletion::STATUS_VERIFIED, $proof->status);
        $this->assertSame(DocumentVerification::OUTCOME_VERIFIED, $proof->documentVerification?->outcome);
        $this->assertSame(Milestone::STATUS_COMPLETED, $milestone->refresh()->status);
        $this->assertNotNull($milestone->completed_at);
        $this->assertEqualsWithDelta($milestone->pv_of_impact, $tracker->pvRealisedTotal($client), 0.01);

        $dashboard = $tracker->dashboard($client, includeAdvisorActions: true);
        $this->assertEqualsWithDelta($milestone->pv_of_impact, $dashboard['pv_realised_total'], 0.01);
        $this->assertSame(1, $dashboard['active_goals']);
        $this->assertSame('Launch monthly management reporting', $dashboard['goals'][0]['milestones'][0]['title']);
        $this->assertSame('completed', $dashboard['goals'][0]['milestones'][0]['status']);
        $this->assertArrayHasKey('milestone_store_url', $dashboard['goals'][0]);
        $this->assertArrayHasKey('proof_store_url', $dashboard['goals'][0]['milestones'][0]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'milestone.completed',
            'client_id' => $client->id,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.goals.active_goals', 1)
                ->where('client.goals.goals.0.milestones.0.status', 'completed')
                ->where('client.goals.goals.0.milestones.0.actions_count', 1));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('goals.active_goals', 1)
                ->where('goals.goals.0.milestones.0.status', 'completed'));
    }

    public function test_accuracy_discrepancy_blocks_milestone_completion_and_realised_pv(): void
    {
        [$advisor, $client] = $this->clientWithTeam('goals-discrepancy-advisor@example.test');
        $tracker = app(GoalTracker::class);

        $goal = $tracker->createGoal($client, [
            'title' => 'Improve supplier controls',
            'pv_target' => 15000,
        ], $advisor);
        $milestone = $tracker->createMilestone($goal, [
            'title' => 'Supplier reconciliation signed off',
            'pv_of_impact' => 7000,
        ], $advisor);

        $document = $this->document(
            $client,
            'Accuracy discrepancy: the reconciliation does not match the supplied ledger.',
            'discrepancy-proof.txt',
            $advisor,
        );
        $proof = $tracker->completeWithProof($milestone, $document, [
            'claim' => 'The supplier reconciliation has been signed off.',
        ], $advisor)->load('documentVerification');

        $this->assertSame(ProofOfCompletion::STATUS_FLAGGED, $proof->status);
        $this->assertSame(DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY, $proof->documentVerification?->outcome);
        $this->assertSame(Milestone::STATUS_BLOCKED, $milestone->refresh()->status);
        $this->assertNull($milestone->completed_at);
        $this->assertSame(0.0, $tracker->pvRealisedTotal($client));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'milestone.proof_flagged',
            'client_id' => $client->id,
        ]);
    }

    public function test_goal_tables_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Goal RLS assertions require Postgres.');
        }

        $clientA = $this->client('Goal Scope A Limited');
        $clientB = $this->client('Goal Scope B Limited');

        $this->goalFixture($clientA);
        $this->goalFixture($clientB);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        foreach (['goals', 'milestones', 'milestone_actions', 'proof_of_completion'] as $table) {
            $visibleClientIds = $this->withRlsRole(fn (): array => DB::table($table)
                ->pluck('client_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->unique()
                ->values()
                ->all());

            $this->assertContains($clientA->id, $visibleClientIds, "{$table} should expose the scoped client.");
            $this->assertNotContains($clientB->id, $visibleClientIds, "{$table} should hide other clients.");
        }
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithTeam(string $advisorEmail = 'goals-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = $this->client('Goals Client Limited', $advisor);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function clientUserFor(Client $client): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $user;
    }

    private function client(string $name, ?User $createdBy = null): Client
    {
        app(RequestContext::class)->apply('system', [], $createdBy === null ? null : (string) $createdBy->getKey());

        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $createdBy?->getKey(),
        ]);
    }

    private function document(Client $client, string $contents, string $filename, ?User $owner = null): Document
    {
        $path = 'proof/'.Str::uuid().'.txt';
        Storage::disk('secure_local')->put($path, $contents);

        return Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => $filename,
            'stored_path' => $path,
            'byte_size' => strlen($contents),
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $contents),
            'uploaded_by_user_id' => $owner?->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
            'scanner_payload' => ['engine' => 'test-fixture'],
        ]);
    }

    private function goalFixture(Client $client): void
    {
        $tracker = app(GoalTracker::class);
        $goal = $tracker->createGoal($client, [
            'title' => 'Scoped goal for '.$client->legal_name,
            'pv_target' => 10000,
        ]);
        $milestone = $tracker->createMilestone($goal, [
            'title' => 'Scoped milestone',
            'pv_of_impact' => 4000,
        ]);
        $tracker->createAction($milestone, [
            'title' => 'Scoped action',
            'priority' => 'normal',
        ]);
        $tracker->completeWithProof($milestone, $this->document($client, 'The scoped milestone is complete.', 'rls-proof.txt'), [
            'claim' => 'The scoped milestone is complete.',
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
            GRANT SELECT ON goals, milestones, milestone_actions, proof_of_completion TO %1$s;
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
            DB::statement('SAVEPOINT goals_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT goals_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT goals_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
