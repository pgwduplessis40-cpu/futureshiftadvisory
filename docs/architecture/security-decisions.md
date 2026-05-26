# Security Decisions

This file records security architecture decisions that deviate from spec V2.4 section 4 or that warrant a permanent rationale.

| # | Decision | Status | Trigger to revisit |
|---|---|---|---|
| SD-01 | Post-quantum envelope support implemented behind `FEATURE_PQC`; v2 uses `ml-kem-1024+aes-256-gcm` metadata, AES-256-GCM content encryption, wrapped DEK material, and `ml-dsa-87` signature metadata. | Closed at the application seam in WO-117; production still requires NZ-qualified crypto review and provider validation. | Before production cutover with real client data. |
| SD-02 | Hardware Security Module key-management boundary added for v2 envelopes; the HSM client can wrap/unwrap DEKs and directly encrypt small secrets, while dev keeps a software fallback. | Closed at the application seam in WO-118; production still requires real HSM provisioning and reviewer sign-off. | Before production cutover. |
| SD-03 | ClamAV daemon not provisioned in Phase 1. Interface (`FileScanner`) and a `NoopScanner` implementation built so the upload path enforces "scanned before persistence" architecturally; a `ClamAvScanner` skeleton is in place but not deployed. | Pending Phase 2/3. | Before any production upload. |
| SD-04 | Row-level security in Postgres is enforced by per-table policies that read session-scoped variables (`fsa.role`, `fsa.client_ids`) set by the `EnforceClientScope` middleware. Bypass requires `super_admin` session context. | Active design. | If middleware bypass bugs are found, escalate immediately. |
| SD-05 | All AI calls go through the `AiClient` interface, bound to `FakeAiClient` in tests. The live `AnthropicClaudeClient` is the only sanctioned exit point to the Anthropic API. | Active design. | If any team member adds a direct `Http::post('https://api.anthropic.com/...')`, treat as defect. |
| SD-06 | Invite-only registration: Fortify's default register route returns 404. All accounts created via `InviteIssuer`. | Active design. | n/a |
| SD-07 | MFA enforcement is global (`RequireMfa` middleware on all authenticated routes). No per-user-type opt-out. Session-level step-up MFA triggers on configurable risk signals (IP/UA change, super-admin route from new device). | Active design. | If business need arises to relax for any user type, raise an owner decision; do not implement silently. |
| SD-08 | Portal offline queue data is retained on session expiry but cleared on explicit logout. Expiry returns JSON keep-and-retry responses for sync requests; logout deletes IndexedDB `fsa-portal-offline-v1` and the local AES-GCM key. | Active design from WO-122. | Revisit only if product changes the offline-save promise or adds account switching. |

## SD-01 - PQC Envelope Status

WO-117 closes the application-level SD-01 deferral:

- `KeyEnvelope` validates the `{v, alg}` pair before dispatch.
- v1 (`aes-256-laravel`) remains decryptable forever.
- `FEATURE_PQC=true` writes v2 envelopes without call-site changes.
- `envelopes:rewrap --target=2` records row-level outcomes in `crypto_rotations` and emits an audit event.

The local v2 provider is software-backed for development and deterministic tests. Production cutover still requires a NZ-qualified crypto review and the provider/key-management validation that WO-118 and WO-119 formalise.

## SD-02 - HSM Status

WO-118 closes the application HSM boundary:

- `HsmClient` has wrap/unwrap and direct small-secret methods, but no KEK export method.
- `PqcEnvelopeCipher` uses HSM direct encrypt/decrypt for small secrets and HSM-wrapped DEKs for bulk payloads.
- `HsmKeyManager::zero()` clears transient DEKs after bulk AES-GCM operations.
- `hsm:rotate-kek` records HSM key-reference rotations in `crypto_rotations`.

The software driver remains a development fallback. Production must bind the real CloudHSM/Azure adapter before client-data cutover.
