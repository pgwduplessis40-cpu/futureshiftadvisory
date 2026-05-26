# Future Shift Advisory

New Zealand SME advisory practice platform. Confidential — Future Shift Advisory internal use and authorised third parties only.

## Project documents (read in this order)

1. [`PLAN.md`](./PLAN.md) — Phase 1 implementation plan, 30 work orders, locked stack and architecture decisions. **Source of truth.**
2. [`CLAUDE.md`](./CLAUDE.md) — Critical rules and project orientation for AI coding agents. Read this before every session.
3. [`docs/dev-setup.md`](./docs/dev-setup.md) — First-boot environment setup (PostgreSQL via Herd, `.env`, test loop).
4. [`docs/spec/Future_Shift_Advisory_App_Specification_v2.4.docx`](./docs/spec) — Definitive product specification (V2.4, May 2026).
5. [`docs/architecture/calendar-sync.md`](./docs/architecture/calendar-sync.md) — Google/Outlook calendar sync architecture and token-handling notes.

## What this is

A platform for SME advisory work in New Zealand, covering four engagement types: Standard Advisory, Due Diligence, Post-Acquisition Advisory, and the Entrepreneur Module. Built on a strict AI Integrity Principle (honest, evidence-based, accurate, free from bias, truthful) with platform-wide document verification on every uploaded artifact.

## Stack

Laravel 13 · Inertia.js · React 19 · TypeScript · PostgreSQL 16 (row-level security) · Tailwind v4 · shadcn/ui · Laravel Fortify · Anthropic Claude API.

See [`PLAN.md` §3](./PLAN.md#3-stack--architecture-locked) for the full stack table and rationale.

## Current phase

**Phase 1 — Foundation (Months 1–3).** See [`PLAN.md` §2](./PLAN.md#2-phase-1-goal--out-of-scope) for scope and out-of-scope items. Phases 2–4 are forward roadmap only — do not implement Phase 2+ features without explicit owner approval.

## Quick start (local dev)

Full instructions in [`docs/dev-setup.md`](./docs/dev-setup.md). Short version:

```pwsh
composer install
npm install
cp .env.example .env
# edit .env per docs/dev-setup.md (Postgres connection, feature flags)
php artisan key:generate
php artisan migrate
php artisan db:seed
composer dev
```

Open `https://futureshiftadvisory.test` (Laravel Herd auto-serves).

## Test loop

```pwsh
composer test                # Pint + PHPUnit
npm run lint:check
npm run types:check
```

## Branch / PR convention

One work order per branch and per PR. Branch name `wo/<id>-<slug>`. PR title leads with the WO ID. See [`PLAN.md` §0](./PLAN.md#0-how-codex-should-use-this-plan) for the full execution protocol.

## Confidentiality

Future Shift Advisory internal use and authorised third parties only. The T&C document must be reviewed by a qualified NZ commercial lawyer before platform go-live. The security architecture should be reviewed by an NZ-qualified developer or auditor before production.
