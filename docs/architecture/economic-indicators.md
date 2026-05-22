# NZ economic indicators

WO-36 turns the RBNZ, Stats NZ, and MBIE integration scaffolds into the Phase 2 economic feed used by later PV, scenario, and market-current analysis work.

## Integration clients

The active clients are:

- `RbnzClient` for OCR and exchange rates
- `StatsNzClient` for CPI, GDP, and unemployment
- `MbieClient` for minimum wage and living wage rates

Each client follows the WO-13/WO-05 pattern:

- `FEATURE_*_LIVE=false` resolves to deterministic fixture data.
- Live mode calls through `ResilientHttp`.
- Missing credentials use a disabled endpoint through `ResilientHttp`, so failures are logged and cached data or fixture fallback is returned.
- Returned rows carry `source`, `source_badge`, `degraded`, and optional `correlation_id`.

The fixture data is intentionally small and stable. It is not a canonical economic source; it is the deterministic contract for tests and local degraded mode.

## Storage

`economic_indicators` stores one row per indicator, period date, and source. `exchange_rates` stores one row per currency pair, rate date, and source. The refresh path uses `updateOrCreate`, so rerunning the same source period updates fetch metadata without duplicating facts.

The tables are global reference data, not client-scoped data, so they do not use client RLS policies.

## Refresh cadence

`economic-indicators:refresh` fetches all configured indicators and rates, persists them, records a `learning_layer_runs` row with layer id `12`, and audits `economic_indicators.refreshed`.

The scheduler runs the command daily at 03:30, after the Phase 2 feedback and bias learning layers.

## Governed candidates

An OCR value change creates one governed `learning_updates` row:

- `layer_id=12`
- `source.type=economic_indicator_auto_update`
- `proposed_change.action=review_pv_discount_rate_assumptions`
- `proposed_change.automatic_application=false`
- `status=detected`

This is a review signal only. WO-36 does not apply discount-rate assumptions, alter PV outputs, or implement WACC automation. Those remain later Phase 2/Phase 4 work.

## Dashboard surface

The advisor dashboard shows the latest stored indicators, NZD exchange rates, source badges, degraded/fallback state, and any open OCR-change review candidates. It reads persisted rows only; page rendering does not call live integrations.
