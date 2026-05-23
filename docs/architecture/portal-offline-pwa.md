# Portal offline PWA

WO-101 adds a browser-side offline layer for the authenticated client portal.
The server routes stay unchanged; the browser queues the two supported offline
workflows and replays them against the existing endpoints after reconnect.

## Assets

- `public/manifest.webmanifest` makes the portal installable with the existing
  brand icons.
- `public/sw.js` caches the offline fallback and static shell assets, returns
  `offline.html` for offline `/portal` navigation, and asks open portal tabs to
  flush the queue when Background Sync fires.
- `resources/js/lib/portal-offline.ts` owns the encrypted IndexedDB queue and
  reconnect flush.

## Queue

Queued payloads are stored in IndexedDB `fsa-portal-offline-v1.queue`. Payload
bodies are encrypted with AES-GCM through `crypto.subtle` before persistence.
Each queue row has a stable dedupe key, so repeat taps while offline do not
create duplicate submissions.

Supported queue kinds:

- `questionnaire`: JSON POST to the existing onboarding step endpoint. Local
  placeholder document ids are stripped before sync because the server accepts
  only real document UUIDs.
- `document-upload`: encrypted file bytes plus upload metadata for the existing
  portal document endpoint.

The queue flushes on page load when online, on the browser `online` event, and
when the service worker receives a `portal-offline-sync` background sync event.
