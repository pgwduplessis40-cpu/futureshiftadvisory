<?php

declare(strict_types=1);

return [
    'integrations' => [
        'nzbn' => [
            'display_name' => 'NZBN',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.nzbn.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.nzbn.api_key',
                    'env_fallback_path' => 'NZBN_API_KEY',
                ],
            ],
        ],
        'companies_office' => [
            'display_name' => 'Companies Office public data (via NZBN)',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.companies_office.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.companies_office.api_key',
                    'env_fallback_path' => 'COMPANIES_OFFICE_API_KEY',
                ],
            ],
        ],
        'companies_entity_role_search' => [
            'display_name' => 'Companies Entity Role Search',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.companies_entity_role_search.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.companies_entity_role_search.api_key',
                    'env_fallback_path' => 'COMPANIES_ENTITY_ROLE_SEARCH_API_KEY',
                ],
            ],
        ],
        'incorporated_societies' => [
            'display_name' => 'Incorporated Societies',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.incorporated_societies.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.incorporated_societies.api_key',
                    'env_fallback_path' => 'INCORPORATED_SOCIETIES_API_KEY',
                ],
            ],
        ],
        'charities_services' => [
            'display_name' => 'Charities Services',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.charities_services.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.charities_services.api_key',
                    'env_fallback_path' => 'CHARITIES_SERVICES_API_KEY',
                ],
            ],
        ],
        'stats_nz' => [
            'display_name' => 'Stats NZ ADE',
            'category' => 'economic_data',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.stats_nz.live',
            'credentials' => [
                'subscription_key' => [
                    'config_path' => 'integrations.stats_nz.api_key',
                    'env_fallback_path' => 'STATS_NZ_API_KEY',
                ],
            ],
        ],
        'rbnz' => [
            'display_name' => 'RBNZ',
            'category' => 'economic_data',
            'fallback_mode' => 'manual',
            'managed_via' => 'environment',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.rbnz.live',
            'purpose' => 'RBNZ is the Reserve Bank of New Zealand website source used for OCR and exchange-rate reference data.',
            'api_outcome' => 'RBNZ does not provide this feed through an API key; live access uses the approved website agent user-agent and RBNZ IP allowlisting.',
            'credentials' => [],
        ],
        'ird' => [
            'display_name' => 'IRD',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'deferred',
            'live_config_path' => 'integrations.ird.live',
            'availability_status' => 'deferred',
            'availability_label' => 'Deferred pending IRD Data Consumer category',
            'availability_note' => 'IRD declined the current Gateway Services application because FSA needs IRD data for advisory verification rather than helping the client meet tax obligations. Reassess when the proposed Data Consumer intermediary category becomes available, currently anticipated from 2027.',
            'purpose' => 'IRD Gateway access is deferred. FSA may record client-supplied IRD/GST information, but the platform must not present it as independently verified with Inland Revenue.',
            'api_outcome' => 'Live IRD verification is unavailable under the current Gateway framework. When the Data Consumer category is enacted and approved, this integration can be reassessed and re-enabled through the governed credential workflow.',
            'credentials' => [],
        ],
        'mbie' => [
            'display_name' => 'MBIE',
            'category' => 'economic_data',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.mbie.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.mbie.api_key',
                    'env_fallback_path' => 'MBIE_API_KEY',
                ],
            ],
        ],
        'fsp' => [
            'display_name' => 'FSPR bulk data / manual',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'manual',
            'wiring_status' => 'not_wired',
            'credentials' => [],
        ],
        'community_matters_cogs' => [
            'display_name' => 'Community Matters COGS',
            'category' => 'npo_funders',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.npo_funders.sources.community_matters_cogs.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.npo_funders.sources.community_matters_cogs.api_key',
                    'env_fallback_path' => 'NPO_FUNDER_COMMUNITY_MATTERS_API_KEY',
                ],
            ],
        ],
        'community_matters_lottery' => [
            'display_name' => 'Community Matters Lottery',
            'category' => 'npo_funders',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.npo_funders.sources.community_matters_lottery.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.npo_funders.sources.community_matters_lottery.api_key',
                    'env_fallback_path' => 'NPO_FUNDER_COMMUNITY_MATTERS_API_KEY',
                ],
            ],
        ],
        'generosity_nz' => [
            'display_name' => 'Generosity NZ',
            'category' => 'npo_funders',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.npo_funders.sources.generosity_nz.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.npo_funders.sources.generosity_nz.api_key',
                    'env_fallback_path' => 'NPO_FUNDER_GENEROSITY_NZ_API_KEY',
                ],
            ],
        ],
        'fundsorter' => [
            'display_name' => 'Fundsorter',
            'category' => 'npo_funders',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.npo_funders.sources.fundsorter.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.npo_funders.sources.fundsorter.api_key',
                    'env_fallback_path' => 'NPO_FUNDER_FUNDSORTER_API_KEY',
                ],
            ],
        ],
        'te_puni_kokiri' => [
            'display_name' => 'Te Puni Kokiri',
            'category' => 'npo_funders',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.npo_funders.sources.te_puni_kokiri.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.npo_funders.sources.te_puni_kokiri.api_key',
                    'env_fallback_path' => 'NPO_FUNDER_TPK_API_KEY',
                ],
            ],
        ],
        'xero' => [
            'display_name' => 'Xero',
            'category' => 'accounting',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.accounting.xero.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.accounting.xero.client_id', 'env_fallback_path' => 'XERO_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.accounting.xero.client_secret', 'env_fallback_path' => 'XERO_CLIENT_SECRET'],
            ],
        ],
        'myob' => [
            'display_name' => 'MYOB',
            'category' => 'accounting',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.accounting.myob.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.accounting.myob.client_id', 'env_fallback_path' => 'MYOB_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.accounting.myob.client_secret', 'env_fallback_path' => 'MYOB_CLIENT_SECRET'],
            ],
        ],
        'quickbooks' => [
            'display_name' => 'QuickBooks',
            'category' => 'accounting',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.accounting.quickbooks.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.accounting.quickbooks.client_id', 'env_fallback_path' => 'QUICKBOOKS_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.accounting.quickbooks.client_secret', 'env_fallback_path' => 'QUICKBOOKS_CLIENT_SECRET'],
            ],
        ],
        'sage' => [
            'display_name' => 'Sage',
            'category' => 'accounting',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.accounting.sage.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.accounting.sage.client_id', 'env_fallback_path' => 'SAGE_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.accounting.sage.client_secret', 'env_fallback_path' => 'SAGE_CLIENT_SECRET'],
            ],
        ],
        'figured' => [
            'display_name' => 'Figured',
            'category' => 'accounting',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.accounting.figured.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.accounting.figured.client_id', 'env_fallback_path' => 'FIGURED_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.accounting.figured.client_secret', 'env_fallback_path' => 'FIGURED_CLIENT_SECRET'],
            ],
        ],
        'workflowmax' => [
            'display_name' => 'WorkflowMax',
            'category' => 'accounting',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.accounting.workflowmax.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.accounting.workflowmax.client_id', 'env_fallback_path' => 'WORKFLOWMAX_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.accounting.workflowmax.client_secret', 'env_fallback_path' => 'WORKFLOWMAX_CLIENT_SECRET'],
            ],
        ],
        'anthropic' => [
            'display_name' => 'Anthropic AI',
            'category' => 'ai',
            'fallback_mode' => 'api_required',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'credentials' => [
                'key' => [
                    'config_path' => 'services.anthropic.key',
                    'env_fallback_path' => 'ANTHROPIC_API_KEY',
                ],
            ],
        ],
        'anthropic_admin' => [
            'display_name' => 'Anthropic Admin API',
            'category' => 'ai',
            'fallback_mode' => 'optional',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'credentials' => [
                'key' => [
                    'config_path' => 'services.anthropic.admin_key',
                    'env_fallback_path' => 'ANTHROPIC_ADMIN_API_KEY',
                ],
            ],
        ],
        'stripe' => [
            'display_name' => 'Stripe',
            'category' => 'payments',
            'fallback_mode' => 'api_required',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.payments.stripe.live',
            'credentials' => [
                'publishable_key' => [
                    'config_path' => 'integrations.payments.stripe.publishable_key',
                    'env_fallback_path' => 'STRIPE_PUBLISHABLE_KEY',
                    'required' => false,
                ],
                'secret' => [
                    'config_path' => 'integrations.payments.stripe.secret',
                    'env_fallback_path' => 'STRIPE_SECRET',
                ],
                'webhook_secret' => [
                    'config_path' => 'integrations.payments.stripe.webhook_secret',
                    'env_fallback_path' => 'STRIPE_WEBHOOK_SECRET',
                ],
            ],
        ],
        'windcave' => [
            'display_name' => 'Windcave',
            'category' => 'payments',
            'fallback_mode' => 'api_required',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.payments.windcave.live',
            'credentials' => [
                'api_user' => [
                    'config_path' => 'integrations.payments.windcave.api_user',
                    'env_fallback_path' => 'WINDCAVE_API_USER',
                ],
                'api_key' => [
                    'config_path' => 'integrations.payments.windcave.api_key',
                    'env_fallback_path' => 'WINDCAVE_API_KEY',
                ],
                'webhook_secret' => [
                    'config_path' => 'integrations.payments.windcave.webhook_secret',
                    'env_fallback_path' => 'WINDCAVE_WEBHOOK_SECRET',
                ],
            ],
        ],
        'employment_hero' => [
            'display_name' => 'Employment Hero',
            'category' => 'business_tools',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.business_tools.employment_hero.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.business_tools.employment_hero.client_id', 'env_fallback_path' => 'EMPLOYMENT_HERO_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.business_tools.employment_hero.client_secret', 'env_fallback_path' => 'EMPLOYMENT_HERO_CLIENT_SECRET'],
            ],
        ],
        'cin7' => [
            'display_name' => 'Cin7',
            'category' => 'business_tools',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.business_tools.cin7.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.business_tools.cin7.client_id', 'env_fallback_path' => 'CIN7_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.business_tools.cin7.client_secret', 'env_fallback_path' => 'CIN7_CLIENT_SECRET'],
            ],
        ],
        'tradify' => [
            'display_name' => 'Tradify',
            'category' => 'business_tools',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.business_tools.tradify.live',
            'credentials' => [
                'client_id' => ['config_path' => 'integrations.business_tools.tradify.client_id', 'env_fallback_path' => 'TRADIFY_CLIENT_ID'],
                'client_secret' => ['config_path' => 'integrations.business_tools.tradify.client_secret', 'env_fallback_path' => 'TRADIFY_CLIENT_SECRET'],
            ],
        ],
        'google_calendar' => [
            'display_name' => 'Google Calendar',
            'category' => 'calendar',
            'fallback_mode' => 'optional',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.calendar.google.live',
            'credentials' => [
                'client_id' => [
                    'config_path' => 'integrations.calendar.google.client_id',
                    'env_fallback_path' => 'GOOGLE_CALENDAR_CLIENT_ID',
                ],
                'client_secret' => [
                    'config_path' => 'integrations.calendar.google.client_secret',
                    'env_fallback_path' => 'GOOGLE_CALENDAR_CLIENT_SECRET',
                ],
            ],
        ],
        'microsoft_calendar' => [
            'display_name' => 'Microsoft Outlook',
            'category' => 'calendar',
            'fallback_mode' => 'optional',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.calendar.microsoft.live',
            'credentials' => [
                'client_id' => [
                    'config_path' => 'integrations.calendar.microsoft.client_id',
                    'env_fallback_path' => 'MICROSOFT_GRAPH_CLIENT_ID',
                ],
                'client_secret' => [
                    'config_path' => 'integrations.calendar.microsoft.client_secret',
                    'env_fallback_path' => 'MICROSOFT_GRAPH_CLIENT_SECRET',
                ],
            ],
        ],
        'slack_notifications' => [
            'display_name' => 'Slack notifications',
            'category' => 'notifications',
            'fallback_mode' => 'optional',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'credentials' => [
                'bot_user_oauth_token' => [
                    'config_path' => 'services.slack.notifications.bot_user_oauth_token',
                    'env_fallback_path' => 'SLACK_BOT_USER_OAUTH_TOKEN',
                ],
            ],
        ],
        'mail_delivery' => [
            'display_name' => 'Email delivery',
            'category' => 'notifications',
            'fallback_mode' => 'environment',
            'managed_via' => 'environment',
            'wiring_status' => 'wired',
            'credentials' => [
                'smtp_username' => [
                    'config_path' => 'mail.mailers.smtp.username',
                    'env_fallback_path' => 'MAIL_USERNAME',
                ],
                'smtp_password' => [
                    'config_path' => 'mail.mailers.smtp.password',
                    'env_fallback_path' => 'MAIL_PASSWORD',
                ],
                'graph_tenant' => [
                    'config_path' => 'mail.mailers.graph.tenant',
                    'env_fallback_path' => 'MICROSOFT_GRAPH_MAIL_TENANT',
                ],
                'graph_client_id' => [
                    'config_path' => 'mail.mailers.graph.client_id',
                    'env_fallback_path' => 'MICROSOFT_GRAPH_MAIL_CLIENT_ID',
                ],
                'graph_client_secret' => [
                    'config_path' => 'mail.mailers.graph.client_secret',
                    'env_fallback_path' => 'MICROSOFT_GRAPH_MAIL_CLIENT_SECRET',
                ],
                'graph_from_address' => [
                    'config_path' => 'mail.mailers.graph.from_address',
                    'env_fallback_path' => 'MICROSOFT_GRAPH_MAIL_FROM_ADDRESS',
                ],
                'ses_key' => [
                    'config_path' => 'services.ses.key',
                    'env_fallback_path' => 'AWS_ACCESS_KEY_ID',
                ],
                'ses_secret' => [
                    'config_path' => 'services.ses.secret',
                    'env_fallback_path' => 'AWS_SECRET_ACCESS_KEY',
                ],
                'postmark_key' => [
                    'config_path' => 'services.postmark.key',
                    'env_fallback_path' => 'POSTMARK_API_KEY',
                ],
                'resend_key' => [
                    'config_path' => 'services.resend.key',
                    'env_fallback_path' => 'RESEND_API_KEY',
                ],
            ],
        ],
        'logging_slack' => [
            'display_name' => 'Logging Slack webhook',
            'category' => 'notifications',
            'fallback_mode' => 'environment',
            'managed_via' => 'environment',
            'wiring_status' => 'wired',
            'credentials' => [
                'webhook_url' => [
                    'config_path' => 'logging.channels.slack.url',
                    'env_fallback_path' => 'LOG_SLACK_WEBHOOK_URL',
                ],
            ],
        ],
        'virus_scanner' => [
            'display_name' => 'ClamAV malware scanner',
            'category' => 'security',
            'fallback_mode' => 'fail_closed',
            'managed_via' => 'environment',
            'wiring_status' => 'wired',
            'live_config_path' => 'virus-scanner.live',
            'purpose' => 'ClamAV scans uploaded documents before encrypted persistence. Infected files are rejected, and scanner failures are quarantined so clients cannot rely on unscanned files.',
            'api_outcome' => 'Production must run ClamAV live scanning. Local and testing may use the no-op scanner only for development fixtures; production falls closed to quarantine if ClamAV is not configured.',
            'credentials' => [
                'host' => [
                    'config_path' => 'virus-scanner.clamav.host',
                    'env_fallback_path' => 'CLAMAV_HOST',
                    'required' => false,
                ],
                'port' => [
                    'config_path' => 'virus-scanner.clamav.port',
                    'env_fallback_path' => 'CLAMAV_PORT',
                    'required' => false,
                ],
            ],
        ],
        'ses_sendgrid' => [
            'display_name' => 'SES / SendGrid scaffold',
            'category' => 'notifications',
            'fallback_mode' => 'environment',
            'managed_via' => 'environment',
            'wiring_status' => 'not_wired',
            'credentials' => [],
        ],
        'ppsr' => [
            'display_name' => 'PPSR',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'wired',
            'live_config_path' => 'integrations.ppsr.live',
            'credentials' => [
                'api_key' => [
                    'config_path' => 'integrations.ppsr.api_key',
                    'env_fallback_path' => 'PPSR_API_KEY',
                ],
            ],
        ],
        'linz' => [
            'display_name' => 'LINZ',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'not_wired',
            'credentials' => ['api_key' => ['config_path' => null, 'env_fallback_path' => null]],
        ],
        'iponz' => [
            'display_name' => 'IPONZ',
            'category' => 'registers',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'not_wired',
            'credentials' => ['api_key' => ['config_path' => null, 'env_fallback_path' => null]],
        ],
        'nz_parliament' => [
            'display_name' => 'NZ Parliament',
            'category' => 'regulatory_monitoring',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'not_wired',
            'credentials' => ['api_key' => ['config_path' => null, 'env_fallback_path' => null]],
        ],
        'worksafe' => [
            'display_name' => 'WorkSafe',
            'category' => 'regulatory_monitoring',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'not_wired',
            'credentials' => ['api_key' => ['config_path' => null, 'env_fallback_path' => null]],
        ],
        'whisper' => [
            'display_name' => 'Whisper',
            'category' => 'voice',
            'fallback_mode' => 'manual',
            'managed_via' => 'vault',
            'wiring_status' => 'not_wired',
            'live_config_path' => 'services.whisper.live',
            'credentials' => [],
        ],
    ],
];
