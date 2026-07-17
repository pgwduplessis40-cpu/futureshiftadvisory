<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\IntegrationCall;
use App\Models\Payment;
use App\Models\PaymentAuthority;
use App\Models\PaymentSchedule;
use App\Models\PaymentWebhookEvent;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Integration\Stripe\LiveStripeClient;
use App\Services\Payments\Gateway;
use App\Services\Payments\PaymentAuthorityRequest;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Mail::fake();
        Storage::fake('secure_local');

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
    }

    public function test_fixture_gateway_charge_succeeds_without_contract_change(): void
    {
        [$authority, $advisor] = $this->authority();

        $result = app(Gateway::class)->charge($authority, '1200.00', [
            'idempotency_key' => 'fixture-success',
        ], $advisor);

        $this->assertSame(PaymentAuthority::GATEWAY_STRIPE, $result->gateway);
        $this->assertSame('succeeded', $result->status);
        $this->assertNull($result->failoverFrom);
        $this->assertStringStartsWith('ch_stripe_', $result->gatewayRef);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_gateway.charge_succeeded',
            'subject_id' => $authority->id,
        ]);
    }

    public function test_primary_failure_fails_over_to_secondary_and_records_failover_from(): void
    {
        [$authority, $advisor] = $this->authority('gateway-failover-advisor@example.test');

        $result = app(Gateway::class)->charge($authority, 1800, [
            'idempotency_key' => 'fixture-failover',
            'metadata' => ['fixture_fail_stripe' => true],
        ], $advisor);

        $this->assertSame(PaymentAuthority::GATEWAY_WINDCAVE, $result->gateway);
        $this->assertSame(PaymentAuthority::GATEWAY_STRIPE, $result->failoverFrom);
        $this->assertStringStartsWith('txn_windcave_', $result->gatewayRef);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_gateway.primary_failed',
            'subject_id' => $authority->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_gateway.failover_succeeded',
            'subject_id' => $authority->id,
        ]);
    }

    public function test_double_gateway_failure_is_logged_and_notified(): void
    {
        $superAdmin = $this->superAdmin();
        [$authority, $advisor] = $this->authority('gateway-double-fail-advisor@example.test');

        try {
            app(Gateway::class)->charge($authority, 2400, [
                'idempotency_key' => 'fixture-double-failure',
                'metadata' => [
                    'fixture_fail_stripe' => true,
                    'fixture_fail_windcave' => true,
                ],
            ], $advisor);
            $this->fail('Both gateway fixture failures should throw.');
        } catch (PaymentGatewayException $e) {
            $this->assertStringContainsString('Both payment gateways failed', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_gateway.double_failure',
            'subject_id' => $authority->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $superAdmin->getKey(),
            'type' => 'payment.gateway.failure',
            'urgency' => 'urgent',
        ]);
    }

    public function test_raw_card_payload_is_rejected_before_persistence(): void
    {
        [$authority, $advisor] = $this->authority('gateway-pan-advisor@example.test');

        try {
            app(Gateway::class)->charge($authority, 900, [
                'metadata' => ['card_number' => '4242424242424242'],
            ], $advisor);
            $this->fail('Raw PAN metadata should be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Raw card numbers', $e->getMessage());
        }

        $this->assertSame(0, AuditEvent::query()->where('action', 'like', 'payment_gateway.%')->count());
    }

    public function test_live_stripe_client_uses_resilient_http_when_enabled(): void
    {
        Config::set('integrations.payments.stripe.live', true);
        Config::set('integrations.payments.stripe.secret', 'sk_test_feature');
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test_feature');
        Config::set('integrations.retry.attempts', 1);
        app()->forgetInstance(LiveStripeClient::class);

        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_live_fixture',
                'status' => 'succeeded',
            ], 200),
        ]);

        $result = app(LiveStripeClient::class)->charge(new PaymentChargeRequest(
            clientId: 'client-live',
            proposalId: 'proposal-live',
            authorityId: 'authority-live',
            token: 'pm_live_fixture',
            customerRef: 'cus_live_fixture',
            amount: '3000.00',
            currency: 'NZD',
            gateway: PaymentAuthority::GATEWAY_STRIPE,
            idempotencyKey: 'live-stripe-fixture',
            metadata: ['client_code' => 'FSA-019F0B'],
        ));

        $this->assertSame('pi_live_fixture', $result->gatewayRef);
        $this->assertSame(PaymentAuthority::GATEWAY_STRIPE, $result->gateway);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.stripe.com/v1/payment_intents'
                && data_get($payload, 'description') === 'Future Shift Advisory FSA-019F0B'
                && data_get($payload, 'statement_descriptor_suffix') === 'FSA-019F0B'
                && data_get($payload, 'metadata.client_code') === 'FSA-019F0B';
        });
        $this->assertDatabaseHas('integration_calls', [
            'service' => 'stripe',
            'status' => IntegrationCall::STATUS_SUCCESS,
        ]);
    }

    public function test_live_stripe_authority_capture_verifies_succeeded_setup_intent(): void
    {
        Config::set('integrations.payments.stripe.live', true);
        Config::set('integrations.payments.stripe.secret', 'sk_test_feature');
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test_feature');
        Config::set('integrations.retry.attempts', 1);
        app()->forgetInstance(LiveStripeClient::class);

        Http::fake([
            'https://api.stripe.com/v1/setup_intents/seti_live_fixture' => Http::response([
                'id' => 'seti_live_fixture',
                'status' => 'succeeded',
                'payment_method' => 'pm_live_fixture',
                'customer' => 'cus_live_fixture',
            ], 200),
        ]);

        $token = app(LiveStripeClient::class)->captureAuthority(new PaymentAuthorityRequest(
            clientId: 'client-live',
            proposalId: 'proposal-live',
            type: PaymentAuthority::TYPE_CARD,
            gateway: PaymentAuthority::GATEWAY_STRIPE,
            payload: [
                'setup_intent_ref' => 'seti_live_fixture',
                'payment_method_ref' => 'pm_live_fixture',
                'customer_ref' => 'cus_live_fixture',
            ],
        ));

        $this->assertSame('pm_live_fixture', $token->token);
        $this->assertSame('cus_live_fixture', $token->customerRef);
        $this->assertSame('seti_live_fixture', $token->metadata['setup_intent_ref']);
        $this->assertDatabaseHas('integration_calls', [
            'service' => 'stripe',
            'status' => IntegrationCall::STATUS_SUCCESS,
        ]);
    }

    public function test_payment_webhook_signatures_are_verified(): void
    {
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test');
        Config::set('integrations.payments.windcave.webhook_secret', 'windcave_secret');
        $payload = ['id' => 'evt_test', 'type' => 'payment_intent.succeeded'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->getTimestamp();
        $stripeSignature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');
        $windcaveSignature = hash_hmac('sha256', $timestamp.'.'.$body, 'windcave_secret');

        $this->call('POST', route('webhooks.payments.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$stripeSignature,
        ], $body)->assertAccepted();

        $this->call('POST', route('webhooks.payments.windcave'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WINDCAVE_TIMESTAMP' => $timestamp,
            'HTTP_X_WINDCAVE_SIGNATURE' => 'sha256='.$windcaveSignature,
        ], $body)->assertAccepted();

        $this->call('POST', route('webhooks.payments.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1=invalid',
        ], $body)->assertUnauthorized();

        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.webhook_received',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.webhook_rejected',
        ]);
    }

    public function test_stripe_webhook_rejects_missing_secret_missing_signature_and_stale_timestamp(): void
    {
        $payload = ['id' => 'evt_signature_edges', 'type' => 'payment_intent.succeeded'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

        Config::set('integrations.payments.stripe.webhook_secret', null);
        $this->call('POST', route('webhooks.payments.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $body)->assertUnauthorized();
        $this->assertWebhookRejectionReasonRecorded('secret_not_configured');

        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test');
        $this->call('POST', route('webhooks.payments.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();
        $this->assertWebhookRejectionReasonRecorded('signature_missing');

        $staleTimestamp = now()->subMinutes(10)->getTimestamp();
        $this->postStripeWebhook($payload, $staleTimestamp)->assertUnauthorized();
        $this->assertWebhookRejectionReasonRecorded('timestamp_out_of_window');
        $this->assertDatabaseMissing('payment_webhook_events', [
            'event_id' => 'evt_signature_edges',
        ]);
    }

    public function test_stripe_succeeded_webhook_reconciles_pending_payment_and_generates_receipt(): void
    {
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test');
        [$authority] = $this->authority('gateway-webhook-success@example.test');
        [$schedule, $payment] = $this->pendingPayment($authority, [
            'amount' => 20,
            'gateway_ref' => 'pi_webhook_success',
        ]);

        $this->postStripeWebhook($this->paymentIntentPayload(
            eventId: 'evt_webhook_success',
            eventType: 'payment_intent.succeeded',
            intentId: 'pi_webhook_success',
            payment: $payment,
            amountCents: 2000,
            status: 'succeeded',
        ))->assertAccepted();

        $payment->refresh();

        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertSame(PaymentAuthority::GATEWAY_STRIPE, $payment->gateway);
        $this->assertSame('pi_webhook_success', $payment->gateway_ref);
        $this->assertSame(PaymentSchedule::STATUS_COMPLETED, $schedule->refresh()->status);
        $this->assertNotNull($payment->receipt()->first());
        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'event_id' => 'evt_webhook_success',
            'event_type' => 'payment_intent.succeeded',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'payment_id' => $payment->getKey(),
            'client_id' => $payment->client_id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.webhook_reconciled',
            'subject_id' => $payment->getKey(),
        ]);
    }

    public function test_stripe_succeeded_webhook_with_amount_or_currency_mismatch_fails_event_without_succeeding_payment(): void
    {
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test');
        [$authority] = $this->authority('gateway-webhook-mismatch@example.test');
        [, $amountPayment] = $this->pendingPayment($authority, [
            'amount' => 20,
            'gateway_ref' => 'pi_webhook_amount_mismatch',
        ]);
        [, $currencyPayment] = $this->pendingPayment($authority, [
            'amount' => 20,
            'gateway_ref' => 'pi_webhook_currency_mismatch',
        ]);

        $this->postStripeWebhook($this->paymentIntentPayload(
            eventId: 'evt_webhook_amount_mismatch',
            eventType: 'payment_intent.succeeded',
            intentId: 'pi_webhook_amount_mismatch',
            payment: $amountPayment,
            amountCents: 1900,
            status: 'succeeded',
        ))->assertAccepted();

        $currencyPayload = $this->paymentIntentPayload(
            eventId: 'evt_webhook_currency_mismatch',
            eventType: 'payment_intent.succeeded',
            intentId: 'pi_webhook_currency_mismatch',
            payment: $currencyPayment,
            amountCents: 2000,
            status: 'succeeded',
        );
        data_set($currencyPayload, 'data.object.currency', 'usd');

        $this->postStripeWebhook($currencyPayload)->assertAccepted();

        $this->assertSame(Payment::STATUS_PENDING, $amountPayment->refresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $currencyPayment->refresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_webhook_amount_mismatch',
            'status' => PaymentWebhookEvent::STATUS_FAILED,
            'payment_id' => $amountPayment->getKey(),
            'failure_reason' => 'amount_mismatch',
        ]);
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_webhook_currency_mismatch',
            'status' => PaymentWebhookEvent::STATUS_FAILED,
            'payment_id' => $currencyPayment->getKey(),
            'failure_reason' => 'currency_mismatch',
        ]);
        $this->assertSame(2, AuditEvent::query()->where('action', 'payment.webhook_failed')->count());
    }

    public function test_stripe_webhook_event_is_idempotent(): void
    {
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test');
        [$authority] = $this->authority('gateway-webhook-idempotent@example.test');
        [, $payment] = $this->pendingPayment($authority, [
            'amount' => 20,
            'gateway_ref' => 'pi_webhook_duplicate',
        ]);
        $payload = $this->paymentIntentPayload(
            eventId: 'evt_webhook_duplicate',
            eventType: 'payment_intent.succeeded',
            intentId: 'pi_webhook_duplicate',
            payment: $payment,
            amountCents: 2000,
            status: 'succeeded',
        );

        $this->postStripeWebhook($payload)->assertAccepted();
        $this->postStripeWebhook($payload)->assertAccepted();

        $this->assertSame(1, PaymentWebhookEvent::query()->where('event_id', 'evt_webhook_duplicate')->count());
        $this->assertSame(1, $payment->refresh()->receipt()->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.webhook_duplicate',
            'subject_id' => $payment->getKey(),
        ]);
    }

    public function test_stripe_unsupported_webhook_event_is_recorded_and_ignored(): void
    {
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test');

        $this->postStripeWebhook([
            'id' => 'evt_webhook_unsupported',
            'object' => 'event',
            'created' => now()->getTimestamp(),
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_webhook_unsupported',
                    'object' => 'charge',
                ],
            ],
        ])->assertAccepted();

        $this->assertDatabaseHas('payment_webhook_events', [
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'event_id' => 'evt_webhook_unsupported',
            'event_type' => 'charge.refunded',
            'status' => PaymentWebhookEvent::STATUS_IGNORED,
            'failure_reason' => 'unsupported_event_type',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment.webhook_ignored',
        ]);
    }

    public function test_stripe_failed_webhook_marks_payment_retrying(): void
    {
        Config::set('integrations.payments.stripe.webhook_secret', 'whsec_test');
        [$authority] = $this->authority('gateway-webhook-failure@example.test');
        [$schedule, $payment] = $this->pendingPayment($authority, [
            'amount' => 20,
            'gateway_ref' => null,
        ]);

        $this->postStripeWebhook($this->paymentIntentPayload(
            eventId: 'evt_webhook_failed',
            eventType: 'payment_intent.payment_failed',
            intentId: 'pi_webhook_failed',
            payment: $payment,
            amountCents: 2000,
            status: 'requires_payment_method',
            extra: [
                'last_payment_error' => [
                    'message' => 'Your card was declined.',
                ],
            ],
        ))->assertAccepted();

        $payment->refresh();

        $this->assertSame(Payment::STATUS_RETRYING, $payment->status);
        $this->assertSame('pi_webhook_failed', $payment->gateway_ref);
        $this->assertSame('Your card was declined.', $payment->failed_reason);
        $this->assertTrue($schedule->refresh()->next_run_at?->greaterThan(now()));
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_webhook_failed',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'payment_id' => $payment->getKey(),
        ]);
    }

    /**
     * @return array{0: PaymentAuthority, 1: User}
     */
    private function authority(string $advisorEmail = 'gateway-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Gateway Fixture Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $feeCalculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => 8000,
            'suggested_mid' => 10000,
            'suggested_high' => 12000,
            'improvement_pv_total' => 25000,
            'risk_cost_pv_total' => 3000,
            'roi_ratio' => 2.5,
            'justification' => ['services' => []],
        ]);

        $proposal = Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $feeCalculation->getKey(),
            'status' => ProposalStatus::Draft,
            'version' => 1,
            'scope' => ['summary' => 'Gateway fixture'],
            'services' => [['name' => 'Gateway fixture advisory', 'line_total' => 10000]],
            'pv_summary' => ['fee_suggested_mid' => 10000],
            'roi_ratio' => 2.5,
            'acceptance_terms' => ['phase' => 'gateway_fixture'],
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $tokenEnvelope = app(KeyEnvelope::class)->encrypt(json_encode([
            'token' => 'tok_gateway_fixture',
            'customer_ref' => 'cus_gateway_fixture',
            'metadata' => ['gateway' => PaymentAuthority::GATEWAY_STRIPE],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $authority = PaymentAuthority::query()->create([
            'client_id' => $client->getKey(),
            'proposal_id' => $proposal->getKey(),
            'type' => PaymentAuthority::TYPE_CARD,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'gateway_customer_ref' => 'cus_gateway_fixture',
            'gateway_token_envelope' => $tokenEnvelope,
            'status' => PaymentAuthority::STATUS_ACTIVE,
            'authorised_by_user_id' => $advisor->getKey(),
            'authorised_at' => now(),
        ]);

        return [$authority, $advisor];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => 'gateway-super-admin@example.test',
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: PaymentSchedule, 1: Payment}
     */
    private function pendingPayment(PaymentAuthority $authority, array $overrides = []): array
    {
        $schedule = PaymentSchedule::query()->create([
            'client_id' => $authority->client_id,
            'proposal_id' => $authority->proposal_id,
            'payment_authority_id' => $authority->getKey(),
            'cadence' => $overrides['cadence'] ?? PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => $overrides['amount'] ?? 20,
            'currency' => $overrides['currency'] ?? 'NZD',
            'next_run_at' => now()->subMinute(),
            'status' => PaymentSchedule::STATUS_ACTIVE,
        ]);

        $payment = Payment::query()->create([
            'client_id' => $authority->client_id,
            'payment_schedule_id' => $schedule->getKey(),
            'payment_authority_id' => $authority->getKey(),
            'amount' => $schedule->amount,
            'currency' => $schedule->currency,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'gateway_ref' => $overrides['gateway_ref'] ?? 'pi_webhook_fixture',
            'status' => Payment::STATUS_PENDING,
            'attempt' => $overrides['attempt'] ?? 1,
        ]);

        return [$schedule, $payment];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function paymentIntentPayload(
        string $eventId,
        string $eventType,
        string $intentId,
        Payment $payment,
        int $amountCents,
        string $status,
        array $extra = [],
    ): array {
        return [
            'id' => $eventId,
            'object' => 'event',
            'created' => now()->getTimestamp(),
            'type' => $eventType,
            'data' => [
                'object' => [
                    'id' => $intentId,
                    'object' => 'payment_intent',
                    'amount' => $amountCents,
                    'amount_received' => $eventType === 'payment_intent.succeeded' ? $amountCents : 0,
                    'currency' => strtolower($payment->currency),
                    'metadata' => [
                        'payment_id' => $payment->getKey(),
                        'payment_schedule_id' => $payment->payment_schedule_id,
                    ],
                    'status' => $status,
                ] + $extra,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postStripeWebhook(array $payload, ?int $timestamp = null, string $secret = 'whsec_test'): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) ($timestamp ?? now()->getTimestamp());
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $this->call('POST', route('webhooks.payments.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $body);
    }

    private function assertWebhookRejectionReasonRecorded(string $reason): void
    {
        $recorded = AuditEvent::query()
            ->where('action', 'payment.webhook_rejected')
            ->get()
            ->contains(fn (AuditEvent $event): bool => data_get($event->after, 'reason') === $reason);

        $this->assertTrue($recorded, "Expected payment.webhook_rejected audit reason [{$reason}] to be recorded.");
    }
}
