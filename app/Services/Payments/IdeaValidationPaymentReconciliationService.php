<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\User;
use App\Services\Entrepreneurs\IdeaValidationCheckout;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Support\RequestContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reconciles the exceptional case where Stripe has confirmed an Idea
 * Validation payment but the stored historical quote no longer matches it.
 *
 * This is intentionally separate from webhook settlement: it is a
 * super-admin, evidence-based recovery path and never creates a new Stripe
 * PaymentIntent, charge, refund, or webhook replay.
 */
final class IdeaValidationPaymentReconciliationService
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly IdeaValidationCheckout $checkout,
        private readonly RequestContext $context,
    ) {}

    /** @return Collection<int, IdeaValidationPurchase> */
    public function candidates(): Collection
    {
        return $this->context->withSystemContext(function (): Collection {
            return IdeaValidationPurchase::query()
                ->with(['payment', 'user'])
                ->whereNotNull('payment_id')
                ->whereNotNull('stripe_payment_intent_ref')
                ->whereNull('paid_at')
                ->orderBy('created_at')
                ->get()
                ->filter(function (IdeaValidationPurchase $purchase): bool {
                    return $purchase->payment instanceof Payment
                        && $purchase->payment->gateway === 'stripe'
                        && ! blank($purchase->payment->gateway_ref)
                        && hash_equals((string) $purchase->stripe_payment_intent_ref, (string) $purchase->payment->gateway_ref)
                        && $this->checkout->purchasePaymentQuoteMismatchReason($purchase) !== null;
                })
                ->values();
        });
    }

    public function reconcile(
        IdeaValidationPurchase $purchase,
        User $actor,
        IdeaValidationHistoricalQuote $quote,
        string $reason,
    ): IdeaValidationPurchase {
        [$purchase, $payment] = $this->context->withSystemContext(function () use ($purchase): array {
            return DB::transaction(function () use ($purchase): array {
                $purchase = IdeaValidationPurchase::query()
                    ->whereKey($purchase->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($purchase->paid_at !== null || $purchase->status === IdeaValidationPurchase::STATUS_PAID) {
                    return [$purchase, null];
                }
                if ($purchase->payment_id === null) {
                    throw ValidationException::withMessages([
                        'payment' => 'This purchase has no recorded payment to reconcile.',
                    ]);
                }

                $payment = Payment::query()
                    ->whereKey($purchase->payment_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($payment->gateway !== 'stripe'
                    || blank($payment->gateway_ref)
                    || blank($purchase->stripe_payment_intent_ref)
                    || ! hash_equals((string) $purchase->stripe_payment_intent_ref, (string) $payment->gateway_ref)) {
                    throw ValidationException::withMessages([
                        'payment' => 'This purchase has no stored Stripe payment reference to verify.',
                    ]);
                }
                if ($this->checkout->purchasePaymentQuoteMismatchReason($purchase) === null) {
                    throw ValidationException::withMessages([
                        'payment' => 'This purchase does not have a payment quote discrepancy to reconcile.',
                    ]);
                }

                return [$purchase, $payment];
            });
        });
        if (! $payment instanceof Payment) {
            return $purchase->refresh();
        }

        $lookup = $this->stripe->findCharge(
            $purchase->stripe_payment_intent_ref,
            (string) $payment->idempotency_key,
            (string) $payment->getKey(),
        );
        if (! $lookup->isSucceeded() || ! $lookup->charge instanceof PaymentChargeResult) {
            throw ValidationException::withMessages([
                'payment' => 'Stripe does not currently confirm this payment as succeeded. No access was activated.',
            ]);
        }

        $charge = $lookup->charge;
        $this->assertVerifiedChargeMatchesPayment($payment, $charge);
        $this->assertHistoricalQuoteMatchesPayment($quote, $payment);

        return $this->checkout->settleReconciledStripePayment(
            payment: $payment,
            charge: $charge,
            quote: $quote,
            actor: $actor,
            reason: $reason,
        );
    }

    private function assertVerifiedChargeMatchesPayment(Payment $payment, PaymentChargeResult $charge): void
    {
        if ($charge->gateway !== 'stripe'
            || ! hash_equals((string) $payment->gateway_ref, $charge->gatewayRef)
            || strtoupper($charge->currency) !== strtoupper((string) $payment->currency)
            || $this->cents($charge->amount) !== $this->cents((string) $payment->amount)) {
            throw ValidationException::withMessages([
                'payment' => 'The Stripe payment evidence does not match the recorded payment. No access was activated.',
            ]);
        }
    }

    private function assertHistoricalQuoteMatchesPayment(IdeaValidationHistoricalQuote $quote, Payment $payment): void
    {
        if (strtoupper($quote->currency) !== strtoupper((string) $payment->currency)
            || $this->cents($quote->amountExGst) <= 0
            || $this->cents($quote->amountExGst) + $this->cents($quote->gstAmount) !== $this->cents($quote->amountIncludingGst)
            || $this->cents($quote->amountIncludingGst) !== $this->cents((string) $payment->amount)) {
            throw ValidationException::withMessages([
                'historical_amount_ex_gst' => 'The historical net amount and GST must total the verified Stripe payment exactly.',
            ]);
        }
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
