# Phase 1 security decisions

This file records security architecture decisions that **deviate from spec V2.4 §4** or that warrant a permanent rationale.

| # | Decision | Status | Trigger to revisit |
|---|---|---|---|
| SD-01 | Post-quantum cryptography (CRYSTALS-Kyber FIPS 203 + CRYSTALS-Dilithium FIPS 204 via liboqs) deferred from Phase 1 to Phase 4. | Active deviation from spec §4 and §27. | Before any production cutover with real client data. |
| SD-02 | Hardware Security Module (CloudHSM / Azure Dedicated HSM) deferred from Phase 1 to Phase 4. Phase 1 uses Laravel `Crypt` facade with the encryption key stored in `.env` (dev) and cloud KMS (production deployment Phase 2+). | Active deviation from spec §4 and §27. | Before any production cutover. |
| SD-03 | ClamAV daemon not provisioned in Phase 1. Interface (`FileScanner`) and a `NoopScanner` implementation built so the upload path enforces "scanned before persistence" architecturally; a `ClamAvScanner` skeleton is in place but not deployed. | Pending Phase 2/3. | Before any production upload. |
| SD-04 | Row-level security in Postgres is enforced by per-table policies that read session-scoped variables (`fsa.role`, `fsa.client_ids`) set by the `EnforceClientScope` middleware. Bypass requires `super_admin` session context. | Active design. | If middleware bypass bugs are found, escalate immediately. |
| SD-05 | All AI calls go through the `AiClient` interface, bound to `FakeAiClient` in tests. The live `AnthropicClaudeClient` is the only sanctioned exit point to the Anthropic API. | Active design. | If any team member adds a direct `Http::post('https://api.anthropic.com/...')`, treat as defect. |
| SD-06 | Invite-only registration: Fortify's default register route returns 404. All accounts created via `InviteIssuer`. | Active design. | n/a |
| SD-07 | MFA enforcement is global (`RequireMfa` middleware on all authenticated routes). No per-user-type opt-out. Session-level step-up MFA triggers on configurable risk signals (IP/UA change, super-admin route from new device). | Active design. | If business need arises to relax for any user type, raise an owner decision; do not implement silently. |

## SD-01 / SD-02 — Why defer PQC and HSM?

The spec mandates CRYSTALS-Kyber / CRYSTALS-Dilithium (NIST PQC standards) and HSM-backed key management from day one. Three reasons for the Phase 1 deferral, agreed with the owner:

1. **Time-to-running-platform.** liboqs binding from PHP/Laravel is non-trivial and adds weeks to Phase 1 with no functional user-facing benefit during the Months-1-3 window where no real client data exists.
2. **HSM provisioning.** CloudHSM / Azure Dedicated HSM provisioning and key ceremony are a one-week+ exercise that is wasted if the architecture changes again before production.
3. **NZ-qualified review.** The spec itself (§27) calls for NZ-qualified developer review of security and payment selections before build. Doing that review once, against the full Phase 1+2 set of components, is cheaper than reviewing now and again later.

### How Phase 1 stays PQC-ready

The `KeyEnvelope` service produces tagged envelopes:

```
{ "alg": "aes-256-gcm", "v": 1, "kid": "...", "body": "<base64-ciphertext>" }
```

When PQC lands in Phase 4, new envelopes will tag with `alg: "kyber-1024+aes-256-gcm"` and `v: 2`. The decryption side already dispatches by `alg`/`v`, so historical AES-only envelopes remain decryptable and new writes use PQC without changing any call site.

### Acceptance for revisiting

Before production go-live, the security architecture must be reviewed by an NZ-qualified developer or auditor (per spec §27). The reviewer's report becomes a new file in this folder. The PQC swap-in and HSM provisioning are recorded as part of the Phase 4 plan and tracked as risks R3, R4, and R6 in [`PLAN.md` §12](../../PLAN.md#12-open-risks--decisions-to-revisit).
