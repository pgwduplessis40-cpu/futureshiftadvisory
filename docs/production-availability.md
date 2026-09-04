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
    - `GET /` contains the server-rendered marker.
2. **Immediate alerting** — configure a dedicated uptime monitor at one-minute
   intervals against `https://futureshiftadvisory.nz/up`. It must notify the
   on-call owner directly. GitHub schedule runs can be delayed, so they are a
   backstop rather than the paging system.
3. **Host supervision** — nginx and the configured PHP-FPM service must be
   enabled at boot. The `inertia-ssr` service must use `Restart=always`; see
   [deployment-ssr.md](deployment-ssr.md).
4. **Release verification** — every production release runs the same external
   probe after deployment and fails if the public edge is unavailable.

## GitHub configuration

Set repository variable `PRODUCTION_AVAILABILITY_MONITOR_ENABLED` to `true`.
The existing `PRODUCTION_URL` secret is required. Add the optional
`PRODUCTION_AVAILABILITY_ALERT_WEBHOOK` secret to send a JSON payload of the
form `{ "text": "..." }` to the incident channel. Use an endpoint owned by
the operations team; never put a webhook URL in the repository.

Enable GitHub notification for failed workflows for each production owner even
when the webhook is configured.

## Incident response

Treat two failed public probes or any client-visible 5xx as a production
incident. Preserve the relevant logs before restarting services:

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
