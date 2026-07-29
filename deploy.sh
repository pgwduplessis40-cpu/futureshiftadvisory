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
EXPECTED_COMMIT="${DEPLOY_EXPECTED_COMMIT:-}"
EXPECTED_VERSION="${DEPLOY_EXPECTED_VERSION:-}"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

# Only reach for sudo when not already running as root.
SUDO=""
if [ "$(id -u)" -ne 0 ]; then
    SUDO="sudo"
fi

require_clean_checkout() {
    local stage="$1"

    if [ -n "$(git status --porcelain)" ]; then
        echo "ERROR: deployment checkout is dirty ${stage}."
        echo "Deploy from a clean Git checkout so every release is traceable and reproducible."
        git status --short

        if git status --porcelain -- deploy.sh | grep -q '^.[MADRCU] deploy\.sh$'; then
            echo "deploy.sh diff summary:"
            git diff --summary -- deploy.sh || true
            echo "deploy.sh diff ignoring line endings:"
            git diff --ignore-space-at-eol -- deploy.sh || true
            echo "deploy.sh diff size:"
            git diff --numstat -- deploy.sh || true
        fi

        exit 1
    fi
}

verify_expected_release() {
    local deployed_commit deployed_version

    deployed_commit="$(git rev-parse HEAD)"
    deployed_version="$(tr -d '\r\n' < VERSION)"

    if [ -n "$EXPECTED_COMMIT" ] && [ "$deployed_commit" != "$EXPECTED_COMMIT" ]; then
        echo "ERROR: expected commit ${EXPECTED_COMMIT}, checked out ${deployed_commit}." >&2
        exit 1
    fi

    if [ -n "$EXPECTED_VERSION" ] && [ "$deployed_version" != "$EXPECTED_VERSION" ]; then
        echo "ERROR: expected version ${EXPECTED_VERSION}, checked out ${deployed_version}." >&2
        exit 1
    fi
}

record_deployment_identity() {
    local version commit deployed_at client_manifest ssr_manifest metadata_path metadata_tmp

    version="$(tr -d '\r\n' < VERSION)"
    commit="$(git rev-parse HEAD)"
    deployed_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    client_manifest="public/build/manifest.json"
    ssr_manifest="bootstrap/ssr/ssr-manifest.json"
    metadata_path="$APP_DIR/storage/app/deployment.json"
    metadata_tmp="${metadata_path}.tmp.$$"

    [ -f "$client_manifest" ] || { echo "ERROR: client manifest is missing." >&2; exit 1; }
    [ -f "$ssr_manifest" ] || { echo "ERROR: SSR manifest is missing." >&2; exit 1; }

    mkdir -p "$(dirname "$metadata_path")"
    printf '{\n  "version": "%s",\n  "commit": "%s",\n  "deployed_at": "%s",\n  "client_manifest_sha256": "%s",\n  "ssr_manifest_sha256": "%s"\n}\n' \
        "$version" \
        "$commit" \
        "$deployed_at" \
        "$(sha256sum "$client_manifest" | awk '{print $1}')" \
        "$(sha256sum "$ssr_manifest" | awk '{print $1}')" \
        > "$metadata_tmp"
    mv "$metadata_tmp" "$metadata_path"
}

verify_live_deployment_identity() {
    local commit version payload

    commit="$(git rev-parse HEAD)"
    version="$(tr -d '\r\n' < VERSION)"
    payload="$(curl -fsS --max-time 20 "$SITE_URL/api/deployment")" || {
        echo "ERROR: live deployment identity endpoint did not return successfully." >&2
        exit 1
    }

    case "$payload" in
        *"\"status\":\"verified\""*"\"version\":\"${version}\""*"\"commit\":\"${commit}\""*) ;;
        *)
            echo "ERROR: live deployment identity does not match the checked-out release." >&2
            echo "$payload" >&2
            exit 1
            ;;
    esac
}

log "Checking deployment checkout"
require_clean_checkout "before pulling code"

log "Pulling latest code"
# Do not use `git pull`: a server can have more than one branch.*.merge value,
# which makes pull attempt to fast-forward multiple branches despite a branch
# argument. FETCH_HEAD always represents this one explicit fetch.
GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_BRANCH="${GIT_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
git fetch "$GIT_REMOTE" "$GIT_BRANCH"
git merge --ff-only FETCH_HEAD
verify_expected_release

log "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

log "Installing Node dependencies"
npm ci

log "Building client + SSR bundles"
# Must be build:ssr - plain `npm run build` omits bootstrap/ssr/app.js,
# which leaves the SSR process with nothing to render.
# Generated Wayfinder helpers are committed with the application source. Build
# them locally when routes/controllers change, but never rewrite or normalize
# them in the production checkout.
WAYFINDER_GENERATE=false npm run build:ssr:production

log "Checking build output"
# Vite/Wayfinder may regenerate tracked route helpers. Treat that as a release
# defect, not a server-only change that will make the next deploy unpredictable.
require_clean_checkout "after building"

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
# Detect the unit with `systemctl cat`, which needs no pipeline. Piping into
# `grep -q` looks equivalent but breaks under `set -o pipefail`: grep exits on
# the first match, systemctl dies of SIGPIPE (141), and an existing unit is
# misreported as missing - which would then stop SSR instead of restarting it.
if systemctl cat "$SSR_SERVICE" >/dev/null 2>&1; then
    $SUDO systemctl restart "$SSR_SERVICE"
    echo "Restarted ${SSR_SERVICE}."
else
    echo "systemd unit '${SSR_SERVICE}' not found - see docs/deployment-ssr.md."
    echo "Starting SSR in the background; it will not survive a reboot."
    php artisan inertia:stop-ssr >/dev/null 2>&1 || true
    nohup php artisan inertia:start-ssr >/dev/null 2>&1 &
fi

log "Verifying the site is server-rendered"
# Poll rather than sleeping once: a restarted daemon needs a few seconds
# (RestartSec plus PHP and Node start-up) before it serves rendered HTML.
ssr_ok=no
for attempt in 1 2 3 4 5; do
    sleep 3
    if curl -fsS --max-time 20 "$SITE_URL/" 2>/dev/null | grep -q 'data-server-rendered'; then
        ssr_ok=yes
        break
    fi
    echo "  not ready yet (attempt ${attempt}/5)..."
done

if [ "$ssr_ok" = "yes" ]; then
    log "Recording deployed release"
    record_deployment_identity

    log "Verifying deployed release"
    verify_live_deployment_identity

    echo "OK - pages are server-rendered and visible to crawlers."
else
    echo "WARNING: ${SITE_URL} is NOT server-rendered."
    echo "Humans will see the site, but search engines and AI answer engines"
    echo "will receive an empty shell with no copy, titles or structured data."
    echo "Check: systemctl status ${SSR_SERVICE}"
    exit 1
fi

log "Deploy complete"
