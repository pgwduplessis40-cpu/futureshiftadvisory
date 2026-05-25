# Advisor API

WO-112 adds the external advisor integration API under `/api/advisor/v1`.
It is separate from the first-party mobile API.

Authentication uses bearer tokens whose plaintext is shown only at issuance.
`advisor_api_clients.token_hash` stores a SHA-256 hash, with super-admin
approval metadata, scopes, status, and per-token rate limit. The
`advisor.api` middleware authenticates the token, sets the advisor as the
request user, applies RLS client scope, updates last-used metadata, and audits
every call.

Allowed surfaces are intentionally narrow:

- read scoped client summaries
- write meeting notes
- write milestone actions

Routes use Laravel's named `advisor-api` rate limiter with `throttle`, so
requests above the per-token limit return HTTP 429.
