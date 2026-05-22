# SWOT / TOWS / MAPS Module

WO-47 adds strategic matrix assembly and a Phase 2 analysis adapter on the shared
analysis spine.

## Matrix Assembly

`StrategicMatrixAssembler` builds a deterministic matrix payload from:

- questionnaire answers
- recent governed analysis findings
- top ranked improvement opportunity PV
- top ranked risk-cost PV

It returns SWOT, TOWS, MAPS, PV summary, and source attributions. The assembler
does not call AI and does not persist rows; it prepares cited evidence for the
module and for future UI/report surfaces.

## Analysis Module

`StrategicMatrices` implements `AnalysisModule` with prompt id
`analysis.strategic_matrices` and module enum `swot`.

The module emits four findings:

- SWOT matrix
- TOWS matrix
- MAPS matrix
- PV-referenced strategic priority

When a top PV improvement or risk exists, the prescriptive finding writes
`analysis_findings.pv_link_id` to that PV row id so downstream waterfall/report
surfaces can reconcile the strategic priority with PV.

## UI Component

`resources/js/components/analysis/StrategicMatrix.tsx` provides a compact
two-column matrix renderer for future advisor/report surfaces. WO-47 does not add
a route or page placement; it supplies the reusable component and backend shape.

## Boundaries

WO-47 adds no schema. It does not create new PV calculations; it only references
PV rows produced by WO-42 or later analysis modules.
