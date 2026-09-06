<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurServiceOfferController extends Controller
{
    public function __construct(private readonly EntrepreneurInviteOffer $offers) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);
        $invite = $this->offers->forUser($user);
        if ($invite === null || $invite->service_offer_accepted_at !== null) {
            return to_route('portal.entrepreneur.dashboard');
        }

        $terms = $this->offers->terms($invite);

        return Inertia::render('portal/entrepreneur/ServiceOffer', [
            'offer' => $terms,
            'offerVersion' => EntrepreneurInviteOffer::version($terms),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR, 403);
        $validated = $request->validate([
            'accepted' => ['required', 'accepted'],
            'offer_version' => ['required', 'string', 'size:64'],
        ]);
        $this->offers->accept($user, $validated['offer_version']);

        return to_route('portal.entrepreneur.dashboard');
    }
}
