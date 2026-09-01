# Browser E2E secret requirements

The three `E2E_*_MFA_SECRET` repository secrets must be distinct base32 TOTP
secrets with at least 128 bits of entropy (26 unpadded base32 characters or
more). The browser fixture rejects shorter values so the quality gate uses the
same TOTP strength enforced by the current `otplib` release.

Generate secrets with the approved password/secret-management process; do not
place live secret values in this repository.
