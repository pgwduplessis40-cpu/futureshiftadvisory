<?php

return [
    'agreements' => [
        'title' => env('PANEL_AGREEMENT_TITLE', 'Future Shift Advisory panel agreement'),
        'introduction' => env(
            'PANEL_AGREEMENT_INTRODUCTION',
            'This agreement records the operating terms for approved Future Shift Advisory panel partners.',
        ),
        'standard_terms' => env(
            'PANEL_AGREEMENT_STANDARD_TERMS',
            "Panel partners must protect confidential information, act only within their authorised scope, and obtain client consent before referral information is shared.\nNo referral fees are payable by either party unless separately agreed in writing.",
        ),
        'broker_terms' => env(
            'PANEL_AGREEMENT_BROKER_TERMS',
            "Brokers remain responsible for regulated financial advice and must keep FSP registration current.\nA lapsed or non-current FSP status may suspend portal access until resolved.",
        ),
        'coach_terms' => env(
            'PANEL_AGREEMENT_COACH_TERMS',
            "Coaches provide coaching support only and must not provide clinical mental-health diagnosis, treatment, crisis support, or regulated health advice.\nClient authorisation is required before key-staff coaching context is shared.",
        ),
    ],
];
