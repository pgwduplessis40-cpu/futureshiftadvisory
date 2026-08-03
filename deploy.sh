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
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php-fpm}"
SITE_URL="${SITE_URL:-https://futureshiftadvisory.nz}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-yes}"
CONFIGURE_SCHEDULER="${CONFIGURE_SCHEDULER:-yes}"
CRON_SERVICE="${CRON_SERVICE:-}"
EXPECTED_COMMIT="${DEPLOY_EXPECTED_COMMIT:-}"
EXPECTED_VERSION="${DEPLOY_EXPECTED_VERSION:-}"

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

# Only reach for sudo when not already running as root.
SUDO=""
if [ "$(id -u)" -ne 0 ]; then
    SUDO="sudo -n"
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

restore_generated_wayfinder_checkout() {
    local generated_paths=(resources/js/actions resources/js/routes)

    if [ -z "$(git status --porcelain -- "${generated_paths[@]}")" ]; then
        return
    fi

    echo "Resetting generated Wayfinder checkout drift."
    git restore --worktree --source=HEAD -- "${generated_paths[@]}"
    git clean -fd -- "${generated_paths[@]}"
}

clear_orphaned_git_index_lock() {
    local lock_path="$APP_DIR/.git/index.lock"

    if [ ! -e "$lock_path" ]; then
        return
    fi

    if ! command -v pgrep >/dev/null 2>&1; then
        echo "ERROR: .git/index.lock exists and pgrep is unavailable, so lock ownership cannot be checked." >&2
        exit 1
    fi

    if pgrep -x git >/dev/null 2>&1; then
        echo "ERROR: .git/index.lock exists while a Git process is running; refusing to remove it." >&2
        exit 1
    fi

    echo "Removing orphaned .git/index.lock left by an earlier Git process."
    rm -f -- "$lock_path"
}

release_version() {
    local tagged_version tag candidate fallback_version

    tag=""
    while IFS= read -r candidate; do
        if [[ "$candidate" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            tag="$candidate"
            break
        fi
    done < <(git tag --points-at HEAD --list 'v*' --sort=-v:refname)

    if [ -n "$EXPECTED_VERSION" ]; then
        if [ "$tag" != "v${EXPECTED_VERSION}" ]; then
            echo "ERROR: expected release tag v${EXPECTED_VERSION} on $(git rev-parse HEAD), found ${tag:-none}." >&2
            exit 1
        fi

        tagged_version="$EXPECTED_VERSION"
    elif [ -n "$tag" ]; then
        tagged_version="${tag#v}"
    else
        fallback_version="$(tr -d '\r\n' < VERSION)"
        tagged_version="$fallback_version"
    fi

    if [[ ! "$tagged_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo "ERROR: release version must use major.minor.patch format; found '${tagged_version}'." >&2
        exit 1
    fi

    printf '%s\n' "$tagged_version"
}

verify_expected_release() {
    local deployed_commit deployed_version

    deployed_commit="$(git rev-parse HEAD)"
    deployed_version="$(release_version)"

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

    version="$(release_version)"
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
    version="$(release_version)"
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

ensure_cron_daemon_running() {
    local cron_service candidates=()

    if [ "$CONFIGURE_SCHEDULER" != "yes" ]; then
        return
    fi

    command -v systemctl >/dev/null 2>&1 || {
        echo "ERROR: systemctl is required to verify the cron daemon for Laravel's scheduler." >&2
        exit 1
    }

    if [ -n "$CRON_SERVICE" ]; then
        candidates=("$CRON_SERVICE")
    else
        candidates=(crond cron cronie)
    fi

    for candidate in "${candidates[@]}"; do
        if systemctl cat "$candidate" >/dev/null 2>&1; then
            cron_service="$candidate"
            break
        fi
    done

    if [ -z "${cron_service:-}" ]; then
        echo "ERROR: no cron systemd unit was found; set CRON_SERVICE to the host's cron service name." >&2
        exit 1
    fi

    if ! systemctl is-active --quiet "$cron_service"; then
        echo "Starting cron service '${cron_service}' for Laravel scheduler."
        $SUDO systemctl enable --now "$cron_service"
    fi

    if ! systemctl is-active --quiet "$cron_service"; then
        echo "ERROR: cron service '${cron_service}' is not active; scheduled app checks will not run." >&2
        exit 1
    fi

    echo "Cron service '${cron_service}' is active."
}

configure_scheduler_cron() {
    local php_binary scheduler_line current_crontab updated_crontab

    if [ "$CONFIGURE_SCHEDULER" != "yes" ]; then
        echo "Skipping scheduler cron setup (CONFIGURE_SCHEDULER=$CONFIGURE_SCHEDULER)."
        return
    fi

    ensure_cron_daemon_running

    command -v crontab >/dev/null 2>&1 || {
        echo "ERROR: crontab is required to keep Laravel's scheduler running." >&2
        exit 1
    }

    php_binary="$(command -v php)"
    scheduler_line="* * * * * cd '${APP_DIR}' && '${php_binary}' artisan schedule:run --no-interaction >/dev/null 2>&1"
    current_crontab="$(mktemp)"
    updated_crontab="$(mktemp)"

    if ! crontab -l > "$current_crontab" 2>/dev/null; then
        : > "$current_crontab"
    fi

    awk '
        /^# BEGIN FUTURESHIFT SCHEDULER$/ { skip = 1; next }
        /^# END FUTURESHIFT SCHEDULER$/ { skip = 0; next }
        ! skip { print }
    ' "$current_crontab" > "$updated_crontab"

    printf '%s\n' \
        '# BEGIN FUTURESHIFT SCHEDULER' \
        "$scheduler_line" \
        '# END FUTURESHIFT SCHEDULER' \
        >> "$updated_crontab"

    crontab "$updated_crontab"
    rm -f -- "$current_crontab" "$updated_crontab"

    case "$(crontab -l)" in
        *"$scheduler_line"*) ;;
        *)
            echo "ERROR: Laravel scheduler cron entry could not be verified." >&2
            exit 1
            ;;
    esac

    php artisan schedule:run --no-interaction
    echo "Laravel scheduler cron entry is installed and verified."
}

ensure_malware_scanner() {
    local configured_service="${CLAMAV_SERVICE:-}"
    local wait_seconds="${CLAMAV_START_TIMEOUT_SECONDS:-180}"

    case "$wait_seconds" in
        ''|*[!0-9]*)
            echo "ERROR: CLAMAV_START_TIMEOUT_SECONDS must be a positive integer." >&2
            exit 1
            ;;
    esac

    if [ "$wait_seconds" -lt 1 ]; then
        echo "ERROR: CLAMAV_START_TIMEOUT_SECONDS must be at least 1." >&2
        exit 1
    fi

    start_clamav_service() {
        local target_service="$1"
        local elapsed=0

        echo "Starting ClamAV service '${target_service}'."
        if ! $SUDO systemctl enable --now "$target_service"; then
            echo "ERROR: ClamAV service '${target_service}' could not be enabled and started." >&2
            $SUDO systemctl status "$target_service" --no-pager --full || true
            $SUDO journalctl -u "$target_service" -n 40 --no-pager || true
            return 1
        fi

        while [ "$elapsed" -lt "$wait_seconds" ]; do
            if php artisan fsa:rescan-quarantined-documents --probe --limit=1; then
                echo "ClamAV service '${target_service}' is ready."
                return 0
            fi

            sleep 2
            elapsed=$((elapsed + 2))
        done

        echo "ERROR: ClamAV service '${target_service}' did not become ready within ${wait_seconds} seconds." >&2
        $SUDO systemctl status "$target_service" --no-pager --full || true
        $SUDO journalctl -u "$target_service" -n 40 --no-pager || true
        return 1
    }

    configure_clamav_daemon() {
        local clamd_config="/etc/clamd.d/scan.conf"
        local freshclam_config
        local updated_config

        if [ -f "$clamd_config" ]; then
            log "Configuring Enterprise Linux ClamAV daemon"
            updated_config="$(mktemp)"

            awk '
                /^# BEGIN FUTURESHIFT CLAMD$/ { skip = 1; next }
                /^# END FUTURESHIFT CLAMD$/ { skip = 0; next }
                skip { next }
                /^[[:space:]]*Example[[:space:]]*$/ { next }
                /^[[:space:]]*TCPSocket[[:space:]]+/ { next }
                /^[[:space:]]*TCPAddr[[:space:]]+/ { next }
                { print }
            ' "$clamd_config" > "$updated_config"

            printf '%s\n' \
                '# BEGIN FUTURESHIFT CLAMD' \
                'TCPSocket 3310' \
                'TCPAddr 127.0.0.1' \
                '# END FUTURESHIFT CLAMD' \
                >> "$updated_config"

            if ! $SUDO install -m 0644 "$updated_config" "$clamd_config"; then
                rm -f -- "$updated_config"
                echo "ERROR: ${clamd_config} could not be configured." >&2
                return 1
            fi

            rm -f -- "$updated_config"
        fi

        for freshclam_config in /etc/freshclam.conf /etc/clamav/freshclam.conf; do
            [ -f "$freshclam_config" ] || continue

            if ! $SUDO sed -i -E 's/^[[:space:]]*Example[[:space:]]*$/# Example/' "$freshclam_config"; then
                echo "ERROR: ${freshclam_config} could not be activated." >&2
                return 1
            fi
        done
    }

    clamav_signatures_ready() {
        local database

        for database in /var/lib/clamav/*.cvd /var/lib/clamav/*.cld; do
            if [ -s "$database" ]; then
                return 0
            fi
        done

        return 1
    }

    ensure_clamav_signatures() {
        local elapsed=0

        if ! command -v freshclam >/dev/null 2>&1; then
            return 0
        fi

        while [ "$elapsed" -lt 20 ]; do
            if clamav_signatures_ready; then
                return 0
            fi

            sleep 2
            elapsed=$((elapsed + 2))
        done

        log "Downloading initial ClamAV signatures"

        if systemctl cat clamav-freshclam >/dev/null 2>&1; then
            $SUDO systemctl stop clamav-freshclam || true
        fi

        if ! $SUDO freshclam --stdout && ! clamav_signatures_ready; then
            echo "ERROR: ClamAV signatures could not be downloaded." >&2
            return 1
        fi

        if systemctl cat clamav-freshclam >/dev/null 2>&1; then
            if ! $SUDO systemctl enable --now clamav-freshclam; then
                echo "ERROR: clamav-freshclam could not be enabled and started." >&2
                return 1
            fi
        fi

        if ! clamav_signatures_ready; then
            echo "ERROR: ClamAV signature database is still missing after freshclam completed." >&2
            return 1
        fi
    }

    install_clamav_daemon() {
        if [ "${INSTALL_CLAMAV:-yes}" != "yes" ]; then
            echo "Automatic ClamAV installation is disabled (INSTALL_CLAMAV=${INSTALL_CLAMAV:-})." >&2
            return 1
        fi

        log "Installing ClamAV daemon"

        if command -v apt-get >/dev/null 2>&1; then
            if ! $SUDO apt-get -o Acquire::Retries=3 -o DPkg::Lock::Timeout=120 update; then
                echo "ERROR: apt package indexes could not be refreshed." >&2
                return 1
            fi

            if ! $SUDO env DEBIAN_FRONTEND=noninteractive apt-get -o DPkg::Lock::Timeout=120 install -y clamav-daemon clamav-freshclam; then
                echo "ERROR: apt could not install clamav-daemon and clamav-freshclam." >&2
                return 1
            fi
        elif command -v dnf >/dev/null 2>&1; then
            if ! $SUDO dnf install -y clamav clamd clamav-update; then
                echo "ERROR: dnf could not install ClamAV." >&2
                return 1
            fi
        elif command -v yum >/dev/null 2>&1; then
            if ! $SUDO yum install -y clamav clamd clamav-update; then
                echo "ERROR: yum could not install ClamAV." >&2
                return 1
            fi
        else
            echo "ERROR: no supported package manager was found for automatic ClamAV installation." >&2
            return 1
        fi

        return 0
    }

    prepare_clamav_daemon() {
        configure_clamav_daemon && ensure_clamav_signatures
    }

    start_available_clamav_service() {
        local available_service

        for available_service in "$configured_service" clamav-daemon clamd@scan clamd; do
            [ -n "$available_service" ] || continue

            if systemctl cat "$available_service" >/dev/null 2>&1; then
                if start_clamav_service "$available_service"; then
                    return 0
                fi
            fi
        done

        return 1
    }

    if php artisan fsa:rescan-quarantined-documents --probe --limit=1; then
        return
    fi

    log "Starting local ClamAV daemon"
    prepare_clamav_daemon

    if start_available_clamav_service; then
        return
    fi

    install_clamav_daemon || {
        echo "ERROR: ClamAV could not be installed automatically." >&2
        echo "Install clamav-daemon or configure CLAMAV_SOCKET/CLAMAV_HOST and CLAMAV_PORT." >&2
        exit 1
    }

    prepare_clamav_daemon

    if start_available_clamav_service; then
        return
    fi

    echo "ERROR: no reachable ClamAV endpoint or local daemon service was found." >&2
    echo "Install clamav-daemon or configure CLAMAV_SOCKET/CLAMAV_HOST and CLAMAV_PORT." >&2
    exit 1
}

log "Checking deployment checkout"
clear_orphaned_git_index_lock
restore_generated_wayfinder_checkout
require_clean_checkout "before pulling code"

log "Pulling latest code"
# Do not use `git pull`: a server can have more than one branch.*.merge value,
# which makes pull attempt to fast-forward multiple branches despite a branch
# argument. FETCH_HEAD always represents this one explicit fetch.
GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_BRANCH="${GIT_BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
git fetch --tags "$GIT_REMOTE" "$GIT_BRANCH"
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

log "Ensuring entrepreneur rating framework"
php artisan db:seed --class=Database\\Seeders\\RatingFrameworkSeeder --force
php artisan db:seed --class=Database\\Seeders\\FoundingRatingFrameworkValuesSeeder --force

log "Refreshing caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Configuring Laravel scheduler"
configure_scheduler_cron

log "Verifying malware scanner and recovering quarantined documents"
ensure_malware_scanner
php artisan fsa:rescan-quarantined-documents --probe --limit=1000

log "Running authenticated operational health checks"
php artisan fsa:operational-health-check --ensure-fixtures

log "Restarting PHP-FPM"
# PHP-FPM may retain PHP bytecode even after the release checkout and Laravel
# caches have changed. Restart it before validating the public site so every
# web request runs the released application code.
if systemctl cat "$PHP_FPM_SERVICE" >/dev/null 2>&1; then
    $SUDO systemctl restart "$PHP_FPM_SERVICE"
    echo "Restarted ${PHP_FPM_SERVICE}."
else
    echo "ERROR: PHP-FPM systemd unit '${PHP_FPM_SERVICE}' was not found." >&2
    exit 1
fi

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
