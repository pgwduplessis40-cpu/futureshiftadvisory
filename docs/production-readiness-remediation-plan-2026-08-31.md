# Production-readiness remediation plan

**Status:** proposed implementation plan
**Created:** 31 August 2026
**Scope:** Laravel/PHP backend, React/TypeScript frontend, CI, and authenticated browser quality gates

This plan turns the comprehensive review findings into mergeable work. It does
not certify the current checkout as production-ready. In particular, the full
coverage run did not complete and authenticated browser validation could not
run without the CI-only test secrets; current coverage and browser quality must
therefore be demonstrated rather than inferred from older results.

## Release policy while work is in progress

Treat the following as release blockers until their acceptance criteria are
met:

1. formatting is clean;
2. production structural limits pass, not merely no-growth limits;
3. sensitive models cannot be broadly mass assigned;
4. a full, artifact-producing coverage run meets the defined thresholds; and
5. authenticated desktop and mobile browser flows pass.

Do not bypass a blocker with a permanent baseline or an undocumented CI
exception. If an emergency release is unavoidable, record a time-limited,
approved exception with its owner, exact commit, risk, compensating control,
and expiry. The release gate must reject an expired exception.

## Delivery order

| Wave | Work items | Primary outcome |
| --- | --- | --- |
| 0 | PR-01 to PR-03 | Reliable, enforceable CI evidence and a clean baseline |
| 1 | PR-04 to PR-06 | Smaller frontend boundaries and protected financial data |
| 2 | PR-07 to PR-09 | Non-blocking report output and deterministic locale handling |
| 3 | PR-10 to PR-12 | Complete coverage governance and authenticated browser proof |
| Continuous | PR-13 to PR-14 | Shrinking static-analysis debt and regression prevention |

Keep each work item in a focused pull request. No item may include unrelated
worktree files, generated report previews, test credentials, or production
data.

## Wave 0 — establish trustworthy release evidence

### PR-01: Clean and enforce frontend formatting

**Owner:** frontend
**Priority:** release blocker

Run Prettier over the twelve reported files, review the diff for semantic
changes, and require the existing formatting check in CI. The review found
formatting failures in:

- `resources/js/pages/admin/learning/Index.tsx`
- `resources/js/pages/advisor/clients/Show.tsx`
- `resources/js/pages/advisor/dashboard/types.ts`
- `resources/js/pages/advisor/entrepreneurs/types.ts`
- `resources/js/pages/advisor/service-activations/Show.tsx`
- `resources/js/pages/portal/Dashboard.tsx`
- `resources/js/pages/portal/dd/BusinessPlan.tsx`
- `resources/js/pages/portal/onboarding/Step.tsx`
- `resources/js/pages/portal/outcomes/Show.tsx`
- `resources/js/pages/portal/ProposalSignoff.tsx`
- `resources/js/pages/portal/StrategicPlanBudget.tsx`
- `resources/js/pages/settings/install-app.tsx`

**Acceptance criteria**

- `npm run format:check`, linting, and TypeScript checks pass from a clean
  checkout.
- Formatting and build commands leave no generated or lockfile noise in the
  worktree.

### PR-02: Make full PHPUnit coverage reproducible

**Owner:** platform and backend
**Priority:** release blocker

Provide a dedicated serial test lane using PCOV (preferred for speed) or an
appropriately bounded Xdebug runtime. It must use PostgreSQL and ZipArchive so
the intended RLS and archive tests execute. Persist JUnit, Clover, a summary,
and the failure log even when the suite fails or times out.

The lane must have a defined time budget and a diagnostic split strategy:
first record the slow test, then run the relevant group serially. Do not call a
detached or artifact-free process a passing test suite.

**Acceptance criteria**

- The full suite completes in CI and produces non-empty JUnit and Clover
  artifacts.
- Results publish passed, failed, skipped, incomplete, and risky/flaky counts.
- A failed, timed-out, or artifact-free coverage run fails the quality job.

### PR-03: Enforce actual production structural limits

**Owner:** platform and frontend
**Priority:** release blocker

Wire `php scripts/assert-monolith-size.php --production` into the required
release-quality job and branch protection. The current no-growth assertion is
insufficient because it permits already-over-limit files to ship.

The immediate known failures are:

- `resources/js/pages/advisor/clients/Show.tsx` — 7,702 lines; production
  limit 1,000.
- `resources/js/pages/portal/entrepreneur/Plan.tsx` — 5,767 lines; production
  limit 1,000.

**Acceptance criteria**

- Required CI invokes the production assertion.
- Both named pages meet their configured limits before a normal production
  release.
- Any exception is explicit, time-limited, and cannot silently roll forward.

## Wave 1 — protect high-risk domains and restore boundaries

### PR-04: Decompose advisor client detail page

**Owner:** frontend
**Priority:** high

Break `resources/js/pages/advisor/clients/Show.tsx` into domain panels,
typed page-prop contracts, data hooks, and small presentation components. Keep
the route page responsible only for route-level composition. Preserve existing
permissions, loading/error states, and URL behaviour during extraction.

Create focused tests for each extracted contract and update the authenticated
advisor-client E2E path.

**Acceptance criteria**

- The page is at or below 1,000 lines and each extracted module has a single
  domain responsibility.
- No `any`-based bridge or unsafe cast is introduced solely to make extraction
  compile.
- Advisor client route, permission, error, and responsive tests pass.

### PR-05: Decompose entrepreneur plan page

**Owner:** frontend
**Priority:** high

Apply the same extraction approach to
`resources/js/pages/portal/entrepreneur/Plan.tsx`: section navigation,
autosave/form state, plan content panels, document interactions, and calculation
display logic need separate typed boundaries. Preserve the client-facing plan
workflow and draft-recovery behaviour.

**Acceptance criteria**

- The page is at or below 1,000 lines.
- Autosave, draft recovery, validation, and client document flows have focused
  tests.
- The production structural gate passes without a permanent allowance.

### PR-06: Remove broad mass assignment from sensitive models

**Owner:** backend and security
**Priority:** high

Inventory every `$guarded = []` model and classify it by financial, report,
identity, client-data, or low-risk reference data. Begin with the financial
models identified in the review:

- `app/Models/AccountingInvoice.php`
- `app/Models/FeeCalculation.php`
- `app/Models/ClientFunderRecord.php`
- `app/Models/ServiceRatePackage.php`

Replace unrestricted declarations with explicit `$fillable` fields. Move
privileged status, calculated values, and internal-only updates into dedicated
actions/services. Add request validation that rejects unrecognised input,
register these models as sensitive, and ratchet the allowed broad-model count
down on every change.

**Acceptance criteria**

- No financial, report, identity, or client-data model uses `$guarded = []`.
- Tests prove protected fields cannot be supplied through request-backed
  `create` or `update` calls.
- The sensitive-model registry and mass-assignment gate reject regressions.

## Wave 2 — eliminate blocking report work and rendering drift

### PR-07: Queue report artifact regeneration

**Owner:** backend
**Priority:** high

Replace synchronous stale/missing artifact rendering in portal and advisor
report controllers with an idempotent job. Use a report ID plus content revision
or template hash as the deduplication key. Persist `pending`, `rendering`,
`ready`, and `failed` states; dispatch only after transaction commit; return a
clear pending response that the client can poll or retry.

The job must safely handle duplicate requests, stale jobs, renderer timeouts,
artifact cleanup, and retry limits. Existing after-commit composition behaviour
is retained; the remediation addresses the remaining request-time Browsershot
work.

**Acceptance criteria**

- No portal or advisor HTTP request invokes a long-running PDF renderer for a
  stale or missing artifact.
- Tests cover duplicate dispatch, report revision changes, retry/failure,
  authorization, and final artifact visibility.
- Observability records render duration, failure reason, and report ID without
  leaking client content.

### PR-08: Use explicit locale and date semantics in SSR UI

**Owner:** frontend
**Priority:** medium

Create shared currency, number, and date formatters with an explicit locale
and intentional treatment of date-only values and timezone. Replace
`Intl.NumberFormat(undefined, ...)` and `Intl.DateTimeFormat(undefined, ...)`,
starting with the Dashboard and DD business-plan pages.

Add a repository rule that prohibits implicit-locale `Intl` construction in
SSR-rendered code unless a reviewed exception documents why it is safe.

**Acceptance criteria**

- Server and client render the same money and date strings for core flows.
- Tests cover NZ daylight-saving boundaries and date-only values.
- New implicit-locale formatter calls fail lint or the repository check.

### PR-09: Add calculation and report boundary tests

**Owner:** backend and frontend
**Priority:** medium

Add contract-level tests around money, dates, scoring, runway, funding terms,
and client-facing report construction. Include invalid values, rounding,
zero/negative boundaries, leap days, timezone changes, concurrent updates, and
rendered report status transitions.

Retain regression coverage for behaviours already corrected: client-access
policy checks, webhook throttling, funding-term validation, null-safe executive
summary generation, and after-commit report composition.

**Acceptance criteria**

- Every critical calculation and report boundary is associated with an explicit
  coverage group in PR-10.
- Defects at the listed boundaries have a focused test before a fix merges.

## Wave 3 — complete coverage and browser governance

### PR-10: Replace fuzzy coverage classification with a risk manifest

**Owner:** platform, backend, and frontend
**Priority:** release blocker

Replace filename-only coverage matching with a reviewed manifest that names
every source file in these groups:

- money, invoices, funding, pricing, payments, and fee calculation;
- dates, forecasts, runway, scoring, and business calculations;
- report composition and all client-facing PDF/DOCX outputs; and
- authorization, identity, and client-data access boundaries.

The reporter must list matched files, unmatched critical files, each module's
coverage, and skipped/disabled tests. Keep the thresholds: overall 85%, module
80%, and every critical group 90%. Do not treat backend Clover as coverage for
TSX; establish an explicit frontend test/evidence metric for critical flows.

**Acceptance criteria**

- A current coverage report identifies overall percentage, module percentages,
  and each critical group.
- Any critical group below 90%, module below 80%, unexpected skip, or empty
  critical group fails CI.
- Threshold changes only accompany new tests and an upward-reviewed baseline.

### PR-11: Run authenticated browser checks in CI

**Owner:** frontend, QA, and platform
**Priority:** release blocker

Use the existing CI-secret-only accounts and seeded environment to run
`npm run browser:e2e` for auth/onboarding, Interactive Dashboard, NPO, Budget
and Runway Builder, and Client Screen/WebRTC at 1440px and 390px. Retain the
existing console, page-error, HTTP/request, Axe, missing-alt, overflow,
WebRTC, and approved-screenshot checks.

Extend keyboard testing to assert a *visible* focus indicator, not simply a
focusable element. Save reviewed screenshots and browser reports as CI
artifacts. Never place passwords, MFA secrets, bypasses, or production captures
in the repository.

Meridian Warm validation applies to public pages, as documented by the brand
guide. Authenticated product screens should be reviewed against their approved
application theme and accessibility tokens unless design approval expands the
brand scope.

**Acceptance criteria**

- All five flows pass at both viewport sizes on the secret-backed CI lane.
- Axe violations, browser runtime errors, failed requests, horizontal overflow,
  absent alt text, or invisible keyboard focus fail the job.
- Visual changes require reviewed baseline updates with the reason recorded.

### PR-12: Make release branch protection reflect the policy

**Owner:** repository owner and platform
**Priority:** release blocker

Require the PHP 8.4/8.5 suites, quality job, authenticated browser job, and
release-quality structural/coverage assertions before merging to `main` or
releasing. Repository YAML cannot configure GitHub branch protection, so this
requires a repository-settings change and an evidence check.

**Acceptance criteria**

- Branch protection requires the declared checks and prevents direct bypasses.
- A release dry run confirms required checks cannot be skipped by workflow
  ordering or a successful-but-incomplete artifact upload.

## Continuous improvement

### PR-13: Burn down PHPStan baseline debt

**Owner:** backend
**Priority:** medium

PHPStan level 6 currently passes with a large baseline. Divide baseline entries
by domain, address financial/reporting and authorization diagnostics first, and
set a decreasing cap for every release cycle. Disallow new baseline entries or
suppression growth.

**Acceptance criteria**

- The baseline count decreases each agreed cycle.
- New diagnostics are fixed in the same change or explicitly rejected by CI.

### PR-14: Extend structural and performance safeguards

**Owner:** backend and frontend
**Priority:** medium

As the two priority pages are decomposed, add further controllers/services/pages
to the structural registry before they become unmaintainable. Profile critical
dashboard/report paths for N+1 queries and excessive payloads; fix confirmed
issues with query-count and payload-contract tests. Use explicit typed
boundaries, error states, and error boundaries for extracted client modules.

**Acceptance criteria**

- New structural limits are based on measured, reviewed boundaries.
- Confirmed N+1 and oversized-payload fixes include regression tests.
- Extracted React features have typed loading, error, and empty states.

## Mandatory verification matrix

Run these from a clean checkout before declaring the implementation complete:

```text
vendor/bin/pint --parallel --test
npm run format:check
npm run lint:check
npm run types:check
vendor/bin/phpstan analyse --memory-limit=1G
npm run build
php scripts/assert-monolith-size.php --production
php scripts/assert-mass-assignment.php <verified-base-sha>
<serial PCOV coverage command producing JUnit and Clover>
php scripts/assert-coverage-thresholds.php <coverage-artifact>
npm run browser:e2e
```

Use the lockfile-pinned Node and PHP runtimes in CI. Capture command output and
artifacts with the release evidence; a command that does not run, times out, or
produces no result is a failed gate, not a pass.

## Definition of done

The implementation may be merged as production-quality work only when every
release blocker above passes on the merge commit, both oversized pages meet
their limits, current coverage proves all critical groups at or above 90%, and
the authenticated browser suite has supplied reviewed desktop and mobile
evidence. A green targeted test suite, a clean static-analysis baseline, or an
unverified historical coverage number is not sufficient on its own.
