<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\BillingAdjustment;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\Payment;
use App\Models\PaymentAuthority;
use App\Models\PaymentInstallment;
use App\Models\PaymentSchedule;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Payments\InstallmentPaymentProcessor;
use App\Services\Payments\InstallmentScheduleBuilder;
use App\Services\Payments\PaymentWebhookReconciler;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InstallmentPaymentProcessorTest extends TestCase
{
    use RefreshDatabase;

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

        Config::set('integrations.payments.primary_gateway', PaymentAuthority::GATEWAY_STRIPE);
        Config::set('integrations.payments.stripe.live', false);
        Config::set('integrations.payments.windcave.live', false);
        Config::set('integrations.payments.max_attempts', 2);
        Config::set('integrations.payments.retry_delay_minutes', 45);
    }

    public function test_due_installment_charges_settles_and_generates_a_receipt(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [, $schedule] = $this->paymentFixture();
        $installment = $this->installment($schedule, $now);

        $result = app(InstallmentPaymentProcessor::class)->processDue($now);

        $this->assertSame(['scanned' => 1, 'succeeded' => 1, 'retrying' => 0, 'failed' => 0, 'receipts' => 1], $result);
        $payment = Payment::query()->sole();
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertSame('115.00', $payment->amount);
        $this->assertSame(PaymentInstallment::STATUS_SETTLED, $installment->refresh()->status);
        $this->assertSame(PaymentSchedule::STATUS_COMPLETED, $schedule->refresh()->status);
        $this->assertNotNull($payment->receipt()->first());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_installment.claimed',
            'subject_id' => $installment->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_installment.settled',
            'subject_id' => $installment->id,
        ]);
    }

    public function test_available_credit_is_allocated_then_settled_without_a_gateway_charge(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [$client, $schedule] = $this->paymentFixture();
        $installment = $this->installment($schedule, $now, baseAmount: 100.00);
        $credit = BillingAdjustment::query()->create([
            'client_id' => $client->getKey(),
            'type' => BillingAdjustment::TYPE_SCOPING_FEE_CREDIT,
            'amount' => 100.00,
            'currency' => 'NZD',
            'status' => BillingAdjustment::STATUS_AVAILABLE,
        ]);

        $result = app(InstallmentPaymentProcessor::class)->processDue($now);

        $this->assertSame(['scanned' => 1, 'succeeded' => 1, 'retrying' => 0, 'failed' => 0, 'receipts' => 1], $result);
        $payment = Payment::query()->sole();
        $this->assertSame('0.00', $payment->amount);
        $this->assertSame('internal_credit', $payment->gateway);
        $this->assertSame(PaymentInstallment::STATUS_SETTLED_ZERO, $installment->refresh()->status);
        $this->assertSame(100.0, $installment->credit_applied);
        $this->assertSame(0.0, $installment->net_amount);
        $this->assertSame(BillingAdjustment::STATUS_APPLIED, $credit->refresh()->status);
        $this->assertDatabaseHas('billing_adjustment_applications', [
            'adjustment_id' => $credit->id,
            'payment_installment_id' => $installment->id,
            'amount_applied' => 100.00,
        ]);
    }

    public function test_ambiguous_gateway_failure_is_deferred_then_can_settle_from_webhook(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [, $schedule] = $this->paymentFixture();
        $installment = $this->installment($schedule, $now);

        $first = app(InstallmentPaymentProcessor::class)->processDue($now, chargeMetadata: [
            'fixture_fail_stripe' => true,
            'fixture_fail_windcave' => true,
        ]);

        $this->assertSame(['scanned' => 1, 'succeeded' => 0, 'retrying' => 1, 'failed' => 0, 'receipts' => 0], $first);
        $payment = Payment::query()->sole();
        $this->assertSame(PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION, $installment->refresh()->status);
        $this->assertStringContainsString('Both payment gateways failed', (string) $payment->refresh()->failed_reason);

        $confirmation = app(InstallmentPaymentProcessor::class)->confirmAmbiguous($now->addMinutes(5));

        $this->assertSame(['swept' => 0, 'confirmed' => 0, 'reopened' => 0, 'manual_review' => 0], $confirmation);
        $this->assertSame(1, $installment->refresh()->confirmation_attempts);
        $this->assertTrue($installment->next_confirmation_at?->equalTo($now->addMinutes(10)) ?? false);

        $outcome = app(InstallmentPaymentProcessor::class)->settleFromWebhook(
            $payment->refresh(),
            PaymentAuthority::GATEWAY_STRIPE,
            'evt_installment_settled',
            $now->addMinutes(6),
        );

        $this->assertSame(['claimed' => true, 'status' => 'succeeded', 'receipt' => true], $outcome);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->refresh()->status);
        $this->assertSame(PaymentInstallment::STATUS_SETTLED, $installment->refresh()->status);
    }

    public function test_webhook_decline_reopens_before_the_attempt_cap_and_pauses_at_the_cap(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [, $retrySchedule] = $this->paymentFixture();
        [$retryInstallment, $retryPayment] = $this->claimedAttempt($retrySchedule, $now, attemptCount: 1);

        $retry = app(InstallmentPaymentProcessor::class)->declineFromWebhook(
            $retryPayment,
            PaymentAuthority::GATEWAY_STRIPE,
            'evt_retry',
            'Issuer declined the charge.',
            $now,
        );

        $this->assertSame(['claimed' => true, 'status' => 'retrying', 'receipt' => false], $retry);
        $this->assertSame(PaymentInstallment::STATUS_DUE, $retryInstallment->refresh()->status);
        $this->assertTrue($retryInstallment->next_attempt_at?->equalTo($now->addMinutes(45)) ?? false);
        $this->assertSame(PaymentSchedule::STATUS_ACTIVE, $retrySchedule->refresh()->status);

        [, $terminalSchedule] = $this->paymentFixture();
        [$terminalInstallment, $terminalPayment] = $this->claimedAttempt($terminalSchedule, $now, attemptCount: 2);

        $terminal = app(InstallmentPaymentProcessor::class)->declineFromWebhook(
            $terminalPayment,
            PaymentAuthority::GATEWAY_STRIPE,
            'evt_terminal',
            'Issuer declined the final charge.',
            $now,
        );

        $this->assertSame(['claimed' => true, 'status' => 'failed', 'receipt' => false], $terminal);
        $this->assertSame(PaymentInstallment::STATUS_FAILED, $terminalInstallment->refresh()->status);
        $this->assertSame(PaymentSchedule::STATUS_PAUSED, $terminalSchedule->refresh()->status);
    }

    public function test_stale_processing_and_missing_active_payments_are_sent_to_manual_review(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [, $staleSchedule] = $this->paymentFixture();
        $stale = $this->installment($staleSchedule, $now, [
            'status' => PaymentInstallment::STATUS_PROCESSING,
            'processing_started_at' => $now->subMinutes(6),
        ]);
        [, $missingPaymentSchedule] = $this->paymentFixture();
        $missingPayment = $this->installment($missingPaymentSchedule, $now, [
            'status' => PaymentInstallment::STATUS_AWAITING_GATEWAY_CONFIRMATION,
            'next_confirmation_at' => $now,
        ]);

        $result = app(InstallmentPaymentProcessor::class)->confirmAmbiguous($now);

        $this->assertSame(['swept' => 1, 'confirmed' => 0, 'reopened' => 0, 'manual_review' => 2], $result);
        $this->assertSame(PaymentInstallment::STATUS_MANUAL_REVIEW, $stale->refresh()->status);
        $this->assertSame(PaymentInstallment::STATUS_MANUAL_REVIEW, $missingPayment->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_installment.stale_processing_swept',
            'subject_id' => $stale->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_installment.manual_review',
            'subject_id' => $missingPayment->id,
        ]);
    }

    public function test_stripe_success_webhook_settles_an_installment_payment(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [, $schedule] = $this->paymentFixture();
        [$installment, $payment] = $this->claimedAttempt($schedule, $now, attemptCount: 1);

        $event = app(PaymentWebhookReconciler::class)->handleStripe($this->stripeEvent(
            eventId: 'evt_installment_success',
            type: 'payment_intent.succeeded',
            intentId: 'pi_installment_success',
            payment: $payment,
        ));

        $this->assertSame('processed', $event->status);
        $this->assertSame($payment->id, $event->payment_id);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->refresh()->status);
        $this->assertSame(PaymentInstallment::STATUS_SETTLED, $installment->refresh()->status);
        $this->assertSame(PaymentSchedule::STATUS_COMPLETED, $schedule->refresh()->status);
    }

    public function test_stripe_failure_webhook_reopens_an_installment_before_the_attempt_cap(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [, $schedule] = $this->paymentFixture();
        [$installment, $payment] = $this->claimedAttempt($schedule, $now, attemptCount: 1);

        $event = app(PaymentWebhookReconciler::class)->handleStripe($this->stripeEvent(
            eventId: 'evt_installment_failure',
            type: 'payment_intent.payment_failed',
            intentId: 'pi_installment_failure',
            payment: $payment,
            failureMessage: 'Insufficient funds.',
        ));

        $this->assertSame('processed', $event->status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertSame('Insufficient funds.', $payment->failed_reason);
        $this->assertSame(PaymentInstallment::STATUS_DUE, $installment->refresh()->status);
        $this->assertSame(PaymentSchedule::STATUS_ACTIVE, $schedule->refresh()->status);
    }

    public function test_integration_schedule_builder_creates_one_credit_adjusted_first_installment(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 20:00:00');
        [$client, $schedule, $proposal] = $this->paymentFixture(method: FeeMethod::Integration);
        BillingAdjustment::query()->create([
            'client_id' => $client->getKey(),
            'type' => BillingAdjustment::TYPE_SCOPING_FEE_CREDIT,
            'amount' => 25.00,
            'currency' => 'NZD',
            'status' => BillingAdjustment::STATUS_AVAILABLE,
        ]);

        $builder = app(InstallmentScheduleBuilder::class);
        $first = $builder->ensureFirstForIntegrationProposal($schedule, $proposal);
        $again = $builder->ensureFirstForIntegrationProposal($schedule, $proposal);

        $this->assertInstanceOf(PaymentInstallment::class, $first);
        $this->assertSame($first->id, $again?->id);
        $this->assertSame(1, PaymentInstallment::query()->where('payment_schedule_id', $schedule->id)->count());
        $this->assertSame(100.0, $first->base_amount);
        $this->assertSame(25.0, $first->credit_applied);
        $this->assertSame(75.0, $first->net_amount);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_installment.created',
            'subject_id' => $first->id,
        ]);
    }

    /**
     * @return array{0: Client, 1: PaymentSchedule, 2: Proposal}
     */
    private function paymentFixture(FeeMethod $method = FeeMethod::OutcomeBased): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Installment Coverage '.fake()->unique()->word().' Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $calculation = FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => $method,
            'inputs' => ['fixture' => true],
            'suggested_low' => 80,
            'suggested_mid' => 100,
            'suggested_high' => 120,
            'improvement_pv_total' => 250,
            'risk_cost_pv_total' => 30,
            'roi_ratio' => 2.5,
            'justification' => ['services' => []],
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $proposal = Proposal::query()->create([
            'client_id' => $client->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => ProposalStatus::Draft,
            'version' => 1,
            'scope' => ['summary' => 'Installment coverage fixture'],
            'services' => [['name' => 'Coverage fixture', 'line_total' => 100]],
            'pv_summary' => ['fee_suggested_mid' => 100],
            'roi_ratio' => 2.5,
            'acceptance_terms' => ['fixture' => true],
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $authority = PaymentAuthority::query()->create([
            'client_id' => $client->getKey(),
            'proposal_id' => $proposal->getKey(),
            'type' => PaymentAuthority::TYPE_CARD,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'gateway_customer_ref' => 'cus_installment_coverage',
            'gateway_token_envelope' => app(KeyEnvelope::class)->encrypt(json_encode([
                'token' => 'tok_installment_coverage',
                'customer_ref' => 'cus_installment_coverage',
            ], JSON_THROW_ON_ERROR)),
            'status' => PaymentAuthority::STATUS_ACTIVE,
            'authorised_by_user_id' => $advisor->getKey(),
            'authorised_at' => now(),
        ]);
        $schedule = PaymentSchedule::query()->create([
            'client_id' => $client->getKey(),
            'proposal_id' => $proposal->getKey(),
            'payment_authority_id' => $authority->getKey(),
            'cadence' => PaymentSchedule::CADENCE_ONE_OFF,
            'amount' => 100,
            'currency' => 'NZD',
            'next_run_at' => now(),
            'status' => PaymentSchedule::STATUS_ACTIVE,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return [$client, $schedule, $proposal];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function installment(PaymentSchedule $schedule, CarbonImmutable $now, array $attributes = [], float $baseAmount = 100.00): PaymentInstallment
    {
        return PaymentInstallment::query()->create([
            'client_id' => $schedule->client_id,
            'payment_schedule_id' => $schedule->getKey(),
            'sequence' => 1,
            'due_date' => $now->toDateString(),
            'base_amount' => $baseAmount,
            'credit_applied' => 0,
            'net_amount' => $baseAmount,
            'status' => PaymentInstallment::STATUS_DUE,
            'next_attempt_at' => $now,
            ...$attributes,
        ]);
    }

    /**
     * @return array{0: PaymentInstallment, 1: Payment}
     */
    private function claimedAttempt(PaymentSchedule $schedule, CarbonImmutable $now, int $attemptCount): array
    {
        $installment = $this->installment($schedule, $now, [
            'status' => PaymentInstallment::STATUS_PROCESSING,
            'attempt_count' => $attemptCount,
            'processing_started_at' => $now,
        ]);
        $payment = Payment::query()->create([
            'client_id' => $schedule->client_id,
            'payment_schedule_id' => $schedule->getKey(),
            'payment_installment_id' => $installment->getKey(),
            'payment_authority_id' => $schedule->payment_authority_id,
            'amount' => '115.00',
            'currency' => 'NZD',
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
            'gateway_ref' => 'pending_'.$attemptCount,
            'idempotency_key' => 'fixture-claimed-'.$installment->getKey(),
            'status' => Payment::STATUS_PENDING,
            'attempt' => $attemptCount,
        ]);
        $installment->forceFill(['active_payment_id' => $payment->getKey()])->save();

        return [$installment, $payment];
    }

    /**
     * @return array<string, mixed>
     */
    private function stripeEvent(
        string $eventId,
        string $type,
        string $intentId,
        Payment $payment,
        ?string $failureMessage = null,
    ): array {
        return [
            'id' => $eventId,
            'type' => $type,
            'created' => 1_787_232_000,
            'data' => [
                'object' => array_filter([
                    'id' => $intentId,
                    'currency' => 'nzd',
                    'amount_received' => (int) round(((float) $payment->amount) * 100),
                    'metadata' => ['payment_id' => $payment->getKey()],
                    'last_payment_error' => $failureMessage === null ? null : ['message' => $failureMessage],
                ], static fn (mixed $value): bool => $value !== null),
            ],
        ];
    }
}
