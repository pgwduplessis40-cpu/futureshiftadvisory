# Population Intelligence

WO-108 starts the population-intelligence track with anonymised cross-client
industry signals. Every release path uses `CohortGuard`, so cohorts below
`privacy.min_cohort` are suppressed and aggregate outputs strip identifiers,
per-entity values, and min/max extremes.

`intelligence:cross-client` scans recent analysis findings, groups repeated
patterns by industry, and writes `industry_intelligence_signals`. Eligible
signals notify affected advisors once, but the persisted signal contains only
aggregate counts, severity/module distributions, and privacy metadata.

No client names, client ids, finding ids, record-level values, or raw examples
are persisted in the signal aggregate.
