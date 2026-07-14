# PLAN — Website Audit Upgrade (from self-report commentary to verified evaluation)

**Plan version:** 1.1 — code-grounded design pass, review-fixed. *(Build target: Codex, into the test env, then push to live.)*

**One-line intent:** Turn the Standard Advisory `WebsiteAudit` module from *keyword-matching what the client
typed about their site* into a **real evaluation of the actual website** — fetched, parsed, measured,
NZ-compliance-checked, AI-evaluated against the client's stated offer, scored with trajectory, and wired into
the report, strategic plan, proposal/PV machinery — without weakening any other module or the AI-integrity contract.

---

## 1. Why (verified problem)

[WebsiteAudit.php](app/Services/Analysis/Modules/WebsiteAudit.php) never accesses the website. It:
- filters the client's **questionnaire answers** by keyword ([:208-232](app/Services/Analysis/Modules/WebsiteAudit.php:208));
- pattern-matches phrases in the client's own words — `'mobile pages are slow'`, `'cta is unclear'`, `'missing schema'`
  ([:313-440](app/Services/Analysis/Modules/WebsiteAudit.php:313));
- computes "alignment" as 25% word-overlap between two answers ([:445-476](app/Services/Analysis/Modules/WebsiteAudit.php:445));
- emits four largely **templated** findings dense with hedges ("should still be confirmed", "still needs measurement");
- attributes **every** finding to `questionnaire_answer:N` ([:237-263](app/Services/Analysis/Modules/WebsiteAudit.php:237)).

The ambitious scope it declares — SEO/GEO/AEO/AIO, structured data, mobile, NZ visibility
([:46-58](app/Services/Analysis/Modules/WebsiteAudit.php:46)) — is never measured. Net effect: the client is
told what they told us, and asked to verify it themselves. It also sits awkwardly against the AI-Integrity
Principle (*evidence-based; every claim cites its source*): the module structurally cannot cite the site.

## 2. Anchors (verified — the upgrade fits behind existing contracts)

- **Shared module contract unchanged:** `AnalysisModule` is `module()/promptId()/promptInput()/sourceReferences()/mapFindings()`
  ([Contracts/AnalysisModule.php:13-33](app/Services/Analysis/Contracts/AnalysisModule.php:13)); `AnalysisRunner::run(Client, AnalysisModule)`
  invokes it generically ([AnalysisRunner.php:52](app/Services/Analysis/AnalysisRunner.php:52)). The shared runner,
  enum, and every other module stay untouched. Website-specific side effects live in a **new `WebsiteAuditRunner` wrapper**
  (matching the `FinancialAnalysisRunner` pattern): create/fetch snapshot before the AI prompt, bind that snapshot ID
  into a request-scoped `WebsiteAuditSnapshotContext`, call the shared runner, then attach `analysis_run_id`/PV links
  after completion.
- **HTTP goes through the resilience layer:** `ResilientHttp::get(service, endpoint, query, cacheKey, fallback,
  headers, timeout, maxAttempts): IntegrationResult` ([ResilientHttp.php:29-38](app/Services/Integration/Resilience/ResilientHttp.php:29))
  — retry + circuit breaker + `IntegrationCall` health logging + redaction already built. **No raw `Http::` to any site** (CLAUDE.md hard rule).
  Website crawling needs one small extension: an **HTTP probe mode / acceptable-status policy** so expected observations
  like `301`, `302`, `404`, and `410` are returned as measured page states, not treated as integration failures that
  retry or open the breaker.
- **Findings model** already carries `attributions`, `documentSupport`, `uncertainty`, `severity`, `pvLinkId`
  ([AnalysisFindingData.php](app/Services/Analysis/AnalysisFindingData.php)) — real website citations and PV links drop straight in.
- **Reuse, don't reinvent:** `PromptRegistry`/`AiClient` (examiner side, `score_source` discipline), `ImprovementOpportunity`
  + `PvEngine`/`ImprovementPv` for the commercial framing, NZBN integration scaffolds for NAP checks, `EconomicIndicator`/
  reference-data pattern for thresholds, `ResilientHttp` health dashboard for fetch observability.

## 3. New services (behind the module)

| Service | Role |
|---|---|
| `WebsiteAuditRunner` | Website-specific wrapper around `AnalysisRunner`: confirms/loads the nominated URL, skips cleanly when no URL is listed/confirmed, creates the snapshot before prompt construction, runs the module, then links the snapshot and PV opportunities to the completed `AnalysisRun`. |
| `WebsiteAuditSnapshotContext` | Request-scoped handoff containing the snapshot ID for the current website-audit run, so `WebsiteAudit::promptInput()/sourceReferences()/mapFindings()` read the exact snapshot instead of "latest for client". Cleared after run/failure. |
| `WebsiteUrlConfirmation` | Stores the advisor-confirmed root URL (`client_id`, `root_url`, `confirmed_by_user_id`, `confirmed_at`, source questionnaire answer(s), status). No fetch starts from unconfirmed free text. |
| `WebsiteFetcher` | Discovers + fetches the nominated site's key pages through `ResilientHttp` (service `website_audit`). SSRF-guarded, robots-respecting, capped. Returns raw page snapshots. |
| `WebsitePageParser` | **Deterministic** extraction per page: title, meta description, canonical, OG/Twitter tags, H1–H3 outline, JSON-LD schema types, `tel:`/`mailto:`/`<form>` presence, image alt coverage, internal/broken link set, word count, viewport/lang. No AI. |
| `WebsiteTechnicalProbe` | Site-level signals: SSL + cert validity, HTTP→HTTPS redirect, robots.txt + sitemap.xml presence, canonical host, 404 handling. |
| `PageSpeedProbe` | Google **PageSpeed Insights API** (free key) via `ResilientHttp` → mobile + desktop Core Web Vitals (LCP/CLS/INP), performance score. Fallback = "not measured" (never a fabricated number). |
| `NzTrustComplianceCheck` | Deterministic NZ sweep: privacy policy / T&Cs presence (Privacy Act 2020, Fair Trading Act), GST-inclusive pricing cues on consumer pages, NAP (name/address/phone) **consistency vs the NZBN record**. |
| `WebsiteHealthScorer` | Dimension sub-scores — **findability, credibility, conversion, technical** — from the deterministic signals (0–100 each + blended), snapshotted per audit for trajectory. Shared normalisation helper (one formula, per the scoring-consolidation lesson). |
| `WebsiteAuditSnapshotStore` | Persists the timestamped snapshot + scores; each finding attributes to `website:{url}{path} as at {ts}`. Prior snapshot enables before/after. |

`WebsiteAuditRunner` orchestrates: confirm URL → fetch → parse → probe → score → snapshot → bind snapshot context →
shared `AnalysisRunner` → link completed run/PV.
`WebsiteAudit` (the module) then builds the examiner prompt from
**real page content + the client's stated products/services** → map findings from measured signals + AI, each
cited to the page. The keyword phrase-lists ([:313-440](app/Services/Analysis/Modules/WebsiteAudit.php:313))
are **deleted** — they matched client phrasing, not reality.

## 4. Data model — `website_audit_snapshots` (client-scoped)

| Column | Notes |
|---|---|
| `id` / `client_id` FK | client-scoped RLS (mirror analysis/document scoping); audited |
| `analysis_run_id` FK nullable | linked by `WebsiteAuditRunner` after the shared runner creates/completes the run; nullable only for pre-run/failed-fetch snapshots |
| `website_url_confirmation_id` FK nullable | proves the fetch used an advisor-confirmed URL, not arbitrary questionnaire text; nullable only for `skipped_no_url` |
| `root_url` nullable | the advisor-confirmed nominated URL; null only for `skipped_no_url` |
| `fetched_at` nullable | snapshot timestamp (drives "as at" attributions + `data_quality` staleness); null only for `skipped_no_url` |
| `pages` | jsonb — per-page parsed signals (title, meta, headings, schema types, CTAs, alt coverage, HTTP status, redirect chain) |
| `ai_evidence` | jsonb — capped page text excerpts/summaries, content hashes, byte counts, and truncation flags for exactly what the examiner saw |
| `technical` | jsonb — SSL, redirects, robots/sitemap, canonical host |
| `performance` | jsonb — PSI mobile/desktop vitals + `measured: bool` (false ⇒ never invent) |
| `nz_compliance` | jsonb — privacy/T&Cs/GST/NAP flags with per-flag evidence |
| `scores` | jsonb — `{findability, credibility, conversion, technical, overall}` + method version |
| `fetch_status` | `ok` \| `partial` \| `blocked` \| `unreachable` \| `skipped_no_url` — drives the honest empty-state |
| `skip_reason` | nullable; set to `no_website_url_listed` or `awaiting_advisor_confirmation` when `fetch_status = skipped_no_url` |
| `source_attributions` | jsonb — page/URL-scoped references for every measured claim |
| timestamps | |

`website_url_confirmations` is a small companion table (or equivalent client-scoped model) with `client_id`,
`root_url`, `status`, `source_questionnaire_answer_ids`, `confirmed_by_user_id`, `confirmed_at`, timestamps, RLS, and audit
events. The advisor UI can prefill candidates from the questionnaire's "Website URL and main product/service pages"
answer, but the persisted confirmation is the only fetch authority.

### Client intake and analysis readiness

- Every client engagement uses the same client-facing onboarding journey: welcome, goals, website, questionnaire,
  documents, and review. Identity verification and registry/business-snapshot checks remain account-security and
  advisor-workspace controls, not client tasks.
- A client submission is stored as `pending_advisor_review` and appears in the advisor readiness panel. The advisor may
  confirm the submitted address or replace it with a corrected address; only the resulting `confirmed` record authorizes
  a fetch.
- `Run analysis` runs the website review in the same Standard Advisory run. It remains unavailable until the client has
  submitted the questionnaire, uploaded supporting evidence, resolved any blocking evidence flags, and any nominated
  website URL has been advisor-confirmed. A missing URL remains a valid no-website path: the website module skips and
  the report records the required note.
- The advisor action uses traffic-light readiness directly on the Run analysis button: red means required client inputs
  are incomplete and blocks analysis; amber means the minimum client inputs allow analysis; green means the client has
  submitted the complete onboarding pack. Document verification remains a separate advisor control and only blocks
  analysis when it raises an unresolved blocking flag.

## 5. Guardrails (hard — every WO)

- **Client-nominated + advisor-confirmed URL only.** No crawling arbitrary domains; the advisor confirms the
  root URL into `website_url_confirmations` before a fetch runs. Depth/page cap (e.g. ≤ 15 pages) and total-byte cap.
- **No URL = skipped, not scored.** If no website URL is listed, or a listed URL has not been advisor-confirmed,
  `WebsiteAuditRunner` skips fetch/probe/PSI/AI and records `fetch_status = skipped_no_url` with a clear reason.
  Reports must say "Website review not performed — no website URL listed/confirmed"; no website health score, fake
  finding, PSI fallback number, or strategic-plan remediation is created from missing evidence.
- **SSRF defence:** resolve + reject private/loopback/link-local/metadata IPs; block non-http(s) schemes; block
  redirects that cross to a private host. Public web only.
- **Politeness:** respect `robots.txt`, a descriptive UA identifying FSA, rate-limited, all through
  `ResilientHttp`'s breaker/health logging — a slow or hostile site degrades gracefully, never hangs analysis.
- **Probe semantics:** expected website statuses and redirects are measured facts. `404`/`410` page checks,
  `301`/`302` redirect chains, and robots responses must not be logged as integration outages or allowed to open the
  global website-audit breaker. Breaker keys should be host-scoped (e.g. `website_audit:{host}`) so one hostile site
  does not suppress unrelated client audits.
- **No fabricated measurement (AI-Integrity):** if PSI/fetch fails, findings say *not measured* — never a guessed
  score. Every measured claim cites `website:{url}{path} as at {ts}`; AI-derived claims carry `score_source`.
- **AI reads real content only:** the examiner prompt receives fetched page text + the client's stated offer;
  it never invents page content. The snapshot stores content hashes and capped excerpts/summaries for the exact text
  sent to AI so claims remain auditable after the live site changes. Examiner-side classification in the prompt registry
  (no coaching leakage).
- **`fetch_status` honesty:** `skipped_no_url`/`blocked`/`unreachable` produces a *clear advisory/report flag*
  ("website review not performed — no URL listed/confirmed" or "site could not be evaluated — nominated URL
  unreachable / blocked by robots"), not silent low scores.

## 6. Work Orders

| WO | Title | Deliverable |
|---|---|---|
| **W0** | URL confirmation + HTTP probe semantics | `WebsiteUrlConfirmation` model/migration/RLS/audit + client Website onboarding submission + advisor confirmation/update UI/API + red/amber/green analysis readiness + `skipped_no_url` state. Extend `ResilientHttp` with probe/acceptable-status support and host-scoped breaker keys for website audit calls. Tests prove unconfirmed client/questionnaire URLs do not fetch or enable analysis; no listed URL skips cleanly; 301/302/404/410 are measured states; one failed host does not open the breaker for another host. |
| **W1** | Fetch + parse + snapshot (**the transformative core**) | `WebsiteAuditRunner` + `WebsiteAuditSnapshotContext` + `WebsiteFetcher` (ResilientHttp probe mode, SSRF guard, robots, caps) + `WebsitePageParser` (deterministic) + `website_audit_snapshots` migration (client RLS, audit, nullable URL fields only for `skipped_no_url`, `website_url_confirmation_id`, `ai_evidence`) + `WebsiteAuditSnapshotStore`. `WebsiteAudit` rewritten: findings from parsed signals, cited to pages; **keyword phrase-lists deleted**; `fetch_status` empty-states, including no-URL skip. Unit tests parse fixture HTML (schema present/absent, CTA present/absent, missing meta, broken links); SSRF-rejection tests (private IP, non-http, cross-host redirect). |
| **W2** | Technical + performance probes | `WebsiteTechnicalProbe` (SSL/redirect/robots/sitemap/404 handling using probe semantics) + `PageSpeedProbe` (PSI via ResilientHttp; API key in `.env` only, never committed; `measured:false` fallback). Findings quantify: "LCP 6.1s mobile vs Google's 2.5s threshold." |
| **W3** | NZ trust/compliance + health score | `NzTrustComplianceCheck` (privacy/T&Cs/GST/NAP-vs-NZBN) + `WebsiteHealthScorer` (four dimensions + overall, one shared normalisation helper, snapshotted). Trajectory: this snapshot vs prior. |
| **W4** | AI evaluation of real content | Examiner prompt (`analysis.website_audit` updated) fed **fetched page text + stated products/services**: does the site name/explain/prove the offer; value-prop clarity; trust signals. `score_source` persisted; `FakeAiClient`/`RecordingAiClient` tests. |
| **W5** | Commercial wiring + report + strategic plan | Website gaps become `ImprovementOpportunity` rows with **PV framing** via `ImprovementPv` (`pvLinkId` on the finding). Verified website findings flow into the client report and strategic plan sections — per the house standard (what's wrong / why it matters commercially / what to fix). The report section renders scored dimensions + before/after for `ok`/`partial`; for `skipped_no_url`, the report renders a clear "Website review not performed — no website URL listed/confirmed" note and the strategic plan excludes website remediation unless the advisor separately adds "confirm website URL" as an intake/admin action. |

Sequence W0 → W1 → W2 → W3 → W4 → W5. **W1 alone** converts the module from hedged self-report commentary into
verified, cited findings — the biggest credibility gain per unit of work. One WO per branch/PR.

## 7. Testing

URL confirmation required before fetch; no listed URL produces `skipped_no_url`, no fetch/probe/PSI/AI, and a report
note; HTTP probe mode preserves `301`/`302`/`404`/`410` as measured states and keeps
breaker isolation host-scoped; fixture-HTML parse matrix (schema/CTA/meta/alt/broken-link permutations); SSRF
rejections; robots-disallow →
`blocked` finding, not a fetch; PSI failure → `measured:false`, no fabricated vitals; NAP mismatch vs a seeded
NZBN record flags; health-score boundaries + trajectory (score rises when a fixed snapshot replaces a weak one);
AI evaluation with `FakeAiClient` (real page text in, `score_source` out, `ai_evidence` hashes/excerpts retained);
snapshot-context tests prove the module uses the bound snapshot, not the latest/oldest snapshot for the client;
every actual website finding carries a
`website:`/`page:` attribution (assert none fall back to `questionnaire_answer:` once a site is fetched); the
module still returns coherent report output when `fetch_status = skipped_no_url` or `unreachable`; report composer
tests assert website findings appear in reports; strategic-plan tests assert website fixes appear only when real
website findings exist; `pint --test`, Larastan (if adopted), `tsc`, ESLint, full PHPUnit; **no other analysis
module's output changes** (regression). Onboarding coverage asserts every client engagement receives only the six
client-facing steps and cannot surface the retired identity or business-snapshot controls.

## 8. Owner-confirm / open

1. **PSI API key** — free Google key, `.env` only (like the Stats NZ keys). Confirm you'll provision it; without
   it W2 degrades to `measured:false` (still shipped, just no vitals).
2. **Page cap + audit cadence** — default ≤ 15 pages/audit; re-audit is advisor-triggered (or on the LivingPlan
   cadence). Confirm the cap.
3. **NAP source of truth** — the NZBN record vs the client-entered address when they differ is itself a finding;
   confirm NZBN is authoritative for the flag.
4. **AI evidence retention** — confirm the retention policy for capped page excerpts/summaries in `ai_evidence`
   (private client evidence, not public report copy), including byte limits and deletion/export expectations.

## 9. Strategic note (out of plan scope, worth flagging)

A verified website audit is also a **standalone low-fee entry product** (like the integration scoping mini-fee)
that converts into web-improvement work — potentially delivered through
[PLAN-INTEGRATION-EFFICIENCY-SERVICE.md](PLAN-INTEGRATION-EFFICIENCY-SERVICE.md)'s build lane. Not built here;
the upgraded module is the credibility foundation that makes that offer sellable.

## 10. CLAUDE.md block (add on build)

> **Website audit.** The `WebsiteAudit` module evaluates the **actual** client website through `WebsiteAuditRunner`:
> `WebsiteUrlConfirmation` (advisor-confirmed URL only) → `WebsiteFetcher`
> (via `ResilientHttp` probe mode, SSRF-guarded, robots-respecting, public web only, host-scoped breaker) →
> deterministic parse/probe → `website_audit_snapshots` (client-scoped, timestamped) → `WebsiteHealthScorer`
> (findability/credibility/conversion/technical) → examiner AI over real page text + stated offer. Every
> measured claim cites `website:{url} as at {ts}`; snapshots retain content hashes and capped excerpts/summaries
> for the text sent to AI; expected statuses like redirects and 404s are measured facts, not integration outages;
> failures say *not measured* (never fabricated); AI claims carry `score_source`;
> `skipped_no_url`/`blocked`/`unreachable` is a clear report/advisory flag, not a silent low score. If no website
> URL is listed/confirmed, skip fetch/probe/PSI/AI and note that in the report; do not create website remediation in
> the strategic plan from missing evidence. Verified website findings must render in reports and flow into the
> strategic plan. No raw
> `Http::` to any site; PSI key in `.env` only.

---

*Companion to the Standard Advisory analysis suite. Produced at owner request — "make the website evaluation
better than low-key."*
