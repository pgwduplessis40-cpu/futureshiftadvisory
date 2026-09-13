<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IdeaValidationPurchase;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\IdeaValidationHistoricalQuote;
use App\Services\Payments\IdeaValidationPaymentReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class PaymentReconciliationController extends Controller
{
    public function __construct(private readonly IdeaValidationPaymentReconciliationService $reconciliations) {}

    public function index(Request $request): Response
    {
        $response = Inertia::render('admin/payment-reconciliations/Index', [
            'candidates' => $this->reconciliations
                ->candidates()
                ->map(fn (IdeaValidationPurchase $purchase): array => $this->candidatePayload($purchase))
                ->all(),
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

    /** @return array<string, mixed> */
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
