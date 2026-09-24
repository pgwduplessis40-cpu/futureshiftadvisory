<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\AuditEvent;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\PaymentAccountingSync;
use App\Models\PaymentRefund;
use App\Models\ServiceActivation;
use App\Models\ServiceRatePackage;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\IdeaValidationPurchaseAdvisorNotification;
use App\Notifications\IdeaValidationPurchaseConfirmedNotification;
use App\Services\Entrepreneurs\ExternalStripeRefundReconciler;
use App\Services\Entrepreneurs\IdeaValidationCancellation;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\PaymentChargeLookup;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentRefundResult;
use App\Services\Payments\PaymentRefundSearch;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

final class PaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        $this->withoutVite();
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    public function test_only_a_super_admin_can_view_payment_reconciliation_candidates(): void
    {
        [, $buyer] = $this->mismatchedPurchase();
        $admin = $this->superAdmin();

        $buyerResponse = $this->actingAsMfa($buyer)
            ->get(route('admin.payment-reconciliations.index'));
        $this->assertNotSame(200, $buyerResponse->getStatusCode());

        $this->actingAsMfa($admin)
            ->get(route('admin.payment-reconciliations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/payment-reconciliations/Index')
                ->has('candidates', 1)
                ->where('candidates.0.customer_email', $buyer->email)
                ->where('candidates.0.recorded_payment_amount', 115));
    }

    public function test_super_admin_dashboard_announces_payment_reconciliation_candidates(): void
    {
        $this->mismatchedPurchase();
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('advisor/Dashboard')
                ->where('paymentReconciliationQueue.available', true)
                ->where('paymentReconciliationQueue.total', 1)
                ->where('paymentReconciliationQueue.action_url', route('admin.payment-reconciliations.index', absolute: false))
                ->where('paymentReconciliationQueue.action_label', 'Review payments'));
    }

    public function test_a_settled_matching_payment_is_visible_for_explicit_xero_backfill_and_never_exports_without_approval(): void
    {
        [$purchase, $buyer, $payment] = $this->mismatchedPurchase();
        $payment->forceFill([
            'status' => Payment::STATUS_SUCCEEDED,
            'processed_at' => now(),
        ])->save();
        $purchase->forceFill([
            'status' => IdeaValidationPurchase::STATUS_PAID,
            'paid_at' => now(),
            'amount_ex_gst' => '100.00',
            'gst_amount' => '15.00',
            'amount_including_gst' => '115.00',
            'package_snapshot' => [
                ...(array) $purchase->package_snapshot,
                'fixed_fee' => '100.00',
            ],
        ])->save();
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get(route('admin.payment-reconciliations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounting.backfill_candidates', 1)
                ->where('accounting.backfill_candidates.0.customer_email', $buyer->email)
                ->where('accounting.backfill_candidates.0.amount', 115)
                ->where('accounting.backfill_candidates.0.backfill_url', route('admin.payment-accounting.backfill', $payment, absolute: false)));

        $this->assertDatabaseCount('payment_accounting_syncs', 0);

        $this->actingAsMfa($admin)
            ->from(route('admin.payment-reconciliations.index'))
            ->post(route('admin.payment-accounting.backfill', $payment), [
                'reason' => 'Export the settled historical Stripe payment to the practice Xero ledger.',
            ])
            ->assertRedirect(route('admin.payment-reconciliations.index'))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseCount('payment_accounting_syncs', 0);

        $this->actingAsMfa($admin)
            ->post(route('admin.payment-accounting.backfill', $payment), [
                'reason' => 'Export the settled historical Stripe payment to the practice Xero ledger.',
                'confirmation' => '1',
            ])
            ->assertRedirect(route('admin.payment-reconciliations.index'));

        $this->assertDatabaseHas('payment_accounting_syncs', [
            'payment_id' => $payment->getKey(),
            'status' => PaymentAccountingSync::STATUS_FAILED,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'payment_accounting_sync.backfill_approved',
            'subject_type' => PaymentAccountingSync::class,
        ]);
    }

    public function test_a_super_admin_reconciles_a_verified_historical_quote_once_without_charging_or_refunding(): void
    {
        Notification::fake();
        [$purchase, $buyer, $payment] = $this->mismatchedPurchase();
        $admin = $this->superAdmin();

        $this->mock(StripeClient::class, function (MockInterface $stripe) use ($payment): void {
            $stripe->shouldReceive('findCharge')
                ->once()
                ->with('pi_reconcile_idea_validation', (string) $payment->idempotency_key, (string) $payment->getKey())
                ->andReturn(PaymentChargeLookup::succeeded(new PaymentChargeResult(
                    gateway: 'stripe',
                    gatewayRef: 'pi_reconcile_idea_validation',
                    status: 'succeeded',
                    amount: '115.00',
                    currency: 'NZD',
                )));
            $stripe->shouldNotReceive('createIdeaValidationPaymentIntent');
            $stripe->shouldNotReceive('charge');
            $stripe->shouldNotReceive('refund');
        });

        $payload = [
            'historical_amount_ex_gst' => '100.00',
            'historical_gst_amount' => '15.00',
            'reason' => 'The original customer quote and the succeeded Stripe PaymentIntent both confirm the historical price.',
            'confirmation' => '1',
        ];
        $this->actingAsMfa($admin)
            ->post(route('admin.payment-reconciliations.reconcile', $purchase), $payload)
            ->assertRedirect(route('admin.payment-reconciliations.index'));

        $purchase->refresh();
        $payment->refresh();
        $this->assertSame(IdeaValidationPurchase::STATUS_PAID, $purchase->status);
        $this->assertSame('100.00', $purchase->amount_ex_gst);
        $this->assertSame('15.00', $purchase->gst_amount);
        $this->assertSame('115.00', $purchase->amount_including_gst);
        $this->assertSame('100.00', data_get($purchase->package_snapshot, 'fixed_fee'));
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertNotNull($purchase->service_activation_id);
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->getKey()]);
        $this->assertDatabaseHas('entrepreneur_profiles', ['user_id' => $buyer->getKey()]);
        $this->assertDatabaseHas('service_activations', [
            'id' => $purchase->service_activation_id,
            'status' => ServiceActivation::STATUS_ACTIVE,
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_reference' => 'pi_reconcile_idea_validation',
        ]);
        $audit = AuditEvent::query()->where('action', 'idea_validation.payment_reconciled')->firstOrFail();
        $this->assertSame((string) $admin->getKey(), $audit->actor_user_key);
        $this->assertSame('11.50', data_get($audit->before, 'amount_including_gst'));
        $this->assertSame('115.00', data_get($audit->after, 'amount_including_gst'));
        $this->assertSame('pi_reconcile_idea_validation', data_get($audit->after, 'payment_reference'));
        Notification::assertSentTo($buyer, IdeaValidationPurchaseConfirmedNotification::class);
        Notification::assertSentTo($purchase->advisor, IdeaValidationPurchaseAdvisorNotification::class);

        $this->actingAsMfa($admin)
            ->post(route('admin.payment-reconciliations.reconcile', $purchase), $payload)
            ->assertRedirect(route('admin.payment-reconciliations.index'));

        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseCount('service_activations', 1);
        $this->assertSame(1, AuditEvent::query()->where('action', 'idea_validation.payment_reconciled')->count());
        $this->assertSame(1, EntrepreneurProfile::query()->where('user_id', $buyer->getKey())->count());
    }

    public function test_reconciliation_leaves_access_unavailable_when_stripe_does_not_confirm_payment(): void
    {
        [$purchase, , $payment] = $this->mismatchedPurchase();
        $admin = $this->superAdmin();

        $this->mock(StripeClient::class, function (MockInterface $stripe): void {
            $stripe->shouldReceive('findCharge')->once()->andReturn(PaymentChargeLookup::notCharged());
            $stripe->shouldNotReceive('charge');
            $stripe->shouldNotReceive('refund');
        });

        $this->actingAsMfa($admin)
            ->from(route('admin.payment-reconciliations.index'))
            ->post(route('admin.payment-reconciliations.reconcile', $purchase), [
                'historical_amount_ex_gst' => '100.00',
                'historical_gst_amount' => '15.00',
                'reason' => 'The original customer quote and the succeeded Stripe PaymentIntent both confirm the historical price.',
                'confirmation' => '1',
            ])
            ->assertRedirect(route('admin.payment-reconciliations.index'))
            ->assertSessionHasErrors('payment');

        $purchase->refresh();
        $payment->refresh();
        $this->assertSame(IdeaValidationPurchase::STATUS_PAYMENT_PROCESSING, $purchase->status);
        $this->assertSame('11.50', $purchase->amount_including_gst);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertDatabaseCount('service_activations', 0);
    }

    public function test_super_admin_reconciles_a_completed_external_stripe_refund_without_creating_another_refund(): void
    {
        Notification::fake();
        [$client, $buyer, $activation] = $this->refundableIdeaValidation(simulatedRefund: true);
        $admin = $this->superAdmin();

        $this->mock(StripeClient::class, function (MockInterface $stripe): void {
            $stripe->shouldReceive('findRefundsForPayment')
                ->twice()
                ->with('pi_external_refund')
                ->andReturn(PaymentRefundSearch::found([new PaymentRefundResult(
                    gateway: 'stripe',
                    gatewayRef: 're_live_external_refund',
                    status: 'succeeded',
                    amount: '115.00',
                    currency: 'NZD',
                    metadata: [
                        'live' => true,
                        'payment_intent' => 'pi_external_refund',
                    ],
                )]));
            $stripe->shouldNotReceive('refund');
        });

        $reconciler = app(ExternalStripeRefundReconciler::class);
        $candidates = $reconciler->candidates();
        $this->assertCount(1, $candidates);
        $this->assertSame($buyer->email, $candidates->first()['customer_email']);

        $reconciler->reconcile(
            $activation,
            $admin,
            'Stripe dashboard and the customer correspondence confirm the full Idea Validation refund.',
        );

        $this->assertSame(ClientStatus::SUSPENDED, $client->refresh()->status);
        $this->assertSame(ServiceActivation::STATUS_CANCELLED, $activation->refresh()->status);
        $this->assertSame(IdeaValidationCancellation::SUSPENSION_REASON, $buyer->refresh()->suspended_reason);
        $this->assertSame(
            EntrepreneurStage::CANCELLED,
            EntrepreneurProfile::query()->where('user_id', $buyer->getKey())->firstOrFail()->stage,
        );
        $this->assertDatabaseHas('payment_refunds', [
            'service_activation_id' => $activation->getKey(),
            'gateway_ref' => 're_live_external_refund',
            'status' => PaymentRefund::STATUS_ACCEPTED,
            'amount' => '115.00',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.idea_validation_external_refund_reconciled',
            'subject_id' => (string) $activation->getKey(),
        ]);

        $reconciler->reconcile(
            $activation,
            $admin,
            'Stripe dashboard and the customer correspondence confirm the full Idea Validation refund.',
        );

        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertSame(1, AuditEvent::query()
            ->where('action', 'entrepreneur.idea_validation_external_refund_reconciled')
            ->count());
    }

    public function test_external_refund_reconciliation_fails_closed_when_stripe_refund_is_for_another_payment(): void
    {
        [$client, $buyer, $activation] = $this->refundableIdeaValidation();
        $admin = $this->superAdmin();

        $this->mock(StripeClient::class, function (MockInterface $stripe): void {
            $stripe->shouldReceive('findRefundsForPayment')
                ->once()
                ->with('pi_external_refund')
                ->andReturn(PaymentRefundSearch::found([new PaymentRefundResult(
                    gateway: 'stripe',
                    gatewayRef: 're_other_payment',
                    status: 'succeeded',
                    amount: '115.00',
                    currency: 'NZD',
                    metadata: [
                        'live' => true,
                        'payment_intent' => 'pi_other_payment',
                    ],
                )]));
            $stripe->shouldNotReceive('refund');
        });

        try {
            app(ExternalStripeRefundReconciler::class)->reconcile(
                $activation,
                $admin,
                'Stripe dashboard and the customer correspondence confirm the full Idea Validation refund.',
            );
            $this->fail('A Stripe refund for another payment must not cancel this client account.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }

        $this->assertSame(ClientStatus::ACTIVE, $client->refresh()->status);
        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->refresh()->status);
        $this->assertNull($buyer->refresh()->suspended_at);
        $this->assertDatabaseHas('payment_refunds', [
            'service_activation_id' => $activation->getKey(),
            'status' => PaymentRefund::STATUS_FAILED,
        ]);
    }

    public function test_external_refund_reconciliation_requires_exactly_one_matching_completed_refund(): void
    {
        [$client, $buyer, $activation] = $this->refundableIdeaValidation();
        $admin = $this->superAdmin();

        $this->mock(StripeClient::class, function (MockInterface $stripe): void {
            $stripe->shouldReceive('findRefundsForPayment')
                ->once()
                ->with('pi_external_refund')
                ->andReturn(PaymentRefundSearch::found([
                    new PaymentRefundResult(
                        gateway: 'stripe',
                        gatewayRef: 're_first_matching_refund',
                        status: 'succeeded',
                        amount: '115.00',
                        currency: 'NZD',
                        metadata: ['payment_intent' => 'pi_external_refund'],
                    ),
                    new PaymentRefundResult(
                        gateway: 'stripe',
                        gatewayRef: 're_second_matching_refund',
                        status: 'succeeded',
                        amount: '115.00',
                        currency: 'NZD',
                        metadata: ['payment_intent' => 'pi_external_refund'],
                    ),
                ]));
            $stripe->shouldNotReceive('refund');
        });

        try {
            app(ExternalStripeRefundReconciler::class)->reconcile(
                $activation,
                $admin,
                'Stripe returned more than one completed refund for this payment.',
            );
            $this->fail('More than one matching completed refund must not cancel this client account.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }

        $this->assertSame(ClientStatus::ACTIVE, $client->refresh()->status);
        $this->assertSame(ServiceActivation::STATUS_ACTIVE, $activation->refresh()->status);
        $this->assertNull($buyer->refresh()->suspended_at);
    }

    /** @return array{0: IdeaValidationPurchase, 1: User, 2: Payment} */
    private function mismatchedPurchase(): array
    {
        $advisor = User::factory()->create([
            'name' => 'Idea Validation Advisor',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $buyer = User::factory()->create([
            'name' => 'Adele Customer',
            'email' => 'adele@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $buyer->assignRole(User::TYPE_ENTREPRENEUR);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::ENTREPRENEUR_MODULE,
            'status' => ClientStatus::PAUSED,
            'legal_name' => $buyer->name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => ['source' => 'payment-reconciliation-test'],
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $buyer->getKey(),
        ]);
        $terms = TermsVersion::query()->create([
            'document_scope' => TermsVersion::SCOPE_WEBSITE,
            'version' => 'payment-reconciliation-terms-v1',
            'title' => 'Payment reconciliation terms',
            'material' => true,
            'published_at' => now()->subMinute(),
            'notice_period_days' => 30,
        ]);
        $package = ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'package_name' => 'Idea Validation',
            'client_label' => 'Idea Validation',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 10,
            'deposit_percent' => 100,
            'currency' => 'NZD',
            'scope_description' => 'Idea validation.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ]);
        $payment = Payment::query()->create([
            'client_id' => $client->getKey(),
            'payment_schedule_id' => null,
            'amount' => '115.00',
            'currency' => 'NZD',
            'gateway' => 'stripe',
            'gateway_ref' => 'pi_reconcile_idea_validation',
            'idempotency_key' => 'idea-validation-reconciliation-'.$buyer->getKey(),
            'status' => Payment::STATUS_PENDING,
            'attempt' => 1,
        ]);
        $purchase = new IdeaValidationPurchase;
        $purchase->forceFill([
            'user_id' => $buyer->getKey(),
            'client_id' => $client->getKey(),
            'advisor_id' => $advisor->getKey(),
            'terms_version_id' => $terms->getKey(),
            'service_rate_package_id' => $package->getKey(),
            'payment_id' => $payment->getKey(),
            'status' => IdeaValidationPurchase::STATUS_PAYMENT_PROCESSING,
            'email_verified_at' => now(),
            'amount_ex_gst' => '10.00',
            'gst_amount' => '1.50',
            'amount_including_gst' => '11.50',
            'currency' => 'NZD',
            'package_snapshot' => [
                ...$package->snapshot(),
                'fixed_fee' => '10.00',
            ],
            'stripe_payment_intent_ref' => 'pi_reconcile_idea_validation',
            'payment_intent_created_at' => now()->subMinute(),
            'metadata' => ['source' => 'payment-reconciliation-test'],
        ])->save();

        return [$purchase->refresh()->load(['payment', 'user', 'advisor']), $buyer, $payment];
    }

    /** @return array{0: Client, 1: User, 2: ServiceActivation} */
    private function refundableIdeaValidation(bool $simulatedRefund = false): array
    {
        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $buyer = User::factory()->create([
            'name' => 'External refund founder',
            'email' => 'external-refund@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $buyer->assignRole(User::TYPE_ENTREPRENEUR);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::ENTREPRENEUR_MODULE,
            'status' => ClientStatus::ACTIVE,
            'legal_name' => $buyer->name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'registry_sources' => ['source' => 'external-refund-reconciliation-test'],
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $buyer->getKey(),
        ]);
        $package = ServiceRatePackage::query()->create([
            'service_type' => ServiceRatePackage::SERVICE_ENTREPRENEUR,
            'package_scope' => ServiceRatePackage::SCOPE_ENTREPRENEUR_IDEA_VALIDATION,
            'package_name' => 'Idea Validation',
            'client_label' => 'Idea Validation',
            'billing_model' => ServiceRatePackage::BILLING_FIXED_FEE,
            'fixed_fee' => 100,
            'currency' => 'NZD',
            'scope_description' => 'Idea validation only.',
            'is_active' => true,
            'effective_from' => now()->subMinute(),
        ]);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $buyer->getKey(),
            'client_id' => $client->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => $buyer->name,
            'email' => $buyer->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
        ]);
        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'advisor_id' => $advisor->getKey(),
            'approved_by_user_id' => $advisor->getKey(),
            'service_rate_package_id' => $package->getKey(),
            'service_type' => ServiceActivation::SERVICE_ENTREPRENEUR,
            'client_label' => 'Idea Validation',
            'status' => ServiceActivation::STATUS_ACTIVE,
            'selected_package_snapshot' => $package->snapshot(),
            'payment_status' => ServiceActivation::PAYMENT_PAID,
            'payment_completed_at' => now()->subHour(),
            'payment_completed_by_user_id' => $buyer->getKey(),
            'payment_reference' => 'pi_external_refund',
            'related_entrepreneur_profile_id' => $profile->getKey(),
        ]);
        PaymentRefund::query()->create([
            'client_id' => $client->getKey(),
            'service_activation_id' => $activation->getKey(),
            'requested_by_user_id' => $buyer->getKey(),
            'gateway' => 'stripe',
            'payment_reference' => 'pi_external_refund',
            'amount' => '115.00',
            'currency' => 'NZD',
            'gateway_ref' => $simulatedRefund ? 're_stripe_prior_simulated' : null,
            'status' => $simulatedRefund ? PaymentRefund::STATUS_ACCEPTED : PaymentRefund::STATUS_FAILED,
            'idempotency_key' => 'external-refund-recovery-'.$activation->getKey(),
            'failure_reason' => $simulatedRefund ? null : 'Stripe refund verification was unavailable.',
            'processed_at' => $simulatedRefund ? now()->subMinute() : null,
            'metadata' => $simulatedRefund ? ['fixture' => true] : null,
        ]);

        return [$client, $buyer, $activation];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }
}
