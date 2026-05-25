# Advisor Teams

WO-120 adds multi-advisor scaling without replacing the existing `client_team` access model.

## Tables

- `advisor_teams`: team name, lead advisor, status, metadata.
- `advisor_team_members`: advisor/team membership with `lead`, `member`, or `operations` role.
- `client_team.advisor_team_id`: optional team assignment on the existing per-client access row.

## Access Model

Direct client access still comes from `client_team.user_id`. Team inheritance is narrow:

- Team leads inherit every client assigned to their team through `client_team.advisor_team_id`.
- Team members see only clients where they also have a direct `client_team` row.
- `RequestContext` continues to resolve one `fsa.client_ids` array by calling `User::accessibleClientIds()`.
- The `client_team` RLS policy now allows team-lead visibility for team-assigned rows.

## Operations

`AdvisorTeamManager` owns team creation, member addition, client assignment, reassignment, and capacity summaries. Reassignment writes an immutable audit event with before/after team IDs and row counts.

Capacity is aggregated as active team clients against `clients.capacity.limit * active_team_members`.
