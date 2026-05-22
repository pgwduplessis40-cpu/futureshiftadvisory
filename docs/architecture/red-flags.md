# AI red flag alerts

WO-34 promotes critical analysis findings into an advisor-visible red flag workflow.

## Promotion

`AnalysisRunner` persists findings through the Phase 2 analysis spine. After each finding is stored, it calls `RedFlagPromoter`. Only `critical` findings are promoted.

The promoter creates a single `red_flags` row per underlying `analysis_finding_id`, so reprocessing the same finding does not create duplicate flags or resend notifications. Monitor-derived flags can use `source_type` and `source_key` when there is no finding row yet.

Categories are derived from the analysis module:

- financial analysis -> `financial`
- compliance -> `compliance`
- regulatory impact -> `regulatory`
- insurance risk -> `insurance`
- HR and succession -> `key_person`
- other modules -> `viability`

## Alerts

New red flags send `RedFlagUrgentNotification` to super-admins and advisors on the affected client team. The notification extends `ChannelAwareNotification` with `urgency=urgent`, so `ChannelResolver` bypasses user channel/frequency preferences and routes to the database ledger plus mail.

## Advisor workflow

The advisor dashboard now includes an AI red flags panel for open flags in the viewer's client scope. Each item links back to the client and exposes acknowledge/resolve actions.

Acknowledging a flag sets `acknowledged_at` and `acknowledged_by_user_id`. Resolving sets `resolved_at`. Both actions are audited as `red_flag.acknowledged` and `red_flag.resolved`.

No WO-34 path edits or suppresses the underlying analysis finding; the red flag is an alerting and workflow layer only.
