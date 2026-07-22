# Production SSR (server-side rendering)

The public marketing site depends on SSR for search and AI-answer-engine visibility.
Without it the server returns an empty shell: no `<h1>`, no body copy, no per-page
title/meta/canonical, and no JSON-LD. Crawlers that do not execute JavaScript -
including several AI answer engines - see nothing.

## Why local looks fine but production does not

Locally the **Vite dev server** is running, and `@inertiajs/vite` provides SSR through
it. Production has no dev server, so it needs a **built SSR bundle** plus a **running
SSR process**. If either is missing, Inertia silently falls back to client-side
rendering - the site still works for humans, but is invisible to non-JS crawlers.

Verify which mode a deployed site is in:

```bash
curl -s https://futureshiftadvisory.nz/ | grep -c 'data-server-rendered'
```

`1` = SSR is on. `0` = client-side only (the SEO/AI work is not being served).

## What production needs

### 1. Build the SSR bundle at deploy time

Replace `npm run build` with:

```bash
npm run build:ssr
```

This runs the normal client build **and** `vite build --ssr`, writing the bundle to
`bootstrap/ssr/app.js`. That path is set in `config/inertia.php` (`ssr.bundle`).
`bootstrap/ssr/` is git-ignored - it is built on the server, like `public/build`.

### 2. Run the SSR process persistently

```bash
php artisan inertia:start-ssr
```

It listens on `http://127.0.0.1:13714` (see `config/inertia.php`). It must run as a
supervised daemon so it survives crashes and reboots.

**Laravel Forge:** Site → Daemons → New Daemon
- Command: `php artisan inertia:start-ssr`
- Directory: the site root (e.g. `/home/forge/futureshiftadvisory.nz`)
- User: `forge`

**Ploi:** Site → Daemons → same command and directory.

**systemd (plain VPS)** - `/etc/systemd/system/inertia-ssr.service`:

```ini
[Unit]
Description=Inertia SSR (futureshiftadvisory.nz)
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/futureshiftadvisory
ExecStart=/usr/bin/php artisan inertia:start-ssr
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now inertia-ssr
```

**Supervisor** - `/etc/supervisor/conf.d/inertia-ssr.conf`:

```ini
[program:inertia-ssr]
command=php artisan inertia:start-ssr
directory=/var/www/futureshiftadvisory
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/inertia-ssr.log
```

### 3. Restart SSR after every deploy

The running process holds the **old** bundle in memory. Add to the end of the deploy
script, after `npm run build:ssr`:

```bash
php artisan inertia:stop-ssr || true
```

The supervisor/daemon restarts it automatically, picking up the new bundle. Without
this step a deploy silently serves stale pages.

## Deploying

Use the script in the repo root - it performs every step in the right order and
**fails loudly if the deployed site is not server-rendered**, so a broken SSR
process cannot pass unnoticed:

```bash
./deploy.sh
```

Options (environment variables):

| Variable | Default | Purpose |
|---|---|---|
| `RUN_MIGRATIONS` | `yes` | `RUN_MIGRATIONS=no ./deploy.sh` to skip `migrate --force` |
| `SITE_URL` | `https://futureshiftadvisory.nz` | URL used for the post-deploy SSR check |
| `SSR_SERVICE` | `inertia-ssr` | systemd unit name to restart |

The script runs: pull → composer install → npm ci → **`npm run build:ssr`** →
migrations → cache refresh → **restart the SSR daemon** → verify SSR output.

Deploying by hand is possible but easy to get wrong: pulling alone updates PHP
instantly while the SSR process keeps serving the previous JavaScript bundle,
so page copy, titles and structured data silently go stale. Prefer the script.

## Verifying after deploy

```bash
curl -s https://futureshiftadvisory.nz/ | grep -c 'data-server-rendered'   # expect 1
curl -s https://futureshiftadvisory.nz/faq | grep -c 'FAQPage'            # expect 1
curl -s https://futureshiftadvisory.nz/ | grep -o '<title>[^<]*</title>'  # page-specific title
```

If the SSR process is down, pages still render for humans - so monitor it explicitly
rather than relying on the site "looking fine".
