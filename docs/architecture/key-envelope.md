# Key envelope (encryption-at-rest)

Per spec §4 ("Encryption — AES-256 at rest") and security decision **SD-01/SD-02** (PQC and HSM deferred to Phase 4), the platform uses a tagged envelope around all encrypted-at-rest payloads.

## Why an envelope?

Two reasons:

1. **Future-proofing.** Spec §4 mandates post-quantum cryptography (CRYSTALS-Kyber FIPS 203 + CRYSTALS-Dilithium FIPS 204) eventually. Wrapping every Phase 1 ciphertext in a versioned envelope means the Phase 4 swap-in is a single-file change: new envelopes are written in v2 (`alg: "kyber-1024+aes-256-gcm"`), historical v1 envelopes (`alg: "aes-256-laravel"`) remain decryptable.
2. **Operational hygiene.** The envelope carries a `kid` (key ID) hash so that future key rotations can target the right keys for re-wrapping without scanning every column or file.

## Envelope shape

```json
{
  "v": 1,
  "alg": "aes-256-laravel",
  "kid": "aes-256-laravel:6f4a1b3c7e2d8f90",
  "body": "<laravel-encrypter ciphertext base64>"
}
```

Fields:

- **`v`** (int, required) — envelope schema version. Currently 1.
- **`alg`** (string, required) — algorithm tag. Phase 1: `aes-256-laravel`. Phase 4: `kyber-1024+aes-256-gcm`.
- **`kid`** (string, required) — opaque key identifier; first 16 hex chars of `SHA-256(APP_KEY)`. Lets future key rotation locate envelopes that need re-wrapping.
- **`body`** (string, required) — the ciphertext. For v1, this is whatever Laravel's `Encrypter::encryptString` produces (AES-256-CBC with HMAC-SHA-256 over `APP_KEY`).

## Usage

```php
use App\Services\Storage\KeyEnvelope;

$envelope = app(KeyEnvelope::class)->encrypt('hello');
// store $envelope in the database column or file body

$plaintext = app(KeyEnvelope::class)->decrypt($envelope);
```

Direct calls to `Crypt::encryptString` / `Crypt::decryptString` outside `KeyEnvelope` are forbidden by convention (no enforcement yet — a linter rule may be added later). Anything stored "encrypted at rest" — file bodies, MFA secrets, signed-PDF audit hashes, encrypted JSONB columns — uses the envelope.

## Phase 4 PQC swap-in plan

When PQC lands:

1. Add a new `alg` constant `kyber-1024+aes-256-gcm` and an envelope `v: 2`.
2. Add a v2 encrypt path that uses liboqs (Kyber-1024 KEM + AES-256-GCM symmetric) instead of Laravel's encrypter.
3. Add a v2 decrypt branch in the `match` in `KeyEnvelope::decrypt`.
4. Add a one-off `php artisan envelopes:rewrap` command that streams all stored envelopes through `decrypt` then `encrypt`, producing v2 envelopes. Idempotent: skip rows already at v2.
5. Update `KeyEnvelope::CURRENT_VERSION` to 2.

The single-line change in `CURRENT_VERSION` is the cutover moment. Before that, v1 and v2 coexist; after, all new writes are v2 and any remaining v1 envelopes are still readable until rewrapped.

## What is and is NOT encrypted

In Phase 1 the envelope is used by:

- **Secure file storage** (WO-06) — every file body on the `secure_local` disk.
- **Signed T&C acceptance PDFs** (WO-11) — the PDF bytes plus the hash that proves they were not altered.
- **MFA TOTP secrets and recovery codes** (WO-08).
- **Document SHA-256 hashes** — stored in plaintext; the envelope is only for confidentiality, not integrity.

What is NOT in an envelope (Phase 1):

- **Postgres data at rest.** Postgres TDE (transparent disk encryption) handles that at the cluster/disk level. The envelope is for app-controlled secrets and file blobs.
- **Postgres backups.** Backup encryption is an operational concern handled by the backup tool (pg_dump piped through GPG, or the managed-DB platform's backup encryption).
- **TLS in transit.** TLS 1.3 terminates at the edge; the envelope is unrelated.
