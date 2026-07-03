# PLAN — Authenticated App UI Restyle ("Tasko" language × Meridian Warm)

**Plan version:** 1.4 — owner direction + code-grounded design pass + shell-reality + functional-preservation review fixes (gate now AST-required). *(Build target: Codex, into the test env, then push to live.)*

> **v1.4 revision (review pass — the gate must match how this codebase actually writes links/forms).** Four fixes:
> (P1) **AST snapshot is now the REQUIRED gate** (was "gold standard") — verified failure modes of the regex
> form: named form variables (`createForm.post` etc., 23 sites/8 files, 11 in `advisor/clients/Show.tsx`)
> and template-literal hrefs (`` href={`…${id}/preview`} ``, 23 sites incl. broker/coach dashboards) both
> escape it. `scripts/link-inventory.ts` (built in UI-0) captures any-receiver `.post/.patch/…` calls,
> full JSX attribute expressions incl. template literals, `useForm`, and `@/routes`/`@/actions` imports (§5.1 rule 3).
> (P1→ carried) `transform` added to the protected method list.
> (P2) **UI-0 neutrality is machine-proven** — `git diff --ignore-cr-at-eol -w` empty, else a functional-surface
> extraction (export names + route `path`/`method` literals) HEAD-vs-worktree must be identical before reset;
> the check output ships in the UI-0 PR (§6).
> (P3) **`.gitattributes` spelled as two explicit lines** — `resources/js/routes/** text eol=lf` +
> `resources/js/actions/** text eol=lf` (brace expansion isn't reliable gitattributes syntax) (§6, UI-0).

> **v1.3 revision (review pass — make the safety gate actually catch breakage).** Five fixes:
> (P1) **Inventory gate captures VALUES, not counts** — a count-based gate passes `href={x.download_url}` →
> `href="#"`. §5.1 rule 3 now snapshots the **full expressions** (`href`/`as`/`method`/`onClick`/`onSubmit`
> values, `router.*`/`form.*`/`useForm` call arguments, `@/routes`+`@/actions` import specifiers) with
> `rg -U -o`, sorted, **byte-identical diff**; AST snapshot (ts-morph) noted as the gold standard.
> (P1) **Pre-flight baseline (UI-0)** — the worktree **currently has 70 modified files** under
> `resources/js/routes/**`+`resources/js/actions/**` (418+/418− symmetric; CRLF→LF churn from a Windows
> Wayfinder run, budget routes already in HEAD). The byte-unchanged gate is unprovable from a dirty baseline —
> UI-0 resolves it (reset the churn or commit it as a named baseline) before any UI-* branch (§6).
> (P2) **Deletion exemption** — deleted dead files can't have "identical inventories"; §5.1 rule 3 now exempts
> **zero-import deletions** with their own proof (repo-wide `rg` zero imports + `tsc` + full suites, UI-4's own PR).
> (P2) **`StatCard.href` constrained** — forbidden during UI-5 adoption unless it carries an **existing
> identical destination** being replaced, proven by the gate; never a new destination (§4).
> (P3) **`shadow-card` token path** — `--shadow-card` must be declared **inside `@theme`** (Tailwind v4
> `--shadow-*` namespace generates the utility); otherwise use `shadow-[var(--shadow-card)]` (§3.3).

> **v1.2 revision (review pass — nothing functional may break).** Five fixes, all one theme:
> (P1) **UI-2 restyles only controls that render today** — the live top bar is trigger + breadcrumbs + brand
> band + `NotificationBell` ([app-sidebar-header.tsx:17-42](resources/js/components/app-sidebar-header.tsx:17));
> the Tasko search field / mail icon / header avatar chip are **descoped** (optional future WO gated on
> per-role link-parity evidence — messages nav is role-specific; broker/coach have none) (§5, §5.1, UI-2).
> (P1) **Logout is never reimplemented or moved** — it lives in `UserMenuContent` with a load-bearing cleanup
> path (`cleanup()` + `clearPortalOfflineQueue()` + `router.flushAll()` + generated `logout()` as button +
> `data-test="logout-button"`, [user-menu-content.tsx:23-27,51-62](resources/js/components/user-menu-content.tsx:23));
> reuse `NavUser`/`UserMenuContent` unchanged, style containers only (§5.1).
> (P1) **UI-5 gains a link/action preservation gate** — scripted before/after inventory diff of `href=`,
> `download_url`, `view_url`, `connect_url`, `router.*`, `form.*`, `@/routes` + `@/actions` imports per
> edited file; must be identical (§5.1, UI-5, §11).
> (P2) **Generated client route/action modules protected** — `resources/js/routes/**` and
> `resources/js/actions/**` (Wayfinder output) must be byte-unchanged; no `wayfinder:generate` in this plan (§9, §11).
> (P2) **CountBadge only where a count already renders** — `NavItem` has no count field
> ([navigation.ts:9-14](resources/js/types/navigation.ts:9)); no new payload/props, **never** static/fake
> counts (§4).

> **v1.1 revision (review pass — the live shell vs the dead one).** Five fixes:
> (P1) **Single live shell** — the Inertia resolver routes portal/broker/coach/default through `AppLayout`
> ([app.tsx:18-38](resources/js/app.tsx:18)); `PortalLayout`/`ExternalPanelLayout` have **zero imports** (dead
> code) and the sidebar shell renders **`AppSidebarHeader`**, not `app-header.tsx`
> ([app-sidebar-layout.tsx:17](resources/js/layouts/app/app-sidebar-layout.tsx:17)). §2/§5/WOs retargeted;
> dead shells deleted in UI-4 (decision §8.4).
> (P1) **`PageHeader` already exists** ([page-header.tsx:11](resources/js/components/page-header.tsx:11),
> `title/eyebrow/description/icon/actions`, broadly imported) — restyle it **backward-compatibly**, don't
> introduce a conflicting primitive (§4).
> (P2) **`hsl(var(...))` call site** — [sidebar.tsx:476](resources/js/components/ui/sidebar.tsx:476) wraps
> `--sidebar-border`/`--sidebar-accent` in `hsl()`; hex token values would break it. UI-2 rewrites those
> shadows to plain `var(...)` (§3.1 note).
> (P2) **Hardcoded-color inventory corrected** — not just 2 files: 131 occurrences of `--fs-*`/`slate-`/
> `gray-`/`zinc-` across 17 page files (incl. portal/broker/coach dashboards), plus
> [app-sidebar-header.tsx:20,31](resources/js/components/app-sidebar-header.tsx:20) commodore/gold hex.
> Portal `--fs-*` uses are **currently undefined outside `.public`** — the UI-1 hoist silently fixes them (§2.4, UI-6).
> (P2) **NPO board dashboard added** — `npo_board_member` redirects to `portal/npo-board/Dashboard`
> ([DashboardController.php:92](app/Http/Controllers/DashboardController.php:92)); added to UI-5 + UI-7 (§6, §11).

**Owner direction (locked):** Restyle the **authenticated FSA app** to the visual language of the supplied
"Tasko – Modern Task Management Dashboard" reference (pill sidebar, ultra-rounded cards, inverted hero stat
card, soft shadows, rounded-full CTAs, warm off-white canvas) — **but keep the existing Meridian Warm
palette**. No green; Tasko's greens map to Meridian navy/teal/gold.

**One-line intent:** One token flip + a small set of shared shell/primitive components give every dashboard
(advisor, admin, client portal, entrepreneur, broker, coach) the Tasko look in Meridian Warm — **zero
behaviour change**.

> **Scope guard (per repo rules + fsa-app guardrails):** styling only. No route, controller, payload, RLS,
> audit, or AI-surface changes. The **public marketing site keeps its existing Meridian styling untouched**
> (`.public` scope). Report/PDF templates (Browsershot) are out of scope. This doc is the plan-of-record for
> the work; reference it in each PR description.

---

## 1. What the reference style is (anatomy of "Tasko")

| # | Element | Reference behaviour |
|---|---|---|
| A | **Sidebar** | White surface; logo + circular brand mark top; section labels (`MENU`, `GENERAL`) in tiny uppercase muted text; nav items icon+label; **active item = filled dark pill (rounded-full/xl) with white text**; count badges (e.g. Tasks `124`); Logout at bottom. |
| B | **Top bar** | Large **rounded-full search** input with keyboard-shortcut hint chip (⌘K); mail + bell icon buttons (bell has red dot); **avatar chip** with name + email at right. |
| C | **Page header** | Big bold heading + one-line muted subtitle; primary CTA = **dark filled rounded-full** ("+ Add Project"); secondary = white outline rounded-full. |
| D | **KPI stat cards** | 4-up row; **first card inverted** (dark fill, light text), rest white; very rounded (~20px); small title, huge display number, **corner circular icon button** (↗), trend footnote ("Increased from last month"). |
| E | **Chart card** | White rounded card, title + legend dot; **bar chart with rounded-top bars in 2–3 palette shades**, one emphasis bar; footer stat strip (Average / Peak). |
| F | **Side cards** | "Reminders": nested inner card with meeting + time + **full-width dark CTA with icon** ("Start Meeting"). "Project Progress": **radial/donut progress** with hatched unfilled track and huge % label. |
| G | **Overall feel** | Warm off-white canvas; generous whitespace; soft diffuse shadows; flat (no gradients); friendly geometric sans; high contrast between dark fills and white cards. |

---

## 2. Verified code facts (what the build hangs off)

1. **The app theme is stock shadcn neutral.** `:root`/`.dark` hold greyscale oklch tokens with
   `--radius: 0.625rem` ([app.css:66-135](resources/css/app.css)). Meridian Warm exists **only** inside
   `.public` ([app.css:291-350](resources/css/app.css)) — the restyle **promotes the palette into the app
   tokens** (this deliberately supersedes the earlier "app stays neutral" scoping comment at app.css:287-289).
2. **Fonts are already loaded globally** — Outfit (300–700), DM Serif Display, Cormorant Garamond via the
   Google Fonts `@import` at [app.css:5](resources/css/app.css). Applying Outfit app-wide costs nothing new.
3. **ONE live shell covers every authenticated screen (v1.1 — verified against the resolver).** The Inertia
   layout resolver ([app.tsx:18-38](resources/js/app.tsx:18)) routes `portal/*` (client, entrepreneur, NPO
   board), `advisor/*` (via the thin `AdvisorLayout` wrapper,
   [AdvisorLayout.tsx:14-18](resources/js/layouts/AdvisorLayout.tsx)), **and the default case (admin, broker,
   coach — everything else)** through **`AppLayout`** → `app-sidebar-layout` → `AppShell` + `AppSidebar` +
   **`AppSidebarHeader`** ([app-sidebar-layout.tsx:13-25](resources/js/layouts/app/app-sidebar-layout.tsx:13)).
   So restyling `app-sidebar.tsx` + `app-sidebar-header.tsx` + `components/ui/sidebar` restyles **every
   role**. Non-shell layouts that remain live: `auth/*`, `settings/layout` (stacked on AppLayout),
   `notifications-layout`, `public-layout` (untouched).
   - **Dead code (zero imports — do NOT style these):** `PortalLayout.tsx`, `ExternalPanelLayout.tsx`, and
     `app-header.tsx`/`app-header-layout.tsx` (the resolver never returns the header layout). Styling them
     would "succeed" while changing nothing visible; they're **deleted in UI-4** instead (decision §8.4).
   - `AppSidebarHeader` already carries a brand band with **hardcoded** commodore `bg-[#2a3b5c]` + gold
     `text-[#d4a020]` ([app-sidebar-header.tsx:20,31](resources/js/components/app-sidebar-header.tsx:20)) —
     tokenised in UI-2.
4. **Propagation is cheap — with a corrected inventory (v1.1).** Pages overwhelmingly use theme classes
   (`bg-background`, `text-muted-foreground`, `border-border`…), so the token flip restyles ~550 files for
   free. The **full** hardcoded-style inventory for the sweep (grep `#hex|--fs-|slate-|gray-|zinc-`):
   **131 occurrences across 17 page files** — the `public/*` set (keeps its own styling) plus
   `portal/Dashboard`, `portal/entrepreneur/Dashboard`, `portal/onboarding/Step`, `portal/ProposalSignoff`,
   `portal/StrategicPlanBudget`, `portal/wellbeing/Pulse`, `portal/surveys/Show`, `broker/Dashboard`,
   `coach/Dashboard`, `admin/welcome-message/Index`, `advisor/clients/Show`, `advisor/templates/*` — plus
   component-level items (`app-sidebar-header.tsx` hex above; `WaterfallChart.tsx`). **Note:** the portal
   pages' `--fs-*` references are **undefined outside `.public` today** (silently falling back) — UI-1's
   hoist of the `--fs-*` definitions to `:root` fixes them as a side effect. UI-6 owns this list.
5. **Charts are hand-rolled SVG, not Recharts** — zero `from 'recharts'` imports; the chart component in
   `components/` is [pv/WaterfallChart.tsx](resources/js/components/pv/WaterfallChart.tsx) (custom SVG);
   dashboards render their own inline SVG bars. Chart theming = the `--chart-1..5` tokens
   ([app.css:50-54, 86-90](resources/css/app.css)) **plus a grep for inline chart color classes** in the big
   dashboard pages (UI-5 owns this).
6. **Dark mode is real** — `.dark` token block + appearance settings ship today; the restyle must map
   Meridian Warm to dark or it regresses.

---

## 3. Token remap (the core of the whole restyle — UI-1)

Replace the neutral oklch values in `:root` with Meridian Warm equivalents (hex is fine; Tailwind v4 doesn't
care). `.public` keeps its own block unchanged.

### 3.1 Light (`:root`)

| shadcn token | New value | Meridian name | Tasko role |
|---|---|---|---|
| `--background` | `#f9f6f0` | parchment | warm off-white canvas |
| `--foreground` | `#1c2b45` | admiralty | body text |
| `--card` / `--popover` | `#ffffff` | elevated white | cards |
| `--card-foreground` / `--popover-foreground` | `#1c2b45` | admiralty | card text |
| `--primary` | `#1c2b45` | admiralty | dark fills: active pill, primary CTA, inverted stat card |
| `--primary-foreground` | `#f9f6f0` | parchment | text on dark fills |
| `--secondary` | `#f0ead8` | linen | secondary surfaces/chips |
| `--secondary-foreground` | `#1c2b45` | admiralty | |
| `--muted` | `#f0ead8` | linen | muted surfaces |
| `--muted-foreground` | `#5a6a7a` | graphite | subtitles, section labels |
| `--accent` | `#e8d5a0` | champagne | hover washes, subtle highlights |
| `--accent-foreground` | `#1c2b45` | admiralty | |
| `--destructive` | keep current red family | — | unchanged semantics |
| `--border` / `--input` | `#e0d8cc` | sand | card borders, input outlines |
| `--ring` | `#0d7a7a` | pacific | focus rings (AA-visible on parchment) |
| `--chart-1..5` | `#1b5070`, `#0d7a7a`, `#0d6a5a`, `#d4a020`, `#2a3b5c` | harbour, pacific, deep-cove, warm-gold, commodore | bar ramp; **gold = emphasis bar only** |
| `--radius` | `1rem` | — | Tasko roundness (derived sm/md/lg/xl scale with it) |
| `--sidebar` | `#ffffff` | white | Tasko sidebar surface |
| `--sidebar-foreground` | `#1c2b45` | admiralty | |
| `--sidebar-primary` | `#1c2b45` | admiralty | **active nav pill fill** |
| `--sidebar-primary-foreground` | `#f9f6f0` | parchment | active pill text |
| `--sidebar-accent` | `#f0ead8` | linen | hover pill |
| `--sidebar-accent-foreground` | `#1c2b45` | admiralty | |
| `--sidebar-border` | `#e0d8cc` | sand | |
| `--sidebar-ring` | `#0d7a7a` | pacific | |

Additionally expose the raw brand tokens app-wide (move the `--fs-*` custom-property *definitions* from
`.public` to `:root` so both scopes share one source; `.public`'s semantic mappings stay scoped — this also
fixes the portal pages that already reference `--fs-*` where it's currently undefined, §2.4). Add one new
semantic token used by stat cards & CTAs: `--gold: #d4a020; --gold-strong: #b8860b;`.

> **⚠️ `hsl(var(...))` call-site fix (P2, goes with the token flip):**
> [sidebar.tsx:476](resources/js/components/ui/sidebar.tsx:476) wraps `--sidebar-border` / `--sidebar-accent`
> in `hsl(var(...))` inside arbitrary shadow values — valid for the current oklch-component-less values but
> **invalid CSS once the tokens are hex**. UI-2 rewrites those two shadows to
> `shadow-[0_0_0_1px_var(--sidebar-border)]` (and the accent hover equivalent). UI-1's gate includes a grep
> for any other `hsl(var(` call sites (this is the only one found in `resources/js` today).

### 3.2 Dark (`.dark`) — Meridian Night (flagged for owner eyeball, §8)

Navy-anchored, gold constant: `--background #101c2c`, `--card #1c2b45`, `--foreground #f0ead8`,
`--primary #e8d5a0` (champagne fills, admiralty text), `--muted-foreground #9fb0c4`,
`--border rgba(224,216,204,.16)`, `--sidebar #16233a`, active pill = `#0d7a7a` pacific fill / parchment text,
`--ring #d4a020`, chart ramp brightened one step (`#2e7ca8`, `#14a3a3`, `#17907c`, `#d4a020`, `#4a5f8a`).

### 3.3 Typography & elevation

- **App UI font: Outfit everywhere** (body 400, headings 600–700). Set on `body` via the base layer.
  **DM Serif Display stays reserved for the public site** — Tasko's app-tool feel is bold sans, and mixing
  the serif into dense dashboards hurts scanability. *(Flip is one line if the owner prefers serif page
  titles — §8.)*
- Shadows: two steps only — `--shadow-card: 0 1px 2px rgb(28 43 69 / .04), 0 8px 24px -12px rgb(28 43 69 / .10);`
  and a stronger hover variant. **Declare both inside the `@theme` block** — Tailwind v4 generates the
  `shadow-card` / `shadow-card-hover` utilities from the `--shadow-*` namespace **only** when declared there;
  declared elsewhere, use `shadow-[var(--shadow-card)]` instead (v1.3). Flat design otherwise: **no
  gradients** in the app shell.
- Spacing: cards `p-5`/`p-6`; page gutter `px-6 lg:px-8`; card grid `gap-4 lg:gap-5`.

---

## 4. Shared primitives (new/updated components — UI-3)

All in `resources/js/components/` using existing shadcn/cva conventions; **presentation-only props**.

| Component | Spec (Tasko → Meridian) |
|---|---|
| `StatCard` | Props: `title, value, footnote, trend ('up'\|'down'\|'flat'), inverted?, icon?, href?`. White card `rounded-[1.25rem] border-border shadow-card`; **`inverted` variant: `bg-primary text-primary-foreground`** with gold corner icon-button; value `text-4xl font-bold tracking-tight`; footnote `text-xs text-muted-foreground` with trend arrow. Replaces the ad-hoc stat blocks on the dashboards (adoption in UI-5). **`href` constraint (v1.3, §5.1):** during UI-5 adoption `href` is **forbidden** unless the stat block being replaced already links to that **identical destination** (proven by the §5.1 value gate) — `StatCard` never introduces a new destination. |
| `PageHeader` | **Restyle the EXISTING component backward-compatibly** ([page-header.tsx:11](resources/js/components/page-header.tsx:11)) — it already ships `title, eyebrow, description, icon, actions` and is broadly imported. **Keep the prop contract byte-identical** (no renames — `description` stays `description`, not "subtitle"); change only the visual treatment: title `text-3xl font-bold tracking-tight`, description `text-sm text-muted-foreground`, eyebrow restyled as the Tasko uppercase micro-label, actions right-aligned. Zero call-site edits required. |
| `Button` (ui) | Add `pill` prop or set default `rounded-full` for `default`/`outline` sizes used in headers/CTAs; primary = admiralty fill, hover deepens to `#16233a`; outline = white bg, sand border, admiralty text. **Gold is never a text-on-white color** (§7). |
| ~~Header search / mail `IconButton` / avatar chip~~ | **Descoped (v1.2, §5.1)** — new interactive controls; not built in this plan. The existing `NotificationBell` trigger is *restyled in place* (circular `size-9 rounded-full` ghost treatment, existing dot behaviour) without changing its component, handler, or position in the `actions` slot. |
| `CountBadge` | Small rounded-full badge (`bg-primary text-primary-foreground` on active pill; `bg-secondary text-foreground` otherwise). **Applied ONLY where a count already renders from live page props today** — `NavItem` has no count field ([navigation.ts:9-14](resources/js/types/navigation.ts:9)), so sidebar nav gets **no** badges in this plan (no new payload/props, and **never** static/fake counts — §5.1 rule 5). Dashboards may use it for counts they already receive. |
| `RadialProgress` | SVG donut: track = champagne **hatched pattern** (`<pattern>` diagonal lines, matching Tasko's striped unfilled arc), arc = gold→pacific by threshold, center % `text-3xl font-bold`. Used by portal/entrepreneur progress cards (adoption UI-5). |
| `MiniBarChart` | Thin wrapper for the inline SVG bar groups the dashboards already draw: rounded-top bars (`rx≈6`), colors **only** from `var(--chart-*)`, emphasis bar = `--chart-4` gold, footer stat strip slot. |

`components/ui/*` primitives (card, dialog, dropdown, table, tabs, sonner toasts) inherit the token flip
automatically — touch them only where a hardcoded neutral survives (grep in UI-6).

---

## 5. The shell (UI-2 — one shell, every role; v1.1 corrected)

There is **one** live shell (§2.3): `AppSidebar` + `AppSidebarHeader` + `ui/sidebar`. Restyling it restyles
advisor, admin, client portal, entrepreneur, NPO board, broker, and coach in a single WO.

- **`app-sidebar.tsx` + `ui/sidebar`:** white surface; brand mark + "Future Shift Advisory" lockup top
  (reuse `BrandMark`); group labels styled as Tasko section headers
  (`text-[11px] font-semibold uppercase tracking-[0.15em] text-muted-foreground`); menu buttons
  `rounded-full h-10 px-4` with `data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground`.
  **The footer keeps the existing `NavUser`/user-menu exactly as wired** — logout stays inside
  `UserMenuContent`, untouched (§5.1 rule 2). Collapse/mobile behaviour unchanged.
  **Includes the `hsl(var(...))` shadow rewrite at [sidebar.tsx:476](resources/js/components/ui/sidebar.tsx:476)** (§3.1 note).
- **`app-sidebar-header.tsx` (the live top bar — NOT `app-header.tsx`):** restyle **only what renders today**
  ([:17-42](resources/js/components/app-sidebar-header.tsx:17)): `SidebarTrigger`, `Breadcrumbs` (muted
  Tasko treatment), the brand band, and the `actions` slot carrying the existing `NotificationBell`
  ([app-sidebar-layout.tsx:20](resources/js/layouts/app/app-sidebar-layout.tsx:20)). **Tokenise the brand
  band** — `bg-[#2a3b5c]` → `bg-[var(--fs-commodore)]`, `text-[#d4a020]` → `text-[var(--gold)]`
  ([:20,31](resources/js/components/app-sidebar-header.tsx:20)); keep the `brandHeader` behaviour
  byte-identical. **Descoped (v1.2):** the Tasko search field, mail icon, and header avatar chip — they are
  *new interactive controls*, messages routes are **role-specific** (broker/coach have no messages nav), and
  a dead search box is worse than none. If wanted later, that's a separate WO gated on per-role link-parity
  evidence.
- **`auth/*`, `settings/layout`, `notifications-layout`:** live non-shell layouts — inherit tokens; small
  leftover-neutral sweeps (UI-6).
- **Dead shells are deleted, not styled (UI-4, decision §8.4):** `PortalLayout.tsx`,
  `ExternalPanelLayout.tsx`, `app-header.tsx` + `app-header-layout.tsx` — zero imports today; deleting them
  removes the trap of someone later "fixing" portal styling in a file nothing renders. Gate: `rg` proves
  zero imports before each delete; `tsc` green after.

### 5.1 Functional preservation — hard rules (v1.2; every WO, checked per PR)

1. **Restyle-only for interactive elements.** No new interactive control (link, button, input, menu) is
   added, and no existing one is removed or *relocated*, anywhere in this plan. A control may change
   appearance; its target, handler, method, and DOM identity (`data-test` attributes) stay identical.
2. **Logout is untouchable.** It ships inside `UserMenuContent` with a load-bearing sequence —
   `cleanup()` + `clearPortalOfflineQueue()` + `router.flushAll()`, posting the generated `logout()` route
   `as="button"` with `data-test="logout-button"`
   ([user-menu-content.tsx:23-27,51-62](resources/js/components/user-menu-content.tsx:23)). Reuse
   `NavUser`/`UserMenuContent` as-is; style wrappers only. **Never** reimplement logout as a plain link.
3. **Link/action inventory gate — AST-based, REQUIRED, per edited file (v1.4).** Regex capture is not
   sufficient for this codebase — two verified failure modes: **(a) named form variables** —
   `createForm.post(...)`, `replyForm.post(...)`, `revokeForm.patch(...)` etc. (23 call sites across 8
   files, **11 in `advisor/clients/Show.tsx`** — a UI-5 target) escape a `(router|form)\.` pattern; **(b)
   template-literal / multi-line hrefs** — `` href={`/x/${id}/preview`} `` (23 sites incl. broker/coach
   dashboards) defeat `href=\{[^}]+\}` because the `${...}` brace closes the match early, so `/preview` →
   `/download` passes unseen. **The required gate is an AST snapshot**, via a small committed script
   (`scripts/link-inventory.ts`, ts-morph or the TS compiler API, built once in **UI-0**) that emits, per
   file, the sorted full-expression list of:
   - JSX attributes `href`, `as`, `method`, `onClick`, `onSubmit` — **entire expression text**, template
     literals and multi-line included;
   - every `CallExpression` whose callee property is `post|get|patch|put|delete|submit|visit|reload|transform`
     — **any receiver** (`router`, `form`, `createForm`, `deleteForm`, …) — with full argument text;
   - `useForm` calls (type args + initialiser) and all import specifiers from `@/routes` / `@/actions`.
   Snapshot before and after each UI-2/UI-4/UI-5/UI-6 edit; **`diff` must be empty**. Intentional exceptions
   are named in the PR with the old/new expression and a reason. (A quick `rg` pre-check is fine as a
   developer convenience; it is **not** the gate.)
   - **Deletion exemption (v1.3):** deleted dead files (UI-4) are exempt from the inventory diff — their
     proof is different: repo-wide `rg` shows **zero imports** of the deleted module, `tsc` green, full
     ESLint + PHPUnit green, in UI-4's own PR. No other WO may delete files.
4. **Generated client modules are read-only for this plan.** `resources/js/routes/**` and
   `resources/js/actions/**` (Wayfinder output) stay byte-unchanged; do **not** run `wayfinder:generate` in
   any UI-* branch (this plan changes no PHP routes, so regeneration is never needed).
5. **No fake data, ever.** Visual affordances that imply data (counts, statuses, trends) render only from
   values that already exist in page props today — never placeholders/static numbers (AI-integrity: the UI
   must not assert what the system doesn't know).

---

## 6. Work Orders

| WO | Title | Deliverable |
|---|---|---|
| **UI-0** | **Pre-flight: clean generated-module baseline + gate tooling** (v1.3, hardened v1.4) | The worktree has **70 modified files** under `resources/js/routes/**` + `resources/js/actions/**` (symmetric 418+/418−, CRLF warnings — *likely* churn from a Windows Wayfinder run, but the diff also carries generated-comment/whitespace noise, so eyeballing is not proof). **Machine-check neutrality before reset/baseline (v1.4):** (1) `git diff --ignore-cr-at-eol -w -- resources/js/routes resources/js/actions` — empty ⇒ pure EOL/whitespace churn ⇒ `git checkout --` both dirs; (2) if non-empty, extract the **functional surface** per file from HEAD and worktree — export names + route `path`/`method`/`url` string literals (script or `rg -o "export (const\|function) \w+\|path: '[^']*'\|method: '[^']*'" \| sort`) — identical ⇒ still neutral ⇒ reset; genuinely different ⇒ commit as its own named baseline **outside** this plan's branches. Prevent recurrence with **two explicit `.gitattributes` lines**: `resources/js/routes/** text eol=lf` and `resources/js/actions/** text eol=lf` (the brace form `{routes,actions}` is not reliable gitattributes syntax) — or regenerate Wayfinder only in CI/WSL. **Also build the §5.1 rule-3 AST gate script (`scripts/link-inventory.ts`) here**, so it exists before the first styling edit. **Gate:** `git status` clean for both dirs + the neutrality check output attached to the UI-0 PR + `link-inventory.ts` runs against a sample page. |
| **UI-1** | Token flip + typography | Replace `:root`/`.dark` values per §3 (light + Meridian Night); `--radius: 1rem`; hoist `--fs-*` definitions to `:root` (public semantics untouched); `--gold`/`--gold-strong`; Outfit on body; shadow tokens **in `@theme`** (§3.3). **Gate:** app boots with warm canvas, no page edits; `tsc`, ESLint, Prettier, PHPUnit all green (no behaviour change). |
| **UI-2** | **The single authenticated shell** (all roles) | Tasko sidebar + top bar per §5 on `app-sidebar` / **`app-sidebar-header`** (the live top bar) / `ui/sidebar` — **restyling only controls that render today (§5.1)**: pill actives, section labels, muted breadcrumbs, restyled `NotificationBell` slot, footer `NavUser` untouched; **no search/mail/avatar-chip additions (descoped, §5)**; **`hsl(var(...))` shadow rewrite** ([sidebar.tsx:476](resources/js/components/ui/sidebar.tsx:476)); **brand-band tokenisation** ([app-sidebar-header.tsx:20,31](resources/js/components/app-sidebar-header.tsx:20)); §5.1 inventory gate on every touched file. One WO restyles every role's chrome. |
| **UI-3** | Shared primitives | `StatCard`, **existing `PageHeader` restyled backward-compatibly (prop contract unchanged)**, pill `Button`, `CountBadge` (existing-counts-only, §4), `RadialProgress`, `MiniBarChart` per §4 (plain components + type exports only; the header search/mail/avatar primitives are descoped, §4/§5.1). |
| **UI-4** | Dead-shell removal + live non-shell sweeps | **Delete** `PortalLayout.tsx`, `ExternalPanelLayout.tsx`, `app-header.tsx`, `app-header-layout.tsx` (zero imports — `rg` gate before each delete, `tsc` green after; decision §8.4); token-inherit sweeps on the live `auth/*`, `settings/layout`, `notifications-layout`. |
| **UI-5** | Dashboard adoption (**seven** dashboards) | Swap ad-hoc stat blocks/heroes to `StatCard`/`PageHeader`/`MiniBarChart`/`RadialProgress` on: advisor `Dashboard.tsx`, portal `Dashboard.tsx`, entrepreneur `Dashboard.tsx`/`Plan.tsx` headers, broker `Dashboard.tsx`, coach `Dashboard.tsx`, admin index, **and `portal/npo-board/Dashboard.tsx`** (`npo_board_member` lands there via [DashboardController.php:92](app/Http/Controllers/DashboardController.php:92); board-facing surface — visual swaps only, report-visibility rules untouched). First stat card on each = `inverted`. Inline SVG charts re-pointed at `var(--chart-*)`. **Purely mechanical swaps — no payload/logic edits — and the §5.1 link/action inventory gate runs per edited file** (these pages are dense with `href`/`download_url`/`view_url`/`connect_url`/`form.*` call sites; the before/after inventories must be identical). |
| **UI-6** | Hardcoded-style sweep + a11y audit | Grep-driven off the **§2.4 inventory (131 occurrences / 17 files)**: `advisor/templates/*` hex; **`--fs-*` usages outside `public/`** (portal Dashboard/onboarding/ProposalSignoff/StrategicPlanBudget/wellbeing/surveys, entrepreneur Dashboard — verify each renders correctly post-hoist); **semantic `slate-`/`gray-`/`zinc-` status classes** in broker/coach/portal/admin pages + `components/`; `WaterfallChart.tsx` → chart tokens; re-grep `hsl(var(` and raw hex in `components/` as the completeness gate; then the §7 contrast checklist against both modes. |
| **UI-7** | Visual QA + regression gate | Screenshot pass light+dark of every role's landing surface — the **nine user types plus the `npo_board_member` board dashboard**; `npm run types:check`, ESLint, Prettier, Pint, full PHPUnit; existing feature tests untouched and green. |

Sequencing: **UI-0 (pre-flight, blocking)** → UI-1 → UI-2/UI-3 (parallel) → UI-4 → UI-5 → UI-6 → UI-7. One
WO per branch/PR (`wo/UI-1-token-flip`, …). UI-1 alone already delivers ~70% of the visual change.

---

## 7. Accessibility (hard rules — carried into every WO)

- **Warm gold `#d4a020` is never body/label text on light surfaces** (≈2.2:1 — fails AA). Gold is for:
  fills behind dark text, large display numerals on admiralty, icons ≥24px, the emphasis chart bar, focus
  ring in dark mode. Text-on-white accent = `--gold-strong #b8860b` **at ≥18px semibold only**; otherwise
  cognac `#8b6c42` or graphite.
- AA pairs to verify (axe or manual): admiralty on parchment (≈12:1 ✓), parchment on admiralty (✓), graphite
  on parchment (≈5.5:1 ✓), graphite on white (✓), admiralty on champagne (✓), champagne on `#101c2c` (✓).
- Focus visible everywhere: pacific ring on light, gold ring on dark, `focus-visible:ring-2 ring-offset-2`.
- The hatched `RadialProgress` track must not be the only progress signal — keep the numeric % label.
- Respect `prefers-reduced-motion` for any hover/transition added to cards/pills.

---

## 8. Owner-flagged decisions (defaults chosen; flip is cheap)

1. **Dark mode "Meridian Night" mapping (§3.2)** — shipped in UI-1 behind the existing appearance toggle;
   owner eyeballs it in the test env. Fallback if disliked: temporarily pin appearance to light for
   authenticated apps (one-line) while the mapping is tuned. *(Removing dark mode entirely is **not** the
   default — it exists today and silently degrading it would be a regression.)*
2. **App headings = bold Outfit** (Tasko-faithful), serif stays marketing-only. Flip: apply `font-display`
   to `PageHeader` titles.
3. **First-card-inverted** on each dashboard defaults to the leftmost/primary KPI. If the owner prefers a
   specific KPI highlighted per role (e.g. advisor = "Active clients"), specify per dashboard in UI-5's PR.
4. **Shell consolidation (v1.1 — answers the review's open question):** `AppLayout`/`AppSidebar` is treated
   as **the single authenticated shell** for portal, broker, and coach — that is what the resolver already
   does ([app.tsx:18-38](resources/js/app.tsx:18)). The dormant `PortalLayout`/`ExternalPanelLayout`/
   `app-header*` files are **deleted in UI-4**, not kept as dormant code and not styled: keeping them invites
   the exact trap this review caught (styling a file nothing renders), and deleting unimported files is a
   zero-behaviour change guarded by the `rg`/`tsc` gate. If a future phase wants a *different* portal shell,
   that's a new decision made then — not dead weight carried now.

---

## 9. Out of scope (explicit)

- Public marketing site (`.public`) — already Meridian; untouched.
- Browsershot PDF/report templates and email templates.
- Any navigation *structure* change (no items added/removed/reordered), route, controller, payload, RLS,
  audit, or AI-surface change.
- **The Wayfinder-generated client modules** — `resources/js/routes/**` and `resources/js/actions/**` are
  the source of every typed hyperlink/form endpoint and stay **byte-unchanged**; no `wayfinder:generate`
  runs in any UI-* branch (§5.1 rule 4).
- New interactive controls in the shell (search, mail, header avatar chip) — descoped per §5; separate WO if
  wanted later.
- New icon set (lucide stays), new fonts beyond those already imported at app.css:5.
- The `fsa-responsive-table` mobile pattern keeps its behaviour (inherits tokens only).

---

## 10. Risks / honesty

- **Token flip blast radius:** ~550 files restyle at once; the win and the risk are the same thing. UI-1's
  gate is the full test suite + a click-through of each role. Anything that "looks wrong" after UI-1 is a
  hardcoded style — feed it into UI-6's grep list rather than hotfixing pages ad-hoc.
- **The six giant page files** (`advisor/clients/Show.tsx` ~6.0k lines, `advisor/Dashboard.tsx` ~4.1k,
  `portal/entrepreneur/Plan.tsx` ~4.0k…) make UI-5 the riskiest WO — mechanical swaps only, no refactors of
  those files inside this plan (a decomposition effort is a separate, future plan; **do not** bundle it).
- **Contrast regressions** are the most likely review finding — hence §7 as hard rules, checked per-WO, not
  once at the end.
- **Charts:** colors are scattered through inline SVG in the big pages; UI-5/UI-6's grep
  (`fill=`, `stroke=`, `text-emerald`, `bg-blue`, hex) is the completeness tool — same pattern as the plans'
  earlier consumer-map greps.

## 11. Acceptance (UI-7 gate)

Every role's shell (super admin, advisor, junior advisor, entrepreneur mentor, client primary, client team,
entrepreneur, broker, coach — **plus the `npo_board_member` board dashboard**) renders the Tasko language in
Meridian Warm, light **and** dark; zero behaviour diffs (`tsc`, ESLint, Prettier, Pint, full PHPUnit green;
no controller/route/payload changes in any diff); §7 checklist passes; the **§2.4 inventory is swept clean**
(re-grep `#hex|--fs-|slate-|gray-|zinc-|hsl\(var\(` over `resources/js` excluding `public/` returns only
intentional survivors, listed in the UI-6 PR); the dead shells are gone (`rg PortalLayout|ExternalPanelLayout|app-header`
finds no imports); **the §5.1 AST inventory diffs (`scripts/link-inventory.ts`) are empty for every edited
file** (deleted
files use the UI-4 zero-import proof instead; exceptions named + justified per PR with old/new expressions);
**`git diff` against the UI-0 baseline shows zero changes under `resources/js/routes/` and
`resources/js/actions/`** across all UI-* branches (UI-0 must land first — the gate is meaningless from
today's dirty worktree); logout still posts via `UserMenuContent` with `data-test="logout-button"` intact;
screenshots attached to the UI-7 PR.

---

*Companion to the repo's plan set (PLAN.md WOs, PLAN-ENTREPRENEUR-*.md). Produced at owner request — "restyle
to the attached reference, keep Meridian Warm."*
