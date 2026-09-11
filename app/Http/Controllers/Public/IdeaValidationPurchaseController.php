<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\IdeaValidationPurchase;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\IdeaValidationEmailVerificationNotification;
use App\Services\Entrepreneurs\IdeaValidationCheckout;
use App\Services\Terms\TermsAcceptanceGate;
use App\Support\RequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class IdeaValidationPurchaseController extends Controller
{
    public function __construct(
        private readonly IdeaValidationCheckout $checkout,
        private readonly TermsAcceptanceGate $terms,
        private readonly RequestContext $context,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $purchase = $user instanceof User ? $this->checkout->purchaseFor($user) : null;
        if ($purchase instanceof IdeaValidationPurchase && $purchase->status === IdeaValidationPurchase::STATUS_PAID) {
            $request->session()->put('fsa.idea_validation_purchase_flow', true);

            return redirect()->route('mfa.setup');
        }

        $terms = $this->terms->latestPublishedVersion(withClauses: false);

        return Inertia::render('public/idea-validation-purchase', [
            'state' => $purchase instanceof IdeaValidationPurchase
                ? ($purchase->email_verified_at === null ? 'verify_email' : 'checkout')
                : 'register',
            'purchase' => $purchase instanceof IdeaValidationPurchase ? $this->purchasePayload($purchase) : null,
            'terms' => $terms instanceof TermsVersion ? [
                'id' => $terms->getKey(),
                'version' => $terms->version,
                'title' => $terms->title,
                'url' => route('public.terms-and-privacy', absolute: false),
            ] : null,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        abort_if($request->user() instanceof User, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms_version_id' => ['required', 'uuid'],
            'terms_accepted' => ['accepted'],
        ]);

        $purchase = $this->checkout->register($request, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'terms_version_id' => $validated['terms_version_id'],
        ]);
        $user = $purchase->user;
        abort_unless($user instanceof User, 500);

        Auth::login($user);
        $request->session()->regenerate();
        $user->notify(new IdeaValidationEmailVerificationNotification($purchase));

        return to_route('public.validate-idea.purchase')->with('status', 'idea-validation-verification-sent');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $purchase = $this->checkout->purchaseFor($user);
        abort_unless($purchase instanceof IdeaValidationPurchase, 404);

        if ($purchase->email_verified_at === null) {
            $user->notify(new IdeaValidationEmailVerificationNotification($purchase));
        }

        return to_route('public.validate-idea.purchase')->with('status', 'idea-validation-verification-resent');
    }

    public function verify(Request $request, string $purchase, string $hash): RedirectResponse
    {
        $record = $this->context->withSystemContext(fn (): IdeaValidationPurchase => IdeaValidationPurchase::query()
            ->with('user')
            ->whereKey($purchase)
            ->firstOrFail());
        $record = $this->checkout->markEmailVerified($record, $hash);
        $user = $record->user;
        abort_unless($user instanceof User, 500);

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('public.validate-idea.purchase')->with('status', 'idea-validation-email-verified');
    }

    public function paymentIntent(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $purchase = $this->checkout->purchaseFor($user);
        abort_unless($purchase instanceof IdeaValidationPurchase, 404);

        $intent = $this->checkout->beginPayment($user, $purchase);
        $purchase = $this->checkout->purchaseFor($user);
        abort_unless($purchase instanceof IdeaValidationPurchase, 404);

        return response()->json([
            'publishable_key' => $intent->publishableKey,
            'client_secret' => $intent->clientSecret,
            'payment_intent_id' => $intent->paymentIntentRef,
            'fixture' => $intent->fixture,
            'amount_ex_gst' => $purchase->amount_ex_gst !== null ? (float) $purchase->amount_ex_gst : null,
            'gst_amount' => $purchase->gst_amount !== null ? (float) $purchase->gst_amount : null,
            'amount_including_gst' => $purchase->amount_including_gst !== null ? (float) $purchase->amount_including_gst : null,
            'currency' => $purchase->currency,
        ]);
    }

    public function confirmPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:191', 'regex:/^pi_/'],
        ]);
        $user = $this->currentUser($request);
        $purchase = $this->checkout->purchaseFor($user);
        abort_unless($purchase instanceof IdeaValidationPurchase, 404);

        $settled = $this->checkout->confirmPayment($user, $purchase, $validated['payment_intent_id']);
        if ($settled instanceof IdeaValidationPurchase) {
            $request->session()->put('fsa.idea_validation_purchase_flow', true);
        }

        return response()->json([
            'paid' => $settled instanceof IdeaValidationPurchase,
            'next_url' => $settled instanceof IdeaValidationPurchase ? route('mfa.setup', absolute: false) : null,
        ], $settled instanceof IdeaValidationPurchase ? 200 : 202);
    }

    public function confirmFixturePayment(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $purchase = $this->checkout->purchaseFor($user);
        abort_unless($purchase instanceof IdeaValidationPurchase, 404);

        $purchase = $this->checkout->confirmFixturePayment($user, $purchase);
        $request->session()->put('fsa.idea_validation_purchase_flow', true);

        return response()->json([
            'paid' => true,
            'next_url' => route('mfa.setup', absolute: false),
            'purchase' => $this->purchasePayload($purchase),
        ]);
    }

    private function currentUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array{
     *     id: int|string,
     *     status: string,
     *     email_verified_at: string|null,
     *     amount_ex_gst: float|null,
     *     gst_amount: float|null,
     *     amount_including_gst: float|null,
     *     currency: string|null
     * }
     */
    private function purchasePayload(IdeaValidationPurchase $purchase): array
    {
        return [
            'id' => $purchase->getKey(),
            'status' => $purchase->status,
            'email_verified_at' => $purchase->email_verified_at?->toIso8601String(),
            'amount_ex_gst' => $purchase->amount_ex_gst !== null ? (float) $purchase->amount_ex_gst : null,
            'gst_amount' => $purchase->gst_amount !== null ? (float) $purchase->gst_amount : null,
            'amount_including_gst' => $purchase->amount_including_gst !== null ? (float) $purchase->amount_including_gst : null,
            'currency' => $purchase->currency,
        ];
    }
}
