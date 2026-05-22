# Regulatory Change Impact Assessment

WO-51 turns governed legislative-currency candidates from WO-50 into
client-specific regulatory impact findings.

## Assessor

`RegulatoryImpactAssessor` accepts a `Client` and a `LearningUpdate` legislative
candidate. It creates a completed `analysis_runs` row with module
`regulatory_impact`, then writes a prescriptive `analysis_findings` row containing:

- the legislative-change summary
- estimated financial impact
- probability and duration
- compliance actions recorded directly on the finding body
- source attribution back to the `learning_update`

## PV Linkage

The assessor routes financial exposure through the existing WO-42 `RiskCostPv`
service. The generated risk row links back to the regulatory-impact finding, and
the finding stores the risk id in `analysis_findings.pv_link_id`.

This keeps regulatory-impact PV in the same waterfall/report data path as other
risk-cost rows.

## Boundaries

WO-51 does not create a milestones/action-item table. Phase 2 records recommended
actions on the finding text. Phase 3 can lift those actions into a tracker.
