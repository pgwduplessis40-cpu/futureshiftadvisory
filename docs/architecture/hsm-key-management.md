# HSM Key Management

WO-118 closes SD-02 at the application boundary. `KeyEnvelope` v2 delegates key wrapping and small-secret encryption to `HsmKeyManager`, which is backed by an `HsmClient` binding.

## Binding

`AppServiceProvider` binds `HsmClient` from `config/hsm.php`:

- `HSM_DRIVER=software` or empty: local software fallback for development and tests.
- Any other driver: an `UnsupportedHsmClient` fails closed until infrastructure binds the real CloudHSM/Azure adapter.

The interface deliberately has no method that exports a KEK. The testable invariant is: the KEK is generated/held by the HSM driver and never leaves through the application interface.

## Envelope Paths

- Small payloads at or below `HSM_DIRECT_SECRET_MAX_BYTES` use HSM direct encrypt/decrypt (`mode: "hsm-direct"`).
- Bulk payloads use a per-envelope DEK for AES-256-GCM (`mode: "wrapped-dek"`). The DEK is wrapped/unwrapped by the HSM client and zeroed by `HsmKeyManager::zero()` after use.

The software fallback derives wrapping/direct keys from `HSM_SOFTWARE_KEY` or `APP_KEY`. It is for local development only; production must bind a real HSM client.

## Rotation

`php artisan hsm:rotate-kek --key-id=<id>` asks the active HSM driver to rotate the active KEK reference and records the result in `crypto_rotations` with `rotation_type: hsm_kek_rotation`. After rotation, operators run `php artisan envelopes:rewrap --target=2` so stored envelopes move to the new key reference.
