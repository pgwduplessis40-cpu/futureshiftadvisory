# Production Auto Deploy

Pushing to `main` does not deploy code by itself. Once this workflow is configured, a normal `main` push follows this sequence:

1. GitHub waits for `quality` and every PHP test-matrix check on the source commit. Any failed, cancelled, missing, or timed-out check stops the release.
2. GitHub creates an immutable `vMAJOR.MINOR.PATCH` tag on that exact tested commit. It does not create a follow-up commit on `main`.
3. GitHub connects to the VPS with the dedicated deployment account.
4. `deploy.sh` checks out that exact commit, builds the client and SSR bundles, runs migrations, installs and verifies the Laravel scheduler systemd timer, runs the authenticated operational-health checks, restarts PHP-FPM and SSR, and verifies SSR.
5. The script writes `storage/app/deployment.json` only after those checks pass. Its version comes from the release tag, which also drives the PWA cache identity.
6. GitHub requests `https://futureshiftadvisory.nz/api/deployment` and fails the workflow unless its commit and version match the release.
7. GitHub then probes `/up`, `/api/deployment`, and the server-rendered public
   home page from outside the production host. A release is not considered
   verified until that external probe passes.

The endpoint is deliberately public and contains only release identity and manifest hashes. It has `Cache-Control: no-store`, so neither the PWA nor an intermediary cache can present it as a cached deployment result.

See [production-availability.md](production-availability.md) for the ongoing
public-edge monitor, incident alerts, and host recovery procedure.

## One-time GitHub configuration

Add the following **Actions secrets** to the repository:

| Secret                       | Value                                                                    |
| ---------------------------- | ------------------------------------------------------------------------ |
| `PRODUCTION_SSH_HOST`        | VPS hostname or IP address                                               |
| `PRODUCTION_SSH_PORT`        | SSH port, normally `22`                                                  |
| `PRODUCTION_SSH_USER`        | Dedicated deploy user                                                    |
| `PRODUCTION_SSH_PRIVATE_KEY` | Private key for that deploy user                                         |
| `PRODUCTION_SSH_KNOWN_HOSTS` | Exact `known_hosts` line for the VPS, obtained from a trusted connection |
| `PRODUCTION_APP_PATH`        | `/var/www/futureshiftadvisory`                                           |
| `PRODUCTION_URL`             | `https://futureshiftadvisory.nz`                                         |

Create the repository variable `PRODUCTION_DEPLOY_ENABLED` with value `true` only after every secret is present. Until then, the production deploy job is skipped rather than pretending to have deployed anything. The release cannot proceed unless the existing `quality`, `ci (8.4)`, and `ci (8.5)` check-runs all pass for the source commit.

The deploy user needs write access to the application checkout and permission to install the scheduler unit files in `/etc/systemd/system`, run `systemctl daemon-reload`, enable/restart/start the scheduler timer/service, and restart the PHP-FPM and SSR services through passwordless sudo. Do not use the VPS root password or place it in GitHub.

## Confirming a release

After GitHub marks `deploy-production` successful, open:

```text
https://futureshiftadvisory.nz/api/deployment
```

The response must be HTTP 200 and contain `status: "verified"`, the same `commit` shown in the GitHub workflow, and the release `version`. A 503 or a mismatched commit means the production deployment did not complete and the PWA should be treated as stale until the failed workflow is fixed.

For the first verification after configuration, use **Actions -> release version -> Run workflow** on `main`. It runs the same CI gate and deployment checks without requiring a product-code change.
