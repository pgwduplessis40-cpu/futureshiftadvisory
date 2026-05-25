# Future Shift Advisory — Platform Methodology & Formula Registry (internal)

**Source:** owner request (May 2026) — "list all current methodologies/formulas used across features; hover explains what it is and where it's used."
**Track scope:** a single **internal, read-only registry** of the *platform's own* computational methods (formulas, scoring, valuation, thresholds), surfaced on the advisor Knowledge tab with hover + drill, **registry-as-code** so it can't drift from the real implementation.
**Predecessors:** Phase 1–3 complete on `featureApp`. Pairs with `PLAN-DASHBOARD-INTERACTIVITY.md` (those hovers cite this registry).
**Work orders:** WO-M01 … WO-M05 (a dedicated `M` band — does **not** consume the global numeric sequence reserved for Phase 4 = WO-102…120).
**Sequencing:** independent; best run **after/with** the Dashboard-Interactivity track (so its hovers cite the registry). Does not block Phase 4.
**Plan version:** 1.10

> **v1.10 — tenth review resolution (1 P2):** the integration-health entry's `config_refs` are now the **four exact resolvable keys** (`integrations.health.{green,amber}.{min_success_rate,max_p95_latency_ms}`), not wildcards — `config_refs` must resolve via `resolvedParameters()` and pass the existence test; wildcards live **only** in the WO-M01 allowlist reject-pattern, never in an entry's `config_refs`.

> **v1.9 — ninth review resolution (2 P2):** (P2) the namespace-completeness scan no longer targets the whole `App\Services\Integration` tree (full of non-methodology adapters/fixtures) — it scans the **single extracted `IntegrationHealthBander`** (or a dedicated narrow `…\Methodologies` namespace), avoiding a large unrelated exclusion list. (P2) "marked **service**" → "marked **class** (service or command)" throughout the round-trip test, acceptance, DoD, and MR-3, so the command-owner fallback isn't accidentally rejected by the guard.

> **v1.8 — eighth review resolution (1 P1):** integration-health banding has **no service owner today** — it lives in `AggregateIntegrationHealth::healthFor()` (a console command, outside the service-scan namespaces). The owner rule is generalised to a **marked *class*** (service or command, never a bare enum/model), and **WO-M02 now carries an explicit task** to extract the banding into a marked `App\Services\Integration\IntegrationHealthBander` (preferred) or mark the command + scan its namespace — the one small refactor the catalogue requires. The namespace-scan list updated accordingly.

> **v1.7 — seventh review resolution (1 P2):** the **WO-M03 acceptance** now lists the same failure cases as the guard (§WO-M03) and the DoD — adding the **reverse registry-entry→owner `methodologyIds()` check** and **malformed/empty/duplicate `methodologyIds()`** so implementers don't treat them as track-boundary-only checks.

> **v1.6 — sixth review resolution (2 P2):** (P2) the methodology-id format is now an **exact regex** `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$` — underscores explicitly allowed, so `radar.dimension_score` is valid; the same regex governs every registry id. (P2) the Definition of Done now includes the **reverse registry→marker check** (an entry id not declared by its owner's `methodologyIds()` fails) and the id-regex violation, matching the actual WO-M03 guard.

> **v1.5 — fifth review resolution (1 P1, 1 P2):** (P1) the marker↔registry round-trip is now **truly bidirectional** — each registry entry's id must appear in its `owning_service`'s `methodologyIds()` (not just "owner is marked"), and `methodologyIds()` must be non-empty, unique, lowercase dotted slugs. (P2) Risk MR-3 reworded to the honest scope (marked services + designated namespaces, residual gap outside).

> **v1.4 — fourth review resolution (1 P1, 2 P2):** (P1) the marker now returns **`methodologyIds(): array`** (repeatable attribute) — one service can own several methods (PV DCF + discount method + waterfall), and **multiple registry entries may share one `owning_service`**; the round-trip test checks every declared id and that every entry's owner is a marked service. (P2) §6 updated to describe **both** drift-guard layers (marker discovery **+ namespace completeness + exclusions**). (P2) the Definition of Done reworded to the honest scope (marked services / designated-namespace classes / enum-model-owner / dangling id / bad config_ref), not "any calculator."

> **v1.3 — third review resolution (1 P1, 1 P2):** (P1) the drift guard now has **two layers** so a *forgotten* marker still fails CI — marker→registry discovery **plus** a **namespace-completeness scan** of designated calculation namespaces (every concrete class must be marked or explicitly excluded); acceptance reworded to "marked / in-namespace" honesty. (P2) enum/model-backed methods (`DiscountMethod`, `FeeMethod`, `AnalysisLens`, `FindingSeverity`, `IntegrationHealthSample`, `BusinessValuation`) must set `owning_service` to the **marked service that applies them** (not a bare enum/model), so they're covered by service discovery — enforced by a test.

> **v1.2 — second review resolution (2 P1, 2 P2):** (P1) the **`ProvidesMethodology` marker and config-ref allowlist now ship in WO-M01** (WO-M02 only *adopts* the marker, WO-M03 only *tests* discovery) — so each WO is a single green commit. (P1) `methodology_id` linkage is **scoped to internal advisor payloads only** — explicitly forbidden in any portal/client/export payload (incl. the client-portal radar), with a test. (P2) `config_refs` are constrained to a **non-secret allowlist** with a sensitive-pattern reject (`*secret*`/`*key*`/`*token*`/…) so a mistaken ref can't expose credentials. (P2) the §6 drift-guard open-decision is updated to the marker-discovery design (the stale curated-list option removed).

> **v1.1 — first review resolution (2 P1, 2 P2):** (P1) the `knowledge/methodologies` route must be **registered before `knowledge/{knowledgeEntry}`** (+ `whereUuid` constraint) or it's shadowed as an entry id. (P1) the drift guard now uses an **enforced marker interface/attribute** (`ProvidesMethodology`) discovered by the test — so a new calculator without a registry entry genuinely fails CI — and the acceptance is scoped honestly (structural coverage, not prose/math correctness). (P2) entries carry **`config_refs`** and the detail view renders **live config values** (engagement weights/bands, radar `severity_weights`/`load_cap`, payment caps) instead of hard-coded prose. (P2) confidentiality tests now cover **`client_team`** (not just `client_primary`) and **unauthenticated** access.

> Companion to `PLAN.md` and the phase/track plans — they stay as-is. This is a small **enhancement track**, not a new phase. It is **distinct from the advisor's private Knowledge base** (`KnowledgeEntry`, manual per-advisor entries) — this registry is **system-owned, read-only, and developer-maintained.**

---

## 0. How to use this plan

1. Read §1–§4 once. They carry the non-negotiable principles, the current-state audit, and the registry design every WO reuses.
2. Implement **Work Orders** in §6 in order — **WO-M01 (foundation) blocks the rest.**
3. **Commit cadence (matches the other tracks):** single `featureApp` branch, **no WO branches/PRs**; **one commit per WO** (`WO-M0x: <slug>`); **`IMPLEMENTATION.md` updated in the same commit**; full verification at the track boundary. Regenerate Wayfinder (`php artisan wayfinder:generate --with-form`) on any controller/route change.
4. A WO is done only when its acceptance criteria hold and the gate is green: **PHPUnit via `php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --no-coverage`** (⚠️ `composer test` wraps `artisan test`, which hangs in this environment), plus `npm run lint:check` / `types:check` / `format:check`.
5. **Zero new analysis/AI behaviour.** This track only *describes* existing math; it never changes a formula, a score, or a weight. If a formula needs changing, that's a separate WO in its owning plan.

---

## 1. Non-negotiable principles (carried forward — still binding)

- **Methodology/IP is confidential (spec §; PLAN-PHASE2 redaction posture).** Stakeholder reports already **redact "FSA methodology/IP."** This registry concentrates that IP in one place, so it is **internal-only** — visible to internal staff (super_admin / advisor / junior_advisor / entrepreneur_mentor via `knowledge.view`) and **never** rendered in, or reachable from, the **client (`client_primary` *and* `client_team`)** / entrepreneur / broker / coach portals, the public site, an unauthenticated request, or any client/stakeholder export. A test enforces this for every one of those caller types.
- **Explainability (PLAN.md "no black-box numbers") + AI Integrity (spec §3).** Every registry entry states *what it is*, *the formula*, *its inputs/assumptions*, *where it's used*, and (where relevant) its *source/citation*. This is the same evidence-based ethos applied to the platform's own computations.
- **Registry-as-code (accuracy over prose).** The registry is the single source of truth in the repo, reviewed in PRs, and **guarded by a test** so it can't silently drift from the services it describes. It is descriptive metadata, not executable logic — it never becomes a second place where a formula "lives."
- **Audit + RLS unchanged.** The registry holds **no client data** (it's about methods, not clients), so it is not client-scoped and needs no RLS table. Reads may be audit-logged (optional, WO-M04). All existing security middleware applies to the route.
- **No silent learning.** The registry is fixed content; it is not a learning layer and never self-updates.

---

## 2. Goal & scope

### 2.1 Goal
Give internal staff a **single, trustworthy, always-current catalogue** of how the platform calculates everything — surfaced on the Knowledge tab with a hover ("what is this?") and a detail view ("the formula + where it's used") — so advisors can explain any number the platform produces, and so the explainability/AI-integrity commitments have a concrete home.

### 2.2 In scope
- A structured **registry of platform methodologies/formulas** (registry-as-code).
- A complete **catalogue** of the methods currently implemented (§3.3).
- An **internal read-only "Platform methodologies" view** on the Knowledge tab (list · search · hover · detail · where-used).
- A **drift-guard test** that keeps the registry honest, and (optional) linkage so dashboard hovers cite registry ids.

### 2.3 Explicitly out of scope
- **The advisor's private Knowledge base** (`KnowledgeEntry`) — untouched; this is a separate, system-owned surface.
- **Any change to a formula/score/weight.** Description only.
- **Client-facing methodology disclosure** — forbidden (§1 confidentiality).
- AI-assisted capture of advisor methodologies (that's the existing Knowledge-base Phase 3 item, unrelated).

---

## 3. Current-state audit (grounded in the code)

**3.1 The Knowledge tab today** — `KnowledgeController` + `KnowledgeEntry`: advisor-**private**, manually-entered notes (`forAuthor` scope), with categories incl. a free-text `methodology` one. PLAN.md: *"Private methodology repository owned by each advisor; manual entry only in Phase 1."* So the existing `methodology` category is the advisor's *own* notes — **not** a system-formula catalogue. The new registry sits **alongside** it as a separate, read-only, system-owned tab.

**3.2 Relevant commitments already in the plan** — PLAN.md: *"Score components are individually explainable in the UI (no black-box numbers)"*; spec §3 AI Integrity (evidence-based, source-attributed); PLAN-PHASE2 redacts "FSA methodology/IP" from stakeholder reports (⇒ internal-only).

**3.3 The methods that exist to catalogue** (representative — WO-M02 completes the list; verify each against its service before writing the entry):

| Area | Method (service/model) | Gist to capture |
|---|---|---|
| Valuation | `BusinessValuation` reconciliation | SDE / EBITDA / DCF → reconciled low/mid/high |
| PV | `PvEngine` / `DiscountMethod` / `PvWaterfallBuilder` | DCF present value; discount method + rate |
| PV | `RiskCostPv` | probability × impact, discounted, ranked |
| Fees | `FeeCalculation` / `FeeMethod` | outcome-based fee, ROI ratio |
| Data quality | `DataQualityScorer` | weighted signals (verified docs, questionnaire completeness, freshness) → level |
| Engagement | `ClientEngagementScorer` *(dashboard track)* | 4 weighted 0–100 sub-scores → G/A/R |
| Health radar | `BusinessHealthRadarBuilder` *(dashboard track)* | severity-weighted load per dimension → 0–100 |
| Economic | `EconomicExposureMapper` *(dashboard track)* | indicator/FX → exposed-client heuristics |
| Funnel | `FunnelTracker` | drop-off rate per step |
| Entrepreneur | `RatingFramework` / concept-PV projection | weighted criteria + grade bands; draft-stage PV |
| Analysis | `AnalysisLens`, `FindingSeverity` | the descriptive→prescriptive lens model; severity scale |
| DD | DD risk register / price adjustment / `DdValuation` | risk levels, adjustment, FX normalisation |
| Integration | `IntegrationHealthSample` | success-rate / p95 → green/amber/red bands |
| Wellbeing/coach | wellbeing scoring, coach-signal thresholds | check-in scales, referral thresholds |

These are real, distinct, and currently under-documented — so the registry has genuine content from day one.

---

## 4. Design — the registry (built in WO-M01)

**Source of truth:** `config/methodologies.php` (a keyed array) **or** a small `app/Support/Methodology/MethodologyRegistry` returning typed value objects — implementer's choice; config is simplest. Each **entry**:

- `id` — stable slug, e.g. `pv.dcf`, `engagement.score`, `radar.dimension_score`, `fees.outcome_based`.
- `name` — human title.
- `summary` — plain-English "what it is" (the **hover** text; 1–2 sentences).
- `formula` — human-readable formula/method (the detail view), written **parametrically** (refer to named parameters, not baked-in numbers).
- `inputs` — inputs / assumptions / units.
- **`config_refs`** — list of `config()` keys whose **current values are the live parameters** of this method (e.g. `dashboards.engagement.weights`, `dashboards.engagement.thresholds`, `dashboards.radar.severity_weights`, `dashboards.radar.load_cap`, `integrations.payments.max_attempts`). ⚠️ Several formulas read weights/thresholds from config (`ClientEngagementScorer`, `BusinessHealthRadarBuilder`, `config/dashboards.php`), so the registry **must not hard-code those numbers in prose** — they'd drift the moment config changes. The detail view **renders the current value of each `config_ref` at request time**, so what an advisor sees is always the live parameter set. **Keys must be on the WO-M01 non-secret allowlist** (parameter namespaces only); any sensitive-pattern key (`*secret*`/`*key*`/`*token*`/`*password*`/`*credential*`) is **rejected**, so a mistaken `config_ref` can never render a credential.
- `where_used` — list of feature/surface keys (+ WO refs) that consume it; resolvable to a label.
- `sources` — optional citations (regulatory / NZ-specific / spec section) for evidence-based entries.
- `owning_service` — the class that implements it (the drift-guard anchor).
- `version` + `internal_only` (default `true`).

**Service API:** `MethodologyRegistry::all()`, `::get(id)`, `::byArea()/byFeature(key)`, and `::resolvedParameters(entry)` (reads each `config_ref` and returns its current value). Pure, no DB, no client data.

**No new DB table** — config-as-data; the registry is global, not client-scoped, so no migration/RLS.

---

## 5. Work Orders

### WO-M01 — Registry foundation (incl. marker + config allowlist)
- **Goal:** Ship the registry structure + `MethodologyRegistry` service + entry value object/shape (per §4), **plus the `ProvidesMethodology` marker and the config-ref allowlist** so later WOs can adopt them in a single green commit each. No catalogue content yet beyond 2–3 seed entries to prove the shape.
- **Backend:**
  - `config/methodologies.php` skeleton; `MethodologyRegistry` (`all`/`get`/`byArea`/`resolvedParameters`); a value object or array contract; validate ids are unique and `where_used`/`owning_service`/`config_refs` are well-formed.
  - **`App\Support\Methodology\ProvidesMethodology`** marker interface returning **`public static function methodologyIds(): array;`** (a list, **not** a single id — one service routinely owns several methods, e.g. the PV engine owns DCF, the discount-method selection, and waterfall assembly). *Defined here* (the WO-M03 drift guard discovers its implementers; WO-M02 adopts it). **Multiple registry entries may share one `owning_service`.** No adopters yet.
  - **Config-ref allowlist + secret guard (resolves the credential-exposure risk):** `resolvedParameters()` may only read keys under an **allowlist of non-secret parameter namespaces** (e.g. `dashboards.*`, `integrations.payments.max_attempts`, `integrations.payments.retry_delay_minutes`) and **must reject** any key matching a sensitive pattern (`*secret*`, `*key*`, `*token*`, `*password*`, `*credential*`, `*.api_*`). A `config_ref` outside the allowlist or matching a sensitive pattern is a validation failure, not a silent render — so a mistaken `config_ref` can never surface a credential to `knowledge.view` users.
- **Tests:** registry loads; `get(id)` returns a typed entry; duplicate-id and malformed-entry guards; **`resolvedParameters` rejects a non-allowlisted key and any sensitive-pattern key** (e.g. a `config_ref` of `services.stripe.secret` fails).
- **Acceptance:** registry resolves seed entries; service API + marker + allowlist stable and ready for adoption.
- **Files:** `config/methodologies.php`, `app/Support/Methodology/*` (incl. `ProvidesMethodology`, the allowlist).

### WO-M02 — Catalogue the platform's methods
- **Goal:** Register **every** current computational method (§3.3 + a full pass over `app/Services/**` and scoring enums). Each entry: accurate `summary`/`formula` (parametric)/`inputs`/`config_refs`/`where_used`/`sources`/`owning_service`.
- **Backend:** populate `config/methodologies.php`; **add the WO-M01 `ProvidesMethodology` marker** to each catalogued calculator service so the WO-M03 drift guard discovers it (the marker contract already exists from WO-M01, so this WO stays a single green commit). ⚠️ **Read each owning service and transcribe the *actual* implemented formula** — do not paraphrase from memory, and write it **parametrically** with `config_refs` for any weight/threshold the service reads from config (engagement weights/bands, radar `severity_weights`/`load_cap`, payment caps, etc.) rather than hard-coding numbers. The drift guard enforces structural coverage, not the math — text accuracy is on the author + PR review.
  - **Integration-health banding refactor (required for an honest owner).** Before cataloguing `integration.health.banding`, resolve its ownership per WO-M03: **[preferred]** extract `AggregateIntegrationHealth::healthFor()` into a new marked `App\Services\Integration\IntegrationHealthBander` (command delegates to it; behaviour unchanged; covered by a unit test), then catalogue with that service as owner; **or** the documented fallback (mark the command + scan its namespace). This is the **one** small refactor the catalogue requires — bound it to this WO.
- **Tests:** every entry has the required fields; `owning_service` classes exist and carry the marker; `where_used` keys resolve to known feature labels; every `config_ref` resolves to a real config key.
- **Acceptance:** the catalogue covers the §3.3 set and any other marked calculator; reviewers can trace each entry to its service; no formula hard-codes a value that lives in config (it uses `config_refs`).
- **Files:** `config/methodologies.php`.

### WO-M03 — Drift guard (+ dashboard-hover linkage)
- **Goal:** Make the registry trustworthy and wire it as the canonical definition source for explanatory hovers.
- **Enforced discovery, two layers (so a *forgotten* marker still fails).** Calculator services **self-declare** via `App\Support\Methodology\ProvidesMethodology { public static function methodologyIds(): array; }` (or repeatable `#[Methodology('id')]` attributes — one per owned method). The drift-guard test then runs **both**:
  1. **Marker ↔ registry (true bidirectional round-trip):** for each marked class, assert **every id in its `methodologyIds()` exists in the registry**; and for each registry entry, assert its **`owning_service` is a marked class (service or command) *and* that entry's id is present in that class's `methodologyIds()`** — so `pv.waterfall` listing `PvEngine` as owner fails unless `PvEngine::methodologyIds()` actually declares `pv.waterfall`. Also validate `methodologyIds()` itself: **non-empty, unique, and matching the exact id regex `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$`** — lowercase dotted segments where each segment is alphanumeric **with underscores allowed** (so `radar.dimension_score`, `pv.dcf`, `fees.outcome_based` are valid; hyphens/uppercase/empty segments fail). A service with an empty/duplicate/malformed id list fails. *(The same regex governs every registry `id`.)*
  2. **Namespace completeness (catches the *unmarked* calculator):** scan a defined set of **calculation namespaces** (e.g. `App\Services\Pv`, `App\Services\Dashboards`, `App\Services\Analysis`, `App\Services\Payments`, `App\Services\Reports` valuation/PV pieces, rating/fee services, and the **extracted `App\Services\Integration\IntegrationHealthBander` specifically** — ⚠️ **not** the whole `App\Services\Integration` tree, which holds many non-methodology adapters/fixtures (`LiveNzbnClient`, `FixtureRepository`, …) that would force a large unrelated exclusion list; scan the single banding class (or a dedicated narrow namespace like `App\Services\Integration\Methodologies`), **plus `App\Console\Commands\AggregateIntegrationHealth` only if option (b) below is chosen**) and assert **every concrete class there is either marked or on an explicit `#[NotAMethodology]` / exclusion allowlist**. So adding a new calculator in those namespaces and *forgetting* the marker fails CI — not just the opt-in case. (Calculators *outside* the designated namespaces still rely on the marker; that residual gap is documented, not over-claimed.)
- **Owner must be a marked *class* — incl. enum/model-backed and command-resident methods (resolves the §3.3 scope gap).** `owning_service` must point at a **class that carries the marker** — normally a service, but it may be a **console command** where the calculation genuinely lives there — **never** a bare enum/model. Methods whose math lives in an enum/model (`DiscountMethod`, `FeeMethod`, `AnalysisLens`, `FindingSeverity`, `BusinessValuation` reconciliation) set the owner to the **service that applies them** (e.g. `DiscountMethod` → the PV engine; valuation reconciliation → the valuation service). A WO-M02 test asserts no entry's owner is an enum/model.
  - **⚠️ Integration-health banding has no service owner today (must be handled, not assumed).** The success-rate/p95 → green/amber/red banding lives in **`App\Console\Commands\AggregateIntegrationHealth::healthFor()`** (reading the `integrations.health.*` thresholds from `config/integrations.php`), which is **outside the designated service-scan namespaces**. So it cannot get a marked owner without action. **WO-M02 must do one of:** (a) **[preferred]** extract `healthFor()` into a marked `App\Services\Integration\IntegrationHealthBander` service (the command calls it) — banding is reusable methodology and shouldn't be buried in a scheduler command; or (b) mark the command itself **and** add `App\Console\Commands` (or just that command) to the WO-M03 namespace-completeness scan. Either way the registry entry's `config_refs` are the **four exact keys** (not wildcards — `config_refs` must resolve): `integrations.health.green.min_success_rate`, `integrations.health.green.max_p95_latency_ms`, `integrations.health.amber.min_success_rate`, `integrations.health.amber.max_p95_latency_ms`. *(Wildcards like `*secret*` exist **only** in the WO-M01 allowlist reject-pattern, never in an entry's `config_refs`.)*
- **Backend/Tests:** the two-layer drift guard above, plus (b) every methodology `id` referenced elsewhere (e.g. an advisor `methodology_id` payload field) **exists** in the registry, and (c) every `config_ref` resolves to a real, allowlisted config key.
- **Linkage — internal advisor payloads ONLY (resolves the portal-exposure conflict).** ⚠️ The registry is internal-only (§1), so `methodology_id` may appear **only in advisor-facing payloads** (e.g. the advisor dashboard's engagement / PV-waterfall / economic-exposure / funnel widgets). It is **forbidden** in any **portal / client / entrepreneur / broker / coach payload or export** — notably the **client-portal Business Health radar**, which must explain itself with self-contained client-safe text and carry **no** `methodology_id` pointing at the internal registry. A test asserts no portal/export payload contains a `methodology_id`. Where an advisor payload does carry one, the hover's "how it's calculated" resolves it against the registry — one definition, reused.
- **Acceptance:** each of these **fails** CI — a marked calculator with no registry entry; a **registry entry whose id is not declared in its `owning_service`'s `methodologyIds()`** (the reverse-direction check); a **malformed/empty/duplicate `methodologyIds()` value** (violates the id regex); a **new concrete class in a designated calculation namespace that is neither marked nor excluded** (the forgotten-marker case); an entry whose `owning_service` is an enum/model rather than a marked class (service or command); a dangling `methodology_id`; a non-resolving/non-allowlisted `config_ref`. ⚠️ **Scope honestly:** the guard enforces *structural coverage* (every calculator in the designated namespaces is marked + described; ids/config refs resolve) — it **cannot** verify the prose/math is *correct* (PR-review responsibility), and a calculator placed *outside* the designated namespaces without the marker is the one residual gap (documented in `docs/methodology-registry.md`).
- **Files:** the drift-guard feature/unit test (discovers `ProvidesMethodology` implementers — the marker itself ships in WO-M01, adopted in WO-M02); minor **internal advisor** payload additions where hovers cite ids (see linkage scope above).

### WO-M04 — Internal "Platform methodologies" surface
- **Goal:** Add a read-only view on the Knowledge tab listing the registry with hover + detail + where-used.
- **Backend:** a controller (e.g. `Advisor\MethodologyController@index`/`show`) + routes `advisor/knowledge/methodologies` and `advisor/knowledge/methodologies/{methodology}`, **gated `permission:knowledge.view`** (internal staff only; `client_primary`/`client_team` et al. lack it). Optional `audit.read:methodology.viewed`.
  - **⚠️ Route ordering — register before `knowledge/{knowledgeEntry}` (resolves the shadowing bug).** `routes/advisor.php` already has a catch-all `knowledge/{knowledgeEntry}` (show/edit/update/destroy). If the methodology routes are added *after* it, Laravel binds `methodologies` as a `{knowledgeEntry}` id → ModelNotFound. So **place `knowledge/methodologies*` before `knowledge/{knowledgeEntry}`** (same way `knowledge/create` precedes it today) **and** belt-and-suspenders **constrain the param: `->whereUuid('knowledgeEntry')`** so a non-UUID segment can never match the entry routes.
  - **Detail view renders live config** — for each `config_ref`, read and display the **current** `config()` value (via `MethodologyRegistry::resolvedParameters`), so the shown weights/thresholds are always the live ones, never a stale prose copy.
- **Frontend:** a new `resources/js/pages/advisor/knowledge/Methodologies.tsx` (or a tab within the Knowledge index) — searchable list; each row's name wrapped so **hover shows `summary`**; click → detail (`formula` with **resolved live parameters**, `inputs`, `where_used` as links, `sources`). Reuse the dashboard track's `InsightHoverCard` if landed; otherwise a simple tooltip/detail. **Clearly separated from the advisor's editable Knowledge entries** (read-only, "Platform" labelled).
- **Confidentiality test:** **`client_primary` AND `client_team`** (both client user types), plus entrepreneur / broker / coach, each get **403/redirect** on the route; an **unauthenticated/guest** request is redirected to login (not served); and the registry appears in **no** portal payload or export.
- **Acceptance:** internal staff see the catalogue with working hover + live-parameter detail + where-used; the methodologies route resolves (not shadowed by `{knowledgeEntry}`); every non-internal and unauthenticated caller is blocked.
- **Files:** new controller + routes (ordered before `{knowledgeEntry}`; `whereUuid` constraint on `{knowledgeEntry}`); new page; Knowledge index nav link.

### WO-M05 — QA, docs, baseline
- **Goal:** Green, accessible, documented.
- **Work:** full PHPUnit suite green (per §0.4); Pint · ESLint · `tsc` · Prettier clean; zero forbidden markers; Wayfinder regenerated. Update `IMPLEMENTATION.md` (new "Methodology Registry" section, WO-M01…M05 status) and add a short `docs/methodology-registry.md` (how to add/maintain an entry; the confidentiality rule).
- **Acceptance:** all gates green; docs updated; new green baseline recorded.
- **Files:** `IMPLEMENTATION.md`, `docs/methodology-registry.md`, fixups.

---

## 6. Open decisions / assumptions (recommended defaults — owner may override)

1. **Surface placement** — default: a **second read-only tab on the Knowledge page** ("Platform methodologies"), separate from the advisor's private entries. Alternative: a standalone `advisor/methodologies` page.
2. **Source-of-truth form** — default: `config/methodologies.php` (simplest, reviewable). Alternative: a registry class with PHP attributes on the owning services (more "live", more work) — defer unless the drift guard proves insufficient.
3. **Audience** — default: internal staff via `permission:knowledge.view` (super_admin / advisor / junior_advisor / entrepreneur_mentor). Never client/entrepreneur/broker/coach. (If the owner later wants a *curated, client-safe* subset, that's a separate, redaction-reviewed feature.)
4. **Drift-guard mechanism** — default (per WO-M03): **two layers** — (1) an **enforced `ProvidesMethodology` marker** (`methodologyIds(): array`) discovered by the test with a two-way round-trip, **and** (2) a **namespace-completeness scan** over designated calculation namespaces (e.g. `App\Services\Pv`, `App\Services\Dashboards`, `App\Services\Analysis`, `App\Services\Payments`, plus the valuation/fee/rating services) requiring every concrete class there to be marked or on an explicit `#[NotAMethodology]`/exclusion allowlist (so a *forgotten* marker fails CI) — plus referenced-`methodology_id` and allowlisted-`config_ref` existence checks. (A curated hand-maintained list was rejected.) The guard enforces **structural coverage only** (within the designated namespaces; a calculator placed outside them without a marker is the documented residual gap), **not** prose/math correctness.

## 7. Risks

| ID | Risk | Mitigation |
|---|---|---|
| MR-1 | Registry text drifts from the real formula | Author transcribes from the owning service (WO-M02); drift guard ties entries to services + ids (WO-M03); reviewed in PR. |
| MR-2 | IP leakage to clients | `internal_only`, `knowledge.view` gate, explicit confidentiality test, excluded from all portals/exports (WO-M04, §1). |
| MR-3 | Becomes a stale "docs" page | It's code, reviewed with the calculators; the drift guard fails CI when a **marked class** (service or command) or a **concrete class in a designated calculation namespace** lacks a registry entry (a calculator placed *outside* those namespaces without the marker is the documented residual gap, not full coverage). |
| MR-4 | Scope creep into changing formulas | Description-only rule (§0.5); any formula change is a WO in its owning plan, not here. |

## 8. Definition of done (track)
- WO-M01…M05 committed on `featureApp`; `IMPLEMENTATION.md` updated per commit.
- The Knowledge tab shows an internal, read-only "Platform methodologies" catalogue with hover (`summary`) + detail (`formula`/`inputs`/`where_used`/`sources`); non-internal users are blocked; nothing leaks to portals/exports.
- The drift guard fails when a **marked class** (service or command; any id in its `methodologyIds()`) lacks a registry entry; when a **registry entry's id is not declared by its `owning_service`'s `methodologyIds()`** (the reverse direction), or its `owning_service` is unmarked / an enum/model; when a **concrete class in a designated calculation namespace** is neither marked nor excluded; when a `methodologyIds()` value violates the id regex; when an unknown `methodology_id` is referenced; or when a `config_ref` is non-resolving/non-allowlisted. (Calculators outside the designated namespaces without a marker are the documented residual gap.)
- Full suite green; Pint/ESLint/tsc/Prettier clean; Wayfinder regenerated; `docs/methodology-registry.md` written; new green baseline recorded.
