<?php

declare(strict_types=1);

return [
    'max_pages' => max(1, (int) env('WEBSITE_AUDIT_MAX_PAGES', 15)),
    'max_total_bytes' => max(100_000, (int) env('WEBSITE_AUDIT_MAX_TOTAL_BYTES', 2_000_000)),
    'max_page_text_bytes' => max(1_000, (int) env('WEBSITE_AUDIT_MAX_PAGE_TEXT_BYTES', 12_000)),
    'timeout_seconds' => max(1, (int) env('WEBSITE_AUDIT_TIMEOUT_SECONDS', 15)),
    'user_agent' => env('WEBSITE_AUDIT_USER_AGENT', 'FutureShiftAdvisoryWebsiteAudit/1.0'),
    'pagespeed_api_key' => env('PAGESPEED_INSIGHTS_API_KEY'),
    'pagespeed_endpoint' => env('PAGESPEED_INSIGHTS_ENDPOINT', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'),
];
