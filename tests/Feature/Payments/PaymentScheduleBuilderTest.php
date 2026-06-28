<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Payments\ScheduleBuilder;
use App\Services\Pdf\PdfRenderer;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Proposals\SignoffFlow;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class PaymentScheduleBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_payment_schedule_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON payment_schedules FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_schedule_is_created_from_signed_proposal_authority(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers();
        [$proposal, $authority] = $this->signedProposalWithAuthority($client, $advisor, $clientUser);

        $schedule = app(ScheduleBuilder::class)->create($proposal, $authority, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => '2500.00',
            'next_run_at' => '2026-06-01 09:00:00',
        ], $clientUser);

        $this->assertSame($client->id, $schedule->client_id);
        $this->assertSame($proposal->id, $schedule->proposal_id);
        $this->assertSame($authority->id, $schedule->payment_authority_id);
        $this->assertSame(PaymentSchedule::CADENCE_ONE_OFF, $schedule->cadence);
        $this->assertSame('2500.00', $schedule->amount);
        $this->assertSame('NZD', $schedule->currency);
        $this->assertSame(PaymentSchedule::STATUS_ACTIVE, $schedule->status);
        $this->assertSame('2026-06-01T09:00:00+00:00', $schedule->next_run_at?->toIso8601String());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_schedule.created',
            'subject_id' => $schedule->id,
        ]);
    }

    public function test_monthly_retainer_cadence_defaults_to_next_month(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        [$advisor, $client, $clientUser] = $this->clientWithUsers('schedule-monthly-advisor@example.test');
        [$proposal, $authority] = $this->signedProposalWithAuthority($client, $advisor, $clientUser);

        $schedule = app(ScheduleBuilder::class)->create($proposal, $authority, [
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
            'amount' => 1200,
        ], $clientUser);

        $this->assertSame(PaymentSchedule::CADENCE_MONTHLY_RETAINER, $schedule->cadence);
        $this->assertSame('1200.00', $schedule->amount);
        $this->assertSame(1, $schedule->collection_day);
        $this->assertSame('2026-06-01T00:00:00+00:00', $schedule->next_run_at?->toIso8601String());
    }

    public function test_authority_revoke_cascades_to_schedules_and_is_audited(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('schedule-revoke-advisor@example.test');
        [$proposal, $authority] = $this->signedProposalWithAuthority($client, $advisor, $clientUser);
        $builder = app(ScheduleBuilder::class);
        $schedule = $builder->create($proposal, $authority, [
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
            'amount' => 1800,
        ], $clientUser);

        $revoked = $builder->revokeAuthority($authority, $clientUser);

        $this->assertSame(2, $revoked);
        $this->assertSame(PaymentAuthority::STATUS_REVOKED, $authority->refresh()->status);
        $this->assertNotNull($authority->revoked_at);
        $this->assertSame(PaymentSchedule::STATUS_REVOKED, $schedule->refresh()->status);
        $this->assertNotNull($schedule->revoked_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_authority.revoked',
            'subject_id' => $authority->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_schedule.revoked',
            'subject_id' => $schedule->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $builder->create($proposal, $authority, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 900,
        ], $clientUser);
    }

    public function test_schedule_requires_signed_proposal_and_matching_active_authority(): void
    {
        [$advisorA, $clientA, $clientUserA] = $this->clientWithUsers('schedule-required-a@example.test', 'Schedule Required A Limited');
        [$advisorB, $clientB, $clientUserB] = $this->clientWithUsers('schedule-required-b@example.test', 'Schedule Required B Limited');
        [$awaitingProposal, $authorityA] = $this->awaitingSignatureProposalWithAuthority($clientA, $advisorA, $clientUserA);
        [$signedProposalB, $authorityB] = $this->signedProposalWithAuthority($clientB, $advisorB, $clientUserB);
        $builder = app(ScheduleBuilder::class);

        try {
            $builder->create($awaitingProposal, $authorityA, [
                'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
                'amount' => 1000,
            ], $clientUserA);
            $this->fail('Awaiting-signature proposals must not receive schedules.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('signed proposal', $e->getMessage());
        }

        try {
            $builder->create($signedProposalB, $authorityA, [
                'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
                'amount' => 1000,
            ], $clientUserB);
            $this->fail('Authorities must not be reused across proposals.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('belong to the signed proposal', $e->getMessage());
        }

        $this->assertDatabaseMissing('payment_schedules', [
            'proposal_id' => $awaitingProposal->id,
        ]);
        $this->assertDatabaseHas('payment_schedules', [
            'payment_authority_id' => $authorityB->id,
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
            'collection_day' => 1,
        ]);
    }

    public function test_payment_schedules_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Payment schedule RLS assertions require Postgres.');
        }

        [$advisorA, $clientA, $clientUserA] = $this->clientWithUsers('schedule-rls-a@example.test', 'Payment Scope A Limited');
        [$advisorB, $clientB, $clientUserB] = $this->clientWithUsers('schedule-rls-b@example.test', 'Payment Scope B Limited');

        [$proposalA, $authorityA] = $this->signedProposalWithAuthority($clientA, $advisorA, $clientUserA);
        [$proposalB, $authorityB] = $this->signedProposalWithAuthority($clientB, $advisorB, $clientUserB);

        app(ScheduleBuilder::class)->create($proposalA, $authorityA, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 1500,
        ], $clientUserA);
        app(ScheduleBuilder::class)->create($proposalB, $authorityB, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 1600,
        ], $clientUserB);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleClientIds = $this->withRlsRole(fn (): array => DB::table('payment_schedules')
            ->pluck('client_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all());

        $this->assertContains($clientA->id, $visibleClientIds);
        $this->assertNotContains($clientB->id, $visibleClientIds);
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithUsers(string $advisorEmail = 'schedule-advisor@example.test', string $clientName = 'Schedule Client Limited'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        foreach ([[$advisor, 'lead_advisor'], [$clientUser, 'primary_contact']] as [$user, $role]) {
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $user->getKey(),
                'role' => $role,
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }

        return [$advisor, $client, $clientUser];
    }

    /**
     * @return array{0: Proposal, 1: PaymentAuthority}
     */
    private function signedProposalWithAuthority(Client $client, User $advisor, User $clientUser): array
    {
        [$proposal, $authority] = $this->awaitingSignatureProposalWithAuthority($client, $advisor, $clientUser);

        app(SignoffFlow::class)->complete($proposal, ProposalSignoffStep::STEP_SIGNATURE, [
            'signature_name' => 'Schedule Signer',
            'accepted' => true,
            'identity_verification' => [
                'password_verified_at' => now()->toIso8601String(),
                'mfa_required' => false,
                'mfa_verified_at' => null,
                'mfa_method' => null,
            ],
            'ip' => '203.0.113.67',
            'user_agent' => 'Schedule feature test',
        ], $clientUser);

        return [$proposal->refresh(), $authority->refresh()];
    }

    /**
     * @return array{0: Proposal, 1: PaymentAuthority}
     */
    private function awaitingSignatureProposalWithAuthority(Client $client, User $advisor, User $clientUser): array
    {
        $proposal = $this->releasedProposal($client, $advisor);
        $flow = app(SignoffFlow::class);

        $flow->complete($proposal, ProposalSignoffStep::STEP_REVIEW, [], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_INSURANCE_CONSENT, [
            'election' => Consent::ELECTION_OPT_IN,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_COACH_CONSENT, [
            'election' => Consent::ELECTION_OPT_OUT,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD, [
            'type' => PaymentAuthority::TYPE_CARD,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'collection_day' => 1,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_AUTHORITY, [
            'fixture_token' => 'schedule-authority-token',
        ], $clientUser);

        $authority = PaymentAuthority::query()
            ->where('proposal_id', $proposal->getKey())
            ->where('status', PaymentAuthority::STATUS_ACTIVE)
            ->sole();

        return [$proposal->refresh(), $authority];
    }

    private function releasedProposal(Client $client, User $advisor): Proposal
    {
        $builder = app(ProposalBuilder::class);
        $proposal = $builder->generate($client, $this->feeCalculation($client), [
            'consents' => [
                Consent::TYPE_INSURANCE_REFERRAL => Consent::ELECTION_UNDECIDED,
                Consent::TYPE_COACH_REFERRAL => Consent::ELECTION_UNDECIDED,
            ],
        ], [
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return $builder->release($proposal, $advisor);
    }

    private function feeCalculation(Client $client): FeeCalculation
    {
        return FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 8000,
            'suggested_mid' => 10000,
            'suggested_high' => 12000,
            'improvement_pv_total' => 25000,
            'risk_cost_pv_total' => 3000,
            'roi_ratio' => 2.5,
            'justification' => [
                'services' => [
                    ['name' => 'Schedule fixture advisory', 'line_total' => 10000],
                ],
            ],
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
            GRANT SELECT ON payment_schedules TO %1$s;
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
            DB::statement('SAVEPOINT payment_schedule_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT payment_schedule_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT payment_schedule_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
