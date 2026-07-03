# PLAN — Authenticated App UI Restyle ("Tasko" language × Meridian Warm)

**Plan version:** 1.0 — owner direction + code-grounded design pass. *(Build target: Codex, into the test env, then push to live.)*

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
3. **Two shell seams cover every authenticated screen:**
   - **Advisor/admin:** `AdvisorLayout` is a thin wrapper ([AdvisorLayout.tsx:14-18](resources/js/layouts/AdvisorLayout.tsx))
     over `app-layout` → `app-sidebar-layout` → the shadcn starter shell (`app-sidebar.tsx`, `app-header.tsx`,
     `app-shell.tsx`, `app-content.tsx`, `app-sidebar-header.tsx` + `components/ui/sidebar`).
   - **Portal (client + entrepreneur):** `PortalLayout.tsx` — custom sidebar already structured as
     `NavSection { label, items }` ([PortalLayout.tsx:29-38](resources/js/layouts/PortalLayout.tsx)) — i.e.
     the Tasko `MENU`/`GENERAL` section pattern already exists structurally; it needs the *visual* treatment.
   - Plus `ExternalPanelLayout` (broker/coach), `auth/*` layouts, `settings/layout`, `notifications-layout`.
4. **Propagation is cheap:** across all of `resources/js/pages`, only **5 files** contain hardcoded hex
   colors — 3 are `public/*` (keep as-is), 2 are `advisor/templates/*` (sweep in UI-6). Everything else uses
   theme classes (`bg-background`, `text-muted-foreground`, `border-border`…), so the token flip restyles
   ~550 files for free.
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
`.public` to `:root` so both scopes share one source; `.public`'s semantic mappings stay scoped). Add one new
semantic token used by stat cards & CTAs: `--gold: #d4a020; --gold-strong: #b8860b;`.

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
  and a stronger hover variant. Flat design otherwise: **no gradients** in the app shell.
- Spacing: cards `p-5`/`p-6`; page gutter `px-6 lg:px-8`; card grid `gap-4 lg:gap-5`.

---

## 4. Shared primitives (new/updated components — UI-3)

All in `resources/js/components/` using existing shadcn/cva conventions; **presentation-only props**.

| Component | Spec (Tasko → Meridian) |
|---|---|
| `StatCard` | Props: `title, value, footnote, trend ('up'\|'down'\|'flat'), inverted?, icon?, href?`. White card `rounded-[1.25rem] border-border shadow-card`; **`inverted` variant: `bg-primary text-primary-foreground`** with gold corner icon-button; value `text-4xl font-bold tracking-tight`; footnote `text-xs text-muted-foreground` with trend arrow. Replaces the ad-hoc stat blocks on the dashboards (adoption in UI-5). |
| `PageHeader` | `title, subtitle, actions` slot. `text-3xl font-bold` + `text-sm text-muted-foreground`; actions right-aligned; primary action uses the `rounded-full` Button. |
| `Button` (ui) | Add `pill` prop or set default `rounded-full` for `default`/`outline` sizes used in headers/CTAs; primary = admiralty fill, hover deepens to `#16233a`; outline = white bg, sand border, admiralty text. **Gold is never a text-on-white color** (§7). |
| `Input`/search | Header search: `rounded-full bg-card border-border` with `⌘K` kbd chip (wire to the existing search/nothing new — visual only). |
| `IconButton` | Circular `size-9 rounded-full` ghost (mail/bell); optional notification dot (destructive). |
| `CountBadge` | Small rounded-full badge for nav counts (`bg-primary text-primary-foreground` when on active pill; `bg-secondary text-foreground` otherwise). |
| `RadialProgress` | SVG donut: track = champagne **hatched pattern** (`<pattern>` diagonal lines, matching Tasko's striped unfilled arc), arc = gold→pacific by threshold, center % `text-3xl font-bold`. Used by portal/entrepreneur progress cards (adoption UI-5). |
| `MiniBarChart` | Thin wrapper for the inline SVG bar groups the dashboards already draw: rounded-top bars (`rx≈6`), colors **only** from `var(--chart-*)`, emphasis bar = `--chart-4` gold, footer stat strip slot. |

`components/ui/*` primitives (card, dialog, dropdown, table, tabs, sonner toasts) inherit the token flip
automatically — touch them only where a hardcoded neutral survives (grep in UI-6).

---

## 5. Shells (UI-2 advisor/admin · UI-4 portal/external)

- **`app-sidebar.tsx` + `ui/sidebar` (advisor/admin):** white surface; brand mark + "Future Shift Advisory"
  lockup top (reuse `BrandMark`); group labels styled as Tasko section headers
  (`text-[11px] font-semibold uppercase tracking-[0.15em] text-muted-foreground`); menu buttons
  `rounded-full h-10 px-4` with `data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground`;
  count badges via `CountBadge`; Logout pinned to footer group. Collapse/mobile behaviour unchanged.
- **`app-header.tsx`:** the Tasko top bar — search (visual ⌘K chip), `IconButton` mail→messages / bell→
  existing `NotificationBell`, avatar chip (name + email, existing user menu trigger). Breadcrumbs stay,
  restyled muted.
- **`PortalLayout.tsx`:** apply the same treatment to its custom sidebar (it already has the
  section/label structure — style only): white sidebar, pill active states, section labels, footer logout;
  keep the offline-queue and service-switcher logic byte-identical.
- **`ExternalPanelLayout`, `auth/*`, `settings/layout`, `notifications-layout`:** inherit tokens; small
  sweeps for leftover neutral utility classes (part of UI-6).

---

## 6. Work Orders

| WO | Title | Deliverable |
|---|---|---|
| **UI-1** | Token flip + typography | Replace `:root`/`.dark` values per §3 (light + Meridian Night); `--radius: 1rem`; hoist `--fs-*` definitions to `:root` (public semantics untouched); `--gold`/`--gold-strong`; Outfit on body; shadow tokens. **Gate:** app boots with warm canvas, no page edits; `tsc`, ESLint, Prettier, PHPUnit all green (no behaviour change). |
| **UI-2** | Advisor/admin shell | Tasko sidebar + top bar per §5 on `app-sidebar`/`app-header`/`ui/sidebar`; pill actives, section labels, badges, avatar chip. |
| **UI-3** | Shared primitives | `StatCard`, `PageHeader`, pill `Button`, `IconButton`, `CountBadge`, `RadialProgress`, `MiniBarChart` per §4, each with a small story-style demo route **not** registered (plain component + type exports only). |
| **UI-4** | Portal + external shells | `PortalLayout` (client + entrepreneur) and `ExternalPanelLayout` (broker/coach) restyled to the same language; auth/settings/notifications sweeps. |
| **UI-5** | Dashboard adoption | Swap ad-hoc stat blocks/heroes on the six dashboards (advisor `Dashboard.tsx`, `DashboardController` payload untouched; portal `Dashboard.tsx`; entrepreneur `Dashboard.tsx`/`Plan.tsx` headers; broker/coach dashboards; admin index) to `StatCard`/`PageHeader`/`MiniBarChart`/`RadialProgress`. First stat card on each dashboard = `inverted`. Inline SVG charts re-pointed at `var(--chart-*)`. **Purely mechanical swaps — no payload/logic edits.** |
| **UI-6** | Hardcoded-color sweep + a11y audit | Grep-driven: the 2 `advisor/templates/*` hex files, any `bg-gray-*/slate-*/zinc-*` leftovers in `components/` + `pages/` (excluding `public/`), `WaterfallChart.tsx` colors → chart tokens; then the §7 contrast checklist against both modes. |
| **UI-7** | Visual QA + regression gate | Screenshot pass of every role's shell (9 user types' landing screens) light+dark; `npm run types:check`, ESLint, Prettier, Pint, full PHPUnit; existing feature tests untouched and green. |

Sequencing: UI-1 → UI-2/UI-3 (parallel) → UI-4 → UI-5 → UI-6 → UI-7. One WO per branch/PR
(`wo/UI-1-token-flip`, …). UI-1 alone already delivers ~70% of the visual change.

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

---

## 9. Out of scope (explicit)

- Public marketing site (`.public`) — already Meridian; untouched.
- Browsershot PDF/report templates and email templates.
- Any navigation *structure* change (no items added/removed/reordered), route, controller, payload, RLS,
  audit, or AI-surface change.
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
entrepreneur, broker, coach) renders the Tasko language in Meridian Warm, light **and** dark; zero behaviour
diffs (`tsc`, ESLint, Prettier, Pint, full PHPUnit green; no controller/route/payload changes in any diff);
§7 checklist passes; the 2 template-page hex files and `WaterfallChart` use tokens; screenshots attached to
the UI-7 PR.

---

*Companion to the repo's plan set (PLAN.md WOs, PLAN-ENTREPRENEUR-*.md). Produced at owner request — "restyle
to the attached reference, keep Meridian Warm."*
