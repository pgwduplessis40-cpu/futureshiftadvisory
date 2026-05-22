# Bias monitoring

WO-33 extends the Phase 1 bias primitives into a Phase 2 monitoring layer for analysis output.

## Per-output inspection

`AnalysisRunner` now calls `BiasDetector` after source attribution validation and before findings are mapped. This makes every persisted analysis finding carry the response bias signals, even in tests or internal callers that bind a direct `AiClient` and bypass the production `IntegrityCheckedAiClient` wrapper.

The per-analysis call writes `ai.bias_assessed` to the log and immutable audit trail with analysis run, client, and module metadata. It does not create a learning candidate by itself; the Phase 1 heuristic candidate path remains available for the generic AI client wrapper.

## Systematic monitor

`BiasMonitor` is exposed through:

```pwsh
php artisan analysis:bias-monitor
```

The scheduler runs it daily at 03:15, after the feedback-learning layer. Each run records a `learning_layer_runs` row with layer id `3`, the rolling window, minimum cohort size, skew threshold, candidate count, and status.

The monitor scans recent `analysis_findings` by module and compares high/critical severity rates across available client cohorts:

- `entity_type`
- `engagement_type`
- `gst_registered`

When one cohort and its baseline both meet the minimum finding count, and the cohort's high-severity rate exceeds the baseline by the configured threshold, the monitor creates one governed `learning_updates` candidate in `detected` status. The candidate proposes human review of module bias or calibration and sets `automatic_application` to `false`.

## Alerts and idempotency

Each new systematic signal sends an urgent channel-aware notification to super-admins and advisors on the affected client team. The notification is intentionally alert-only: no finding is edited, hidden, downgraded, or corrected.

Idempotency is keyed by module, cohort dimension, cohort value, and metric while a detected candidate remains open. Re-running the monitor over the same pattern records another layer run with zero candidates instead of creating duplicates.
