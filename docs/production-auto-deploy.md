# Production Auto Deploy

Pushing to `main` does not deploy code by itself. Once this workflow is configured, a normal `main` push follows this sequence:

1. GitHub increments `VERSION` and commits the release marker.
2. GitHub connects to the VPS with the dedicated deployment account.
3. `deploy.sh` checks out that exact commit, builds the client and SSR bundles, runs migrations, restarts SSR, and verifies SSR.
4. The script writes `storage/app/deployment.json` only after those checks pass.
5. GitHub requests `https://futureshiftadvisory.nz/api/deployment` and fails the workflow unless its commit and version match the release.

The endpoint is deliberately public and contains only release identity and manifest hashes. It has `Cache-Control: no-store`, so neither the PWA nor an intermediary cache can present it as a cached deployment result.

## One-time GitHub configuration

Add the following **Actions secrets** to the repository:

| Secret | Value |
|---|---|
| `PRODUCTION_SSH_HOST` | VPS hostname or IP address |
| `PRODUCTION_SSH_PORT` | SSH port, normally `22` |
| `PRODUCTION_SSH_USER` | Dedicated deploy user |
| `PRODUCTION_SSH_PRIVATE_KEY` | Private key for that deploy user |
| `PRODUCTION_SSH_KNOWN_HOSTS` | Exact `known_hosts` line for the VPS, obtained from a trusted connection |
| `PRODUCTION_APP_PATH` | `/var/www/futureshiftadvisory` |
| `PRODUCTION_URL` | `https://futureshiftadvisory.nz` |

Create the repository variable `PRODUCTION_DEPLOY_ENABLED` with value `true` only after every secret is present. Until then, the production deploy job is skipped rather than pretending to have deployed anything.

The deploy user needs write access to the application checkout and permission to restart the SSR service through `sudo systemctl restart inertia-ssr`. Do not use the VPS root password or place it in GitHub.

## Confirming a release

After GitHub marks `deploy-production` successful, open:

```text
https://futureshiftadvisory.nz/api/deployment
```

The response must be HTTP 200 and contain `status: "verified"`, the same `commit` shown in the GitHub workflow, and the release `version`. A 503 or a mismatched commit means the production deployment did not complete and the PWA should be treated as stale until the failed workflow is fixed.
