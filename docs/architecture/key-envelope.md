# Key Envelope (Encryption At Rest)

Per spec section 4 and security decision SD-01, the platform uses a tagged JSON envelope around app-controlled encrypted-at-rest payloads.

## Why An Envelope?

1. **Future-proofing.** FIPS 203 ML-KEM (derived from CRYSTALS-Kyber) and FIPS 204 ML-DSA (derived from CRYSTALS-Dilithium) are Phase 4 requirements. The envelope lets new writes move to v2 while v1 payloads remain decryptable.
2. **Operational hygiene.** Every envelope carries a `kid` so rotations can target the correct key material without guessing from the ciphertext alone.
3. **Strict dispatch.** `KeyEnvelope` validates the `{v, alg}` pair before decrypting. A v1 envelope cannot claim the v2 algorithm, and a v2 envelope cannot claim the v1 algorithm.

## V1 Shape

```json
{
  "v": 1,
  "alg": "aes-256-laravel",
  "kid": "aes-256-laravel:6f4a1b3c7e2d8f90",
  "body": "<laravel-encrypter ciphertext>"
}
```

V1 uses Laravel's configured encrypter and remains readable forever.

## V2 Shape

```json
{
  "v": 2,
  "alg": "ml-kem-1024+aes-256-gcm",
  "kid": "ml-kem-1024+aes-256-gcm:6f4a1b3c7e2d8f90",
  "body": {
    "provider": "software",
    "kem_alg": "ml-kem-1024",
    "sig_alg": "ml-dsa-87",
    "encapsulated_key": "<base64 wrapped DEK>",
    "wrap_nonce": "<base64>",
    "wrap_tag": "<base64>",
    "nonce": "<base64>",
    "tag": "<base64>",
    "ciphertext": "<base64>",
    "signature": "<base64>"
  }
}
```

V2 has two HSM-backed modes:

- `hsm-direct` for small secrets at or below `HSM_DIRECT_SECRET_MAX_BYTES`.
- `wrapped-dek` for bulk payloads, where a per-envelope DEK encrypts the body and the HSM wraps/unwraps that DEK.

Both modes sign canonical envelope metadata/body. The local development provider is software-backed so tests can run without HSM provisioning; production must bind a real HSM client.

## Usage

```php
use App\Services\Storage\KeyEnvelope;

$ciphertext = app(KeyEnvelope::class)->encrypt('hello');
$plaintext = app(KeyEnvelope::class)->decrypt($ciphertext);
```

`FEATURE_PQC=true` makes `encrypt()` write v2. With the flag off, default writes remain v1 for local development and rollback safety. Call sites continue to use the same `encrypt()` / `decrypt()` methods.

## Rewrap

`php artisan envelopes:rewrap --target=2` streams known stored database envelopes through `decrypt()` and `encryptForVersion(..., 2)`. It records each row in `crypto_rotations` with from/to metadata, status, idempotency key, and an audit event. Rows already at the target version are skipped and recorded as such.

`php artisan hsm:rotate-kek` rotates the active HSM key reference and records the event in `crypto_rotations`; run envelope rewrap after rotation to move stored envelopes to the new key reference.

## Current Envelope Sources

- Secure file storage on the `secure_local` disk.
- MFA factor secrets and recovery codes.
- Signed T&C PDF hashes.
- Accounting/NZ tool integration tokens.
- Proposal signature evidence hashes and payment authority tokens.
- Receipt and panel agreement PDF hashes.

Postgres disk encryption, backups, and TLS remain operational controls outside `KeyEnvelope`.
