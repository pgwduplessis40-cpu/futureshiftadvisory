# Portal offline PWA

WO-101 adds a browser-side offline layer for the authenticated client portal.
WO-122 hardens the existing replay path server-side; there is still no second
service worker, manifest, or dedicated replay endpoint.

## Assets

- `public/manifest.webmanifest` makes the portal installable with the existing
  brand icons.
- `public/sw.js` caches the offline fallback and static shell assets, returns
  `offline.html` for offline `/portal` navigation, and asks open portal tabs to
  flush the queue when Background Sync fires.
- `resources/js/lib/portal-offline.ts` owns the encrypted IndexedDB queue,
  reconnect flush, sync headers, and explicit logout cleanup.

## Queue

Queued payloads are stored in IndexedDB `fsa-portal-offline-v1.queue`. Payload
bodies are encrypted with AES-GCM through `crypto.subtle` before persistence.
Each queue row has a stable dedupe key plus the client id resolved at queue
time. Replays send `X-Portal-Offline-Sync`, `X-Idempotency-Key`, and
`X-Portal-Client-Id` to the original portal endpoint.

Legacy rows created before WO-122 have no client id. The flush path keeps those
rows, increments attempts, and emits `portal-offline-legacy-record` so the UI can
surface a manual re-submit/discard path. It never falls back to the server's
latest-client resolver for those rows.

Supported queue kinds:

- `questionnaire`: JSON POST to the existing onboarding step endpoint. Local
  placeholder document ids are stripped before sync because the server accepts
  only real document UUIDs.
- `document-upload`: encrypted file bytes plus upload metadata for the existing
  portal document endpoint.

The queue flushes on page load when online, on the browser `online` event, and
when the service worker receives a `portal-offline-sync` background sync event.
Only JSON success responses delete a queued row; redirects, auth-flow HTML,
401/403/409/419, and other non-JSON outcomes are kept for retry.

## Server replay hardening

Sync requests still post directly to the existing onboarding and document-upload
routes. Those routes use `PortalOfflineSync` to validate the queued client
against the current user's accessible clients, compute a server-side request
fingerprint, and store deterministic success responses in
`portal_offline_sync_records`.

The ledger key is `(user_id, client_id, operation, idempotency_key)`. A repeat
with the same fingerprint returns the cached JSON response without re-running
the controller. A repeat with the same key and a different fingerprint returns
409, so a filename/size collision on the browser side cannot become a stale
cached success.

Document-upload replays use the normal `DocumentController`, so files still pass
through `SecureFileWriter` and the virus-scanner contract before persistence.

## Auth and logout

`NormalizePortalOfflineSyncResponse` rewrites sync-only redirects to login, MFA,
terms, and verification routes as JSON 401 responses. The exception handler also
renders sync requests as JSON even when the browser sent an HTML `Accept` header.

Explicit logout calls `clearPortalOfflineQueue()`, deleting the IndexedDB queue
and local AES key. Session expiry does not clear local offline data; it returns
401 so the row stays encrypted locally and can retry after re-authentication.
