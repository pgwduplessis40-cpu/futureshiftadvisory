# Brand assets — Meridian Warm

This folder holds the visual identity for Future Shift Advisory. The brand system is referenced as "Meridian Warm" across the spec.

## Status

**Placeholder.** Owner-supplied brand assets are not yet present in this folder. The build will run against the default shadcn/ui neutral palette until these are dropped in. Once the kit is present, WO-01 acceptance flips from "scaffolded with placeholder tokens" to "Meridian Warm tokens active across all surfaces."

## Required files

When dropping the brand kit into this folder, please include:

### Colour
- `meridian-warm-palette.json` — the full token set in JSON (primary, accents, semantic, surface, text, border). Example shape:
  ```json
  {
    "primary": { "DEFAULT": "#...", "foreground": "#..." },
    "meridian": { "warm": "#...", "deep": "#...", "sand": "#...", "ink": "#..." },
    "semantic": { "success": "#...", "warning": "#...", "danger": "#...", "info": "#..." }
  }
  ```
- `meridian-warm-palette.pdf` — visual palette reference for humans.

### Typography
- `typography.md` — primary typeface, secondary typeface, weights used, fallback stack, license terms.
- Font files (woff2 preferred) under `fonts/` if self-hosted.

### Logo and marks
- `logo-primary.svg` — primary horizontal lockup.
- `logo-mark.svg` — square mark (favicon source).
- `logo-mono-light.svg` and `logo-mono-dark.svg` — monochrome variants for stamped contexts (PDFs, footers).
- `favicon.ico`, `favicon-32.png`, `favicon-16.png`, `apple-touch-icon.png`.
- `logo-usage.md` — clear-space rules, minimum size, do/don't.

### Imagery and motifs
- `imagery-guidelines.md` — photo style, illustration style, gradient usage.
- Any reusable SVG motifs under `motifs/`.

### Voice and tone
- `voice-and-tone.md` — written brand voice notes for UI copy, error messages, marketing tone.

## How these get wired in

Once present, a follow-up WO will:

1. Convert `meridian-warm-palette.json` into CSS variables in `resources/css/app.css`.
2. Override the shadcn theme tokens so all primitives pick up Meridian Warm without rewrites.
3. Wire the typography into Tailwind's `fontFamily` config.
4. Place logo files into `public/brand/` and reference them from `BrandShell.tsx`.
5. Generate the favicon set and reference from `resources/views/app.blade.php`.

Until then, all UI uses the neutral defaults from `components.json`.

## Confidentiality

These assets are Future Shift Advisory's proprietary visual identity. Do not commit derivative work or test files using these assets to any public repository.
