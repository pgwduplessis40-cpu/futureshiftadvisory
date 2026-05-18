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
| `security-decisions.md` | this WO | Phase 1 security deviations from spec §4 (PQC deferral, etc.) |

A skeleton `security-decisions.md` lives in this folder as part of WO-01 so the PQC deferral is recorded from day one.
