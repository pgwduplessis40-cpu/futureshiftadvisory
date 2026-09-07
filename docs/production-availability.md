# Production availability

The application cannot treat an in-process Laravel check as evidence that
clients can reach the site. nginx, PHP-FPM, TLS, DNS, or the host itself can
fail before a request reaches Laravel.

## Required monitoring layers

1. **Public-edge probe** — GitHub Actions runs
   `scripts/probe-production-availability.sh` every five minutes from outside
   the VPS. It requires all of the following to succeed:
    - `GET /up` returns HTTP 200;
    - `GET /api/deployment` returns a verified identity with both manifest
      hashes; and
    - `GET /` contains the server-rendered marker; and
    - `GET /sw.js` returns HTTP 200, JavaScript content, and `Cache-Control:
      no-store`.
2. **One-minute recovery watchdog** — every release installs the root-owned
   `futureshiftadvisory-edge-watchdog.timer`. It requests `/login` through
   nginx every minute, sends an incident webhook on the first failure, captures
   the HTTP response plus nginx/PHP-FPM status and logs, and restarts PHP-FPM
   only after two consecutive failures. Evidence is retained under
   `/var/lib/futureshiftadvisory-edge-watchdog/incidents`.
3. **Browser recovery** — the release-managed nginx snippet routes `/sw.js`
   through Laravel, prevents worker caching, and maps upstream 502/503/504
   responses to the non-cacheable `_fsa-reconnecting.html` page. It retries
   with a safe GET navigation and never sends `Clear-Site-Data`, so users are
   not signed out.
4. **Host supervision** — nginx and the configured PHP-FPM service must be
   enabled at boot. The `inertia-ssr` service must use `Restart=always`; see
   [deployment-ssr.md](deployment-ssr.md).
5. **Release verification** — every production release runs the same external
   probe after deployment and fails if the public edge or service worker is
   unavailable.

## GitHub configuration

Set repository variable `PRODUCTION_AVAILABILITY_MONITOR_ENABLED` to `true`.
The existing `PRODUCTION_URL` secret is required. Set
`PRODUCTION_AVAILABILITY_ALERT_WEBHOOK` to an HTTPS endpoint owned by the
operations team. It receives a JSON payload of the form `{ "text": "..." }`
from both GitHub's public-edge backstop and the one-minute host watchdog; never
put the webhook URL in the repository.

The deploy script finds the nginx server block whose `server_name` contains the
host from `SITE_URL`, then adds its managed include. If the host has an unusual
nginx layout, set `NGINX_SITE_CONFIG` to the active `/etc/nginx/...` server
configuration path before deployment. The script backs up a changed file,
runs `nginx -t`, and refuses to reload nginx if validation fails.

Enable GitHub notification for failed workflows for each production owner even
when the webhook is configured.

## Incident response

Treat any watchdog alert, two failed public probes, or a client-visible 5xx as
a production incident. The watchdog automatically preserves the first
30 minutes of nginx/PHP-FPM journal output, current service status, nginx
configuration validation, the public response, and available nginx logs before
its threshold-based PHP-FPM restart. For manual investigation:

```bash
sudo systemctl status nginx php-fpm inertia-ssr --no-pager --full
sudo journalctl -u nginx -u php-fpm -u inertia-ssr --since '30 minutes ago' --no-pager
sudo nginx -t
```

If nginx configuration is valid, restart the failed application service, then
verify both locally and from outside the host:

```bash
sudo systemctl restart php-fpm
sudo systemctl restart inertia-ssr
sudo systemctl reload nginx
curl -fsS http://127.0.0.1/up
curl -fsS https://futureshiftadvisory.nz/api/deployment
```

Use the actual PHP-FPM unit name on the host. Do not deploy or roll back until
the failure is identified from the preserved logs and the public probe passes.
