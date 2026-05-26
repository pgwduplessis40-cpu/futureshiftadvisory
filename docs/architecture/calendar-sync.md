# Calendar Sync

WO-124 implements advisor-owned Google Calendar and Microsoft Outlook sync.

## Ownership and Storage

- `calendar_connections` belongs to one advisor user and stores provider OAuth
  access/refresh tokens only as `KeyEnvelope` ciphertext.
- `calendar_event_mappings` links provider event IDs to FSA `meetings` when FSA
  created the event, or stores provider-only events with `meeting_id = null`.
- Both calendar token columns are listed in `RewrapEnvelopes::sources()` so key
  rotation does not strand credentials.

## Provider Runtime

Calendar clients follow the existing integration pattern:

- fake clients provide deterministic fixtures for local and CI runs;
- live clients use `ResilientHttp` and provider config under
  `integrations.calendar`;
- fallback clients use fixtures when `FEATURE_CALENDAR_LIVE=false` or when live
  transport degrades.

The same `stub_live_fallback` health ledger path used by accounting and NZ
business tools applies when live mode is enabled without credentials.

## Sync Contract

- FSA meetings created by the connected advisor are pushed to every connected
  calendar.
- Manual connection sync pushes recent/upcoming FSA meetings and pulls provider
  events using `sync_token`/`delta_link` cursors.
- `(calendar_connection_id, external_event_id)` is unique, so repeat syncs
  update existing rows instead of duplicating events.
- Provider pulls preserve an existing `meeting_id` for FSA-created events and
  mark them `two_way` rather than external-only.

## Access Control

Settings routes require authenticated, verified, MFA-confirmed advisor, junior
advisor, or super-admin users. Route handlers additionally scope sync/revoke to
the connection owner. Postgres RLS enforces the same user-owned visibility for
connections and mappings.
