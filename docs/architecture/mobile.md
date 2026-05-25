# Mobile API Foundation

WO-115 adds a first-party mobile API without introducing a third-party token package.

## Device Tokens

`MobileTokenIssuer` registers a device only after:

- the user has completed MFA enrolment
- the current terms gate does not require acceptance
- the user is not suspended

The plaintext token is returned once and stored only as a SHA-256 hash in `device_registrations`. Re-registering the same device revokes the previous active row.

## API Surface

Mobile endpoints live under `/api/mobile/v1` and require the `mobile.api` middleware:

- `GET /me`
- `GET /clients`
- `GET /clients/{client}`
- `POST /voice-assistant/sessions`

The middleware validates the bearer token, checks MFA and terms status again on every request, sets the authenticated user, applies RLS request context, updates `last_used_at`, and writes `mobile_api.call` audit events.

## Scope

Client payloads are limited to the authenticated user's `accessibleClientIds()`. Voice assistant shortcut sessions reuse the WO-114 static-intent payload builder.
