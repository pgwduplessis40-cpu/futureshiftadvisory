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

WO-110 adds the anonymous benchmarking community. `benchmark_community`
consent is an explicit opt-in consent type and can be revoked. Active community
membership requires an unrevoked consent; revocation removes the member from
future aggregates.

`intelligence:benchmark-community` writes `benchmark_aggregates` separately for
the `sme` and `entrepreneur` domains. Aggregates expose percentile bands only,
are suppressed below `privacy.min_cohort`, and record privacy-counsel sign-off
on the aggregate row before production use.

WO-111 adds the moderated peer network. `peer_network` is a separate consent
type from `benchmark_community`; a peer member can post only while that consent
is active and unrevoked. Every post creates a pending moderation row and remains
invisible until approved. Visible feeds return pseudonyms only, separated by
`sme` and `entrepreneur` communities, and moderation can report posts or suspend
members.
