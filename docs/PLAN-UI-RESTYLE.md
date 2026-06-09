# PLAN — Full UI Restyle (Meridian Warm)

> Status: **Proposed / not yet scheduled.** A forward plan to bring every screen
> in the app onto one consistent, polished visual system without changing
> business logic.
> Owner: TBD. Prereq sign-off: design language + this plan.

---

## 0. Scope reality check (read first)

"~250 pages" is the **route count** (`php artisan route:list` ≈ 259), and most of
those are POST/PATCH/DELETE action routes with **no view**, plus role-state
variants of shared components. The actual **restyle surface** is:

| Surface | Count |
|---|---|
| React page components (`resources/js/pages/**/*.tsx`) | **81** |
| Shared layouts (`resources/js/layouts/**`) | ~13 |
| Shared UI primitives (`resources/js/components/ui` + app components) | 28 + a handful |

Page components by area: **advisor 26, admin 17, portal 10, auth 10, public 6,
settings 5, terms 2**, plus singletons (dashboard, calendar, broker, coach,
notifications). This is the unit of work — far smaller than "250 pages" implies,
which makes a primitive-driven restyle very achievable.

### In scope
- Visual consistency of all 81 page components + shared layouts.
- A completed, documented component/primitive library.
- Responsiveness (mobile/tablet/desktop) and accessibility baseline.

### Explicitly out of scope
- Any change to business logic, routes, forms, API calls, or permissions.
- The locked brand colour scheme / "Meridian Warm" identity (refine layout, keep identity).
- New features or content changes (copy edits only where a heading/empty-state needs a label).

---

## 1. Guiding principles
1. **Primitive-first.** Pages compose shared primitives; they do not hand-roll
   padding/card/header strings. Consistency is enforced by the library, not by
   discipline on 81 pages.
2. **No functional change.** Styling-only. Routes/forms/permissions/props untouched.
3. **Incremental, never big-bang.** Migrate in small per-area batches, each one
   green-gated and fast-forwarded — the established `featureApp → green → main`
   workflow. No long-lived divergent branch.
4. **Visual-regression-gated.** See §2 — the automated suite does **not** cover
   visuals, so we add screenshot diffing before touching pages.
5. **Brand locked, layout refined.** Keep `--fs-*` tokens and the Meridian Warm
   palette; standardise spacing, hierarchy, and surfaces only.

---

## 2. The critical gap: tests don't see pixels

The PHPUnit suite uses Inertia `assertInertia`, which checks **component names and
props — never rendered HTML or classNames**. So 739 green tests give **zero**
coverage that a restyle didn't break a layout. This is the single biggest risk and
must be closed in Phase 0 with tooling:

- **Visual regression** (Playwright screenshots, or Percy/Chromatic). Capture a
  baseline screenshot of every route × representative role **before** the restyle,
  then diff after each batch. A page is "done" only when its diff is intentional
  and reviewed.
- **Accessibility** checks (axe-core in Playwright): landmarks, heading order,
  contrast, focus, labels.
- **Storybook** for the primitives, so the library is reviewed in isolation before
  it's rolled out across pages.

---

## 3. Phase 0 — Foundation & tooling (do before any page work)

### 3.1 Design language sign-off
- Adopt/finalise the **Meridian Warm** spec (`docs/brand/`) as the visual source
  of truth: spacing scale, radii, shadow usage, typography scale, surface levels,
  state colours, density. Produce a one-page "UI language" reference.
- Decision needed: card standard (radius `rounded-md` vs `rounded-lg`, shadow
  yes/no, surface `bg-background` vs `bg-card`). Lock ONE.

### 3.2 Tokens (Tailwind v4 `@theme`)
- Audit and document the canonical **spacing scale** (page padding, section gaps,
  card padding, control gaps) and **type scale** (page title, section title, body,
  meta). Encode as tokens/utilities so values stop being ad-hoc.

### 3.3 Complete the primitive library
Already shipped: `PageHeader`, `SectionCard`, `EmptyState`, plus 28 shadcn `ui/`
components and the shared layout padding container. Build the rest so every
archetype has parts:

| Primitive | Purpose | Status |
|---|---|---|
| `PageHeader` | eyebrow + title + description + actions | ✅ exists |
| `SectionCard` | standard panel (border, surface, padding) | ✅ exists |
| `EmptyState` | empty list/section | ✅ exists |
| Page container (layout) | consistent responsive page padding | ✅ in `app-layout` |
| `DataTableCard` | table shell: header, hover, responsive scroll, empty, pagination | ➕ build |
| `FilterBar` / `Toolbar` | search + filters above lists | ➕ build |
| `StatCard` / `MetricGrid` | dashboard KPI tiles (replaces ad-hoc StatusPanel) | ➕ build |
| `DetailGrid` / `DescriptionList` | key-value detail (show pages) | ➕ build |
| `FormSection` / `FormRow` | label + control + error spacing, grid | ➕ build |
| `Callout` | info/warning blocks (wrap `ui/alert`) | ➕ build |
| `StatusBadge` | consistent status pills | ➕ build |
| `TabBar` | consistent tabs (replaces ad-hoc `*TabList`) | ➕ build |
| `Skeleton` / loading | consistent loading states | ➕ build |
| `Pagination` | consistent paging | ➕ build |

### 3.4 Enforcement
- ESLint/custom check that **forbids ad-hoc patterns** once primitives exist (e.g.
  raw `rounded-md border bg-background p-4`, one-off `<header className="flex...">`)
  so regressions can't re-enter. Add to the hardened `lint.yml` gate.
- Storybook published; visual-regression baseline committed.

**Exit criteria for Phase 0:** design language signed off; tokens documented;
primitive library complete + in Storybook; visual-regression + axe harness running
in CI with a full baseline; enforcement lint rule live.

---

## 4. Page archetypes (the templating strategy)

Don't restyle 81 snowflakes — restyle ~8 **archetypes**, build one polished
reference page per archetype, then conform each page to its archetype. Most of the
81 pages fall into:

| # | Archetype | Built from | Example pages |
|---|---|---|---|
| A | **Auth** | `auth-*` layouts, centered card | login, mfa, password reset (10) |
| B | **Public/marketing** | public layout, hero/sections | home, about, services, contact (6) |
| C | **Dashboard** | PageHeader + MetricGrid + SectionCard tabs | dashboard, advisor, portal, broker, coach, entrepreneur |
| D | **List/index** | PageHeader + FilterBar + DataTableCard + EmptyState | clients, prospects, templates, learning, panels, testimonials, bulk-comms, credentials |
| E | **Detail/show** | PageHeader + DetailGrid + SectionCard panels | clients/Show, entrepreneurs/Show, post-acquisition |
| F | **Form / create-edit** | PageHeader + FormSection + SectionCard | reference-data, service-rates, welcome-message, questionnaires/Edit |
| G | **Wizard / multi-step** | stepper + SectionCard | portal onboarding |
| H | **Report / document** | PageHeader + read surface | reports, methodology, terms gate |

Each archetype gets: a reference implementation, a Storybook entry, and a short
"how to build a page of this type" note.

---

## 5. Migration phases (sequenced)

Order chosen to de-risk: lowest-traffic/lowest-complexity first to validate the
system, then the high-value surfaces, then the long tail.

| Phase | Batch | Pages (≈) | Rationale |
|---|---|---|---|
| 0 | Foundation + tooling | — | Prereq (design, primitives, visual regression, Storybook, enforcement) |
| 1 | **Archetype reference pages** | 8 | One polished page per archetype; design sign-off anchor |
| 2 | Auth + Public | 16 | Low risk, isolated layouts, validate the system end-to-end |
| 3 | Admin (17) | 17 | High internal use; many are forms/lists already part-migrated |
| 4 | Advisor (26) | 26 | Largest area; split into sub-batches (clients, calendar, comms, knowledge, prospects, reports) |
| 5 | Portal — client + onboarding + wellbeing + messages | ~7 | Client-facing; high polish value |
| 6 | Portal — entrepreneur + DD + NPO | ~5 | Module-specific detail/wizard pages |
| 7 | Broker, Coach, Calendar, Notifications, Settings, Terms, Dashboard | ~11 | Long tail |
| 8 | a11y + responsive + cross-browser sweep | all | Final hardening pass |

Each phase merges to `main` incrementally — the app is always shippable.

---

## 6. Per-batch workflow & per-page Definition of Done

### Per batch
1. Branch off `featureApp` (`ui/<area>`), migrate the batch's pages to archetype templates/primitives.
2. Run visual-regression diff; review every changed screenshot (intentional vs accidental).
3. Run the full gate: `tsc`, `eslint`, `prettier`, `pint`, **PHPUnit**, plus the visual + axe checks.
4. Design/peer review of the diff.
5. Green → fast-forward `main`.

### A page is "done" when
- [ ] Uses shared primitives only — no ad-hoc padding/card/header strings.
- [ ] Responsive: clean at mobile / tablet / desktop; no horizontal overflow.
- [ ] a11y: correct landmarks/heading order, focus states, labels, contrast (axe clean).
- [ ] Visual diff reviewed and approved.
- [ ] All gates green; **no functional/route/form/permission change** (props/component name unchanged in Inertia tests).
- [ ] Loading + empty + error states styled (not just the happy path).

---

## 7. Effort & sequencing

Bucket the 81 components by complexity (from this codebase's structure):

| Bucket | ~Count | Per-page effort | Notes |
|---|---|---|---|
| Simple (auth, public, singletons, small lists) | ~30 | 0.5–1.5 h | Mostly header/card/empty swaps |
| Medium (forms, standard lists, dashboards) | ~35 | 2–4 h | FilterBar/DataTable/FormSection adoption |
| Complex (clients/Show, advisor dashboard, onboarding, NPO/DD detail) | ~16 | 1–2 days | Many panels, custom layouts, careful diffs |

**Indicative total:** Phase 0 ≈ 1–2 weeks (1 dev); page migration ≈ 4–7 dev-weeks
depending on review cadence and how much of the long tail is templated. Parallelise
by assigning **archetype owners** (one dev owns Dashboard archetype across all
roles, another owns List, etc.) for consistency. Treat estimates as ranges, not
commitments — the real driver is review/sign-off throughput, not typing.

---

## 8. Risk register

| Risk | Likelihood | Mitigation |
|---|---|---|
| Visual regressions invisible to PHPUnit | High | Visual-regression harness in Phase 0; per-batch diff review |
| Scope creep into redesign / new features | Med | Strict "styling-only" DoD; design spec locks the target |
| Accidental functional/permission breakage | Low | Inertia prop/component tests stay green; DoD forbids logic edits |
| Inconsistency between batches | Med | Archetype reference pages + enforcement lint rule + Storybook |
| Concurrent feature work conflicting | Med | Small batches, frequent ff to main, rebase often |
| a11y/contrast regressions from new surfaces | Med | axe-core in CI; contrast tokens locked in Phase 0 |
| Mobile overflow from new grids | Med | Responsive checks in visual harness at 3 breakpoints |

---

## 9. Governance
- **Design sign-off** at Phase 0 (language) and Phase 1 (archetype reference pages).
- **Batch sign-off** on the visual diff before each ff to `main`.
- Track progress in a per-area checklist (the §4 archetype table × the §0 inventory).
- Keep `CLAUDE.md` invariants intact (brand locked, no functional change, green gate).

---

## 10. First concrete steps (when scheduled)
1. Generate the authoritative page inventory: `route:list` × `pages/**/*.tsx`,
   tagged by area, archetype, role(s), and complexity bucket → tracking sheet.
2. Stand up Playwright visual-regression + axe with a full baseline (seeded data,
   one login per role) and wire into CI.
3. Stand up Storybook; complete the §3.3 primitive library; add the enforcement lint rule.
4. Build the 8 archetype reference pages; design sign-off.
5. Begin batch migration per §5, ff to `main` after each green batch.

---

*Builds on the spacing/primitive groundwork already merged (shared page container,
`PageHeader`/`SectionCard`/`EmptyState`, hardened lint/type/format CI gate).*
