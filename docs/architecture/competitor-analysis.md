# Competitor Analysis Module

WO-46 adds the Phase 2 competitor analysis adapter on the shared analysis spine.

## Module Shape

`CompetitorAnalysis` implements `AnalysisModule` with prompt id
`analysis.competitor`. It supplies competitor evidence to the spine; the shared
runner still owns data quality, document verification, attribution validation,
bias inspection, audit, and finding persistence.

The module emits four lenses:

- Descriptive: the named competitor set under review.
- Diagnostic: product/service, pricing, and visibility gaps.
- Predictive: lead-flow and positioning risk from competitor visibility.
- Prescriptive: side-by-side product, pricing, and visibility action plan.

## Competitor Bound

The spec caps competitor analysis at six competitors. WO-46 enforces that in the
module input mapper, before prompt construction. Additional supplied competitors
are ignored for the run rather than sent to AI.

## Evidence And Citations

Competitor evidence comes from questionnaire answers and is cited as:

- `questionnaire_answer:{id}`

If no competitor-specific evidence exists, the module can still identify the
client as the analysis subject with `client:{id}`, but useful gap analysis depends
on the supplied competitor list.

## Boundaries

WO-46 does not perform live competitor monitoring, scraping, paid-search checks,
or cross-client industry alerts. Those are outside Phase 2 or later roadmap
items. WO-46 adds no schema.
