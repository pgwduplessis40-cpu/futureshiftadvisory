# Improvement And Risk PV

WO-42 implements PV Type 2 and PV Type 3 on top of the WO-40 engine.

## Improvement Opportunities

`ImprovementPv` accepts measurable opportunities with:

- title
- annual benefit
- duration in years
- optional originating `analysis_finding_id`
- source reference

It writes one `pv_calculations` row per opportunity, then persists an `improvement_opportunities` row with `pv_of_impact`. Opportunities are ranked descending by PV impact.

## Risk Costs

`RiskCostPv` accepts measurable risks with:

- title
- financial impact
- probability
- duration in years
- optional NZ statutory penalty range
- optional originating `analysis_finding_id`
- source reference

When a statutory range is present, the service uses the midpoint of that range if it is higher than the supplied financial impact. The annual expected cost is:

```text
applied_impact * probability
```

The expected annual cost is discounted through `PvEngine` and persisted as `pv_of_cost`. Risks are ranked descending by PV cost.

## Finding Linkage

Both tables can link to `analysis_findings`. Later analysis modules write or update `analysis_findings.pv_link_id` when they want a finding card to reference a produced PV row.

## Boundaries

WO-42 calculates and ranks measured opportunities/risks only. Dashboard/report surfacing and waterfall composition are WO-43.
