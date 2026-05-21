# Architecture decisions

This folder holds Architecture Decision Records (ADRs) and topic-specific architecture notes for the Future Shift Advisory platform.

## When to add a file here

- A non-trivial architectural choice was made and a future contributor would benefit from knowing the *why* (not just the *what*).
- A decision diverges from the spec — record the deviation, the rationale, the trigger for revisiting.
- A cross-cutting concern needs its own write-up (security, audit, AI integrity, integration patterns).

## Files expected during Phase 1

| File | Created by WO | Purpose |
|---|---|---|
| `postgres-rls.md` | WO-02 | How row-level security is wired; how to add a new client-scoped table |
| `key-envelope.md` | WO-02 | AES-256 envelope design and PQC-ready seam (spec §4 deviation) |
| `audit-trail.md` | WO-03 | Immutable audit table contract, redaction policy, chain verification job |
| `ai-integrity.md` | WO-04 | AiClient contract, prompt registry, attribution validator, bias detector |
| `integration-pattern.md` | WO-05 | ResilientHttp + RetryPolicy + CircuitBreaker + cached fallback shape |
| `secure-file-storage.md` | WO-06 | SecureFileWriter, encrypted disk wrapper, scanner contract, quarantine flow |
| `rbac-matrix.md` | WO-07 | Spatie role/permission matrix, DD guest token-only rule, middleware/policy gates |
| `auth-invite-mfa.md` | WO-08 | Invite-only account creation, Fortify MFA enforcement, session MFA gate |
| `session-security.md` | WO-09 | Per-user-type session timeout, step-up MFA risk scoring, session audit hooks |
| `terms-versioning.md` | WO-10 | Terms versions, clause editing, material publish re-acceptance seam |
| `terms-acceptance.md` | WO-11 | Authenticated T&C gate, signed PDF evidence, decline suspension |
| `notifications.md` | WO-12 | Channel preferences, notification decision ledger, digest jobs |
| `nz-integrations.md` | WO-13 | NZBN, Companies Office, IRD clients and empty named integration scaffolds |
| `client-management.md` | WO-14 | Client creation, engagement type, NZBN population, conflict declaration gate |
| `security-decisions.md` | this WO | Phase 1 security deviations from spec §4 (PQC deferral, etc.) |

A skeleton `security-decisions.md` lives in this folder as part of WO-01 so the PQC deferral is recorded from day one.
