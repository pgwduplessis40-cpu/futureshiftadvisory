# Business health trajectory report

WO-59 adds the trajectory profile to the shared report engine. It is an
advisor-reviewed journey report rather than a client-shareable artifact by
default.

## Inputs

`ReportComposer` assembles trajectory reports from persisted records:

- earliest and latest `financial_snapshots` for start-to-current metrics
- ordered `business_valuations` for PV milestones
- latest analysis finding titles for current focus areas

The narrative is deterministic and generated from platform data, not a fresh AI
call. The generated section carries `advisor_review_required = true` metadata.

## Review Gate

Trajectory reports are created with `reports.review_status = pending_review`.
Advisors can mark the report reviewed through the advisor report panel, which
sets `reviewed_by_user_id` and `reviewed_at` and writes `report.reviewed` to the
audit trail.

The client portal continues to show only Client report summaries, so trajectory
reports are not client-shared before advisor review in WO-59.
