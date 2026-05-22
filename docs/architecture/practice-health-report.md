# Practice Health Report

WO-62 adds a portfolio-level practice health report for advisors and
super-admins. It aggregates only active clients and uses the same client-scope
resolver as the advisor dashboard: super-admins receive the whole active
practice, while advisors receive only their assigned client portfolio.

## Metrics

The report combines Phase 2 operating signals:

- current PV from each client's latest business valuation
- improvement PV and risk-mitigation PV from PV impact records
- target PV as current PV plus improvement and risk mitigation
- revenue under management from the latest accounting financial snapshot
- released proposal count, generated report count, open red flags, and funnel
  drop-off summary

Rows are calculated on demand by `App\Services\Reports\PracticeHealthReport`.
The payload is also exposed to the advisor dashboard as `practiceHealth`.

## Snapshots

Monthly caching is handled by `practice_health_snapshots` and the
`practice-health:snapshot` command. The scheduled task creates one super-admin
practice snapshot and, with `--all-advisors`, one portfolio snapshot per advisor,
junior advisor, and entrepreneur mentor.

Snapshot rows are scoped by `advisor_user_id`. Postgres RLS permits system and
super-admin roles to see all snapshots; non-super-admin users can see only rows
whose `advisor_user_id` matches `fsa_current_user_id()`.
