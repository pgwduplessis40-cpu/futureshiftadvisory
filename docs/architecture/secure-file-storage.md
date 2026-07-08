# Secure file storage

WO-06 establishes the only approved path for sensitive upload persistence.

## Required flow

Application code calls `App\Services\Storage\SecureFileWriter`; it must not call `Storage::put` directly for sensitive user uploads.

`SecureFileWriter` performs:

- local active-content threat inspection before persistence
- malware scan through `FileScanner`
- encrypted write to the `secure_local` disk
- `documents` metadata creation
- audit event write for uploaded, rejected, or quarantined files
- advisor notice cache/audit entry when scanning cannot complete

## Disk encryption

`secure_local` is registered in `config/filesystems.php` with the `encrypted-local` driver. The driver uses `WriteWrappedAdapter`, which wraps Flysystem local storage and encrypts every write with `KeyEnvelope`; reads decrypt through the same envelope.

Raw bytes under `storage/app/secure/` are JSON envelopes and must not contain the plaintext upload contents.

## Threat inspection and scanner implementations

`UploadThreatInspector` runs before the external scanner and rejects strong
active-content indicators, including executable file signatures, blocked script
extensions, scripted PDFs, Office macro projects, ActiveX controls, embedded
executables, and external Office relationships.

`FileScanner` has three implementations:

- `NoopScanner` for local/dev and test-safe operation only when `VIRUS_SCAN_ALLOW_NOOP=true`. It returns `clean`, including for the EICAR test fixture.
- `ClamAvScanner` for production-style live mode. It talks to a ClamAV daemon via the INSTREAM TCP protocol when `FEATURE_VIRUS_SCAN_LIVE=true`.
- `UnavailableScanner` for fail-closed operation when live scanning is disabled and no-op scanning is not allowed. It returns `error`, which quarantines uploads rather than marking them clean.

If ClamAV cannot connect or returns an unknown response, the scan result is `error`.
Local environments may set `VIRUS_SCAN_FAIL_OPEN_ON_ERROR=true` together with
`VIRUS_SCAN_ALLOW_NOOP=true`; in that case scanner outages are recorded as a
clean development fallback with the original scanner error preserved in
`scanner_payload`. Production ignores this fallback because it is additionally
gated by `APP_ENV`.
The admin integration credentials screen surfaces `virus_scanner` readiness so
production operators can see whether ClamAV live scanning is configured. Local
and testing environments may opt into the no-op scanner; production cannot.

## Infection and quarantine

`infected` scan results are rejected before persistence. No file is written and an immutable audit event records the rejection.

`error` scan results are persisted under `quarantine/`, recorded with `scanner_result=error`, excluded from `Document::visibleToClients()`, and surfaced through `SecureStorageNotice`. Future work can convert that cache/audit notice into a `ChannelAwareNotification` now that WO-12 has introduced the central notification resolver.
