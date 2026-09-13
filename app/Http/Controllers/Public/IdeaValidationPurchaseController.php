<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\IdeaValidationPurchase;
use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\IdeaValidationEmailVerificationNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\IdeaValidationCheckout;
use App\Services\Entrepreneurs\IdeaValidationRegistrationConflict;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Terms\TermsAcceptanceGate;
use App\Support\RequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class IdeaValidationPurchaseController extends Controller
{
    private const CHECKOUT_PURCHASE_SESSION_KEY = 'fsa.idea_validation_checkout_purchase_id';

    public function __construct(
        private readonly IdeaValidationCheckout $checkout,
        private readonly TermsAcceptanceGate $terms,
        private readonly RequestContext $context,
        private readonly AuditWriter $audit,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $purchase = $this->checkout->purchaseFor($user);
            if ($purchase instanceof IdeaValidationPurchase && $purchase->status === IdeaValidationPurchase::STATUS_PAID) {
                $request->session()->put('fsa.idea_validation_purchase_flow', true);

                return redirect()->route('mfa.setup');
            }

            return $this->renderPurchasePage($request, state: 'existing_session');
        }

        $purchase = $this->checkoutPurchase($request);

        if ($purchase instanceof IdeaValidationPurchase && $purchase->status === IdeaValidationPurchase::STATUS_PAID) {
            return redirect()->route('login')->with('status', 'Your Idea Validation payment is complete. Sign in to continue.');
        }

        return $this->renderPurchasePage(
            $request,
            state: $purchase instanceof IdeaValidationPurchase
                ? ($purchase->email_verified_at === null ? 'verify_email' : 'checkout')
                : 'register',
            purchase: $purchase,
        );
    }

    public function register(Request $request): RedirectResponse
    {
        if ($request->user() instanceof User) {
            return to_route('public.validate-idea.purchase')->withErrors([
                'checkout' => 'A portal account is already signed in. For account safety, sign out or use a private browser window before creating an Idea Validation account.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms_version_id' => ['required', 'uuid'],
            'terms_accepted' => ['accepted'],
        ]);

        try {
            $purchase = $this->checkout->register($request, [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'terms_version_id' => $validated['terms_version_id'],
            ]);
        } catch (IdeaValidationRegistrationConflict $conflict) {
            return to_route('public.validate-idea.purchase')
                ->with('idea_validation_account_conflict', $conflict->conflict);
        }
        $user = $purchase->user;
        abort_unless($user instanceof User, 500);

        $request->session()->regenerate();
        $request->session()->put(self::CHECKOUT_PURCHASE_SESSION_KEY, $purchase->getKey());
        $user->notify(new IdeaValidationEmailVerificationNotification($purchase));

        return to_route('public.validate-idea.purchase')->with('status', 'idea-validation-verification-sent');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $purchase = $this->checkoutPurchase($request);
        abort_unless($purchase instanceof IdeaValidationPurchase, 403);
        $user = $this->purchaseUser($purchase);

        if ($purchase->email_verified_at === null) {
            $user->notify(new IdeaValidationEmailVerificationNotification($purchase));
        }

        return to_route('public.validate-idea.purchase')->with('status', 'idea-validation-verification-resent');
    }

    public function verify(string $purchase, string $hash): Response
    {
        $record = $this->context->withSystemContext(fn (): IdeaValidationPurchase => IdeaValidationPurchase::query()
            ->with('user')
            ->whereKey($purchase)
            ->firstOrFail());
        $user = $record->user;
        abort_unless($user instanceof User, 500);

        $record = $this->checkout->markEmailVerified($record, $hash);

        return Inertia::render('public/idea-validation-email-verified');
    }

    private function renderPurchasePage(Request $request, string $state, ?IdeaValidationPurchase $purchase = null): Response
    {
        $terms = $this->terms->latestPublishedVersion(
            withClauses: false,
            documentScope: TermsVersion::SCOPE_WEBSITE,
        );

        return Inertia::render('public/idea-validation-purchase', [
            'state' => $state,
            'accountConflict' => $this->accountConflict($request),
            'purchase' => $purchase instanceof IdeaValidationPurchase ? $this->purchasePayload($purchase) : null,
            'terms' => $terms instanceof TermsVersion ? [
                'id' => $terms->getKey(),
                'version' => $terms->version,
                'title' => $terms->title,
                'url' => route('public.terms-and-privacy', ['return_to' => 'idea-validation'], absolute: false),
            ] : null,
        ]);
    }

    private function accountConflict(Request $request): ?string
    {
        $conflict = $request->session()->pull('idea_validation_account_conflict');

        return is_string($conflict) && in_array($conflict, [
            IdeaValidationRegistrationConflict::EXISTING_ACCOUNT,
            IdeaValidationRegistrationConflict::EXISTING_PROFILE,
        ], true) ? $conflict : null;
    }

    public function paymentIntent(Request $request): JsonResponse
    {
        $purchase = $this->checkoutPurchase($request);
        abort_unless($purchase instanceof IdeaValidationPurchase, 403);
        $user = $this->purchaseUser($purchase);

        try {
            $intent = $this->checkout->beginPayment($user, $purchase);
        } catch (PaymentGatewayException $exception) {
            $reference = 'IV-'.Str::upper(Str::random(8));
            $auditablePurchase = $this->checkout->purchaseFor($user) ?? $purchase;
            $this->audit->record('idea_validation.purchase_payment_setup_failed', subject: $auditablePurchase, actor: $user, after: [
                'gateway' => 'stripe',
                'support_reference' => $reference,
                'status' => $auditablePurchase->status,
                'payment_taken' => false,
            ]);
            Log::warning('Idea Validation secure payment setup failed', [
                'reference' => $reference,
                'purchase_id' => $purchase->getKey(),
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
                'cause' => $exception->getPrevious()?->getMessage() ?? $exception->getMessage(),
            ]);

            return response()->json([
                'code' => 'payment_setup_unavailable',
                'message' => 'We could not start secure payment right now. Your email is verified and no payment has been taken. Please try again shortly, or contact Future Shift Advisory and quote reference '.$reference.'.',
                'support_reference' => $reference,
            ], 503);
        }

        $purchase = $this->checkoutPurchase($request);
        abort_unless($purchase instanceof IdeaValidationPurchase, 403);

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
        $purchase = $this->checkoutPurchase($request);
        abort_unless($purchase instanceof IdeaValidationPurchase, 403);
        $user = $this->purchaseUser($purchase);

        $settled = $this->checkout->confirmPayment($user, $purchase, $validated['payment_intent_id']);
        if ($settled instanceof IdeaValidationPurchase) {
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('fsa.idea_validation_purchase_flow', true);
            $request->session()->forget(self::CHECKOUT_PURCHASE_SESSION_KEY);
        }

        return response()->json([
            'paid' => $settled instanceof IdeaValidationPurchase,
            'next_url' => $settled instanceof IdeaValidationPurchase ? route('mfa.setup', absolute: false) : null,
        ], $settled instanceof IdeaValidationPurchase ? 200 : 202);
    }

    public function confirmFixturePayment(Request $request): JsonResponse
    {
        $purchase = $this->checkoutPurchase($request);
        abort_unless($purchase instanceof IdeaValidationPurchase, 403);
        $user = $this->purchaseUser($purchase);

        $purchase = $this->checkout->confirmFixturePayment($user, $purchase);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('fsa.idea_validation_purchase_flow', true);
        $request->session()->forget(self::CHECKOUT_PURCHASE_SESSION_KEY);

        return response()->json([
            'paid' => true,
            'next_url' => route('mfa.setup', absolute: false),
            'purchase' => $this->purchasePayload($purchase),
        ]);
    }

    private function checkoutPurchase(Request $request): ?IdeaValidationPurchase
    {
        $purchaseId = $request->session()->get(self::CHECKOUT_PURCHASE_SESSION_KEY);
        if (! is_string($purchaseId) || ! Str::isUuid($purchaseId)) {
            return null;
        }

        return $this->context->withSystemContext(fn (): ?IdeaValidationPurchase => IdeaValidationPurchase::query()
            ->with('user')
            ->whereKey($purchaseId)
            ->first());
    }

    private function purchaseUser(IdeaValidationPurchase $purchase): User
    {
        $user = $purchase->user;
        abort_unless($user instanceof User, 500);

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
