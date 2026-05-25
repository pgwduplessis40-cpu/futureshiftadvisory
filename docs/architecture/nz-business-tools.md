# NZ Business Tool Integrations

WO-113 adds a first-party connection layer for New Zealand operational tools used by advisory clients.

## Providers

The active providers are:

- Employment Hero for payroll, HR, leave, and compliance indicators
- Cin7 for inventory, sales orders, and purchasing indicators
- Tradify for jobs, invoicing, and timesheet indicators

Each provider has fake, live, and fallback clients under `App\Services\Integration`. Live mode is disabled by default and must be enabled per provider with:

- `FEATURE_EMPLOYMENT_HERO_LIVE`
- `FEATURE_CIN7_LIVE`
- `FEATURE_TRADIFY_LIVE`

## Connection Storage

`nz_tool_connections` stores one active connection per client/provider. Tokens are encrypted through `KeyEnvelope`; raw OAuth tokens must not be stored outside `token_envelope`.

The table is scoped by `client_id` with Postgres RLS. Stored snapshot payloads are advisory evidence only and keep their provider `source_badge` so degraded fixture fallbacks remain visible.

## Runtime Flow

`NzToolConnector` owns the connection lifecycle:

- builds provider authorization URLs with signed state
- exchanges callback codes for encrypted token envelopes
- syncs operational snapshots through provider clients
- revokes connections without deleting historical sync metadata

All live HTTP calls use `ResilientHttp`, so failed/missing credentials are recorded in `integration_calls` and fall back to deterministic fixture payloads.
