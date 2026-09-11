<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Idea Validation - public purchase entry
    |--------------------------------------------------------------------------
    |
    | The public /idea-validation marketing page links to the self-serve
    | checkout journey (account + MFA + payment) that lives in the portal.
    | That journey is owned by the portal/payment work; the marketing site
    | only needs to know where to send the visitor.
    |
    | `checkout_url` is where "Validate my idea" points. Default matches the
    | agreed public journey path (/validate-idea); if that journey is not yet
    | live the link simply 404s until it lands.
    |
    | The displayed price is NOT configured here - it is resolved live from the
    | idea_validation Service Rate package (see App\Support\Public\
    | IdeaValidationOffer), so the page can never show a stale hardcoded figure.
    | `gst_rate` and `currency` are only fallbacks for formatting.
    |
    */

    'idea_validation' => [
        'checkout_url' => env('IDEA_VALIDATION_CHECKOUT_URL', '/validate-idea'),
        'currency' => env('IDEA_VALIDATION_CURRENCY', 'NZD'),
        'gst_rate' => 0.15,
    ],

];
