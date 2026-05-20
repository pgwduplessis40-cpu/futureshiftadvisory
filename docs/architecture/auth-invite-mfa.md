# Invite-only auth and MFA

WO-08 removes public self-registration and makes MFA mandatory before authenticated application routes are reachable.

## Invite-only account creation

Fortify registration is disabled in `config/fortify.php`, so `/register` is no longer routed.

Accounts are created through `InviteIssuer` and `/invite/{token}`:

- only a SHA-256 token hash is stored in `invite_tokens`
- tokens expire according to `INVITE_TOKEN_TTL_HOURS`
- accepted tokens are one-shot via `accepted_at`
- accepting an invite creates the user with `user_type` and `primary_role`, assigns the matching Spatie role when the WO-07 matrix is seeded, logs the user in, and redirects to MFA setup

`primary_role` remains as a legacy/audit-friendly string seam, but Spatie roles now drive authorization. If a historical user has no Spatie role assignment, `User::fsaRole()` falls back to `primary_role` until a seeder or admin repair assigns the role.

## MFA enforcement

`RequireMfa` protects authenticated app routes. It allows auth-support routes such as security settings, Fortify two-factor endpoints, password confirmation, and email verification, then applies this order:

1. If the user has not completed MFA enrolment, redirect to `mfa.setup`.
2. If the user has enrolled MFA but the current session has not passed MFA, redirect to `mfa.challenge`.
3. Otherwise allow the request.

The MFA session marker is stored in the server-side session as `auth.mfa_confirmed_at` and `auth.mfa_user_id`.

## Fortify integration

Fortify remains the TOTP engine. Event listeners sync Fortify events into FSA metadata:

- `TwoFactorAuthenticationConfirmed` sets `users.mfa_enabled_at`, `users.mfa_method`, and upserts `mfa_factors`
- `ValidTwoFactorAuthenticationCodeProvided` marks the current session MFA-verified
- `TwoFactorAuthenticationDisabled` clears the FSA MFA columns

`mfa_factors.secret_envelope` and `mfa_factors.recovery_codes_envelope` use `KeyEnvelope`, keeping MFA secrets aligned with the platform encryption-at-rest seam.

## Terms gate placeholder

After invite acceptance and MFA confirmation, the user is sent to `terms.pending`. The full T&C acceptance model and signed-PDF flow remain WO-11.
