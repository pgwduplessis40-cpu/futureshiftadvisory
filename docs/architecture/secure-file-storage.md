# Secure file storage

WO-06 establishes the only approved path for sensitive upload persistence.

## Required flow

Application code calls `App\Services\Storage\SecureFileWriter`; it must not call `Storage::put` directly for sensitive user uploads.

`SecureFileWriter` performs:

- malware scan through `FileScanner`
- encrypted write to the `secure_local` disk
- `documents` metadata creation
- audit event write for uploaded, rejected, or quarantined files
- advisor notice cache/audit entry when scanning cannot complete

## Disk encryption

`secure_local` is registered in `config/filesystems.php` with the `encrypted-local` driver. The driver uses `WriteWrappedAdapter`, which wraps Flysystem local storage and encrypts every write with `KeyEnvelope`; reads decrypt through the same envelope.

Raw bytes under `storage/app/secure/` are JSON envelopes and must not contain the plaintext upload contents.

## Scanner implementations

`FileScanner` has two Phase 1 implementations:

- `NoopScanner` for local/dev and test-safe degraded operation. It returns `clean`, including for the EICAR test fixture.
- `ClamAvScanner` for production-style live mode. It talks to a ClamAV daemon via the INSTREAM TCP protocol when `FEATURE_VIRUS_SCAN_LIVE=true`.

If ClamAV cannot connect or returns an unknown response, the scan result is `error`.

## Infection and quarantine

`infected` scan results are rejected before persistence. No file is written and an immutable audit event records the rejection.

`error` scan results are persisted under `quarantine/`, recorded with `scanner_result=error`, excluded from `Document::visibleToClients()`, and surfaced through `SecureStorageNotice`. Future work can convert that cache/audit notice into a `ChannelAwareNotification` now that WO-12 has introduced the central notification resolver.
