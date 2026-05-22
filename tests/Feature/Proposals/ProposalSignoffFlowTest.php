<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\PaymentAuthority;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Pdf\PdfRenderer;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Proposals\SignoffFlow;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class ProposalSignoffFlowTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_signoff_rls_app';

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
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON proposal_signoff_steps, payment_authorities FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_seven_step_signoff_enforces_order_and_reaches_awaiting_then_signed(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers();
        $proposal = $this->releasedProposal($client, $advisor);
        $flow = app(SignoffFlow::class);

        try {
            $flow->complete($proposal, ProposalSignoffStep::STEP_SIGNATURE, [
                'signature_name' => 'Client Signer',
                'accepted' => true,
            ], $clientUser);
            $this->fail('Signature should not be accepted before previous sign-off steps.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('review', $e->getMessage());
        }

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
        ], $clientUser);

        $this->assertSame(ProposalStatus::Released, $proposal->refresh()->status);

        $flow->complete($proposal, ProposalSignoffStep::STEP_AUTHORITY, [
            'fixture_token' => 'fixture-authority-token',
        ], $clientUser);

        $proposal = $proposal->refresh();
        $this->assertSame(ProposalStatus::AwaitingSignature, $proposal->status);
        $this->assertNotNull($proposal->awaiting_signature_at);
        $this->assertDatabaseHas('payment_authorities', [
            'proposal_id' => $proposal->id,
            'status' => PaymentAuthority::STATUS_ACTIVE,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
        ]);

        $flow->complete($proposal, ProposalSignoffStep::STEP_SIGNATURE, [
            'signature_name' => 'Client Signer',
            'accepted' => true,
            'ip' => '203.0.113.10',
            'user_agent' => 'Feature test',
        ], $clientUser);

        $proposal = $proposal->refresh();
        $this->assertSame(ProposalStatus::Signed, $proposal->status);
        $this->assertNotNull($proposal->signed_at);
        $this->assertSame($clientUser->getKey(), $proposal->signed_by_user_id);
        $this->assertNotNull($proposal->signature_evidence_sha256_envelope);
        $this->assertIsArray($proposal->signature_envelope_meta);
        Storage::disk('secure_local')->assertExists($proposal->signature_evidence_path);

        $flow->complete($proposal, ProposalSignoffStep::STEP_CONFIRMATION, [], $clientUser);

        $this->assertSame(7, $proposal->signoffSteps()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.signoff_step_completed',
            'subject_id' => $proposal->id,
        ]);
    }

    public function test_consent_elections_can_be_captured_and_revoked_in_signoff(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-consent-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor);
        $flow = app(SignoffFlow::class);

        $flow->complete($proposal, ProposalSignoffStep::STEP_REVIEW, [], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_INSURANCE_CONSENT, [
            'election' => Consent::ELECTION_OPT_IN,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_INSURANCE_CONSENT, [
            'election' => Consent::ELECTION_OPT_OUT,
        ], $clientUser);

        $this->assertDatabaseHas('consents', [
            'proposal_id' => $proposal->id,
            'type' => Consent::TYPE_INSURANCE_REFERRAL,
            'election' => Consent::ELECTION_OPT_OUT,
            'captured_by_user_id' => $clientUser->getKey(),
        ]);
        $this->assertSame(2, $proposal->signoffSteps()->count());
    }

    public function test_authority_capture_failure_keeps_proposal_pre_signature(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-failure-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor);
        $flow = app(SignoffFlow::class);

        $this->completeToPaymentMethod($flow, $proposal, $clientUser);

        try {
            $flow->complete($proposal, ProposalSignoffStep::STEP_AUTHORITY, [
                'fixture_token' => 'fail',
            ], $clientUser);
            $this->fail('Fixture gateway failure should bubble out of authority capture.');
        } catch (PaymentGatewayException $e) {
            $this->assertStringContainsString('Stripe fixture', $e->getMessage());
        }

        $this->assertSame(ProposalStatus::Released, $proposal->refresh()->status);
        $this->assertNull($proposal->awaiting_signature_at);
        $this->assertDatabaseMissing('proposal_signoff_steps', [
            'proposal_id' => $proposal->id,
            'step' => ProposalSignoffStep::STEP_AUTHORITY,
        ]);
        $this->assertDatabaseMissing('payment_authorities', [
            'proposal_id' => $proposal->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.authority_capture_failed',
            'subject_id' => $proposal->id,
        ]);
    }

    public function test_raw_card_numbers_are_rejected_before_persistence(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-pan-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor);
        $flow = app(SignoffFlow::class);

        $this->completeToPaymentMethod($flow, $proposal, $clientUser);

        try {
            $flow->complete($proposal, ProposalSignoffStep::STEP_AUTHORITY, [
                'card_number' => '4242424242424242',
            ], $clientUser);
            $this->fail('Raw PAN payload should be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Raw card numbers', $e->getMessage());
        }

        $this->assertDatabaseMissing('payment_authorities', [
            'proposal_id' => $proposal->id,
        ]);
    }

    public function test_portal_dashboard_and_signoff_page_surface_released_proposal(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-portal-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->where('proposals.0.id', $proposal->id)
                ->where('proposals.0.status', ProposalStatus::Released->value));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.proposals.signoff.show', $proposal))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/ProposalSignoff')
                ->where('proposal.id', $proposal->id)
                ->where('signoff.next_step', ProposalSignoffStep::STEP_REVIEW));
    }

    public function test_signoff_tables_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Sign-off RLS assertions require Postgres.');
        }

        [$advisorA, $clientA, $clientUserA] = $this->clientWithUsers('signoff-rls-a@example.test', 'Signoff Scope A Limited');
        [$advisorB, $clientB, $clientUserB] = $this->clientWithUsers('signoff-rls-b@example.test', 'Signoff Scope B Limited');

        $this->completeToAuthority(app(SignoffFlow::class), $this->releasedProposal($clientA, $advisorA), $clientUserA);
        $this->completeToAuthority(app(SignoffFlow::class), $this->releasedProposal($clientB, $advisorB), $clientUserB);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        foreach (['proposal_signoff_steps', 'payment_authorities'] as $table) {
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
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithUsers(string $advisorEmail = 'signoff-advisor@example.test', string $clientName = 'Signoff Client Limited'): array
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
                    ['name' => 'Sign-off fixture advisory', 'line_total' => 10000],
                ],
            ],
        ]);
    }

    private function completeToPaymentMethod(SignoffFlow $flow, Proposal $proposal, User $clientUser): void
    {
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
        ], $clientUser);
    }

    private function completeToAuthority(SignoffFlow $flow, Proposal $proposal, User $clientUser): Proposal
    {
        $this->completeToPaymentMethod($flow, $proposal, $clientUser);

        return $flow->complete($proposal, ProposalSignoffStep::STEP_AUTHORITY, [
            'fixture_token' => 'rls-token',
        ], $clientUser);
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
            GRANT SELECT ON proposal_signoff_steps, payment_authorities TO %1$s;
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
            DB::statement('SAVEPOINT signoff_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT signoff_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT signoff_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
