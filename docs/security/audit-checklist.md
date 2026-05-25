# Annual Security And Legal Audit Checklist

Use this checklist with `php artisan security-audit:prepare`.

## Security

- Review `KeyEnvelope` v1/v2 dispatch, PQC feature flag, and rewrap records.
- Verify HSM driver binding, KEK non-export invariant, key rotation records, and production HSM provisioning.
- Review Postgres RLS helper functions, middleware context propagation, and table policies.
- Review audit trail append-only enforcement and `fsa:audit:verify` output.
- Review secure upload scanning, quarantine behavior, and secure file storage encryption.
- Review AI integrity gates, live-client boundaries, learning approval/rollback controls, and no-autonomous-change tests.
- Review mobile/advisor API token hashing, rate limits, scopes, and audit logging.
- Review payment token handling and no-PAN-storage controls.

## Legal And Privacy

- Review current Terms & Conditions, panel agreements, DD disclaimers, and proposal acceptance terms.
- Review Privacy Act 2020 posture for benchmarks, shared intelligence, peer/community features, and consent ledger.
- Review data-retention/offboarding evidence and client access revocation.
- Review external integration terms and live credential management.

## Evidence

- Capture successful verification command output.
- Store the signed external report path on `security_audits.report_path`.
- Enter each finding with severity, owner, remediation, and closure resolution.
