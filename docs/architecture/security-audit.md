# Security Audit Framework

WO-119 adds a repeatable annual audit framework for third-party security and legal review. The framework records the audit cycle in `security_audits`, prepares an evidence manifest, and tracks findings until closure.

## Lifecycle

1. `security-audit:prepare {period}` creates or refreshes a `security_audits` row.
2. The evidence manifest records the checklist path, relevant architecture/legal files, SHA-256 hashes, and verification commands.
3. Findings are stored in `security_audits.findings` as structured JSON with severity, owner, remediation, status, and resolution.
4. An audit cannot close until every finding is closed.
5. Each prepare/finding/close action writes to the immutable audit trail.

## Access

`security_audits` is not client-scoped; it is internal governance data. Postgres RLS allows `super_admin` and `system` only.

## Evidence Command

```bash
php artisan security-audit:prepare 2026 --auditor="External firm"
```

The external audit itself remains out of scope for the app; this framework makes preparation, evidence collection, and remediation tracking repeatable.
