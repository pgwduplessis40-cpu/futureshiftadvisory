<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\ClientFunderAlert;
use App\Models\ClientFunderRecord;
use App\Models\ClientTeamMember;
use App\Models\Funder;
use App\Models\LearningUpdate;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Npo\FunderRegistry;
use App\Services\Npo\NpoFunderMonitor;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class NpoFunderDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_npo_funders_rls_app';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');
            if ($this->rlsRoleExists()) {
                DB::statement('REVOKE SELECT ON client_funder_records, funders FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_registry_mutations_require_approved_layer_34_candidate(): void
    {
        try {
            Funder::query()->create($this->funderPayload(['name' => 'Direct Write Foundation']));
            $this->fail('Direct funder registry writes should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Layer 34', $exception->getMessage());
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::transaction(function (): void {
                    DB::table('funders')->insert([
                        'id' => (string) Str::uuid(),
                        'name' => 'Direct SQL Foundation',
                        'type' => Funder::TYPE_PHILANTHROPIC,
                        'funding_windows' => json_encode([], JSON_THROW_ON_ERROR),
                        'criteria' => json_encode([], JSON_THROW_ON_ERROR),
                        'reporting_requirements' => json_encode([], JSON_THROW_ON_ERROR),
                        'renewal_intelligence' => json_encode([], JSON_THROW_ON_ERROR),
                        'last_verified_at' => now(),
                        'source_learning_update_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                $this->fail('Ungoverned SQL funder registry writes should be rejected.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Layer 34', $exception->getMessage());
            }
        }

        $approved = $this->learningUpdate(LearningUpdate::STATUS_APPROVED);
        $funder = app(FunderRegistry::class)->upsertFromLearningUpdate($approved, $this->funderPayload([
            'name' => 'Community Outcomes Fund',
            'last_verified_at' => now()->subMonths(13)->toIso8601String(),
        ]));

        $this->assertSame('Community Outcomes Fund', $funder->name);
        $this->assertSame($approved->id, $funder->source_learning_update_id);
        $this->assertTrue($funder->needsVerification());
    }

    public function test_deadline_alerts_fire_at_each_threshold_and_overdue_daily(): void
    {
        Carbon::setTestNow('2026-05-27 09:00:00');
        [, $client, $engagement] = $this->npoClient();
        $funder = $this->funder('Threshold Fund');

        $this->record($client, $engagement, $funder, ['reporting_deadline' => now()->addDays(30)->toDateString()]);
        $this->record($client, $engagement, $funder, ['reporting_deadline' => now()->addDays(7)->toDateString()]);
        $this->record($client, $engagement, $funder, ['reporting_deadline' => now()->subDay()->toDateString()]);
        $this->record($client, $engagement, $funder, ['next_application_window_opens_at' => now()->addDays(60)->toDateString()]);
        $this->record($client, $engagement, $funder, [
            'next_application_window_opens_at' => now()->subDay()->toDateString(),
            'next_application_window_closes_at' => now()->addDays(10)->toDateString(),
        ]);
        $this->record($client, $engagement, $funder, ['grant_expiry_at' => now()->addDays(60)->toDateString()]);

        app(NpoFunderMonitor::class)->syncAlerts([$client->id], now());

        $this->assertDatabaseHas('client_funder_alerts', ['type' => ClientFunderAlert::TYPE_REPORT_DUE_30]);
        $this->assertDatabaseHas('client_funder_alerts', ['type' => ClientFunderAlert::TYPE_REPORT_DUE_7]);
        $this->assertDatabaseHas('client_funder_alerts', ['type' => ClientFunderAlert::TYPE_REPORT_OVERDUE]);
        $this->assertDatabaseHas('client_funder_alerts', ['type' => ClientFunderAlert::TYPE_APPLICATION_WINDOW_60]);
        $this->assertDatabaseHas('client_funder_alerts', ['type' => ClientFunderAlert::TYPE_APPLICATION_WINDOW_OPEN]);
        $this->assertDatabaseHas('client_funder_alerts', ['type' => ClientFunderAlert::TYPE_GRANT_EXPIRY_60]);
        $this->assertSame(6, ClientFunderAlert::query()->count());

        app(NpoFunderMonitor::class)->syncAlerts([$client->id], now()->addDay());

        $this->assertSame(2, ClientFunderAlert::query()
            ->where('type', ClientFunderAlert::TYPE_REPORT_OVERDUE)
            ->count());
    }

    public function test_funder_records_surface_on_client_profile_and_advisor_dashboard(): void
    {
        Carbon::setTestNow('2026-05-27 09:00:00');
        [$advisor, $client, $engagement] = $this->npoClient();
        $funder = $this->funder('Profile Fund');
        $this->record($client, $engagement, $funder, [
            'grant_amount' => 120000,
            'reporting_deadline' => now()->addDays(7)->toDateString(),
            'renewal_probability' => 70,
        ]);

        app(NpoFunderMonitor::class)->syncAlerts([$client->id], now());

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.npo_funding.records.0.funder_name', 'Profile Fund')
                ->where('client.npo_funding.alerts.0.type', ClientFunderAlert::TYPE_REPORT_DUE_7)
                ->where('client.npo_funding.concentration.largest_funder_ratio', 1)
                ->where('client.npo_funding.concentration.risk_level', 'high'));

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('npoFunding.summary.active_records', 1)
                ->where('npoFunding.summary.active_alerts', 1)
                ->where('npoFunding.alerts.0.client_name', 'NPO Funder Trust')
                ->where('npoFunding.alerts.0.funder_name', 'Profile Fund'));
    }

    public function test_funder_concentration_data_feeds_value_calculation_inputs(): void
    {
        [, $client, $engagement] = $this->npoClient();
        $anchor = $this->funder('Anchor Funder');
        $secondary = $this->funder('Secondary Funder');
        $this->record($client, $engagement, $anchor, ['grant_amount' => 75_000]);
        $this->record($client, $engagement, $secondary, ['grant_amount' => 25_000]);

        $concentration = app(NpoFunderMonitor::class)->concentrationForClient($client);

        $this->assertSame(100000.0, $concentration['total_active_amount']);
        $this->assertSame(0.75, $concentration['largest_funder_ratio']);
        $this->assertSame('high', $concentration['risk_level']);
        $this->assertSame('client_funder_records', $concentration['source']);
    }

    public function test_client_funder_records_are_client_scoped_under_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Funder RLS assertions require Postgres.');
        }

        [$advisor, $client, $engagement] = $this->npoClient('rls-advisor@example.test', 'NPO RLS Trust');
        [, $otherClient, $otherEngagement] = $this->npoClient('other-rls-advisor@example.test', 'Other NPO Trust');
        $funder = $this->funder('RLS Fund');
        $this->record($client, $engagement, $funder);
        $this->record($otherClient, $otherEngagement, $funder);
        $this->prepareRlsRole();

        app(RequestContext::class)->apply($advisor->fsaRole(), [$client->id], (string) $advisor->getKey());
        DB::statement('SET ROLE '.self::RLS_APP_ROLE);

        $rows = DB::table('client_funder_records')->pluck('client_id')->all();

        DB::statement('RESET ROLE');

        $this->assertSame([$client->id], $rows);
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(string $advisorEmail = 'npo-funder-advisor@example.test', string $clientName = 'NPO Funder Trust'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
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
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);

        return [$advisor, $client, $engagement];
    }

    private function funder(string $name): Funder
    {
        return app(FunderRegistry::class)->upsertFromLearningUpdate(
            $this->learningUpdate(LearningUpdate::STATUS_APPROVED),
            $this->funderPayload(['name' => $name]),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function record(Client $client, NpoEngagement $engagement, Funder $funder, array $overrides = []): ClientFunderRecord
    {
        /** @var ClientFunderRecord $record */
        $record = ClientFunderRecord::query()->create(array_merge([
            'client_id' => $client->id,
            'npo_engagement_id' => $engagement->id,
            'funder_id' => $funder->id,
            'grant_name' => 'Community grant',
            'grant_amount' => 50_000,
            'currency' => 'NZD',
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->addYear()->toDateString(),
            'conditions' => ['restricted' => true],
            'reporting_deadline' => null,
            'next_application_window_opens_at' => null,
            'next_application_window_closes_at' => null,
            'grant_expiry_at' => null,
            'renewal_probability' => 50,
            'history' => [['event' => 'created']],
        ], $overrides));

        return $record;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function funderPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Community Fund',
            'type' => Funder::TYPE_PHILANTHROPIC,
            'funding_windows' => [['opens' => '2026-07-01', 'closes' => '2026-08-15']],
            'criteria' => ['region' => 'Aotearoa', 'focus' => 'community outcomes'],
            'reporting_requirements' => ['six_month_report' => true],
            'renewal_intelligence' => ['renewal_weight' => 0.7],
            'last_verified_at' => now()->toIso8601String(),
            'source_learning_update_id' => null,
        ], $overrides);
    }

    private function learningUpdate(string $status): LearningUpdate
    {
        return LearningUpdate::query()->create([
            'layer_id' => LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES,
            'source' => ['type' => 'npo_funder_registry_test'],
            'summary' => 'Update funder registry',
            'proposed_change' => [
                'action' => 'update_funder_registry',
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'surface' => 'funder_registry',
                'tenant_scope' => 'global',
            ],
            'clients_affected' => 0,
            'magnitude' => 'low',
            'confidence' => 0.8,
            'evidence' => ['source' => 'test'],
            'status' => $status,
        ]);
    }

    private function prepareRlsRole(): void
    {
        if ($this->rlsRoleExists()) {
            DB::statement('REVOKE SELECT ON client_funder_records, funders FROM '.self::RLS_APP_ROLE);
            DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
            DB::statement('DROP ROLE '.self::RLS_APP_ROLE);
        }

        DB::statement('CREATE ROLE '.self::RLS_APP_ROLE.' NOLOGIN NOBYPASSRLS');
        DB::statement('GRANT USAGE ON SCHEMA public TO '.self::RLS_APP_ROLE);
        DB::statement('GRANT SELECT ON client_funder_records, funders TO '.self::RLS_APP_ROLE);
    }

    private function rlsRoleExists(): bool
    {
        return DB::table('pg_roles')
            ->where('rolname', self::RLS_APP_ROLE)
            ->exists();
    }
}
