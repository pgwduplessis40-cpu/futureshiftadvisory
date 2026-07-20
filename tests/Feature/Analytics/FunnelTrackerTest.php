<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Console\Commands\RunFunnelAnalyticsLayer;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FunnelEvent;
use App\Models\LearningUpdate;
use App\Models\User;
use App\Services\Analytics\FunnelTracker;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class FunnelTrackerTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_funnel_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
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
                DB::statement('REVOKE SELECT ON funnel_events FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_tracker_records_entry_completion_and_abandonment(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor();
        $tracker = app(FunnelTracker::class);

        $entered = $tracker->enter(FunnelEvent::FLOW_ONBOARDING, 'welcome', $client, $advisor, now()->subHours(2));
        $completed = $tracker->complete(FunnelEvent::FLOW_ONBOARDING, 'welcome', $client, $advisor);
        $tracker->enter(FunnelEvent::FLOW_ONBOARDING, 'goals', $client, $advisor, now()->subDays(2));
        $abandoned = $tracker->abandonStaleEntries(now()->subDay());

        $this->assertSame($entered->id, $completed->id);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(1, $abandoned);
        $this->assertDatabaseHas('funnel_events', [
            'flow' => FunnelEvent::FLOW_ONBOARDING,
            'step' => 'goals',
            'abandoned' => true,
        ]);
    }

    public function test_drop_off_summary_is_scoped_on_advisor_dashboard(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('funnel-advisor@example.test', 'Scoped Funnel Limited');
        [$otherAdvisor, $otherClient] = $this->clientWithAdvisor('other-funnel-advisor@example.test', 'Other Funnel Limited');
        $tracker = app(FunnelTracker::class);

        $tracker->complete(FunnelEvent::FLOW_ONBOARDING, 'welcome', $client, $advisor);
        $tracker->enter(FunnelEvent::FLOW_ONBOARDING, 'goals', $client, $advisor, now()->subDays(2));
        $tracker->abandonStaleEntries(now()->subDay());
        $tracker->complete(FunnelEvent::FLOW_ONBOARDING, 'welcome', $otherClient, $otherAdvisor);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->loadDeferredProps('advisor-signals', fn (Assert $page): Assert => $page
                    ->where('funnelAnalytics.summary.events', 2)
                    ->where('funnelAnalytics.summary.abandoned', 1)
                    ->where('funnelAnalytics.steps.0.flow', FunnelEvent::FLOW_ONBOARDING)
                    ->where('funnelAnalytics.steps.0.dropped_count', 1)
                    ->where('funnelAnalytics.steps.0.returned_count', 0)
                    ->where('funnelAnalytics.steps.0.dropped_clients.0.id', $client->id)));
    }

    public function test_step_summary_includes_dropped_clients_and_same_step_returns(): void
    {
        [$advisor, $returnedClient] = $this->clientWithAdvisor('returned-funnel@example.test', 'Returned Funnel Limited');
        [, $stillDroppedClient] = $this->clientWithAdvisor('still-dropped-funnel@example.test', 'Still Dropped Limited');
        [, $differentStepClient] = $this->clientWithAdvisor('different-step-funnel@example.test', 'Different Step Limited');
        $tracker = app(FunnelTracker::class);
        $base = now()->subDays(3);

        $tracker->enter(FunnelEvent::FLOW_ONBOARDING, 'goals', $returnedClient, $advisor, $base);
        $tracker->enter(FunnelEvent::FLOW_ONBOARDING, 'goals', $stillDroppedClient, $advisor, $base->copy()->addHour());
        $tracker->enter(FunnelEvent::FLOW_ONBOARDING, 'goals', $differentStepClient, $advisor, $base->copy()->addHours(2));
        $tracker->abandonStaleEntries($base->copy()->addDay());
        $tracker->complete(FunnelEvent::FLOW_ONBOARDING, 'goals', $returnedClient, $advisor, $base->copy()->addDays(2));
        $tracker->complete(FunnelEvent::FLOW_ONBOARDING, 'welcome', $differentStepClient, $advisor, $base->copy()->addDays(2));

        $summary = $tracker->summary([
            (string) $returnedClient->getKey(),
            (string) $stillDroppedClient->getKey(),
            (string) $differentStepClient->getKey(),
        ]);
        $step = collect($summary['steps'])
            ->first(fn (array $row): bool => $row['flow'] === FunnelEvent::FLOW_ONBOARDING && $row['step'] === 'goals');

        $this->assertIsArray($step);
        $this->assertSame(3, $step['dropped_count']);
        $this->assertSame(1, $step['returned_count']);
        $this->assertNotNull($step['last_dropped_at']);
        $this->assertCount(3, $step['dropped_clients']);
        $this->assertTrue(collect($step['dropped_clients'])->contains(
            fn (array $client): bool => $client['id'] === $returnedClient->id
                && $client['show_url'] === route('advisor.clients.show', $returnedClient, absolute: false),
        ));
    }

    public function test_monthly_suggestion_candidate_is_queued_without_auto_implementation(): void
    {
        [$advisor, $client] = $this->clientWithAdvisor('candidate-funnel-advisor@example.test');
        $tracker = app(FunnelTracker::class);

        foreach (range(1, 3) as $index) {
            $user = User::factory()->withTwoFactor()->create([
                'email' => "funnel-user-{$index}@example.test",
                'user_type' => User::TYPE_CLIENT_TEAM,
                'primary_role' => User::TYPE_CLIENT_TEAM,
            ]);
            $tracker->enter(FunnelEvent::FLOW_QUESTIONNAIRE, 'submit', $client, $user, now()->subDays(3));
        }

        $this->artisan(RunFunnelAnalyticsLayer::class, [
            '--minimum-entered' => 3,
            '--window-end' => now()->toIso8601String(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => FunnelTracker::LAYER_ID,
            'candidates_created' => 1,
        ]);
        $this->assertDatabaseHas('learning_updates', [
            'layer_id' => FunnelTracker::LAYER_ID,
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);
        $this->assertDatabaseCount('learning_update_implementations', 0);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'funnel_analytics_layer.ran',
        ]);

        $this->assertTrue($advisor->exists);
    }

    public function test_funnel_events_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Funnel RLS assertions require Postgres.');
        }

        [, $clientA] = $this->clientWithAdvisor('funnel-rls-a@example.test', 'Funnel A Limited');
        [, $clientB] = $this->clientWithAdvisor('funnel-rls-b@example.test', 'Funnel B Limited');
        $eventA = $this->storedEvent($clientA, 'welcome');
        $eventB = $this->storedEvent($clientB, 'goals');

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleIds = $this->withRlsRole(fn (): array => DB::table('funnel_events')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($eventA->id, $visibleIds);
        $this->assertNotContains($eventB->id, $visibleIds);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithAdvisor(string $email = 'funnel-advisor@example.test', string $clientName = 'Funnel Client Limited'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function storedEvent(Client $client, string $step): FunnelEvent
    {
        return FunnelEvent::query()->create([
            'flow' => FunnelEvent::FLOW_ONBOARDING,
            'step' => $step,
            'client_id' => $client->id,
            'entered_at' => now(),
            'abandoned' => false,
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
            GRANT SELECT ON funnel_events TO %1$s;
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
            DB::statement('SAVEPOINT funnel_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT funnel_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT funnel_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
