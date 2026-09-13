<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdeaValidationPurchase;
use App\Models\User;
use App\Services\Entrepreneurs\IdeaValidationCheckout;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An Idea Validation customer becomes a portal user only once their payment
 * has settled. This also repairs any pre-payment web session created before
 * checkout isolation was introduced.
 */
final class RedirectUnpaidIdeaValidationBuyer
{
    private const CHECKOUT_PURCHASE_SESSION_KEY = 'fsa.idea_validation_checkout_purchase_id';

    public function __construct(private readonly IdeaValidationCheckout $checkout) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('logout', 'public.validate-idea.purchase.verify')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $purchase = $this->checkout->purchaseFor($user);
        if (! $purchase instanceof IdeaValidationPurchase || $purchase->status === IdeaValidationPurchase::STATUS_PAID) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(self::CHECKOUT_PURCHASE_SESSION_KEY, $purchase->getKey());

        return redirect()
            ->route('public.validate-idea.purchase')
            ->with('status', 'idea-validation-checkout-session-restored');
    }
}
