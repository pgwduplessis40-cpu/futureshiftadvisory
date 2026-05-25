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

WO-109 adds `intelligence:shared-layer` and `shared_intelligence_patterns`.
The shared layer moves aggregate patterns between the advisory and entrepreneur
domains:

- advisory to entrepreneur from `industry_intelligence_signals`
- entrepreneur to advisory from governed plan-quality benchmark candidates

The bridge never reads or persists record-level source data. Patterns are
created only at or above `privacy.min_cohort` and carry aggregate-only privacy
metadata.
