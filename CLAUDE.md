# CLAUDE.md — Future Shift Advisory

> Project orientation for Claude Code (and any other agentic coding tool) working in this repo.
> Read this entire file before your first edit. Re-read the **Critical Rules** block at the start of every session.

---

## CRITICAL — DO NOT BREAK

These rules are non-negotiable. Breaking any of them is a release-blocking defect, not a code-style preference. They derive directly from spec V2.4 sections 3, 4, and 9.

### AI Integrity Principle (spec §3)
Every AI output — analysis, guidance, scoring, recommendation, document review, resource suggestion — must be:

1. **Honest.** Problems and low scores are stated clearly. Kindness in delivery, never in content.
2. **Evidence-based.** Every factual claim cites its source. AI evidences; it does not assert. Missing attribution is a hard failure, not a warning.
3. **Accurate.** NZ-specific, industry-specific, current. Regulatory content reflects current NZ law.
4. **Free from bias.** Bias detection runs on every output. Detected bias enters the learning update queue — it is never silently corrected.
5. **Truthful.** The platform tells users what the evidence says, not what they want to hear.

### Document AI Verification (spec §9)
Every document uploaded anywhere on the platform is reviewed for relevance and accuracy. Three outcomes only: `verified`, `advisory_flag`, `accuracy_discrepancy`. **Accuracy discrepancies are never suppressed.** They surface to the advisor immediately and pause downstream analysis until resolved.

### Security baseline (spec §4, with Phase 1 deviations)
- **No public registration.** Every account is invite-only. The Fortify register route is disabled. There is no exception for any user type.
- **MFA is mandatory** for all nine user types. There is no "skip MFA" anywhere.
- **Every file is virus-scanned** before persistence, regardless of uploader.
- **Every action is audit-logged** to the immutable `audit_events` table. Direct `UPDATE`/`DELETE` against that table is rejected by a Postgres trigger.
- **No PII in raw logs.** All log lines containing user data go through the redaction helper.
- **All external service calls** go through the resilience layer (`ResilientHttp` + `RetryPolicy` + `CircuitBreaker`). No raw `Http::post` to a third-party hostname.
- **All AI calls** go through the `AiClient` interface. No code path constructs Anthropic requests directly. The `FakeAiClient` is bound in all tests.

### Process rules
- **No feature outside the plan.** `PLAN.md` is the source of truth. If you find a gap, raise it in the PR description rather than implementing it.
- **One Work Order per branch, per PR.** Branch name `wo/<id>-<slug>`. PR title leads with the WO ID.
- **No `TODO`/`FIXME` without a linked WO.** Leaving a stub is fine — leaving an unowned one is not.
- **No `dd()`/`dump()`/`console.log()`** in shipped code. CI will catch them; do not let it.
- **No silent learning updates.** The 32-layer learning system never modifies its own behaviour without going through the governed update queue (Phase 1 builds the queue; Phase 3 builds the approval UI).

---

## Project orientation

### What this is
**Future Shift Advisory (FSA)** is a New Zealand SME advisory practice platform. It serves four engagement types (Standard Advisory, Due Diligence, Post-Acquisition Advisory, Entrepreneur Module) and nine user types (super admin, advisor, junior advisor, entrepreneur mentor, client primary, client team, entrepreneur, insurance broker, life/personal coach), with a guest upload role for DD targets.

### Current phase
**Phase 1 — Foundation (Months 1–3).** The plan covers Phase 1 in implementation detail; Phases 2–4 are forward roadmap only. Do not implement Phase 2+ features without explicit owner approval.

### Stack (locked)
| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Frontend | Inertia.js + React 19 + TypeScript |
| UI | shadcn/ui (new-york), Tailwind v4, Radix, lucide-react, sonner |
| Typed routes | Laravel Wayfinder |
| Auth | Laravel Fortify + custom MFA/invite layer |
| DB | PostgreSQL 16 with row-level security |
| Queues | Laravel queues (database driver in Phase 1) |
| Storage | Encrypted local disk Phase 1; S3 Sydney later |
| AI | Anthropic Claude API via `AiClient` interface |
| PDF | Spatie Browsershot |
| Test | PHPUnit (feature + unit), ESLint, Prettier, Pint, `tsc --noEmit` |

### Where to start
1. Read **`PLAN.md`** end-to-end (root of repo). It contains the locked decisions, the database schema, the cross-cutting foundations, and the 30 work orders in dependency order.
2. Read **`docs/dev-setup.md`** to provision Postgres (via Herd) and configure `.env`.
3. Pick the next Work Order in numeric order. Implement it on its own branch with its tests; open a PR. Do not combine WOs.
4. Before any AI-touching change, re-read PLAN.md §7.1 and CLAUDE.md "AI Integrity Principle" above.
5. Before any data-access change, re-read PLAN.md §7.4 (integration pattern) and §6.2 (RLS).

### Repo map (high-level — full map in PLAN.md §4)
```
app/                # Laravel application code
docs/               # Plan, spec, brand kit, legal text, architecture decisions
  PLAN.md           # Mirror of root PLAN.md
  spec/             # Spec V2.4 docx (source of truth)
  brand/            # Meridian Warm design system, FSA logo
  legal/            # T&C source text (14 clauses)
  rating-criteria/  # Founding rating matrix PDF (Entrepreneur Module — Phase 3)
  architecture/     # Decision records (Postgres RLS, security deviations, etc.)
  dev-setup.md      # First-boot instructions
PLAN.md             # Source of truth — read before any work
resources/js/       # React + Inertia frontend
routes/             # Laravel route files
tests/              # PHPUnit feature + unit tests
```

### How to ask for help / flag a gap
- Open a PR with `[NEEDS DECISION]` in the title. Describe the decision needed and the options considered.
- Or update `PLAN.md` §12 (Open risks) with a new row and reference it in commit messages.

---

## Phase 1 quick reference

If you ever forget what's in scope vs. out of scope, this is the short version (see PLAN.md §2 for the full list):

**In scope (Phase 1):** Postgres + RLS, audit trail, AI integrity foundation, integration resilience layer, secure file storage + virus scan, RBAC for 9 user types, invite-only + MFA, session/step-up, T&C model + acceptance gate + signed PDF, channel-preference notifications, NZBN/Companies Office/IRD scaffolds, client + entrepreneur creation, client portal shell + 7-step onboarding wizard, questionnaire engine (Standard Advisory set), document upload + verification pipeline, data quality gate, wellbeing check-in, conflict-of-interest declaration, structured offboarding, lifecycle management, notification centre UI, in-app messaging, email-from-app, advisor knowledge base (manual entry), basic advisor dashboard with verification flag panel, website prospect intake webhook, API health dashboard.

**Out of scope (Phases 2–4):** All analysis modules, PV module, fee calculator, proposal/payment, DD module, virtual data room, broker portal, coach portal, full entrepreneur module, learning update queue UI, 32-layer learning active behaviours, cross-client intelligence, anonymous benchmarking, peer network, voice assistant, mobile app, PQC cryptography, HSM.

---

*Spec V2.4 — May 2026 — DEFINITIVE VERSION. Confidential. Owned by Future Shift Advisory, Hamilton NZ.*

---

## NPO Module Rules

The NPO module is an additive engagement lane. Do not weaken existing SME, DD, entrepreneur, broker, coach, or portal behaviour to make an NPO path work.

- NPO clients use `EngagementType::NPO`; the concrete module path lives on `npo_engagements.sub_type`.
- Full NPO work is only `standard_npo` or `social_enterprise`; Governance Review remains a standalone `governance_review` sub-type with conversion history.
- `tiriti_mode` is stored as `standalone` or `woven`. Mode A/B are display labels only.
- The eight NPO health dimensions are mission strategy, service operations, governance/compliance, financial sustainability, people/capability, impact measurement, funding resilience, and Te Tiriti.
- NPO value calculations are mission-framed and must retain the +/-15% uncertainty range. Do not present them as commercial profit or EBITDA uplift.
- Social Enterprise dual scorecards use integer weights summing to 100. The blended formula is `(commercial_score*commercial_weight + mission_score*mission_weight) / 100`.
- Governance Review outputs require reviewed findings, document support notes, source attributions, and the non-legal-advice disclaimer.
- Funder database mutations go through Layer 34 governed candidates; never write funder registry rows directly from an integration response.
- NPO learning layers are 34-37: 34 funder database updates, 35 governance compliance thresholds, 36 cost-per-beneficiary benchmarks, 37 funding concentration risk thresholds.
- Funder contacts get report-scoped access only to reviewed Funder Accountability reports. They never get broad client scope.
- Incorporated Societies Act 2022 re-registration alerts are critical gates until acknowledged or resolved.
- NPO reports are Governance Review, NPO Health, NPO Advisor, Funder Accountability, Impact Summary, and Social Enterprise Dual Impact. Board members may see only explicitly allowed board-facing report types.
