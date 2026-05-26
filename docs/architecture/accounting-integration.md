# Accounting API Integration

WO-37 filled the Xero, MYOB, and QuickBooks accounting contracts enough for OAuth connection, token custody, manual snapshot pulls, and revocation. WO-125 extends the same path to Sage, Figured, and WorkflowMax.

## Runtime Shape

Application code resolves accounting providers through `AccountingClientResolver`, which returns the active interface for:

- `XeroClient`
- `MyobClient`
- `QuickBooksClient`
- `SageClient`
- `FiguredClient`
- `WorkflowmaxClient`

Each provider has fake, live, and fallback implementations. The live clients use `ResilientHttp` for token exchange, financial snapshot pulls, and revoke calls. When a live flag is off, fallback returns fixture data. When a live flag is on but credentials are missing or the provider call fails, the resilience layer records failure/fallback rows and returns fixture-backed degraded data stamped with `source_badge=stub_live_fallback`.

Live mode is controlled by:

- `FEATURE_XERO_LIVE`
- `FEATURE_MYOB_LIVE`
- `FEATURE_QUICKBOOKS_LIVE`
- `FEATURE_SAGE_LIVE`
- `FEATURE_FIGURED_LIVE`
- `FEATURE_WORKFLOWMAX_LIVE`

## OAuth And Token Storage

`AccountingConnector` builds provider authorization URLs with a signed state payload containing the client id, provider, and user id. The callback validates that state before exchanging the code.

Returned tokens are JSON-encoded and encrypted through `KeyEnvelope` before persistence on `accounting_connections.token_envelope`. The metadata from `KeyEnvelope::inspect()` is stored separately for rotation diagnostics. Plain access or refresh tokens must never be stored in first-class columns or exposed to Inertia payloads.

Connecting a provider revokes any prior active connection for the same client/provider before creating a new connected row.

## Financial Snapshots

`FinancialSnapshotPuller` decrypts the connection token, calls the provider client, and writes a new `financial_snapshots` row with:

- provider and connection identity
- period start/end
- P&L, balance sheet, cash flow, and metrics JSONB payloads
- source, source badge, degraded state, and optional correlation id

Snapshots are append-only. PostgreSQL rejects direct update and delete attempts through the `financial_snapshots_append_only` trigger. Revoking an accounting connection does not delete historical snapshots.

## UI Surface

The advisor client detail page exposes provider connect links, active/revoked connection state, manual Pull and Revoke actions, and latest snapshot metrics. Continuous scheduled pulls are intentionally deferred to WO-38.

## Fixtures

Fixture data lives in:

- `database/fixtures/integration/xero-accounting.json`
- `database/fixtures/integration/myob-accounting.json`
- `database/fixtures/integration/quickbooks-accounting.json`
- `database/fixtures/integration/sage-accounting.json`
- `database/fixtures/integration/figured-accounting.json`
- `database/fixtures/integration/workflowmax-accounting.json`

The fixtures include deterministic token payloads and April 2026 financial statements for tests and local development.
