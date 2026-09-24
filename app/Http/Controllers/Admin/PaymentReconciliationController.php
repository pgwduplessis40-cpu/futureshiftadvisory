<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\PaymentAccountingSync;
use App\Models\PaymentRefund;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Services\Accounting\IdeaValidationPaymentLedger;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\ExternalStripeRefundReconciler;
use App\Services\Payments\IdeaValidationHistoricalQuote;
use App\Services\Payments\IdeaValidationPaymentReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class PaymentReconciliationController extends Controller
{
    public function __construct(
        private readonly IdeaValidationPaymentReconciliationService $reconciliations,
        private readonly ExternalStripeRefundReconciler $externalRefunds,
        private readonly IdeaValidationPaymentLedger $accountingLedger,
        private readonly AuditWriter $audit,
    ) {}

    public function index(Request $request): Response
    {
        $response = Inertia::render('admin/payment-reconciliations/Index', [
            'candidates' => $this->reconciliations
                ->candidates()
                ->map(fn (IdeaValidationPurchase $purchase): array => $this->candidatePayload($purchase))
                ->all(),
            'accounting' => [
                'backfill_candidates' => $this->accountingLedger
                    ->backfillCandidates()
                    ->map(fn (Payment $payment): array => $this->backfillPayload($payment))
                    ->all(),
                'sync_candidates' => $this->accountingLedger
                    ->outstanding()
                    ->map(fn (PaymentAccountingSync $sync): array => $this->syncPayload($sync))
                    ->all(),
                'refund_exceptions' => $this->accountingLedger
                    ->refundExceptions()
                    ->map(fn (PaymentRefund $refund): array => $this->refundPayload($refund))
                    ->all(),
                'external_refund_candidates' => $this->externalRefunds
                    ->candidates()
                    ->map(function (array $candidate): array {
                        return [
                            ...$candidate,
                            'reconcile_url' => route(
                                'admin.payment-refunds.reconcile',
                                $candidate['id'],
                                absolute: false,
                            ),
                        ];
                    })
                    ->all(),
            ],
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    public function reconcile(Request $request, IdeaValidationPurchase $purchase): RedirectResponse
    {
        $actor = $this->superAdmin($request);
        $validated = $request->validate([
            'historical_amount_ex_gst' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'historical_gst_amount' => ['required', 'string', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirmation' => ['accepted'],
        ]);
        $net = $this->normaliseMoney($validated['historical_amount_ex_gst']);
        $gst = $this->normaliseMoney($validated['historical_gst_amount']);
        $total = $this->moneyFromCents($this->cents($net) + $this->cents($gst));

        $this->reconciliations->reconcile(
            purchase: $purchase,
            actor: $actor,
            quote: new IdeaValidationHistoricalQuote($net, $gst, $total, $this->paymentCurrency($purchase)),
            reason: trim($validated['reason']),
        );

        return to_route('admin.payment-reconciliations.index')
            ->with('status', 'idea-validation-payment-reconciled');
    }

    public function backfill(Request $request, Payment $payment): RedirectResponse
    {
        $actor = $this->superAdmin($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirmation' => ['accepted'],
        ]);

        $sync = $this->accountingLedger->backfill($payment, $actor);
        $this->audit->record('payment_accounting_sync.backfill_approved', subject: $sync, actor: $actor, after: [
            'payment_id' => $payment->getKey(),
            'reason' => trim($validated['reason']),
            'status' => $sync->status,
        ]);

        return to_route('admin.payment-reconciliations.index')
            ->with('status', 'payment-accounting-backfill-started');
    }

    public function retry(Request $request, PaymentAccountingSync $sync): RedirectResponse
    {
        $actor = $this->superAdmin($request);
        $request->validate(['confirmation' => ['accepted']]);

        $this->accountingLedger->retry($sync, $actor);

        return to_route('admin.payment-reconciliations.index')
            ->with('status', 'payment-accounting-sync-retried');
    }

    public function reconcileExternalRefund(Request $request, ServiceActivation $serviceActivation): RedirectResponse
    {
        $actor = $this->superAdmin($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirmation' => ['accepted'],
        ]);

        $this->externalRefunds->reconcile(
            activation: $serviceActivation,
            actor: $actor,
            reason: trim($validated['reason']),
        );

        return to_route('admin.payment-reconciliations.index')
            ->with('status', 'external-stripe-refund-reconciled');
    }

    /**
     * @return array{
     *     id: string,
     *     customer_name: string|null,
     *     customer_email: string|null,
     *     created_at: string|null,
     *     currency: string,
     *     recorded_payment_amount: float,
     *     current_quote: array{
     *         amount_ex_gst: float|null,
     *         gst_amount: float|null,
     *         amount_including_gst: float|null
     *     },
     *     stripe_payment_intent_ref: string|null,
     *     reconcile_url: string
     * }
     */
    private function candidatePayload(IdeaValidationPurchase $purchase): array
    {
        $payment = $purchase->payment;
        if (! $payment instanceof Payment) {
            throw new \LogicException('A payment reconciliation candidate must have a payment.');
        }

        return [
            'id' => $purchase->getKey(),
            'customer_name' => $purchase->user?->name,
            'customer_email' => $purchase->user?->email,
            'created_at' => $purchase->created_at?->toIso8601String(),
            'currency' => strtoupper((string) $payment->currency),
            'recorded_payment_amount' => (float) $payment->amount,
            'current_quote' => [
                'amount_ex_gst' => $purchase->amount_ex_gst !== null ? (float) $purchase->amount_ex_gst : null,
                'gst_amount' => $purchase->gst_amount !== null ? (float) $purchase->gst_amount : null,
                'amount_including_gst' => $purchase->amount_including_gst !== null ? (float) $purchase->amount_including_gst : null,
            ],
            'stripe_payment_intent_ref' => $payment->gateway_ref,
            'reconcile_url' => route('admin.payment-reconciliations.reconcile', $purchase, absolute: false),
        ];
    }

    /**
     * @return array{id:string,customer_name:string|null,customer_email:string|null,currency:string,amount:float,payment_reference:string|null,backfill_url:string}
     */
    private function backfillPayload(Payment $payment): array
    {
        return [
            'id' => $payment->getKey(),
            'customer_name' => $payment->ideaValidationPurchase?->user?->name,
            'customer_email' => $payment->ideaValidationPurchase?->user?->email,
            'currency' => strtoupper((string) $payment->currency),
            'amount' => (float) $payment->amount,
            'payment_reference' => $payment->gateway_ref,
            'backfill_url' => route('admin.payment-accounting.backfill', $payment, absolute: false),
        ];
    }

    /**
     * @return array{id:string,customer_name:string|null,customer_email:string|null,currency:string,amount:float,status:string,refund_status:string,error_message:string|null,refund_error_message:string|null,payment_reference:string|null,retry_url:string}
     */
    private function syncPayload(PaymentAccountingSync $sync): array
    {
        $payment = $sync->payment;

        return [
            'id' => $sync->getKey(),
            'customer_name' => $payment?->ideaValidationPurchase?->user?->name,
            'customer_email' => $payment?->ideaValidationPurchase?->user?->email,
            'currency' => strtoupper((string) $sync->currency),
            'amount' => (float) $sync->amount_including_gst,
            'status' => $sync->status,
            'refund_status' => $sync->refund_status,
            'error_message' => $sync->error_message,
            'refund_error_message' => $sync->refund_error_message,
            'payment_reference' => $payment?->gateway_ref,
            'retry_url' => route('admin.payment-accounting.retry', $sync, absolute: false),
        ];
    }

    /**
     * @return array{id:string,customer_name:string|null,customer_email:string|null,currency:string,amount:float,status:string,failure_reason:string|null,payment_reference:string,refund_reference:string|null}
     */
    private function refundPayload(PaymentRefund $refund): array
    {
        return [
            'id' => $refund->getKey(),
            'customer_name' => $refund->requestedBy?->name,
            'customer_email' => $refund->requestedBy?->email,
            'currency' => strtoupper((string) $refund->currency),
            'amount' => (float) $refund->amount,
            'status' => $refund->status,
            'failure_reason' => $refund->failure_reason,
            'payment_reference' => $refund->payment_reference,
            'refund_reference' => $refund->gateway_ref,
        ];
    }

    private function paymentCurrency(IdeaValidationPurchase $purchase): string
    {
        $payment = $purchase->payment;
        if (! $payment instanceof Payment) {
            throw new \LogicException('A payment reconciliation requires a payment.');
        }

        return strtoupper((string) $payment->currency);
    }

    private function normaliseMoney(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad($fraction, 2, '0');
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function moneyFromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function superAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_SUPER_ADMIN, 403);

        return $user;
    }
}
