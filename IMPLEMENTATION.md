# Future Shift Advisory — Implementation Status & Handoff

> Living status document for the Phase 1 build. Read alongside [`PLAN.md`](./PLAN.md) (the work-order spec) and [`CLAUDE.md`](./CLAUDE.md) (the critical rules). This file answers: *what's done, what's next, how to verify, and what's blocked.*

**Last updated:** 2026-05-20
**Phase:** 1 — Foundation (Months 1–3)
**Plan:** 30 work orders (WO-01 … WO-30). See [`PLAN.md` §8](./PLAN.md).

---

## 1. Snapshot

| | |
|---|---|
| Work orders complete | **3 / 30** (WO-01, WO-02, WO-03) |
| Work orders in progress | none |
| Next work order | **WO-04** — AI Integrity foundation |
| Hard blocker | PostgreSQL not installed locally (PLAN.md risk **R11**) — completed WO code is committed but **unverified against a live database** |
| Stack | Laravel 13 · Inertia · React 19 · TS · PostgreSQL 16 (RLS) · Fortify |

---

## 2. Branch map

Each work order lives on its own branch, **stacked** on the previous one (WO-03 contains WO-02 contains WO-01). They are meant to be reviewed and merged to `main` in order.

```
main
 └─ Add Phase 1 implementation plan (PLAN.md)
     └─ wo/01-foundations      CLAUDE.md, README.md, docs/ tree
         └─ wo/02-postgres-rls  Postgres + RLS harness + KeyEnvelope
             └─ wo/03-audit-trail  audit_events (append-only) + Redactor + chain check

pre-existing/public-site   ← (separate) preserved WIP marketing site; NOT part of the WO stack
```

| Branch | Contains | State |
|---|---|---|
| `main` | `PLAN.md` only | clean |
| `wo/01-foundations` | docs + CLAUDE.md + README.md | committed, no code |
| `wo/02-postgres-rls` | DB switch, RLS harness, `KeyEnvelope`, `EnforceClientScope` | committed, **unverified** (needs Postgres) |
| `wo/03-audit-trail` | `audit_events` + triggers, `AuditWriter`, `Redactor`, `fsa:audit:verify` | committed, **unverified** (needs Postgres) |
| `pre-existing/public-site` | Home/About/Services/FAQ/Contact pages, `ProspectLead` model + migration, contact mailer | preserved snapshot; reconcile with WO-29 later |

> **Note:** The public marketing site work (Home/About/Services/FAQ/Contact, `ProspectLead`) was preserved on `pre-existing/public-site` before the WO stack began. It overlaps **WO-29 (website prospect intake)** and should be reconciled when WO-29 is implemented — most of it can likely be merged forward rather than rebuilt.

---

## 3. Work order completion log

Legend: ✅ done · 🔜 next · ⬜ not started · 🔁 deferred to later phase

### Foundation (done)

| WO | Title | Status | Branch | Notes |
|---|---|---|---|---|
| WO-01 | Project foundations & critical rules | ✅ | `wo/01-foundations` | CLAUDE.md critical-rules block, docs tree, ADR folder, security-decisions SD-01..SD-07 |
| WO-02 | PostgreSQL migration + RLS scaffold | ✅ | `wo/02-postgres-rls` | pgcrypto/uuid-ossp, `fsa_set_request_context()`, `EnforceClientScope`, `KeyEnvelope` (PQC-ready) |
| WO-03 | Immutable audit trail | ✅ | `wo/03-audit-trail` | `audit_events` + append-only triggers, `AuditWriter`, `Redactor`, daily `fsa:audit:verify` |

### Remaining Phase 1 (not started)

| WO | Title | Status | Depends on |
|---|---|---|---|
| WO-04 | AI Integrity foundation (`AiClient`, prompts, bias detector, `FakeAiClient`) | 🔜 | WO-03 |
| WO-05 | Integration resilience layer (`RetryPolicy`, `CircuitBreaker`, health) | ⬜ | WO-03 |
| WO-06 | Secure file storage + virus scanning interface | ⬜ | WO-02, WO-05 |
| WO-07 | User roles, permissions, RBAC (9 user types) | ⬜ | WO-02, WO-03 |
| WO-08 | Invite-only registration + MFA enforcement | ⬜ | WO-07 |
| WO-09 | Session management + step-up MFA | ⬜ | WO-08 |
| WO-10 | Terms model + version control + admin clause editor | ⬜ | WO-07 |
| WO-11 | T&C acceptance gate + signed-PDF generation | ⬜ | WO-10, WO-06 |
| WO-12 | Centralised notifications + channel preferences | ⬜ | WO-07, WO-09 |
| WO-13 | NZ integration scaffolds (NZBN, Companies Office, IRD) | ⬜ | WO-05 |
| WO-14 | Add New Client (engagement-type aware) | ⬜ | WO-07, WO-13, WO-21, WO-22 |
| WO-15 | Add New Entrepreneur (basic profile) | ⬜ | WO-14 |
| WO-16 | Client portal shell + 7-step onboarding wizard | ⬜ | WO-11, WO-12, WO-14 |
| WO-17 | Questionnaire engine (Standard Advisory set) | ⬜ | WO-14, WO-16 |
| WO-18 | Document upload + verification pipeline | ⬜ | WO-04, WO-06, WO-17 |
| WO-19 | Data quality gate | ⬜ | WO-17, WO-18 |
| WO-20 | Wellbeing check-in | ⬜ | WO-16 |
| WO-21 | Conflict of interest declaration | ⬜ | WO-07 |
| WO-22 | Structured offboarding | ⬜ | WO-11, WO-12 |
| WO-23 | Client lifecycle management | ⬜ | WO-14, WO-22 |
| WO-24 | Notification centre UI | ⬜ | WO-12, WO-16 |
| WO-25 | In-app messaging (threaded) | ⬜ | WO-12, WO-16, WO-18 |
| WO-26 | Email-from-app | ⬜ | WO-12, WO-25 |
| WO-27 | Advisor knowledge base (manual entry) | ⬜ | WO-07 |
| WO-28 | Advisor dashboard shell + verification flag panel | ⬜ | WO-14, WO-18, WO-29, WO-30 |
| WO-29 | Website integration layer (prospect capture) | ⬜ | WO-05, WO-12 — *reconcile with `pre-existing/public-site`* |
| WO-30 | API health dashboard | ⬜ | WO-05, WO-12 |

Phases 2–4 (🔁 deferred): see [`PLAN.md` §11](./PLAN.md#11-deferred-work--forward-roadmap-phases-24).

---

## 4. What each completed WO delivered

### WO-01 — Project foundations
- `CLAUDE.md` (root) — critical rules block first: AI Integrity Principle, Document Verification, security baseline, process rules.
- `README.md` (root) — reading order, stack, quick start, branch/PR convention.
- `docs/spec/` — spec V2.4 docx archived as source of truth.
- `docs/brand/`, `docs/legal/terms-v1.md`, `docs/rating-criteria/` — placeholders cataloguing owner-supplied assets still needed.
- `docs/architecture/` — ADR folder; `security-decisions.md` records SD-01..SD-07 (PQC + HSM deferred to Phase 4, invite-only, MFA-everywhere, `AiClient` single exit, RLS contract).
- `docs/dev-setup.md` — Herd + Postgres + `.env` + test loop.

### WO-02 — PostgreSQL + RLS + KeyEnvelope
- Default DB driver → `pgsql`; `futureshift_dev` / `futureshift_test`; new `.env.testing`; `phpunit.xml` decoupled from hardcoded SQLite.
- Migrations (`0000_*` prefix run first): enable `pgcrypto` + `uuid-ossp`; install `fsa_set_request_context()`, `fsa_current_role()`, `fsa_current_client_ids()`.
- `app/Support/RequestContext` + `app/Http/Middleware/EnforceClientScope` — push role + client UUIDs into the Postgres session every request (registered globally on web + api).
- `app/Services/Storage/KeyEnvelope` — tagged AES-256 envelope with version dispatch; the seam where Phase 4 PQC slots in without touching call sites.
- ADRs: `docs/architecture/postgres-rls.md`, `key-envelope.md`.
- Tests: `RlsHarnessTest` (cross-client isolation, super-admin bypass, guest = zero rows), `KeyEnvelopeTest`.

### WO-03 — Immutable audit trail
- `audit_events` table + Postgres triggers rejecting `UPDATE`/`DELETE`/`TRUNCATE` (function `fsa_audit_events_block_mutation()`).
- `app/Services/Audit/AuditWriter` — single sanctioned write path; pulls actor/role/client/ip/UA/request_id; redacts before persist.
- `app/Services/Audit/Redactor` — masks emails, NZ phones, NZ bank accounts, IRD numbers; **NZBN intentionally not masked** (public identifier).
- `app/Concerns/WritesAudit` trait; `app/Http/Middleware/LogAuditEvent` aliased `audit.read` for sensitive reads.
- `app/Console/Commands/VerifyAuditChain` (`fsa:audit:verify`) scheduled daily 02:30.
- ADR: `docs/architecture/audit-trail.md`.
- Tests: `RedactorTest` (8 cases), `AuditTrailImmutableTest` (7 cases).

---

## 5. Verification — MUST run before merging WO-02/03 or starting WO-04

The completed WOs are committed but **were not run against a live PostgreSQL** because Herd's free edition does not bundle Postgres (PLAN.md risk **R11**). To verify:

```pwsh
# 1. Install PostgreSQL  (Herd Pro bundles it, or install standalone Postgres 16)
# 2. Create the databases:
& "$env:USERPROFILE\.config\herd\bin\psql.exe" -U herd -c "CREATE DATABASE futureshift_dev;"
& "$env:USERPROFILE\.config\herd\bin\psql.exe" -U herd -c "CREATE DATABASE futureshift_test;"

# 3. Check out the latest WO branch and verify:
git checkout wo/03-audit-trail
copy .env.example .env        # if no .env yet
php artisan key:generate
php artisan migrate:fresh      # should run 0000_* extension/RLS/audit migrations cleanly
composer test                 # PHPUnit + Pint; RLS + audit tests run on pgsql, skip otherwise
npm run lint:check
npm run types:check
```

Expected: migrations apply, `RlsHarnessTest` + `AuditTrailImmutableTest` pass (not skipped), `KeyEnvelopeTest` + `RedactorTest` pass.

If any RLS test is **skipped**, the test DB is not Postgres — fix `.env.testing` / the `futureshift_test` database before trusting the suite.

---

## 6. Recommended next steps

1. **Unblock R11** — install Postgres, run the section 5 verification against `wo/03-audit-trail`. Do this before writing more code on top of an unverified foundation.
2. **Merge the stack** — once verified, merge `wo/01` → `wo/02` → `wo/03` into `main` in order (or open stacked PRs).
3. **Reconcile `pre-existing/public-site`** — decide whether to fold it into WO-29 now or leave parked. It does not block WO-04..WO-28.
4. **Start WO-04** (AI Integrity foundation). It depends only on WO-03 and unblocks WO-18 (document verification). Branch from `wo/03-audit-trail`:
   ```pwsh
   git checkout wo/03-audit-trail
   git checkout -b wo/04-ai-integrity
   ```
5. Continue WO-05..WO-30 in the dependency order in the table above.

---

## 7. Open blockers & owner inputs

From [`PLAN.md` §12 (risks)](./PLAN.md#12-open-risks--decisions-to-revisit) and [Appendix B (owner inputs)](./PLAN.md#appendix-b--things-the-owner-must-provide-before--during-phase-1):

| Item | Needed for | Status |
|---|---|---|
| **PostgreSQL install** (R11) | Verifying WO-02/03; all DB work | ⛔ blocker |
| Anthropic API key (R1) | Live AI in WO-04/WO-18 | falls back to `FakeAiClient` if absent |
| NZBN / Companies Office / IRD access (R2) | WO-13 live mode | stubs used until arranged |
| Meridian Warm brand kit (R5) | Any client-facing UI (WO-11+) | placeholder in `docs/brand/` |
| 14-clause T&C text, lawyer-reviewed (R3) | WO-10/11 | placeholder in `docs/legal/terms-v1.md` |
| `Business_Plan_Rating_Matrix.pdf` | Phase 3 entrepreneur module | placeholder in `docs/rating-criteria/` |
| NZ-qualified security review (R4) | Before production | pending |
| PQC + HSM (R6, SD-01/SD-02) | Phase 4 | deliberately deferred |

---

## 8. Conventions reminder (from CLAUDE.md / PLAN.md §0)

- One work order per branch (`wo/<id>-<slug>`) and per PR; PR title leads with the WO ID.
- Every WO ships with its tests; a WO is not done until acceptance criteria are demonstrably true.
- No feature outside `PLAN.md`. Found a gap? Raise it in the PR or as a PLAN.md risk row.
- No `dd()` / `dump()` / `console.log()` in shipped code. No `TODO`/`FIXME` without a linked WO.
- All AI calls through `AiClient`; all external calls through the resilience layer; all audit writes through `AuditWriter`; all encryption through `KeyEnvelope`.
