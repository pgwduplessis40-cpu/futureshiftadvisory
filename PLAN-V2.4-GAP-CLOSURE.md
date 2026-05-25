# Future Shift Advisory — V2.4 Gap Closure Plan

**Source:** `docs/v2.4-spec-coverage.md` (the section-by-section spec audit) — the 5 genuine feature gaps/partials it surfaced.
**Track scope:** close the remaining **V2.4 feature** gaps (not provisioning, not stack deviations). On completion the codebase covers the full V2.4 *feature* set.
**Predecessors:** Phases 1–4 complete & verified (513 tests green) on `featureApp`.
**Work orders:** WO-121 … WO-125 — these **continue the global numeric sequence** (Phase 4 ended at WO-120) because they are V2.4 completion, not a post-V2.4 enhancement track.
**Sequencing:** independent of Phase-4 provisioning; each WO is its own green commit.
**Plan version:** 1.2

> **v1.1 revision (post-review).** Four corrections after re-checking the repo: (a) **WO-122 is no longer a fresh PWA build** — WO-101 already shipped the portal PWA (manifest, service worker, `offline.html`, encrypted IndexedDB queue in `resources/js/lib/portal-offline.ts`, `OfflinePwaTest`). WO-122 is now a **server-side hardening** WO over that existing layer. (b) **WO-121** now routes template *creation* through a separate implementer that writes a `LearningUpdateImplementation` ledger row (approval ≠ creation), and defines a **distinct `template.manage` permission** (mutation super-admin/admin only; advisors view/use). (c) **WO-123** adds explicit **draft schema** to `knowledge_entries` (or a sibling draft table) because the table today has no `status`/AI-draft columns and its author-scoped index returns every row — an unflagged draft would surface as live. (d) **WO-124** adds explicit **`calendar_connections` + event-mapping/sync-token** schema (today `meetings` has only a nullable `external_ref`).
>
> **v1.2 revision (second-pass code grounding).** Five further corrections after reading the actual files: (e) **Layer IDs 16 & 17 are already taken** — `LayerCadenceRegistry` defines 16 = *Questionnaire optimisation* and 17 = *Coach referral calibration* (and `QuestionnaireOptimisationLayer::LAYER_ID = 16`). Reusing them would collide in `learning_updates` / layer runs / monitoring. The two new layers get **new IDs 33 & 34** (the registry's spec-conceptual numbering already diverges from the spec's appendix; the registry is the operative source). (f) **WO-122 dedupe transport** — `portal-offline.ts::sendRecord` sends only `X-Portal-Offline-Sync`; the `dedupeKey` is **never transmitted**. Server-side idempotency is impossible without a **frontend transport change** (send `X-Idempotency-Key`) — now required FE work in WO-122. (g) **WO-121 permissions** — this app's permissions are an **enum + `roleMatrix()`** (`App\Enums\Permission`, seeded by `RoleSeeder`→`valuesForRole()`), **not migration rows**. `TEMPLATE_MANAGE`/`TEMPLATE_VIEW` are added as enum cases + role-matrix assignments + reseed. (h) **WO-124 external events** — `meetings.client_id` is **NOT NULL**, so external-only events can't be stored as meetings; `calendar_event_mappings` must carry **event payload columns** (title/start/end/attendees) or a sibling `external_calendar_events` table. (i) **WO-122 clear-on-logout** — the AES key lives in `localStorage` and the queue in IndexedDB; `handleLogout` only calls `router.flushAll()`. Clearing on logout/session-expiry is **explicit WO-122 work + a test**, not just a risk-table claim.

> Companion to `PLAN.md` and the phase/track plans — they stay as-is. This plan closes what the V2.4 audit flagged as **not built / partial**. It does **not** cover the ⚙️ live-gating/provisioning items (credentials, PQC review, HSM, audit firm, etc. — those are owner actions, tracked in `docs/v2.4-spec-coverage.md` and `PLAN-PHASE4.md §12`) or the two accepted stack deviations (Laravel backend, Fortify auth).

---

## 0. How to use this plan

1. Read §1–§4 once. Implement **Work Orders** in §5; each is independent.
2. **Commit cadence (matches the existing tracks):** single `featureApp` branch, no WO branches/PRs; **one commit per WO** (`WO-121: <slug>`); **`IMPLEMENTATION.md` + `docs/v2.4-spec-coverage.md` updated in the same commit** (flip the audit rows to ✅). Regenerate Wayfinder (`--with-form`) on any controller/route change.
3. A WO is done only when its acceptance criteria hold and the gate is green: **`php -d memory_limit=1024M vendor/phpunit/phpunit/phpunit --no-coverage`** (⚠️ `composer test` wraps `artisan test`, which hangs in this environment) + `npm run lint:check` / `types:check` / `format:check`.
4. **No new spec scope.** These WOs implement features the V2.4 spec already names; do not exceed them.

---

## 1. Non-negotiable principles (carried forward — still binding)

Everything in `CLAUDE.md` and the phase plans remains in force. The ones these WOs stress hardest:

- **No silent learning (spec §3, §23).** The **Template Library (WO-121)** and **Knowledge-base AI capture (WO-123)** are governed learning layers — they **emit governed `learning_updates` / review-draft candidates only**; a template is never auto-created and a knowledge entry is never auto-published. **Approval ≠ creation:** approval through the existing `ApprovalFlow`/`assertImplementationAllowed()` gate only *authorises* the change; a **separate implementer step** then writes a `LearningUpdateImplementation` ledger row and materialises the template/entry (the WO-93 implementer pattern). A test asserts no auto-apply **and** that approval alone does not create the artefact.
  - **Layer IDs (do not reuse 16/17).** `LayerCadenceRegistry::definitions()` already occupies **all of 1–32**, with **16 = Questionnaire optimisation** and **17 = Coach referral calibration** (`QuestionnaireOptimisationLayer::LAYER_ID = 16`). The spec's appendix-level "layer 16/17" labels for these features are *conceptual* and already diverge from the live registry. Register the two new layers as **ID 33 (`KnowledgeCaptureLayer`, WO-123)** and **ID 34 (`TemplateSuggestionLayer`, WO-121)** — new entries in `LayerCadenceRegistry` with their own cadence/window/command — so they get clean layer state, runs, and monitoring with **no collision** in `learning_updates`. Do **not** renumber 16/17 (would break existing layer state/runs/monitoring). Update the `docs/v2.4-spec-coverage.md` §23 note to record the registry now carries 34 governed layers (32 spec + 2 gap-closure).
- **AI Integrity (spec §3).** AI-drafted content (template suggestions, knowledge capture) is **evidence-based and source-attributed** via `AiClient`; `FakeAiClient` in tests. No fabricated content; honest where evidence is thin.
- **All external calls through `ResilientHttp` + feature flags (spec §4.2).** The **Calendar (WO-124)** and **accounting (WO-125)** integrations follow the WO-05/WO-37 pattern: feature-flagged (default OFF → fixture/fake), retry/breaker/cached-fallback, tokens in `KeyEnvelope`, every call audited. No raw `Http::` to a third-party host.
- **Security baseline (spec §4).** **Offline/PWA (WO-122)** *hardens the already-shipped WO-101 portal PWA* (encrypted IndexedDB queue, service worker, manifest). The hardening focus is **server-side**: synced documents re-run the **virus scan** (`SecureFileWriter` scan-before-store) and **RLS**; the sync endpoint re-authenticates and is **server-side idempotent** (replay-safe, dedupe-key enforced in the DB, not just the client). No PII in logs (Redactor). MFA/invite-only unaffected.
- **Audit + RLS everywhere.** New client-scoped tables get `ENABLE`+`FORCE` RLS + `*_client_scope` policy; every mutation audited via `AuditWriter`.

---

## 2. Scope

### 2.1 In scope (the 5 audit gaps)
| WO | Gap | Spec § | Audit status |
|---|---|---|---|
| 121 | Template Library | §11; §23 layer 17 | ❌ not built |
| 122 | Offline mode / PWA — **harden** | §7; §27 | ✅ built (WO-101) → server-side hardening |
| 123 | Knowledge-base AI-assisted capture | §11; §23 layer 16 | ⚠️ partial (manual only) |
| 124 | Calendar two-way sync (Google + Outlook) | §21; §25 | ⚠️ scaffold only |
| 125 | Accounting breadth (Sage / Figured / Workflowmax) | §25 | ⚠️ partial |

### 2.2 Out of scope
- **Provisioning / live-gating** (credentials, PQC NZ review, HSM, audit firm, brand/legal assets) — owner actions, not code.
- **Stack deviations** (Laravel vs Node/Python; Fortify vs Auth0/Cognito) — accepted, documented.
- **V2.5+** — new markets, white-label, partner marketplace, new engagement types, AI autonomy beyond the governed queue.

---

## 3. Work Orders

### WO-121 — Template Library (governed AI-suggested templates)
- **Goal:** A practice template library where the AI **suggests** new templates from completed engagements and **admin approves** via the governed learning queue (§11; §23 layer 17). Nothing auto-created.
- **Depends on:** the learning queue (`learning_updates`, `ApprovalFlow`) + `AiClient`.
- **Backend:**
  - `templates` table — `id`, `category` (report/proposal/email/plan-section/…), `title`, `body`/`structure` (jsonb), `source_reference` (the engagement/report it was distilled from), `status` (`draft`/`active`/`archived`), `version`, `created_by_user_id`, `learning_update_implementation_id` (the ledger row that materialised it), timestamps. **Practice-wide, internal** (not client-scoped) → no client RLS; the table holds no client PII (templates are generalised).
  - **Permission model (enum + role matrix, NOT migration rows).** This app's permissions are the `App\Enums\Permission` enum, assigned via `Permission::roleMatrix()` and seeded by `RoleSeeder`→`valuesForRole()` (consuming `PermissionSeeder`). So add **two enum cases** — `TEMPLATE_MANAGE = 'template.manage'` and `TEMPLATE_VIEW = 'template.view'` (do **not** overload `knowledge.manage`) — then wire them into `roleMatrix()`: super-admin already gets all via `self::cases()`; give **advisors `TEMPLATE_VIEW` only** (and any admin role `TEMPLATE_MANAGE`). Re-run the seeder so roles actually receive them. Gate controller **write** actions on `template.manage` (mutation super-admin/admin-only) and **read** actions on `template.view` (advisors view/use). A migration is **not** how the permission is created — only the `templates` table is a migration.
  - A **new layer-34 learning layer** (`TemplateSuggestionLayer`, `LAYER_ID = 34` — see §1; **not** 17) registered in `LayerCadenceRegistry` that, after engagements complete, distils candidate templates via `AiClient` and writes a **`learning_updates` candidate** (`automatic_application=false`, source-attributed). **Approval does not create the template.** On approval, `ApprovalFlow`/`assertImplementationAllowed()` only authorises; a **separate `TemplateImplementer` job/step** (mirroring `LearningUpdateImplementation`) then creates/activates the `templates` row and records the implementation in the `learning_update_implementations` ledger. Never on detection, never on approval alone.
- **Frontend:** an internal "Templates" surface — **read for advisors, manage for admin/super-admin** (`template.manage`); list/active/archived + view; suggested-template candidates appear in the existing Learning Update queue UI for approve/reject.
- **Tests:** the layer emits a candidate (no template auto-created); **approval alone creates nothing** (the implementer must run); the implementer creates the template **and** writes a `LearningUpdateImplementation` ledger row; reject creates nothing; **`template.manage` gating** (advisor 403 on mutate, 200 on view); `FakeAiClient`; source attribution present; no PII in the template body/logs.
- **Acceptance:** AI suggests templates that become real **only after approval *and* the separate implementer step**; advisors can view/use but not mutate; library is internal-only.
- **Files:** migration (`templates` table only) + `Template` model; `App\Enums\Permission` (+`TEMPLATE_MANAGE`/`TEMPLATE_VIEW` cases + `roleMatrix()` assignments) + reseed via `RoleSeeder`/`PermissionSeeder`; `TemplateSuggestionLayer` registered in `LayerCadenceRegistry` (ID 34); `TemplateImplementer` (approval→implementation, writes ledger); `TemplateController` (read vs `template.manage` write) + routes; admin/advisor template page.

### WO-122 — Offline mode / PWA — **harden the existing WO-101 layer**
- **Already built (WO-101 — do not rebuild):** the portal PWA exists today — `public/manifest.webmanifest` (name "Future Shift Advisory Portal", `start_url=/portal`), `public/sw.js` (portal fallback, `'portal-offline-sync'` / `PORTAL_OFFLINE_SYNC` background sync), `public/offline.html`, and `resources/js/lib/portal-offline.ts` (encrypted IndexedDB queue via `crypto.subtle`, `dedupeKey` index, `queueQuestionnaireSubmission`, `queueDocumentUpload`, `flushPortalOfflineQueue`). `tests/Feature/Portal/OfflinePwaTest.php` proves the client-side offline flow (encrypted at rest, dedupe keys, background sync, offline forms). **The client/browser surface is done.**
- **Goal (this WO):** close the **server-side** gaps the client queue assumes — make the sync endpoint replay-safe, scanned, scoped, and re-authenticated — plus stronger browser QA. This is hardening, not a new build.
- **Depends on:** WO-101 (the PWA layer), WO-18 (`SecureFileWriter` upload), WO-16/17 (portal/questionnaire).
- **Frontend transport change (required first — the client doesn't send the key today).** `portal-offline.ts::sendRecord` currently sends **only** `X-Portal-Offline-Sync: '1'`; the `dedupeKey` is computed and stored in IndexedDB but **never transmitted**. Server-side idempotency is impossible until the replay request carries it. Add `'X-Idempotency-Key': record.dedupeKey` to **both** the questionnaire (JSON) and document-upload (FormData) `fetch` calls in `sendRecord` (a header works for both; a form field is the fallback for the multipart case). Keep `OfflinePwaTest` green and add an assertion that the header is sent.
- **Backend (the actual work):**
  - **Server-side idempotency:** the sync endpoint reads the `X-Idempotency-Key` (the client `dedupeKey`) and enforces it in the **database** (unique constraint / dedupe ledger keyed by `(user, idempotency_key)`), so a double-sync or replayed request returns the prior result rather than double-creating a questionnaire submission or a document — idempotency must not rely on the client alone.
  - **Scanned upload replay:** queued document uploads replay through the existing upload path so every file goes through **`SecureFileWriter` (virus-scan-before-store)** on sync — no offline-queued file bypasses scanning.
  - **Re-auth / session handling:** a flush after a session has expired re-authenticates (or 401s cleanly and re-queues) rather than silently failing or leaking across sessions; sync respects **RLS** (a client only writes their own scope).
  - **Clear-on-logout / session-expiry (currently missing).** The AES-GCM key is in `localStorage['fsa.portal.offline.key.v1']` and the queue in IndexedDB `fsa-portal-offline-v1`; `UserMenuContent.handleLogout` only calls `router.flushAll()`. Add a `clearPortalOfflineQueue()` to `portal-offline.ts` that **deletes the IndexedDB database and removes the localStorage key**, and invoke it from `handleLogout` (and on a 401/session-expiry from the sync path) so a shared/kiosk device retains no encrypted client data after logout.
- **Frontend:** verify/finish the offline + syncing indicator wiring to the WO-101 queue (it already exists — confirm states surface); wire `clearPortalOfflineQueue()` into logout. No framework swap.
- **Tests:** feature tests that a queued questionnaire submission and a queued upload **replay idempotently server-side** (DB dedupe via `X-Idempotency-Key`, no duplicates on double-sync), the upload is **scanned on sync** (`SecureFileWriter`), RLS holds, and an expired-session flush is handled; a **clear-on-logout** test (queue + key gone after logout); keep the existing `OfflinePwaTest` green. *(Browser-true offline behaviour stays manual-QA; the sync endpoints/contracts are the automated surface.)*
- **Acceptance:** the WO-101 offline queue syncs **exactly once** server-side (DB-enforced via the transmitted key), every synced file is scanned and RLS-scoped, an expired session degrades cleanly, and **logout clears the local queue + key** — with no regression to the shipped PWA.
- **Files:** `portal-offline.ts` (`X-Idempotency-Key` in `sendRecord` + `clearPortalOfflineQueue()`); `user-menu-content.tsx` (call clear on logout); sync controller/endpoint hardening (DB dedupe constraint/ledger + `SecureFileWriter` replay + re-auth); a migration for the server-side dedupe ledger/constraint; new server-side + clear-on-logout tests. **No** `vite-plugin-pwa` swap, **no** new manifest/service worker — WO-101 owns those.

### WO-123 — Advisor Knowledge Base — AI-assisted capture
- **Goal:** Complete §11 / the knowledge-capture learning layer — the existing manual Knowledge base (WO-27) gains **AI-assisted capture after each engagement**: the AI drafts a candidate methodology/pattern entry for the advisor to review, edit, and save. Never auto-published. (Registered as **new layer ID 33** `KnowledgeCaptureLayer`; the spec's conceptual "layer 16" label is already taken in the live registry — see §1.)
- **Depends on:** WO-27 (`KnowledgeEntry`), `AiClient`, the learning cadence.
- **Schema gap (must fix first):** the existing `knowledge_entries` table (migration `2026_05_21_235900`) has **no `status`/draft column, no AI-source/attribution column, no accepted/discarded state** — and its RLS policy + index `(author_user_id, updated_at)` return **every** row for the author. Writing an AI draft into that table as-is would make it **surface as a live entry** in `KnowledgeIndex`. So either:
  - **Option A (preferred) — sibling draft table** `knowledge_entry_drafts`: `id`, `author_user_id` (per-advisor RLS, same policy shape), `source_engagement_id`/`source_reference`, `category`, `title`, `body`, `tags`, `source_attribution` (jsonb), `state` (`pending`/`accepted`/`discarded`), `accepted_entry_id` (nullable → the `knowledge_entries` row created on accept), timestamps. Drafts never appear in the live index; on accept, a real `knowledge_entries` row is created and the draft marked `accepted`.
  - **Option B — extend `knowledge_entries`**: add `is_ai_draft` (bool), `draft_state`, `source_attribution` (jsonb); **and update the index controller query + `search_vector`/listing to exclude `is_ai_draft = true`** so drafts can't leak into the live list. (More invasive — must touch every read path.)
- **Backend:** a `KnowledgeCaptureService` capture step (on engagement completion or advisor trigger) calls `AiClient` to draft an entry (category `methodology`/`client_pattern`), **source-attributed**, written to the **draft store** (Option A table or `is_ai_draft` row) owned by that advisor — **pending advisor save, not live**. The layer-33 cadence (registered in `LayerCadenceRegistry`) governs it; no auto-publish.
- **Frontend:** the advisor sees AI-drafted entries flagged "AI draft — review", can edit/accept (creates the live `knowledge_entries` entry) or discard. Drafts render in a separate "AI drafts" lane, **never** in the live knowledge list.
- **Tests:** `AiClient` draft via `FakeAiClient`; the draft **does not appear in `KnowledgeIndex`** (the live-leak guard — the cardinal test for this WO); accept creates a live entry + marks the draft accepted; discard leaves no live entry; per-advisor scoping (one advisor can't see another's drafts); source attribution; no PII in logs.
- **Acceptance:** after an engagement, the advisor is offered an AI-drafted knowledge entry to accept/edit/discard; nothing is auto-saved and **no draft is ever visible in the live knowledge list**.
- **Files:** migration (Option A: `knowledge_entry_drafts` + RLS; or Option B: `knowledge_entries` draft columns + read-path filter); `KnowledgeCaptureService`; `KnowledgeCaptureLayer` registered in `LayerCadenceRegistry` (ID 33); capture trigger/command; `KnowledgeController` draft endpoints; Knowledge UI draft lane.

### WO-124 — Calendar two-way sync (Google + Outlook)
- **Goal:** Implement the live two-way calendar sync the spec names (§21; §25) — today only `GoogleCalendarClient`/`MicrosoftGraphClient` Contract+Fake exist.
- **Depends on:** WO-05 (`ResilientHttp`), WO-60 (`Meeting` model), `KeyEnvelope`.
- **Schema gap (must fix first):** today `meetings` (migration `2026_05_23_010000`) has **only a nullable `external_ref` string** — no per-advisor connection record, no provider, no token storage, no sync cursor, and no robust event↔meeting mapping for two-way sync. Add:
  - **`calendar_connections`** — `id`, `user_id` (the advisor; per-advisor RLS), `provider` (`google`/`microsoft`), `external_account_id`/email, `access_token`/`refresh_token` (**`KeyEnvelope`-wrapped**), `token_expires_at`, `sync_token`/`delta_link` (the provider's incremental cursor), `status` (`connected`/`revoked`/`error`), `last_synced_at`, timestamps. Unique `(user_id, provider, external_account_id)`.
  - **`calendar_event_mappings`** — `id`, `calendar_connection_id`, `meeting_id` (nullable for external-only events), `external_event_id`, `etag`/`updated_at` cursor, `direction`/`origin` (`fsa`/`external`), `last_synced_at`, timestamps. Unique `(calendar_connection_id, external_event_id)` — this is the dedupe key replacing the bare `external_ref`.
  - **External-only events need an event payload (they can't be `meetings`).** `meetings.client_id` is **NOT NULL** (`foreignUuid('client_id')->constrained()`), so a pulled external event with no FSA client cannot be stored as a meeting. To render the spec's "external events are read-only in the meetings view", **carry the event payload** — add `title`, `starts_at`, `ends_at`, `location`, `attendees` (jsonb), `is_external_only` (bool) **columns on `calendar_event_mappings`** (preferred — one table), **or** add a sibling **`external_calendar_events`** table. Either way the meetings view unions FSA `meetings` with these external rows; external rows are display-only (no FSA mutation).
- **Backend:**
  - **Live clients** for Google Calendar + Microsoft Graph behind the existing contracts, via `ResilientHttp`; OAuth connect/callback/revoke per advisor writing a `calendar_connections` row; tokens **`KeyEnvelope`-wrapped**; feature-flagged **`FEATURE_CALENDAR_LIVE=false`** (fixture/fake fallback, `stub_live_fallback` badge) like the other integrations.
  - **Two-way sync:** push FSA `meetings` to the advisor's calendar and pull external events in, persisting both sides via `calendar_event_mappings`; incremental pulls use the stored `sync_token`/`delta_link`; deterministic conflict/dedupe by `(connection, external_event_id)`; audited.
- **Frontend:** advisor calendar connect/disconnect + sync status in settings; synced meetings surfaced.
- **Tests:** fixture push/pull; OAuth callback + **`KeyEnvelope`-encrypted** token storage in `calendar_connections`; idempotent re-sync via `calendar_event_mappings` (no duplicate events/meetings on repeat sync); `sync_token` advances; live-mode-without-credentials degrades + logs via the resilience ledger; RLS (advisor sees only their own connections/mappings).
- **Acceptance:** with the flag on + creds, meetings sync both ways with no duplicates across repeated syncs; off, fixtures drive a working demo; failures degrade gracefully.
- **Files:** migration (`calendar_connections` + `calendar_event_mappings` + RLS); `LiveGoogleCalendarClient`, `LiveMicrosoftGraphClient`, `FallbackCalendarClient`; a `CalendarSync` service; connect controller + routes; settings UI.

### WO-125 — Accounting breadth (Sage / Figured / Workflowmax)
- **Goal:** Add the remaining spec-named accounting providers (§25) — Xero/MYOB/QuickBooks are built (WO-37); Sage, Figured, Workflowmax are empty scaffolds.
- **Depends on:** WO-37 (`AccountingConnector`, `accounting_connections`, `financial_snapshots`).
- **Backend:** for each provider, a Contract + Fake + Live + Fallback client mirroring WO-37 (OAuth token exchange, financial-snapshot pull, revoke), via `ResilientHttp`, feature-flagged, tokens in `KeyEnvelope`, append-only snapshots. Extend the provider constants/enum and the connect UI options.
- **Frontend:** the existing client-detail accounting connect UI gains the three providers.
- **Tests:** mirror the WO-37 suite per provider — mocked OAuth callback, encrypted token storage, fixture snapshot pull, append-only enforcement, live fallback logging, revoke.
- **Acceptance:** all six accounting providers connect/pull/revoke through the same resilient, encrypted, audited path.
- **Files:** `app/Services/Accounting/{Sage,Figured,Workflowmax}/*`; provider enum/constants; connect UI.

---

## 4. Open decisions / assumptions (recommended defaults)
1. **Template scope + permission (WO-121)** — default: **practice-wide internal** templates (no client PII); a **distinct `template.manage` permission**, mutation **super-admin/admin-only**, advisors **view/use**. Creation runs through a **separate `TemplateImplementer`** after approval (writes a `LearningUpdateImplementation` ledger row) — approval alone never creates a template. If templates should be client-scoped, add RLS instead.
2. **WO-122 is hardening, not a build** — WO-101 already shipped the PWA (manifest, `sw.js`, `offline.html`, encrypted IndexedDB queue). **Do not** introduce `vite-plugin-pwa` or a second service worker. The work is server-side: **DB-enforced idempotency, scanned upload replay, re-auth/RLS on sync.**
3. **Knowledge draft storage (WO-123)** — default: **Option A — a sibling `knowledge_entry_drafts` table** (drafts physically cannot leak into the live `knowledge_entries` index). Option B (draft columns on `knowledge_entries` + read-path filter) only if a single table is preferred and every read path is updated.
4. **Knowledge capture trigger (WO-123)** — default: on engagement completion **and** an advisor "draft from this engagement" action.
5. **Calendar conflict rule + external-event storage (WO-124)** — default: FSA `meetings` are the source of truth for FSA-created events; external events are read-only in the meetings view; dedupe by **`calendar_event_mappings.(connection, external_event_id)`**, incremental via the stored `sync_token`/`delta_link`. Because `meetings.client_id` is NOT NULL, **external-only events store their payload on `calendar_event_mappings`** (title/start/end/location/attendees + `is_external_only`), not as `meetings` rows.
6. **New learning-layer IDs (WO-121/WO-123)** — default: **append IDs 33 (`KnowledgeCaptureLayer`) and 34 (`TemplateSuggestionLayer`)** to `LayerCadenceRegistry`; do **not** reuse 16/17 (taken) or renumber existing layers. The registry, not the spec appendix, is the operative layer numbering.
7. **Offline dedupe transport (WO-122)** — default: send the existing client `dedupeKey` as **`X-Idempotency-Key`** on both sync `fetch` calls; the server enforces uniqueness per `(user, key)`.

## 5. Risks
| ID | Risk | Mitigation |
|---|---|---|
| GC-1 | Template/knowledge AI capture drifts toward auto-applying | Governed-candidate-only; **approval ≠ creation** — a separate implementer (with a `LearningUpdateImplementation` ledger row) materialises the artefact; knowledge drafts live in a separate store that can't leak into the live index; no-auto-apply + approval-alone-creates-nothing + draft-not-in-live-list tests (the cardinal invariants). |
| GC-2 | Offline sync double-creates or bypasses the virus scan | **Server-side (DB-enforced) idempotency** on the WO-101 client `dedupeKey` — not client-trust alone; sync replays through `SecureFileWriter`; scanned-on-sync + double-sync idempotency tests. |
| GC-3 | Offline encrypted storage of sensitive client data persists after logout | Web Crypto encryption at rest (WO-101); **WO-122 adds `clearPortalOfflineQueue()` wired into logout + session-expiry** (deletes the IndexedDB DB and the `localStorage` AES key) with a test — not just a claim; minimal queue; documented in `security-decisions.md`. |
| GC-6 | New learning layers collide with existing 1–32 in `learning_updates`/runs/monitoring | Append as IDs 33/34 in `LayerCadenceRegistry`; never reuse 16/17 or renumber; a registry test asserts unique IDs. |
| GC-4 | Calendar/accounting live creds unavailable for test | Feature-flagged with fixture/fake fallback (WO-05 pattern); CI runs against fixtures. |
| GC-5 | PWA caching serves stale authed content | Network-first for authed routes; cache only the shell/assets; respect auth/session. |

## 6. Definition of done (track)
- WO-121…WO-125 committed on `featureApp`; `IMPLEMENTATION.md` updated; the matching rows in `docs/v2.4-spec-coverage.md` flipped to ✅.
- Template Library and AI knowledge capture are **governed** (no auto-apply, approval-gated); calendar + accounting go through `ResilientHttp` + flags + `KeyEnvelope`; offline sync is encrypted, scanned, RLS-scoped, idempotent.
- Full PHPUnit suite green (direct binary command); Pint · ESLint · `tsc` · Prettier clean; zero forbidden markers; Wayfinder regenerated.
- On completion the codebase covers the full V2.4 **feature** set; only provisioning/owner items remain for production go-live.
