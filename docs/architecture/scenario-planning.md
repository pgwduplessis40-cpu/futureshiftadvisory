# Scenario planning

WO-53 adds a deterministic scenario-planning surface for up to five named
best/expected/worst/custom scenarios per planning run.

## Flow

`ScenarioPlanner` creates an `analysis_runs` row with module `scenario` before it
writes any scenario rows. The service uses the shared data-quality scorer and
document-verification gate; insufficient data creates a blocked analysis run and
does not persist scenarios.

For each accepted scenario, the service:

- normalises the advisor-supplied name, kind, visibility, and assumptions;
- snapshots the latest OCR, CPI, GDP, and unemployment indicators;
- derives an economic growth overlay from CPI/GDP unless the scenario supplies a
  growth rate;
- routes annual or explicit cash flows through `PvEngine`;
- persists the resulting `pv_calculation_id` and `pv_impact` on `scenarios`.

Because WO-40 deliberately has only three PV ledger types, scenario impacts use
the shared `improvement_opportunity` PV calculation type as a scenario delta.
They do not create `improvement_opportunities` rows, so WO-43 waterfall totals
are not silently changed by hypothetical scenarios.

## Client visibility

`scenarios.is_client_visible` controls portal exposure. The client portal reads
only visible scenarios for the resolved client and renders a read-only list with
scenario name, kind, PV impact, and the applied economic overlay. The advisor
dashboard gets a scoped summary and the latest scenario rows across the
advisor-visible client set.

## RLS

`scenarios` is client-scoped. PostgreSQL RLS allows `system`/`super_admin` or
rows whose `client_id` is present in `fsa_current_client_ids()`. The WO-53 tests
exercise cross-client isolation under a non-bypass role when needed.
