<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteOffer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEntrepreneurInviteOfferAccepted
{
    public function __construct(private readonly EntrepreneurInviteOffer $offers) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User
            && $request->routeIs('portal.entrepreneur.*')
            && ! $request->routeIs('portal.entrepreneur.service-offer.*')) {
            $invite = $this->offers->forUser($user);
            if ($invite !== null && $invite->service_offer_accepted_at === null) {
                return to_route('portal.entrepreneur.service-offer.show', status: 303);
            }
        }

        return $next($request);
    }
}
