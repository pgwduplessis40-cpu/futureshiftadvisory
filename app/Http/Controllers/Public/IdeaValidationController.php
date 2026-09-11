<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Public\IdeaValidationOffer;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public landing page for self-serve Idea Validation.
 *
 * Unlike advisory engagements (invite-only, begin with a discovery call), idea
 * validation can be started directly: the visitor pays a fixed fee online and
 * their portal workspace opens. This page sells that, showing the live price
 * from Service Rates and linking to the checkout journey.
 */
class IdeaValidationController extends Controller
{
    public function __invoke(): Response
    {
        $offer = IdeaValidationOffer::current();

        return Inertia::render('public/idea-validation', [
            'offer' => $offer === null ? ['available' => false] : [
                'available' => true,
                'price' => $offer['price'],
                'priceFormatted' => $offer['ex_gst_formatted'],
                'currency' => $offer['currency'],
            ],
            'checkoutUrl' => $this->checkoutUrl(),
        ]);
    }

    /**
     * Where "Validate my idea" sends the visitor, with source attribution so
     * paid signups from the website can be measured (matches prospect_leads
     * source conventions).
     */
    private function checkoutUrl(): string
    {
        $base = (string) config('public_site.idea_validation.checkout_url', '/validate-idea');
        $query = 'source=website&service=idea_validation';

        return $base.(Str::contains($base, '?') ? '&' : '?').$query;
    }
}
