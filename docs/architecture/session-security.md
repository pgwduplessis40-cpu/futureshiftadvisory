# WO-09 session security

WO-09 adds authenticated-session controls on top of invite-only auth, mandatory MFA, and WO-07 RBAC.

## Timeout policy

`EnforceSessionSecurity` runs in the web middleware stack for every authenticated request. It keeps an FSA-specific `fsa.session.last_activity_at` marker in the server-side session.

Timeout resolution order:

1. `users.session_timeout_minutes`, when set to a positive value
2. `config/security.php` `session_timeouts.{role}`
3. `config/security.php` `session_timeouts.default`

Phase 1 defaults:

| User type | Timeout |
|---|---:|
| `super_admin` | 15 minutes |
| `advisor`, `junior_advisor`, `entrepreneur_mentor` | 30 minutes |
| `client_primary`, `client_team`, `entrepreneur`, `broker`, `coach` | 60 minutes |

An expired session writes `security.session_expired` to the immutable audit log, logs the user out, invalidates the session, and redirects to login.

## Step-up MFA

`StepUpEvaluator` compares the current request with the session's last known device signals:

- IP address change
- Country-code change from `CF-IPCountry` or `X-Country-Code`
- User-agent change
- Super-admin route access from a changed device

Signals add to a risk score. When the score meets or exceeds `STEP_UP_RISK_THRESHOLD` (default `70`), the middleware marks the session as needing step-up MFA and redirects to `mfa.challenge?reason=step_up`.

The challenge must be passed before protected routes become reachable again. Failed step-up attempts write `security.step_up_failed` to the audit log.

## Session table fields

The database session table now includes:

- `risk_score` - latest calculated request risk score
- `step_up_at` - timestamp when the session was last forced into step-up

These fields are best-effort observability for the database session driver. Tests use the array driver, so the enforcement logic remains session-store agnostic.
