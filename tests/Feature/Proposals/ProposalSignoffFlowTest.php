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
use App\Models\IntegrationScope;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
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
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
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
            'collection_day' => 15,
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
            'identity_verification' => [
                'password_verified_at' => now()->toIso8601String(),
                'mfa_required' => false,
                'mfa_verified_at' => null,
                'mfa_method' => null,
            ],
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
        $signedEvidence = Storage::disk('secure_local')->get($proposal->signature_evidence_path);
        $this->assertStringContainsString('Signed proposal certificate', $signedEvidence);
        $this->assertStringContainsString('Collectiondate15thofeachmonth', preg_replace('/\s+/', '', $signedEvidence) ?? '');
        $this->assertDatabaseHas('payment_schedules', [
            'proposal_id' => $proposal->id,
            'payment_authority_id' => PaymentAuthority::query()->where('proposal_id', $proposal->id)->value('id'),
            'cadence' => PaymentSchedule::CADENCE_MONTHLY_RETAINER,
            'collection_day' => 15,
            'amount' => '1666.67',
        ]);

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

    public function test_integration_proposals_skip_referral_consent_steps(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-integration-advisor@example.test');
        $proposal = $this->releasedIntegrationProposal($client, $advisor);
        $flow = app(SignoffFlow::class);

        $payload = $flow->payload($proposal);

        $this->assertSame([
            ProposalSignoffStep::STEP_REVIEW,
            ProposalSignoffStep::STEP_PAYMENT_METHOD,
            ProposalSignoffStep::STEP_AUTHORITY,
            ProposalSignoffStep::STEP_SIGNATURE,
            ProposalSignoffStep::STEP_CONFIRMATION,
        ], collect($payload['steps'])->pluck('step')->all());
        $this->assertSame(ProposalSignoffStep::STEP_REVIEW, $payload['next_step']);

        $flow->complete($proposal, ProposalSignoffStep::STEP_REVIEW, [], $clientUser);
        $this->assertSame(ProposalSignoffStep::STEP_PAYMENT_METHOD, $flow->payload($proposal->refresh())['next_step']);

        $flow->complete($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD, [
            'type' => PaymentAuthority::TYPE_CARD,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'collection_day' => 1,
        ], $clientUser);

        $this->assertFalse($flow->payload($proposal->refresh())['authority_requires_token']);

        $flow->complete($proposal, ProposalSignoffStep::STEP_AUTHORITY, [], $clientUser);
        $this->assertSame(ProposalSignoffStep::STEP_SIGNATURE, $flow->payload($proposal->refresh())['next_step']);
        $this->assertDatabaseHas('payment_authorities', [
            'proposal_id' => $proposal->id,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'status' => PaymentAuthority::STATUS_ACTIVE,
        ]);

        try {
            $flow->complete($proposal, ProposalSignoffStep::STEP_INSURANCE_CONSENT, [
                'election' => Consent::ELECTION_OPT_IN,
            ], $clientUser);
            $this->fail('Integration proposals must not accept referral consent steps.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Step [insurance_consent] is not required for this proposal.', $exception->getMessage());
        }

        $this->actingAsMfa($clientUser)
            ->get(route('portal.proposals.signoff.show', $proposal))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/ProposalSignoff')
                ->has('signoff.steps', 5)
                ->where('signoff.steps.1.step', ProposalSignoffStep::STEP_PAYMENT_METHOD));
    }

    public function test_completed_payment_method_can_be_reopened_before_authority_capture(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-payment-reopen-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor);
        $flow = app(SignoffFlow::class);

        $this->completeToPaymentMethod($flow, $proposal, $clientUser);

        $flow->complete($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD, [
            'type' => PaymentAuthority::TYPE_DIRECT_DEBIT,
            'gateway' => PaymentAuthority::GATEWAY_WINDCAVE,
            'collection_day' => 15,
        ], $clientUser);

        $payload = $flow->payload($proposal->refresh());
        $paymentMethodStep = collect($payload['steps'])
            ->firstWhere('step', ProposalSignoffStep::STEP_PAYMENT_METHOD);

        $this->assertSame(4, $proposal->signoffSteps()->count());
        $this->assertSame(ProposalSignoffStep::STEP_AUTHORITY, $payload['next_step']);
        $this->assertSame([
            'type' => PaymentAuthority::TYPE_DIRECT_DEBIT,
            'gateway' => PaymentAuthority::GATEWAY_WINDCAVE,
            'collection_day' => 15,
        ], $paymentMethodStep['payload']);
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

    public function test_portal_can_start_stripe_card_setup_without_raw_card_details(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-stripe-setup-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor);
        $this->completeToPaymentMethod(app(SignoffFlow::class), $proposal, $clientUser);

        $this->actingAsMfa($clientUser)
            ->postJson(route('portal.proposals.signoff.payment-setup', $proposal), [
                'type' => PaymentAuthority::TYPE_CARD,
                'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'publishable_key',
                'client_secret',
                'setup_intent_ref',
                'customer_ref',
            ])
            ->assertJsonPath('publishable_key', 'pk_test_fixture');
    }

    public function test_zero_fee_proposal_skips_payment_setup_and_signs_without_payment_authority(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-zero-fee-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor, suggestedMid: 0);
        $flow = app(SignoffFlow::class);

        $payload = $flow->payload($proposal);

        $this->assertFalse($payload['payment_required']);
        $this->assertFalse($payload['authority_requires_token']);
        $this->assertSame([
            ProposalSignoffStep::STEP_REVIEW,
            ProposalSignoffStep::STEP_INSURANCE_CONSENT,
            ProposalSignoffStep::STEP_COACH_CONSENT,
            ProposalSignoffStep::STEP_SIGNATURE,
            ProposalSignoffStep::STEP_CONFIRMATION,
        ], collect($payload['steps'])->pluck('step')->all());

        $this->actingAsMfa($clientUser)
            ->postJson(route('portal.proposals.signoff.payment-setup', $proposal), [
                'type' => PaymentAuthority::TYPE_CARD,
                'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method_ref');

        $flow->complete($proposal, ProposalSignoffStep::STEP_REVIEW, [], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_INSURANCE_CONSENT, [
            'election' => Consent::ELECTION_OPT_IN,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_COACH_CONSENT, [
            'election' => Consent::ELECTION_OPT_OUT,
        ], $clientUser);

        try {
            $flow->complete($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD, [
                'type' => PaymentAuthority::TYPE_CARD,
                'gateway' => PaymentAuthority::GATEWAY_STRIPE,
                'collection_day' => 1,
            ], $clientUser);
            $this->fail('Zero-fee proposals should not require a payment-method step.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not required', $e->getMessage());
        }

        $proposal = $flow->complete($proposal->refresh(), ProposalSignoffStep::STEP_SIGNATURE, [
            'signature_name' => 'Zero Fee Signer',
            'accepted' => true,
            'identity_verification' => [
                'password_verified_at' => now()->toIso8601String(),
                'mfa_required' => false,
                'mfa_verified_at' => null,
                'mfa_method' => null,
            ],
            'ip' => '203.0.113.20',
            'user_agent' => 'Feature test',
        ], $clientUser);

        $this->assertSame(ProposalStatus::Signed, $proposal->status);
        $this->assertDatabaseMissing('payment_authorities', [
            'proposal_id' => $proposal->id,
        ]);
        $this->assertDatabaseMissing('payment_schedules', [
            'proposal_id' => $proposal->id,
        ]);

        $flow->complete($proposal->refresh(), ProposalSignoffStep::STEP_CONFIRMATION, [], $clientUser);

        $this->assertSame(5, $proposal->signoffSteps()->count());
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
                ->where('proposals.0.status', ProposalStatus::Released->value)
                ->where('proposals.0.brief', (string) $proposal->scope['summary']));

        $this->actingAsMfa($clientUser)
            ->get(route('portal.proposals.signoff.show', $proposal))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/ProposalSignoff')
                ->where('proposal.id', $proposal->id)
                ->where('proposal.brief', (string) $proposal->scope['summary'])
                ->where('proposal.view_url', route('portal.proposals.show', $proposal, absolute: false))
                ->where('proposal.download_url', route('portal.proposals.download', $proposal, absolute: false))
                ->where('proposal.payment_terms.currency', 'NZD')
                ->where('proposal.payment_terms.cadence', 'monthly')
                ->where('proposal.payment_terms.term_months', 6)
                ->where('proposal.payment_terms.monthly_amount', 1666.67)
                ->where('proposal.payment_terms.monthly_amount_including_gst', 1916.67)
                ->where('proposal.payment_terms.total_amount', 10000)
                ->where('proposal.payment_terms.total_amount_including_gst', 11500)
                ->where('proposal.payment_terms.gst_rate_percent', 15)
                ->where('proposal.payment_terms.tax_mode', 'gst_exclusive')
                ->where('signoff.next_step', ProposalSignoffStep::STEP_REVIEW)
                ->where('signoff.authority_requires_token', false));
    }

    public function test_signature_requires_current_password_when_mfa_is_disabled(): void
    {
        config(['security.mfa_required' => false]);

        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-password-advisor@example.test');
        $proposal = $this->completeToAuthority(app(SignoffFlow::class), $this->releasedProposal($client, $advisor), $clientUser);

        $this->actingAs($clientUser)
            ->post(route('portal.proposals.signoff.step', [$proposal, ProposalSignoffStep::STEP_SIGNATURE]), [
                'signature_name' => 'Client Signer',
                'accepted' => true,
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame(ProposalStatus::AwaitingSignature, $proposal->refresh()->status);

        $this->actingAs($clientUser)
            ->post(route('portal.proposals.signoff.step', [$proposal, ProposalSignoffStep::STEP_SIGNATURE]), [
                'signature_name' => 'Client Signer',
                'accepted' => true,
                'current_password' => 'password',
            ])
            ->assertRedirect(route('portal.proposals.signoff.show', $proposal));

        $proposal = $proposal->refresh();
        $this->assertSame(ProposalStatus::Signed, $proposal->status);

        $payload = $proposal->signoffSteps()
            ->where('step', ProposalSignoffStep::STEP_SIGNATURE)
            ->firstOrFail()
            ->payload;

        $this->assertNotNull(data_get($payload, 'identity_verification.password_verified_at'));
        $this->assertFalse(data_get($payload, 'identity_verification.mfa_required'));
        $this->assertNull(data_get($payload, 'identity_verification.mfa_verified_at'));

        $signedView = $this->actingAs($clientUser)
            ->get(route('portal.proposals.show', $proposal));

        $signedView
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $signedViewContent = (string) $signedView->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $signedViewContent);
        $this->assertStringContainsString('Signed proposal certificate', $signedViewContent);
        $this->assertStringContainsString('PasswordVerifiedat', preg_replace('/\s+/', '', $signedViewContent) ?? '');
        $this->assertStringContainsString('inline;', (string) $signedView->headers->get('Content-Disposition'));

        $signedDownload = $this->actingAs($clientUser)
            ->get(route('portal.proposals.download', $proposal));

        $signedDownload
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('%PDF-1.4', (string) $signedDownload->getContent());
        $this->assertStringContainsString('attachment;', (string) $signedDownload->headers->get('Content-Disposition'));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.portal_signed_viewed',
            'subject_id' => $proposal->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.portal_signed_downloaded',
            'subject_id' => $proposal->id,
        ]);
    }

    public function test_signature_requires_password_and_mfa_code_when_mfa_is_enabled(): void
    {
        config(['security.mfa_required' => true]);

        $this->app->instance(TwoFactorAuthenticationProvider::class, new class implements TwoFactorAuthenticationProvider
        {
            public function generateSecretKey(): string
            {
                return 'secret';
            }

            public function qrCodeUrl($companyName, $companyEmail, $secret): string
            {
                return '';
            }

            public function verify($secret, $code): bool
            {
                return $secret === 'secret' && $code === '123456';
            }
        });

        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-mfa-advisor@example.test');
        $proposal = $this->completeToAuthority(app(SignoffFlow::class), $this->releasedProposal($client, $advisor), $clientUser);

        $this->actingAsMfa($clientUser)
            ->post(route('portal.proposals.signoff.step', [$proposal, ProposalSignoffStep::STEP_SIGNATURE]), [
                'signature_name' => 'Client Signer',
                'accepted' => true,
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('mfa_code');

        $this->actingAsMfa($clientUser)
            ->post(route('portal.proposals.signoff.step', [$proposal, ProposalSignoffStep::STEP_SIGNATURE]), [
                'signature_name' => 'Client Signer',
                'accepted' => true,
                'current_password' => 'password',
                'mfa_code' => '123456',
            ])
            ->assertRedirect(route('portal.proposals.signoff.show', $proposal));

        $proposal = $proposal->refresh();
        $payload = $proposal->signoffSteps()
            ->where('step', ProposalSignoffStep::STEP_SIGNATURE)
            ->firstOrFail()
            ->payload;

        $this->assertSame(ProposalStatus::Signed, $proposal->status);
        $this->assertNotNull(data_get($payload, 'identity_verification.password_verified_at'));
        $this->assertTrue(data_get($payload, 'identity_verification.mfa_required'));
        $this->assertNotNull(data_get($payload, 'identity_verification.mfa_verified_at'));
        $this->assertSame(User::MFA_METHOD_TOTP, data_get($payload, 'identity_verification.mfa_method'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.signature_identity_verified',
            'subject_id' => $proposal->id,
        ]);
    }

    public function test_portal_client_can_view_and_download_only_their_own_released_proposal(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithUsers('signoff-preview-advisor@example.test');
        $proposal = $this->releasedProposal($client, $advisor);
        [, , $otherClientUser] = $this->clientWithUsers('signoff-preview-other-advisor@example.test', 'Other Signoff Client Limited');
        [$draftAdvisor, $draftClient, $draftClientUser] = $this->clientWithUsers('signoff-preview-draft-advisor@example.test', 'Draft Signoff Client Limited');
        $draft = app(ProposalBuilder::class)->generate($draftClient, $this->feeCalculation($draftClient), [], [
            'created_by_user_id' => $draftAdvisor->getKey(),
        ]);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.proposals.show', $proposal))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee($client->legal_name);

        $this->actingAsMfa($clientUser)
            ->get(route('portal.proposals.download', $proposal))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAsMfa($otherClientUser)
            ->get(route('portal.proposals.show', $proposal))
            ->assertNotFound();

        $this->actingAsMfa($otherClientUser)
            ->get(route('portal.proposals.download', $proposal))
            ->assertNotFound();

        $this->actingAsMfa($draftClientUser)
            ->get(route('portal.proposals.show', $draft))
            ->assertNotFound();
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

    private function releasedProposal(Client $client, User $advisor, float $suggestedMid = 10000): Proposal
    {
        $builder = app(ProposalBuilder::class);
        $proposal = $builder->generate($client, $this->feeCalculation($client, $suggestedMid), [
            'consents' => [
                Consent::TYPE_INSURANCE_REFERRAL => Consent::ELECTION_UNDECIDED,
                Consent::TYPE_COACH_REFERRAL => Consent::ELECTION_UNDECIDED,
            ],
        ], [
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return $builder->release($proposal, $advisor);
    }

    private function releasedIntegrationProposal(Client $client, User $advisor): Proposal
    {
        $scope = IntegrationScope::query()->create([
            'client_id' => $client->getKey(),
            'status' => IntegrationScope::STATUS_COMPLETE,
            'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
            'computed' => [
                'complexity_band' => 'L',
                'quoted_fee' => 45_000,
                'quote_range' => ['low' => 40_000, 'mid' => 45_000, 'high' => 50_000],
            ],
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'integration_scope_id' => $scope->getKey(),
            'method' => FeeMethod::Integration,
            'inputs' => ['integration_scope_id' => $scope->getKey()],
            'suggested_low' => 40_000,
            'suggested_mid' => 45_000,
            'suggested_high' => 50_000,
            'improvement_pv_total' => 100_000,
            'risk_cost_pv_total' => 0,
            'roi_ratio' => 2.22,
            'justification' => ['method' => FeeMethod::Integration->value],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => ProposalStatus::Released,
            'version' => 1,
            'scope' => ['proposal_variant' => FeeMethod::Integration->value],
            'services' => [],
            'pv_summary' => ['fee_suggested_mid' => 45_000],
            'roi_ratio' => 2.22,
            'acceptance_terms' => ['referral_consents_required' => false],
            'released_at' => now(),
            'released_by_user_id' => $advisor->getKey(),
            'expires_at' => now()->addDays(30),
            'created_by_user_id' => $advisor->getKey(),
        ]);
    }

    private function feeCalculation(Client $client, float $suggestedMid = 10000): FeeCalculation
    {
        return FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => round($suggestedMid * 0.8, 2),
            'suggested_mid' => $suggestedMid,
            'suggested_high' => round($suggestedMid * 1.2, 2),
            'improvement_pv_total' => 25000,
            'risk_cost_pv_total' => 3000,
            'roi_ratio' => 2.5,
            'justification' => [
                'services' => [
                    ['name' => 'Sign-off fixture advisory', 'line_total' => $suggestedMid],
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
            'collection_day' => 1,
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
