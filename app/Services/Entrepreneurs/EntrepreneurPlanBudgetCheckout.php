<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\EntrepreneurPlanBudgetPurchase;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\Payment;
use App\Models\ServiceRatePackage;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Payments\GstCalculator;
use App\Services\Payments\IdeaValidationPaymentIntent;
use App\Services\Payments\IdeaValidationPaymentIntentRequest;
use App\Services\Payments\PaymentChargeResult;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\ReceiptGenerator;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Owns the authenticated, fixed-price BP&B add-on checkout for an existing
 * Idea Validation client. The browser callback and Stripe webhook converge on
 * the same settlement path.
 */
final class EntrepreneurPlanBudgetCheckout
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly GstCalculator $gst,
        private readonly ReceiptGenerator $receipts,
        private readonly EntrepreneurPlanBudgetProvisioner $provisioner,
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    public function beginPayment(User $user, EntrepreneurProfile $profile): IdeaValidationPaymentIntent
    {
        [$purchase, $payment] = $this->context->withSystemContext(function () use ($user, $profile): array {
            return DB::transaction(function () use ($user, $profile): array {
                $this->assertOwnerAndApproved($user, $profile);

                $purchase = EntrepreneurPlanBudgetPurchase::query()
                    ->where('entrepreneur_profile_id', $profile->getKey())
                    ->lockForUpdate()
                    ->first();
                if ($purchase instanceof EntrepreneurPlanBudgetPurchase && $purchase->status === EntrepreneurPlanBudgetPurchase::STATUS_PAID) {
                    throw ValidationException::withMessages(['checkout' => 'Business Plan & Budget has already been purchased for this idea.']);
                }

                if (! $purchase instanceof EntrepreneurPlanBudgetPurchase) {
                    if ($profile->client_id === null) {
                        throw new \LogicException('An Idea Validation client record is required before purchasing Business Plan & Budget.');
                    }

                    $purchase = EntrepreneurPlanBudgetPurchase::query()->create([
                        'user_id' => $user->getKey(),
                        'client_id' => $profile->client_id,
                        'entrepreneur_profile_id' => $profile->getKey(),
                        'advisor_id' => $profile->assigned_advisor_id,
                        'status' => EntrepreneurPlanBudgetPurchase::STATUS_PAYMENT_PENDING,
                    ]);
                }

                $payment = $purchase->payment_id !== null
                    ? Payment::query()->whereKey($purchase->payment_id)->lockForUpdate()->first()
                    : null;
                if ($payment instanceof Payment) {
                    if ($this->quoteMismatchReason($purchase, $payment) !== null) {
                        throw ValidationException::withMessages(['checkout' => 'This purchase has a pricing discrepancy and requires support review. No further payment will be taken.']);
                    }

                    $purchase->forceFill(['status' => EntrepreneurPlanBudgetPurchase::STATUS_PAYMENT_PROCESSING])->save();

                    return [$purchase->refresh(), $payment->refresh()];
                }

                $package = $this->planBudgetPackage();
                $split = $package->paymentSplit();
                if ($split['requires_bank_transfer'] || $split['deposit_percent'] !== 100.0) {
                    throw ValidationException::withMessages(['checkout' => 'Configure the Business Plan & Budget Service Rate as a full card payment before enabling self-service checkout.']);
                }

                $exclusive = number_format((float) $package->fixed_fee, 2, '.', '');
                $gst = $this->gst->gstFromExclusive($exclusive);
                $gross = $this->gst->grossFromExclusive($exclusive);
                $currency = strtoupper((string) ($package->currency ?: 'NZD'));
                $payment = Payment::query()->create([
                    'client_id' => $purchase->client_id,
                    'payment_schedule_id' => null,
                    'amount' => $gross,
                    'currency' => $currency,
                    'gateway' => 'stripe',
                    'idempotency_key' => 'entrepreneur-plan-budget-'.$purchase->getKey(),
                    'status' => Payment::STATUS_PENDING,
                    'attempt' => 1,
                ]);

                $purchase->forceFill([
                    'service_rate_package_id' => $package->getKey(),
                    'payment_id' => $payment->getKey(),
                    'status' => EntrepreneurPlanBudgetPurchase::STATUS_PAYMENT_PROCESSING,
                    'amount_ex_gst' => $exclusive,
                    'gst_amount' => $gst,
                    'amount_including_gst' => $gross,
                    'currency' => $currency,
                    'package_snapshot' => $package->snapshot(),
                ])->save();

                return [$purchase->refresh(), $payment->refresh()];
            });
        });

        try {
            $intent = $this->stripe->createIdeaValidationPaymentIntent(new IdeaValidationPaymentIntentRequest(
                purchaseId: (string) $purchase->getKey(),
                paymentId: (string) $payment->getKey(),
                clientId: (string) $purchase->client_id,
                customerEmail: $user->email,
                customerName: $user->name,
                amount: (string) $payment->amount,
                currency: $payment->currency,
                idempotencyKey: (string) $payment->idempotency_key,
                metadata: ['entrepreneur_plan_budget_purchase_id' => (string) $purchase->getKey()],
                purchaseType: 'entrepreneur_plan_budget',
                description: 'Future Shift Advisory Business Plan & Budget add-on',
            ));
        } catch (Throwable $exception) {
            $this->restorePaymentPendingAfterSetupFailure($purchase, $payment);

            throw new PaymentGatewayException('Secure payment is temporarily unavailable.', previous: $exception);
        }

        $this->context->withSystemContext(function () use ($purchase, $payment, $intent): void {
            DB::transaction(function () use ($purchase, $payment, $intent): void {
                $lockedPurchase = EntrepreneurPlanBudgetPurchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
                $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $lockedPayment->forceFill(['gateway' => 'stripe', 'gateway_ref' => $intent->paymentIntentRef])->save();
                $lockedPurchase->forceFill([
                    'stripe_payment_intent_ref' => $intent->paymentIntentRef,
                    'payment_intent_created_at' => $lockedPurchase->payment_intent_created_at ?? now(),
                    'metadata' => [
                        ...(array) ($lockedPurchase->metadata ?? []),
                        'stripe_fixture' => $intent->fixture,
                    ],
                ])->save();
            });
        });

        return $intent;
    }

    public function confirmPayment(User $user, EntrepreneurProfile $profile, string $paymentIntentRef): ?EntrepreneurPlanBudgetPurchase
    {
        $purchase = $this->ownedPurchase($user, $profile);
        if ($purchase->paid_at !== null) {
            return $purchase;
        }

        abort_unless(hash_equals((string) $purchase->stripe_payment_intent_ref, $paymentIntentRef), 403);
        abort_unless($purchase->payment_id !== null, 422);
        $payment = $this->context->withSystemContext(fn (): Payment => Payment::query()->whereKey($purchase->payment_id)->firstOrFail());
        $lookup = $this->stripe->findCharge($paymentIntentRef, (string) $payment->idempotency_key, (string) $payment->getKey());
        if (! $lookup->isSucceeded() || ! $lookup->charge instanceof PaymentChargeResult) {
            return null;
        }

        return $this->settlePayment($payment, $lookup->charge, now());
    }

    public function confirmFixturePayment(User $user, EntrepreneurProfile $profile): EntrepreneurPlanBudgetPurchase
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $purchase = $this->ownedPurchase($user, $profile);
        abort_unless((bool) data_get($purchase->metadata, 'stripe_fixture', false), 404);
        abort_unless($purchase->payment_id !== null && $purchase->stripe_payment_intent_ref !== null, 422);
        $payment = $this->context->withSystemContext(fn (): Payment => Payment::query()->whereKey($purchase->payment_id)->firstOrFail());

        return $this->settlePayment($payment, new PaymentChargeResult(
            gateway: 'stripe',
            gatewayRef: $purchase->stripe_payment_intent_ref,
            status: 'succeeded',
            amount: (string) $payment->amount,
            currency: $payment->currency,
            metadata: ['fixture' => true],
        ), now());
    }

    public function settleFromWebhook(Payment $payment, string $gatewayRef, \DateTimeInterface $processedAt): ?EntrepreneurPlanBudgetPurchase
    {
        $purchase = $this->context->withSystemContext(fn (): ?EntrepreneurPlanBudgetPurchase => EntrepreneurPlanBudgetPurchase::query()
            ->where('payment_id', $payment->getKey())
            ->first());
        if (! $purchase instanceof EntrepreneurPlanBudgetPurchase) {
            return null;
        }

        return $this->settlePayment($payment, new PaymentChargeResult(
            gateway: 'stripe',
            gatewayRef: $gatewayRef,
            status: 'succeeded',
            amount: (string) $payment->amount,
            currency: $payment->currency,
        ), $processedAt);
    }

    public function paymentQuoteMismatchReason(Payment $payment): ?string
    {
        $purchase = $this->context->withSystemContext(fn (): ?EntrepreneurPlanBudgetPurchase => EntrepreneurPlanBudgetPurchase::query()
            ->where('payment_id', $payment->getKey())
            ->first());

        return $purchase instanceof EntrepreneurPlanBudgetPurchase
            ? $this->quoteMismatchReason($purchase, $payment)
            : null;
    }

    private function settlePayment(Payment $payment, PaymentChargeResult $charge, \DateTimeInterface $processedAt): EntrepreneurPlanBudgetPurchase
    {
        $purchase = $this->context->withSystemContext(function () use ($payment, $charge, $processedAt): EntrepreneurPlanBudgetPurchase {
            return DB::transaction(function () use ($payment, $charge, $processedAt): EntrepreneurPlanBudgetPurchase {
                $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $purchase = EntrepreneurPlanBudgetPurchase::query()
                    ->with(['user', 'entrepreneurProfile'])
                    ->where('payment_id', $payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($this->quoteMismatchReason($purchase, $payment) !== null) {
                    throw new \LogicException('The Business Plan & Budget purchase quote does not match its payment record.');
                }
                $this->assertChargeMatches($payment, $purchase, $charge);

                $payment->forceFill([
                    'gateway' => 'stripe',
                    'gateway_ref' => $charge->gatewayRef,
                    'status' => Payment::STATUS_SUCCEEDED,
                    'failed_reason' => null,
                    'processed_at' => $payment->processed_at ?? $processedAt,
                ])->save();
                $receipt = $this->receipts->create($payment->refresh());

                if ($purchase->paid_at === null) {
                    $purchase->forceFill([
                        'status' => EntrepreneurPlanBudgetPurchase::STATUS_PAID,
                        'paid_at' => $processedAt,
                    ])->save();
                    $this->audit->record('entrepreneur.plan_budget_purchase_paid', subject: $purchase, actor: $purchase->user, after: [
                        'payment_id' => $payment->getKey(),
                        'payment_reference' => $charge->gatewayRef,
                        'receipt_id' => $receipt->getKey(),
                    ]);
                }

                return $purchase->refresh()->load('entrepreneurProfile');
            });
        });

        if ($purchase->entrepreneurProfile instanceof EntrepreneurProfile) {
            $this->provisioner->provision($purchase->entrepreneurProfile, $purchase->user);
        }

        return $purchase->refresh();
    }

    private function ownedPurchase(User $user, EntrepreneurProfile $profile): EntrepreneurPlanBudgetPurchase
    {
        $this->assertOwner($user, $profile);

        return $this->context->withSystemContext(fn (): EntrepreneurPlanBudgetPurchase => EntrepreneurPlanBudgetPurchase::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail());
    }

    private function restorePaymentPendingAfterSetupFailure(EntrepreneurPlanBudgetPurchase $purchase, Payment $payment): void
    {
        $this->context->withSystemContext(function () use ($purchase, $payment): void {
            DB::transaction(function () use ($purchase, $payment): void {
                $lockedPurchase = EntrepreneurPlanBudgetPurchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
                $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                if ($lockedPurchase->stripe_payment_intent_ref === null) {
                    $lockedPurchase->forceFill(['status' => EntrepreneurPlanBudgetPurchase::STATUS_PAYMENT_PENDING])->save();
                }

                if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                    $lockedPayment->forceFill(['status' => Payment::STATUS_PENDING])->save();
                }
            });
        });
    }

    private function assertOwnerAndApproved(User $user, EntrepreneurProfile $profile): IdeaValidation
    {
        $this->assertOwner($user, $profile);
        $validation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNotNull('advisor_gate_passed_at')
            ->latest('advisor_gate_passed_at')
            ->first();
        if (! $validation instanceof IdeaValidation) {
            throw ValidationException::withMessages(['checkout' => 'Business Plan & Budget can be purchased after your advisor approves the Idea Validation.']);
        }

        return $validation;
    }

    private function assertOwner(User $user, EntrepreneurProfile $profile): void
    {
        abort_unless((string) $profile->user_id === (string) $user->getKey(), 403);
    }

    private function planBudgetPackage(): ServiceRatePackage
    {
        $now = now();
        $package = ServiceRatePackage::query()
            ->where('service_type', ServiceRatePackage::SERVICE_ENTREPRENEUR)
            ->where('package_scope', ServiceRatePackage::SCOPE_ENTREPRENEUR_PLAN_BUDGET)
            ->where('billing_model', ServiceRatePackage::BILLING_FIXED_FEE)
            ->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->whereNotNull('fixed_fee')
            ->where('fixed_fee', '>', 0)
            ->latest('effective_from')
            ->first();
        if (! $package instanceof ServiceRatePackage) {
            throw ValidationException::withMessages(['checkout' => 'Business Plan & Budget checkout is temporarily unavailable because a current Service Rate could not be found.']);
        }

        return $package;
    }

    private function assertChargeMatches(Payment $payment, EntrepreneurPlanBudgetPurchase $purchase, PaymentChargeResult $charge): void
    {
        if ($charge->gateway !== 'stripe'
            || ! hash_equals((string) $purchase->stripe_payment_intent_ref, $charge->gatewayRef)
            || strtoupper($charge->currency) !== strtoupper($payment->currency)
            || number_format((float) $charge->amount, 2, '.', '') !== number_format((float) $payment->amount, 2, '.', '')) {
            throw new \LogicException('Stripe payment confirmation does not match the Business Plan & Budget purchase.');
        }
    }

    private function quoteMismatchReason(EntrepreneurPlanBudgetPurchase $purchase, Payment $payment): ?string
    {
        if ($purchase->amount_including_gst === null || blank($purchase->currency)) {
            return 'entrepreneur_plan_budget_quote_missing';
        }
        if (number_format((float) $purchase->amount_including_gst, 2, '.', '') !== number_format((float) $payment->amount, 2, '.', '')) {
            return 'entrepreneur_plan_budget_quote_amount_mismatch';
        }

        return strtoupper((string) $purchase->currency) === strtoupper($payment->currency)
            ? null
            : 'entrepreneur_plan_budget_quote_currency_mismatch';
    }
}
