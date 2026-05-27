<?php

declare(strict_types=1);

return [
    'retry' => [
        'attempts' => (int) env('INTEGRATION_RETRY_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('INTEGRATION_RETRY_BASE_DELAY_MS', 100),
        'max_delay_ms' => (int) env('INTEGRATION_RETRY_MAX_DELAY_MS', 1000),
        'retry_statuses' => [408, 409, 425, 429, 500, 502, 503, 504],
    ],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('INTEGRATION_BREAKER_FAILURE_THRESHOLD', 5),
        'window_seconds' => (int) env('INTEGRATION_BREAKER_WINDOW_SECONDS', 60),
        'open_seconds' => (int) env('INTEGRATION_BREAKER_OPEN_SECONDS', 300),
    ],

    'cache' => [
        'ttl_seconds' => (int) env('INTEGRATION_CACHE_TTL_SECONDS', 900),
    ],

    'health' => [
        'green' => [
            'min_success_rate' => (float) env('INTEGRATION_HEALTH_GREEN_SUCCESS_RATE', 0.99),
            'max_p95_latency_ms' => (int) env('INTEGRATION_HEALTH_GREEN_P95_MS', 1000),
        ],
        'amber' => [
            'min_success_rate' => (float) env('INTEGRATION_HEALTH_AMBER_SUCCESS_RATE', 0.95),
            'max_p95_latency_ms' => (int) env('INTEGRATION_HEALTH_AMBER_P95_MS', 3000),
        ],
    ],

    'nzbn' => [
        'live' => (bool) env('FEATURE_NZBN_LIVE', false),
        'base_url' => env('NZBN_BASE_URL', 'https://api.business.govt.nz/services/v4'),
        'api_key' => env('NZBN_API_KEY'),
    ],

    'companies_office' => [
        'live' => (bool) env('FEATURE_COMPANIES_OFFICE_LIVE', false),
        'base_url' => env('COMPANIES_OFFICE_BASE_URL', 'https://api.business.govt.nz/services/v1/companies'),
        'api_key' => env('COMPANIES_OFFICE_API_KEY'),
    ],

    'charities_services' => [
        'live' => (bool) env('FEATURE_CHARITIES_LIVE', false),
        'base_url' => env('CHARITIES_SERVICES_BASE_URL', 'https://api.charities.govt.nz'),
        'scrape_url' => env('CHARITIES_SERVICES_SCRAPE_URL', 'https://register.charities.govt.nz/Charity/CCSearch'),
        'api_key' => env('CHARITIES_SERVICES_API_KEY'),
    ],

    'npo_funders' => [
        'sources' => [
            'community_matters_cogs' => [
                'live' => (bool) env('FEATURE_NPO_FUNDER_COMMUNITY_MATTERS_LIVE', false),
                'base_url' => env('NPO_FUNDER_COMMUNITY_MATTERS_BASE_URL', 'https://www.communitymatters.govt.nz'),
                'path' => env('NPO_FUNDER_COMMUNITY_MATTERS_COGS_PATH', 'funding-and-grants/community-organisation-grants-scheme'),
                'api_key' => env('NPO_FUNDER_COMMUNITY_MATTERS_API_KEY'),
            ],
            'community_matters_lottery' => [
                'live' => (bool) env('FEATURE_NPO_FUNDER_COMMUNITY_MATTERS_LIVE', false),
                'base_url' => env('NPO_FUNDER_COMMUNITY_MATTERS_BASE_URL', 'https://www.communitymatters.govt.nz'),
                'path' => env('NPO_FUNDER_COMMUNITY_MATTERS_LOTTERY_PATH', 'funding-and-grants/lottery-grants'),
                'api_key' => env('NPO_FUNDER_COMMUNITY_MATTERS_API_KEY'),
            ],
            'generosity_nz' => [
                'live' => (bool) env('FEATURE_NPO_FUNDER_GENEROSITY_NZ_LIVE', false),
                'base_url' => env('NPO_FUNDER_GENEROSITY_NZ_BASE_URL', 'https://generosity.org.nz'),
                'path' => env('NPO_FUNDER_GENEROSITY_NZ_PATH', 'api/funders'),
                'api_key' => env('NPO_FUNDER_GENEROSITY_NZ_API_KEY'),
            ],
            'fundsorter' => [
                'live' => (bool) env('FEATURE_NPO_FUNDER_FUNDSORTER_LIVE', false),
                'base_url' => env('NPO_FUNDER_FUNDSORTER_BASE_URL', 'https://fundsorter.co.nz'),
                'path' => env('NPO_FUNDER_FUNDSORTER_PATH', 'api/funders'),
                'api_key' => env('NPO_FUNDER_FUNDSORTER_API_KEY'),
            ],
            'te_puni_kokiri' => [
                'live' => (bool) env('FEATURE_NPO_FUNDER_TPK_LIVE', false),
                'base_url' => env('NPO_FUNDER_TPK_BASE_URL', 'https://www.tpk.govt.nz'),
                'path' => env('NPO_FUNDER_TPK_PATH', 'api/funds'),
                'api_key' => env('NPO_FUNDER_TPK_API_KEY'),
            ],
        ],
    ],

    'incorporated_societies' => [
        'live' => (bool) env('FEATURE_INCORPORATED_SOCIETIES_LIVE', false),
        'base_url' => env('INCORPORATED_SOCIETIES_BASE_URL', 'https://api.business.govt.nz/services/v1/incorporated-societies'),
        'scrape_url' => env('INCORPORATED_SOCIETIES_SCRAPE_URL', 'https://is-register.companiesoffice.govt.nz'),
        'api_key' => env('INCORPORATED_SOCIETIES_API_KEY'),
    ],

    'ird' => [
        'live' => (bool) env('FEATURE_IRD_LIVE', false),
        'base_url' => env('IRD_BASE_URL', 'https://api.ird.govt.nz'),
        'api_key' => env('IRD_API_KEY'),
    ],

    'rbnz' => [
        'live' => (bool) env('FEATURE_RBNZ_LIVE', false),
        'base_url' => env('RBNZ_BASE_URL', 'https://api.rbnz.govt.nz'),
        'api_key' => env('RBNZ_API_KEY'),
    ],

    'stats_nz' => [
        'live' => (bool) env('FEATURE_STATS_NZ_LIVE', false),
        'base_url' => env('STATS_NZ_BASE_URL', 'https://api.stats.govt.nz'),
        'api_key' => env('STATS_NZ_API_KEY'),
    ],

    'mbie' => [
        'live' => (bool) env('FEATURE_MBIE_LIVE', false),
        'base_url' => env('MBIE_BASE_URL', 'https://api.mbie.govt.nz'),
        'api_key' => env('MBIE_API_KEY'),
    ],

    'fsp' => [
        'live' => (bool) env('FEATURE_FSP_LIVE', false),
        'base_url' => env('FSP_BASE_URL', 'https://api.fsp-register.govt.nz'),
        'api_key' => env('FSP_API_KEY'),
    ],

    'accounting' => [
        'xero' => [
            'live' => (bool) env('FEATURE_XERO_LIVE', false),
            'base_url' => env('XERO_BASE_URL', 'https://api.xero.com'),
            'authorize_url' => env('XERO_AUTHORIZE_URL', 'https://login.xero.com/identity/connect/authorize'),
            'client_id' => env('XERO_CLIENT_ID'),
            'client_secret' => env('XERO_CLIENT_SECRET'),
        ],
        'myob' => [
            'live' => (bool) env('FEATURE_MYOB_LIVE', false),
            'base_url' => env('MYOB_BASE_URL', 'https://api.myob.com'),
            'authorize_url' => env('MYOB_AUTHORIZE_URL', 'https://secure.myob.com/oauth2/account/authorize'),
            'client_id' => env('MYOB_CLIENT_ID'),
            'client_secret' => env('MYOB_CLIENT_SECRET'),
        ],
        'quickbooks' => [
            'live' => (bool) env('FEATURE_QUICKBOOKS_LIVE', false),
            'base_url' => env('QUICKBOOKS_BASE_URL', 'https://quickbooks.api.intuit.com'),
            'authorize_url' => env('QUICKBOOKS_AUTHORIZE_URL', 'https://appcenter.intuit.com/connect/oauth2'),
            'client_id' => env('QUICKBOOKS_CLIENT_ID'),
            'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        ],
        'sage' => [
            'live' => (bool) env('FEATURE_SAGE_LIVE', false),
            'base_url' => env('SAGE_BASE_URL', 'https://api.accounting.sage.com/v3.1'),
            'authorize_url' => env('SAGE_AUTHORIZE_URL', 'https://www.sageone.com/oauth2/auth/central'),
            'client_id' => env('SAGE_CLIENT_ID'),
            'client_secret' => env('SAGE_CLIENT_SECRET'),
        ],
        'figured' => [
            'live' => (bool) env('FEATURE_FIGURED_LIVE', false),
            'base_url' => env('FIGURED_BASE_URL', 'https://api.figured.com'),
            'authorize_url' => env('FIGURED_AUTHORIZE_URL', 'https://api.figured.com/oauth/authorize'),
            'client_id' => env('FIGURED_CLIENT_ID'),
            'client_secret' => env('FIGURED_CLIENT_SECRET'),
        ],
        'workflowmax' => [
            'live' => (bool) env('FEATURE_WORKFLOWMAX_LIVE', false),
            'base_url' => env('WORKFLOWMAX_BASE_URL', 'https://api.workflowmax.com'),
            'authorize_url' => env('WORKFLOWMAX_AUTHORIZE_URL', 'https://api.workflowmax.com/oauth/authorize'),
            'client_id' => env('WORKFLOWMAX_CLIENT_ID'),
            'client_secret' => env('WORKFLOWMAX_CLIENT_SECRET'),
        ],
        'monitoring' => [
            'enabled' => (bool) env('FEATURE_CONTINUOUS_MONITORING', false),
            'net_profit_drop_threshold' => (float) env('FINANCIAL_MONITOR_NET_PROFIT_DROP_THRESHOLD', 0.2),
            'revenue_drop_threshold' => (float) env('FINANCIAL_MONITOR_REVENUE_DROP_THRESHOLD', 0.15),
            'cash_flow_drop_threshold' => (float) env('FINANCIAL_MONITOR_CASH_FLOW_DROP_THRESHOLD', 0.2),
            'gross_margin_drop_points' => (float) env('FINANCIAL_MONITOR_GROSS_MARGIN_DROP_POINTS', 0.1),
            'current_ratio_floor' => (float) env('FINANCIAL_MONITOR_CURRENT_RATIO_FLOOR', 1.2),
        ],
    ],

    'calendar' => [
        'google' => [
            'live' => (bool) env('FEATURE_CALENDAR_LIVE', false) && (bool) env('FEATURE_GOOGLE_CALENDAR_LIVE', false),
            'base_url' => env('GOOGLE_CALENDAR_BASE_URL', 'https://www.googleapis.com/calendar/v3'),
            'authorize_url' => env('GOOGLE_CALENDAR_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
            'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        ],
        'microsoft' => [
            'live' => (bool) env('FEATURE_CALENDAR_LIVE', false) && (bool) env('FEATURE_MICROSOFT_GRAPH_LIVE', false),
            'base_url' => env('MICROSOFT_GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0/me'),
            'authorize_url' => env('MICROSOFT_GRAPH_AUTHORIZE_URL', 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize'),
            'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_GRAPH_CLIENT_SECRET'),
        ],
    ],

    'business_tools' => [
        'employment_hero' => [
            'live' => (bool) env('FEATURE_EMPLOYMENT_HERO_LIVE', false),
            'base_url' => env('EMPLOYMENT_HERO_BASE_URL', 'https://api.employmenthero.com'),
            'authorize_url' => env('EMPLOYMENT_HERO_AUTHORIZE_URL', 'https://oauth.employmenthero.com/oauth2/authorize'),
            'redirect_uri' => env('EMPLOYMENT_HERO_REDIRECT_URI'),
            'client_id' => env('EMPLOYMENT_HERO_CLIENT_ID'),
            'client_secret' => env('EMPLOYMENT_HERO_CLIENT_SECRET'),
        ],
        'cin7' => [
            'live' => (bool) env('FEATURE_CIN7_LIVE', false),
            'base_url' => env('CIN7_BASE_URL', 'https://api.cin7.com'),
            'authorize_url' => env('CIN7_AUTHORIZE_URL', 'https://connect.cin7.com/oauth/authorize'),
            'redirect_uri' => env('CIN7_REDIRECT_URI'),
            'client_id' => env('CIN7_CLIENT_ID'),
            'client_secret' => env('CIN7_CLIENT_SECRET'),
        ],
        'tradify' => [
            'live' => (bool) env('FEATURE_TRADIFY_LIVE', false),
            'base_url' => env('TRADIFY_BASE_URL', 'https://api.tradifyhq.com'),
            'authorize_url' => env('TRADIFY_AUTHORIZE_URL', 'https://app.tradifyhq.com/oauth/authorize'),
            'redirect_uri' => env('TRADIFY_REDIRECT_URI'),
            'client_id' => env('TRADIFY_CLIENT_ID'),
            'client_secret' => env('TRADIFY_CLIENT_SECRET'),
        ],
    ],

    'payments' => [
        'primary_gateway' => env('PAYMENT_PRIMARY_GATEWAY', 'stripe'),
        'max_attempts' => (int) env('PAYMENT_MAX_ATTEMPTS', 2),
        'retry_delay_minutes' => (int) env('PAYMENT_RETRY_DELAY_MINUTES', 60),
        'webhook_tolerance_seconds' => (int) env('PAYMENT_WEBHOOK_TOLERANCE_SECONDS', 300),
        'stripe' => [
            'live' => (bool) env('FEATURE_STRIPE_LIVE', false),
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'windcave' => [
            'live' => (bool) env('FEATURE_WINDCAVE_LIVE', false),
            'base_url' => env('WINDCAVE_BASE_URL', 'https://sec.windcave.com/api'),
            'api_user' => env('WINDCAVE_API_USER'),
            'api_key' => env('WINDCAVE_API_KEY'),
            'webhook_secret' => env('WINDCAVE_WEBHOOK_SECRET'),
        ],
    ],
];
