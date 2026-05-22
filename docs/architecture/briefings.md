# Industry briefings and pre-meeting briefs

WO-60 adds two advisor-reviewed briefing workflows plus the minimal local
meeting record used to trigger pre-meeting briefs. Calendar sync remains a
Phase 4 concern; the `meetings` table is the Phase 2 source of truth.

## Monthly Industry Briefings

`IndustryBriefingGenerator` creates one draft briefing per client per month.
The body is assembled from persisted client context and the latest NZ economic
indicator rows. Source metadata cites the underlying `economic_indicators`
records so every briefing can be traced back to local evidence.

Draft briefings are not sent automatically. An advisor reviews the briefing from
the client detail page, which records reviewer metadata, marks the briefing
sent, writes an audit event, and routes `IndustryBriefingNotification` through
the existing channel resolver to client portal users.

## Pre-Meeting Briefs

`PreMeetingBriefGenerator` looks for meetings scheduled between 23 and 25 hours
from the scheduler run. Each meeting can generate exactly one brief. The body
summarises recent released proposals, generated reports, open red flags, recent
financial alerts, and current analysis findings.

Pre-meeting briefs are also draft until advisor review. Review records reviewer
metadata, writes an audit event, and routes `PreMeetingBriefNotification`
through the channel resolver to advisor-side client team members.

## Scheduler

- `briefings:generate-monthly` runs monthly and creates draft industry
  briefings.
- `briefings:generate-pre-meeting` runs hourly and creates draft briefs for the
  24-hour meeting window.

Both commands apply system RLS context before reading client-scoped rows.
