# Website Audit Module

The website audit evaluates the actual, advisor-confirmed client website. `WebsiteAuditRunner` owns the website-specific lifecycle around the shared analysis spine:

1. Advisor confirms the root URL in `website_url_confirmations`.
2. `WebsiteFetcher` resolves only public hosts, respects robots.txt, applies page and byte caps, and uses `ResilientHttp::probe()` with a host-scoped breaker key.
3. Deterministic parsing records page metadata, headings, schema, calls to action, contact/form signals, image-alt coverage, redirects, status codes, and capped text excerpts.
4. Technical, NZ trust-presence, health-score, and optional PageSpeed evidence are stored in a timestamped `website_audit_snapshots` row.
5. The shared `AnalysisRunner` supplies only the recorded excerpts and deterministic signals to the examiner AI, then persists cited findings.

## URL And Fetch Guardrails

- Questionnaire URLs are only candidates. No fetch happens until an advisor creates an active confirmation.
- With no listed URL or no confirmation, the runner records `skipped_no_url`, bypasses fetch/probes/PageSpeed/AI, and creates no website remediation finding.
- URLs must be public `http` or `https` addresses on ports 80/443. Loopback, private, link-local, reserved, local, internal, and unsafe redirect targets are rejected.
- Redirects, 404s, 410s, and robots responses are measured page states. They are not integration failures and do not open a global breaker.
- PageSpeed requires `PAGESPEED_INSIGHTS_API_KEY`. When absent or unavailable, the snapshot says `measured: false`; no performance value is invented.

## Evidence And Citations

Every fetched page stores a `website:{url} as at {timestamp}` source reference. The snapshot preserves page-level hashes, byte counts, excerpts, and truncation flags for the text supplied to the examiner. Client questionnaire answers may provide stated-offer context, but they do not stand in for website evidence.

The module emits all four lenses when readable pages are available:

- Descriptive: pages and health dimensions captured.
- Diagnostic: measured findability, technical, trust, and conversion gaps.
- Predictive: examiner assessment of discoverability and offer alignment.
- Prescriptive: verified improvement priorities.

Document verification and data-quality gates remain owned by `AnalysisRunner`. If a confirmed site is unreachable or blocked, the audit produces an honest empty state rather than an AI-generated inference.

## Downstream Flow

Verified website findings appear in the client and advisor reports, proposal focus areas, and strategic-plan action priorities. A skipped, blocked, or unreachable audit instead appears as a clear report note. Strategic plans do not create website remediation solely because a URL was absent.

## Boundaries

The module is advisor-triggered, not continuous monitoring. It does not claim live search rankings, legal compliance, or a complete NZBN NAP comparison when those signals are unavailable. The NZ trust sweep records presence signals only and requires advisor or legal review where needed.
