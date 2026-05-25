# Dashboard Interactivity

The Tier 1 dashboard widgets use fixed, owner-tunable configuration in
`config/dashboards.php`. They are display/read-model layers only; they do not
learn from user behaviour or change analysis outputs.

## Config Knobs

### Engagement Score

`dashboards.engagement.weights` controls the weighted score components:
questionnaire completion, verified documents, milestones on track, and
communication recency. The weights should sum to 1.0.

`dashboards.engagement.thresholds` controls the green/amber/red bands, and
`dashboards.engagement.comms_decay_days` controls how quickly stale
communication loses score.

### Business Health Radar

`dashboards.radar.severity_weights` maps `FindingSeverity` values to fixed
loads: info, low, medium, high, and critical. `dashboards.radar.load_cap`
controls the cap used by:

```text
score = clamp(0, 100, 100 - round(100 * severity_load / load_cap))
```

`dashboards.radar.dimensions` maps the five client-portal dimensions to real
`AnalysisModule` values. Strategic is intentionally mapped from `swot`,
`competitor`, and `website_audit`; there is no `strategic` analysis module.

Radar snapshots are materialised only by explicit recompute:

```bash
php artisan fsa:recompute-health-radar {client?}
```

The advisor client page also exposes the same recompute action. Snapshot data is
client-safe before storage: `AnalysisLens::Prescriptive` findings are excluded
from the score, top finding, contributing finding ids, and source attributions.

### Economic Exposure

`dashboards.economic_exposure` defines supported exposure drills. CPI is
supported for all active clients. OCR is supported from configured debt paths in
`financial_snapshots`. Wage and FX exposure remain unavailable until the client
model stores the needed classification/trade data.

## Drill Contract

Dashboard drill links use query parameters rather than hidden UI state:

- `?focus=<section-key>` scrolls to `id="section-<section-key>"`.
- `&highlight=<element-id>` scrolls to a specific stable element id inside that
  section.
- Advisor client analysis drill: `/advisor/clients/{client}?focus=analysis&highlight={findingId}`.
- Portal radar drill: `/portal?focus=health&highlight=health-financial`.
- Economic exposure drill: `/advisor/clients?exposed_to=ocr` or
  `/advisor/clients?exposed_to=cpi`.

Every hover trigger is a real keyboard-focusable control, and every drill target
is a link or button with a concrete destination. Hover text must cite the data
that drove the score or plainly state that the data is unavailable.
