# Meridian Warm brand system

`resources/css/app.css` is the implementation source for Meridian Warm. This
document is the review contract for its public-facing use. It replaces the old
placeholder-asset guidance: the tokens below are active today.

## Palette

| Role | Token | Value | Use |
| --- | --- | --- | --- |
| Authority text and navigation | `--fs-admiralty` | `#1c2b45` | Headings, primary text, navigation |
| Raised navy | `--fs-commodore` | `#2a3b5c` | Dense dark surfaces only |
| Information | `--fs-harbour` | `#1b5070` | Informational detail and links |
| Interactive accent | `--fs-pacific` | `#0d7a7a` | Primary actions and focus-safe accents |
| Success | `--fs-deep-cove` | `#0d6a5a` | Positive state, never as body text on dark backgrounds |
| Premium accent | `--fs-warm-gold` | `#d4a020` | Rules, small highlights, not paragraph text |
| Reserved accent | `--fs-antique-gold` | `#b8860b` | Gold emphasis where contrast is verified |
| Warm accent text | `--fs-cognac` | `#8b6c42` | Eyebrows and muted emphasis |
| Accent surface | `--fs-champagne` | `#e8d5a0` | Small, non-semantic highlights |
| Page surface | `--fs-parchment` | `#f9f6f0` | Public page background |
| Secondary surface | `--fs-linen` | `#f0ead8` | Grouping and quiet sections |
| Border | `--fs-sand` | `#e0d8cc` | Dividers and low-emphasis borders |
| Muted text | `--fs-graphite` | `#5a6a7a` | Supporting copy only |

Use the semantic aliases (`--fs-bg`, `--fs-bg-elevated`, `--fs-text`,
`--fs-text-muted`, `--fs-text-accent`, `--fs-border`) in components. Raw colour
tokens are only for the documented exceptions above. Meridian Warm is scoped to
`.public`; authenticated product surfaces continue to use their application
theme unless a reviewed design change explicitly expands the scope.

## Typography

- Body and controls: `Outfit`, then the system sans-serif stack; 400 by default.
- Display headings: `DM Serif Display` through `.font-display`; 400, with the
  existing slight negative tracking.
- Editorial accent only: `Cormorant Garamond` through `.font-accent`; never use
  it for navigation, form labels, tables, or dense operational content.
- Eyebrows use `.eyebrow`: 11px, 600, uppercase, 0.15em tracking, cognac.

Headings carry hierarchy through size, weight, and spacing; colour alone must
not signal importance. Keep body copy at a legible size and maintain sufficient
contrast against parchment, linen, and elevated white surfaces.

## Usage rules

1. Use Pacific for a primary action only when its focus and disabled states are
   visible. Do not make gold the primary button colour.
2. Use `.gold-rule` for a short decorative divider, never to communicate a
   required status or validation outcome.
3. Keep at least one text label for an icon-only interaction through an
   accessible name. Checkbox fields require a form `name` and associated label;
   use `AccessibleCheckboxField` for new forms.
4. Do not introduce one-off hex values, fonts, or raw type scale values on a
   public screen. Add a reviewed token first if the existing system is missing a
   semantic role.
5. Preserve the separation between semantic status colours and brand accents:
   error, warning, and success always need text/icon support in addition to
   colour.

## Compliance evidence

Approved authenticated-browser screenshots in `e2e/snapshots/` are the visual
compliance record. The browser CI gate captures the same named flows at 1440px
and 390px, runs axe and keyboard/overflow checks, and fails on any pixel change
until a reviewed baseline is committed. A screenshot is evidence of the exact
route, viewport, role, and build it represents; it is not a substitute for the
token and accessibility rules above.
