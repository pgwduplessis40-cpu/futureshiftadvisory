# Consent-Based Guided Assistance: Test Environment

This feature is limited to named elements inside Future Shift Advisory. It does not share a screen, record a session, open external sites, or control a client's mouse or keyboard.

## Enable only in test

Set the following in the test environment `.env`:

```dotenv
CO_BROWSE_ENABLED=true
CO_BROWSE_REQUEST_TIMEOUT_SECONDS=60
CO_BROWSE_MAX_DURATION_MINUTES=20
CO_BROWSE_HEARTBEAT_INTERVAL_SECONDS=10
CO_BROWSE_PRESENCE_TTL_SECONDS=45
CO_BROWSE_ACTIONS_PER_SECOND=5
```

Leave `CO_BROWSE_ENABLED=false` in live until the test scenarios are signed off. This feature uses the existing Reverb, queue-worker, and scheduler infrastructure. It does not need TURN, coturn, or any media-relay configuration.

## Deploy

1. Run the database migration.
2. Build the frontend assets.
3. Clear and rebuild Laravel configuration cache.
4. Restart the queue worker and confirm the scheduler is running.
5. Run `php artisan co-browse:expire` once to confirm the expiry command resolves.

## Test flow

1. Sign in as an advisor and open a client or entrepreneur detail page.
2. Sign in as the corresponding client in a second browser and open their Future Shift Advisory dashboard.
3. Choose **Guided assistance** and request approval.
4. Confirm the client sees the approval dialogue that explains the advisor cannot control their mouse or keyboard.
5. Approve, move within the advisor pointer area, and use the available highlight actions.
6. Confirm the client sees only the pointer/highlight overlay on the matching Future Shift Advisory page.
7. Stop assistance from the client and confirm both sides close without a stale overlay.
8. Check the audit trail for `co_browse.requested`, `co_browse.client_approved`, `co_browse.pointer` or `co_browse.highlight`, and `co_browse.ended`.

## Rollback

Set `CO_BROWSE_ENABLED=false`, rebuild the configuration cache, and restart the queue worker. Existing sessions will expire automatically; `php artisan co-browse:expire` ends any overdue session immediately.
