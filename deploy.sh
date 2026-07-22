#!/usr/bin/env bash
#
# Production deploy for futureshiftadvisory.nz
#
# Usage, from the site root on the VPS:
#   ./deploy.sh
#
# Why this exists: `git pull` alone updates PHP immediately, but the public
# pages are rendered by a long-running SSR process that holds a compiled
# JavaScript bundle in memory. Without BOTH a rebuild and a restart, the site
# keeps serving stale pages - and if the SSR process is missing entirely it
# silently falls back to client-side rendering, which looks fine to a human
# and leaves crawlers and AI answer engines with an empty shell.
#
# The final step verifies server-rendered output and exits non-zero if it is
# missing, so that failure is loud rather than invisible.
#
# See docs/deployment-ssr.md for the one-time daemon setup.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

SSR_SERVICE="${SSR_SERVICE:-inertia-ssr}"
SITE_URL="${SITE_URL:-https://futureshiftadvisory.nz}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-yes}"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

# Only reach for sudo when not already running as root.
SUDO=""
if [ "$(id -u)" -ne 0 ]; then
    SUDO="sudo"
fi

log "Pulling latest code"
git pull --ff-only

log "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

log "Installing Node dependencies"
npm ci

log "Building client + SSR bundles"
# Must be build:ssr - plain `npm run build` omits bootstrap/ssr/app.js,
# which leaves the SSR process with nothing to render.
npm run build:ssr

if [ "$RUN_MIGRATIONS" = "yes" ]; then
    log "Running migrations"
    php artisan migrate --force
else
    log "Skipping migrations (RUN_MIGRATIONS=$RUN_MIGRATIONS)"
fi

log "Refreshing caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Restarting SSR process"
if systemctl list-unit-files 2>/dev/null | grep -q "^${SSR_SERVICE}\.service"; then
    $SUDO systemctl restart "$SSR_SERVICE"
    echo "Restarted ${SSR_SERVICE}."
else
    echo "systemd unit '${SSR_SERVICE}' not found."
    echo "Falling back to stopping the SSR process so it reloads on next boot."
    echo "For persistent SSR, see docs/deployment-ssr.md."
    php artisan inertia:stop-ssr || true
fi

log "Verifying the site is server-rendered"
sleep 3
if curl -fsS --max-time 20 "$SITE_URL/" | grep -q 'data-server-rendered'; then
    echo "OK - pages are server-rendered and visible to crawlers."
else
    echo "WARNING: ${SITE_URL} is NOT server-rendered."
    echo "Humans will see the site, but search engines and AI answer engines"
    echo "will receive an empty shell with no copy, titles or structured data."
    echo "Check: systemctl status ${SSR_SERVICE}"
    exit 1
fi

log "Deploy complete"
