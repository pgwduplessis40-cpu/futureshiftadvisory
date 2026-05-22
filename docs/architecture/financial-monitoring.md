# Continuous Financial Health Monitoring

WO-38 turns connected accounting snapshots into early-warning financial health alerts. It uses the WO-37 accounting connection and snapshot contracts, then compares consecutive snapshots for material deterioration.

## Runtime Shape

`financial-monitoring:run` is the operational entry point. It accepts a `--cadence` label of `daily` or `weekly`, plus `--force` for tests and manual operator runs.

The command is gated by:

- `FEATURE_CONTINUOUS_MONITORING`

When enabled, the scheduler runs a daily pull at 04:00 and a weekly pull on Monday at 04:30. When disabled, the command exits successfully without pulling snapshots unless `--force` is supplied.

## Monitoring Flow

`HealthMonitor` applies the system RLS context, scans connected and non-revoked `accounting_connections`, and pulls a fresh `financial_snapshots` row through `FinancialSnapshotPuller`.

For each connection, it compares the new snapshot with the latest prior snapshot for that same accounting connection. First snapshots do not raise alerts because there is no baseline.

Pull failures are audited as `financial_monitoring.pull_failed`; a completed run is audited as `financial_monitoring.completed` with connection, snapshot, alert, and failure counts.

## Deterioration Rules

The monitor currently raises deterministic threshold alerts for:

- revenue drop
- net profit drop
- operating cash flow drop, or current operating cash flow below zero
- gross margin percentage-point drop
- current ratio falling below the configured floor and decreasing from the prior snapshot

Thresholds live under `integrations.accounting.monitoring`:

- `FINANCIAL_MONITOR_REVENUE_DROP_THRESHOLD`
- `FINANCIAL_MONITOR_NET_PROFIT_DROP_THRESHOLD`
- `FINANCIAL_MONITOR_CASH_FLOW_DROP_THRESHOLD`
- `FINANCIAL_MONITOR_GROSS_MARGIN_DROP_POINTS`
- `FINANCIAL_MONITOR_CURRENT_RATIO_FLOOR`

These rules are intentionally factual and bounded. WO-38 does not produce the narrative financial analysis owned by WO-44.

## Alerts And Citations

Each alert is stored in `financial_alerts` with an idempotent `alert_key` based on client, provider, connection, previous snapshot, current snapshot, and metric.

The `citation` JSON stores the exact metric path and source references for both snapshots, for example:

- `financial_snapshot:{id}:profit_and_loss.net_profit`

The human-readable detail also cites the previous value, previous period end, current value, and current period end. Historical snapshots remain append-only; alerts point back to immutable source rows.

## Notification Routing

New alerts notify super-admin users and advisor users on the relevant `client_team`. `FinancialAlertNotification` extends `ChannelAwareNotification`, so delivery always runs through `ChannelResolver` and records the routing decision in the notification ledger.

Financial health alerts use normal urgency. They respect user channel and digest preferences rather than bypassing them.
