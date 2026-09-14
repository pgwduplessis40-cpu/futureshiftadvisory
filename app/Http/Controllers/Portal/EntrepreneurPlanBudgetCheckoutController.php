<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\EntrepreneurPlanBudgetPurchase;
use App\Models\EntrepreneurProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\IdeaValidationPaymentLedger;
use App\Services\Entrepreneurs\EntrepreneurPlanBudgetCheckout;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class EntrepreneurPlanBudgetCheckoutController extends Controller
{
    public function __construct(
        private readonly EntrepreneurPlanBudgetCheckout $checkout,
        private readonly IdeaValidationPaymentLedger $accounting,
    ) {}

    public function paymentIntent(Request $request, EntrepreneurPlanWorkspace $workspace): JsonResponse
    {
        $user = $workspace->user($request);
        $profile = $workspace->profileFor($user);

        try {
            $intent = $this->checkout->beginPayment($user, $profile);
        } catch (PaymentGatewayException $exception) {
            $reference = 'BPB-'.Str::upper(Str::random(8));
            report($exception);

            return response()->json([
                'code' => 'payment_setup_unavailable',
                'message' => 'We could not start secure payment right now. No payment has been taken. Please try again shortly, or contact Future Shift Advisory and quote reference '.$reference.'.',
                'support_reference' => $reference,
            ], 503);
        }

        $purchase = $this->checkoutPurchase($user, $profile);

        return response()->json([
            'publishable_key' => $intent->publishableKey,
            'client_secret' => $intent->clientSecret,
            'payment_intent_id' => $intent->paymentIntentRef,
            'fixture' => $intent->fixture,
            'amount_ex_gst' => $purchase?->amount_ex_gst !== null ? (float) $purchase->amount_ex_gst : null,
            'gst_amount' => $purchase?->gst_amount !== null ? (float) $purchase->gst_amount : null,
            'amount_including_gst' => $purchase?->amount_including_gst !== null ? (float) $purchase->amount_including_gst : null,
            'currency' => $purchase?->currency,
        ]);
    }

    public function confirmPayment(Request $request, EntrepreneurPlanWorkspace $workspace): JsonResponse
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:191', 'regex:/^pi_/'],
        ]);
        $user = $workspace->user($request);
        $profile = $workspace->profileFor($user);
        $purchase = $this->checkout->confirmPayment($user, $profile, $validated['payment_intent_id']);
        $this->syncAccounting($purchase?->payment);

        return response()->json([
            'paid' => $purchase !== null,
            'next_url' => $purchase !== null ? route('portal.entrepreneur.plan.show', absolute: false) : null,
        ], $purchase !== null ? 200 : 202);
    }

    public function confirmFixturePayment(Request $request, EntrepreneurPlanWorkspace $workspace): JsonResponse
    {
        $user = $workspace->user($request);
        $profile = $workspace->profileFor($user);
        $purchase = $this->checkout->confirmFixturePayment($user, $profile);
        $this->syncAccounting($purchase->payment);

        return response()->json([
            'paid' => true,
            'next_url' => route('portal.entrepreneur.plan.show', absolute: false),
        ]);
    }

    private function checkoutPurchase(User $user, EntrepreneurProfile $profile): ?EntrepreneurPlanBudgetPurchase
    {
        return EntrepreneurPlanBudgetPurchase::query()
            ->where('user_id', $user->getKey())
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->first();
    }

    private function syncAccounting(?Payment $payment): void
    {
        if (! $payment instanceof Payment || $payment->status !== Payment::STATUS_SUCCEEDED) {
            return;
        }

        try {
            $this->accounting->recordSucceededPayment($payment);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
