<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\Payment;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\ScheduleBuilder;
use App\Services\Pdf\PdfRenderer;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Proposals\SignoffFlow;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PaymentProcessingTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_payment_processing_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        Mail::fake();

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });

        Config::set('integrations.payments.primary_gateway', PaymentAuthority::GATEWAY_STRIPE);
        Config::set('integrations.payments.stripe.live', false);
        Config::set('integrations.payments.windcave.live', false);
        Config::set('integrations.payments.max_attempts', 2);
        Config::set('integrations.payments.retry_delay_minutes', 45);

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
                DB::statement('REVOKE SELECT ON payments, receipts FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_due_schedule_charges_records_payment_and_generates_receipt(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        [$advisor, $client, $clientUser] = $this->clientWithUsers();
        [$proposal, $authority] = $this->signedProposalWithAuthority($client, $advisor, $clientUser);
        $schedule = $this->schedule($proposal, $authority, $clientUser, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 1500,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('payments:process-scheduled')
            ->expectsOutput('Processed 1 due schedule(s): 1 succeeded, 0 retrying, 0 failed, 1 receipt(s).')
            ->assertExitCode(0);

        $payment = Payment::query()->firstOrFail();
        $receipt = $payment->receipt()->firstOrFail();

        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertSame(PaymentAuthority::GATEWAY_STRIPE, $payment->gateway);
        $this->assertStringStartsWith('ch_stripe_', (string) $payment->gateway_ref);
        $this->assertSame(PaymentSchedule::STATUS_COMPLETED, $schedule->refresh()->status);
        $this->assertSame(ProposalStatus::Signed, $proposal->refresh()->status);
        $this->assertNotNull($receipt->receipt_sha256_envelope);
        Storage::disk('secure_local')->assertExists($receipt->receipt_path);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.succeeded',
            'subject_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.receipt_generated',
            'subject_id' => $payment->id,
        ]);
    }

    public function test_failed_first_charge_notifies_advisor_and_client_without_reverting_signed_status(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        [$advisor, $client, $clientUser] = $this->clientWithUsers('payment-fail-advisor@example.test');
        [$proposal, $authority] = $this->signedProposalWithAuthority($client, $advisor, $clientUser);
        $schedule = $this->schedule($proposal, $authority, $clientUser, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 1500,
            'next_run_at' => now()->subMinute(),
        ]);
        $result = app(PaymentProcessor::class)->processDue(now(), chargeMetadata: [
            'fixture_fail_stripe' => true,
            'fixture_fail_windcave' => true,
        ]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(['scanned' => 1, 'succeeded' => 0, 'retrying' => 1, 'failed' => 0, 'receipts' => 0], $result);
        $this->assertSame(Payment::STATUS_RETRYING, $payment->status);
        $this->assertSame(1, $payment->attempt);
        $this->assertSame(ProposalStatus::Signed, $proposal->refresh()->status);
        $this->assertSame(PaymentSchedule::STATUS_ACTIVE, $schedule->refresh()->status);
        $this->assertTrue($schedule->next_run_at?->equalTo(now()->addMinutes(45)));
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $advisor->getKey(),
            'type' => 'payment.failed',
            'urgency' => 'urgent',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $clientUser->getKey(),
            'type' => 'payment.failed',
            'urgency' => 'urgent',
        ]);
    }

    public function test_retry_attempt_can_failover_and_succeed_with_receipt(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        [$advisor, $client, $clientUser] = $this->clientWithUsers('payment-retry-advisor@example.test');
        [$proposal, $authority] = $this->signedProposalWithAuthority($client, $advisor, $clientUser);
        $schedule = $this->schedule($proposal, $authority, $clientUser, [
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
            'amount' => 900,
            'collection_day' => 1,
            'next_run_at' => now()->startOfMonth(),
        ]);
        Payment::query()->create([
            'client_id' => $client->getKey(),
            'payment_schedule_id' => $schedule->getKey(),
            'payment_authority_id' => $authority->getKey(),
            'amount' => $schedule->amount,
            'currency' => $schedule->currency,
            'status' => Payment::STATUS_RETRYING,
            'attempt' => 1,
            'failed_reason' => 'Initial fixture failure',
            'processed_at' => now()->subHour(),
        ]);

        $result = app(PaymentProcessor::class)->processDue(now(), chargeMetadata: [
            'fixture_fail_stripe' => true,
        ]);

        $payment = Payment::query()->where('attempt', 2)->firstOrFail();
        $this->assertSame(['scanned' => 1, 'succeeded' => 1, 'retrying' => 0, 'failed' => 0, 'receipts' => 1], $result);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertSame(PaymentAuthority::GATEWAY_WINDCAVE, $payment->gateway);
        $this->assertSame(PaymentAuthority::GATEWAY_STRIPE, $payment->failover_from);
        $this->assertNotNull($payment->receipt()->first());
        $this->assertSame(PaymentSchedule::STATUS_ACTIVE, $schedule->refresh()->status);
        $this->assertTrue($schedule->next_run_at?->greaterThan(now()));
        $this->assertSame(ProposalStatus::Signed, $proposal->refresh()->status);
    }

    public function test_payments_and_receipts_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Payment processing RLS assertions require Postgres.');
        }

        [$advisorA, $clientA, $clientUserA] = $this->clientWithUsers('payment-rls-a@example.test', 'Payment Process A Limited');
        [$advisorB, $clientB, $clientUserB] = $this->clientWithUsers('payment-rls-b@example.test', 'Payment Process B Limited');
        [$proposalA, $authorityA] = $this->signedProposalWithAuthority($clientA, $advisorA, $clientUserA);
        [$proposalB, $authorityB] = $this->signedProposalWithAuthority($clientB, $advisorB, $clientUserB);
        $this->schedule($proposalA, $authorityA, $clientUserA, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 500,
            'next_run_at' => now()->subMinute(),
        ]);
        $this->schedule($proposalB, $authorityB, $clientUserB, [
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 600,
            'next_run_at' => now()->subMinute(),
        ]);

        app(PaymentProcessor::class)->processDue(now());
        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        foreach (['payments', 'receipts'] as $table) {
            $visibleClientIds = $this->withRlsRole(fn (): array => DB::table($table)
                ->pluck('client_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->unique()
                ->values()
                ->all());

            $this->assertContains($clientA->id, $visibleClientIds);
            $this->assertNotContains($clientB->id, $visibleClientIds);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function schedule(Proposal $proposal, PaymentAuthority $authority, User $actor, array $input): PaymentSchedule
    {
        return app(ScheduleBuilder::class)->create($proposal, $authority, $input, $actor);
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithUsers(string $advisorEmail = 'payment-processing-advisor@example.test', string $clientName = 'Payment Processing Client Limited'): array
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
            'fixture_token' => 'payment-processing-authority-token',
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_SIGNATURE, [
            'signature_name' => 'Payment Processing Signer',
            'accepted' => true,
            'ip' => '203.0.113.69',
            'user_agent' => 'Payment processing feature test',
            'identity_verification' => [
                'password_verified_at' => now()->toIso8601String(),
                'mfa_required' => false,
                'mfa_verified_at' => null,
                'mfa_method' => null,
            ],
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
                    ['name' => 'Payment processing fixture advisory', 'line_total' => 10000],
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
            GRANT SELECT ON payments, receipts TO %1$s;
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
            DB::statement('SAVEPOINT payment_processing_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT payment_processing_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT payment_processing_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
