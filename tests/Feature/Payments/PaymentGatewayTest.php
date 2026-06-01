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
use App\Models\PaymentAuthority;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\Gateway;
use App\Services\Payments\PaymentChargeRequest;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

        Config::set('integrations.payments.primary_gateway', PaymentAuthority::GATEWAY_STRIPE);
        Config::set('integrations.payments.stripe.live', false);
        Config::set('integrations.payments.windcave.live', false);
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
        app()->forgetInstance(StripeClient::class);

        Http::fake([
            'https://api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_live_fixture',
                'status' => 'succeeded',
            ], 200),
        ]);

        $result = app(StripeClient::class)->charge(new PaymentChargeRequest(
            clientId: 'client-live',
            proposalId: 'proposal-live',
            authorityId: 'authority-live',
            token: 'pm_live_fixture',
            customerRef: 'cus_live_fixture',
            amount: '3000.00',
            currency: 'NZD',
            gateway: PaymentAuthority::GATEWAY_STRIPE,
            idempotencyKey: 'live-stripe-fixture',
        ));

        $this->assertSame('pi_live_fixture', $result->gatewayRef);
        $this->assertSame(PaymentAuthority::GATEWAY_STRIPE, $result->gateway);
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
}
