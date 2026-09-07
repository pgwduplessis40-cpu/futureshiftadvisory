#!/usr/bin/env bash

# Root-owned systemd watchdog for the public nginx edge. This deliberately
# does not call Laravel: PHP-FPM can be unavailable precisely when recovery is
# required. deploy.sh installs the unit and supplies the private state path.

set -euo pipefail

site_url="${EDGE_WATCHDOG_URL:-https://futureshiftadvisory.nz/login}"
php_fpm_service="${EDGE_WATCHDOG_PHP_FPM_SERVICE:-php-fpm}"
nginx_service="${EDGE_WATCHDOG_NGINX_SERVICE:-nginx}"
failure_threshold="${EDGE_WATCHDOG_FAILURE_THRESHOLD:-2}"
state_dir="${EDGE_WATCHDOG_STATE_DIR:-/var/lib/futureshiftadvisory-edge-watchdog}"
alert_webhook="${EDGE_WATCHDOG_ALERT_WEBHOOK:-}"
nginx_error_log="${EDGE_WATCHDOG_NGINX_ERROR_LOG:-/var/log/nginx/error.log}"
nginx_access_log="${EDGE_WATCHDOG_NGINX_ACCESS_LOG:-/var/log/nginx/access.log}"
incident_retention_days="${EDGE_WATCHDOG_INCIDENT_RETENTION_DAYS:-14}"

case "$failure_threshold" in
    ''|*[!0-9]*)
        echo "EDGE_WATCHDOG_FAILURE_THRESHOLD must be a positive integer." >&2
        exit 64
        ;;
esac

if [ "$failure_threshold" -lt 2 ]; then
    echo "EDGE_WATCHDOG_FAILURE_THRESHOLD must be at least 2." >&2
    exit 64
fi

case "$incident_retention_days" in
    ''|*[!0-9]*)
        echo "EDGE_WATCHDOG_INCIDENT_RETENTION_DAYS must be a non-negative integer." >&2
        exit 64
        ;;
esac

case "$php_fpm_service" in
    ''|*[!A-Za-z0-9@._-]*)
        echo "EDGE_WATCHDOG_PHP_FPM_SERVICE is not a safe systemd unit name." >&2
        exit 64
        ;;
esac

case "$nginx_service" in
    ''|*[!A-Za-z0-9@._-]*)
        echo "EDGE_WATCHDOG_NGINX_SERVICE is not a safe systemd unit name." >&2
        exit 64
        ;;
esac

umask 077
mkdir -p "$state_dir/incidents"
find "$state_dir/incidents" -type f -mtime "+${incident_retention_days}" -delete
find "$state_dir/incidents" -type d -empty -delete
state_file="$state_dir/consecutive_failures"

notify() {
    local message="$1"

    logger -t futureshiftadvisory-edge-watchdog -- "$message" || true

    if [ -z "$alert_webhook" ]; then
        return
    fi

    case "$alert_webhook" in
        https://*) ;;
        *)
            echo "EDGE_WATCHDOG_ALERT_WEBHOOK must use https." >&2
            return
            ;;
    esac

    local payload
    payload="$(printf '%s' "$message" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' -e ':a;N;$!ba;s/\n/\\n/g')"

    curl --fail --silent --show-error --max-time 15 \
        -H 'Content-Type: application/json' \
        --data "{\"text\":\"${payload}\"}" \
        "$alert_webhook" \
        >/dev/null || echo "Unable to deliver edge watchdog alert." >&2
}

read_failure_count() {
    if [ ! -f "$state_file" ]; then
        printf '0\n'
        return
    fi

    local value
    value="$(tr -d '[:space:]' < "$state_file")"
    case "$value" in
        ''|*[!0-9]*) printf '0\n' ;;
        *) printf '%s\n' "$value" ;;
    esac
}

capture_incident() {
    local incident_dir="$1"
    local status="$2"
    local curl_exit="$3"
    local headers="$4"
    local body="$5"

    mkdir -p "$incident_dir"
    printf 'checked_at=%s\nurl=%s\nhttp_status=%s\ncurl_exit=%s\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        "$site_url" \
        "$status" \
        "$curl_exit" \
        > "$incident_dir/summary.txt"
    cp "$headers" "$incident_dir/response-headers.txt" 2>/dev/null || true
    cp "$body" "$incident_dir/response-body.txt" 2>/dev/null || true
    systemctl status "$nginx_service" "$php_fpm_service" --no-pager --full \
        > "$incident_dir/service-status.txt" 2>&1 || true
    journalctl -u "$nginx_service" -u "$php_fpm_service" --since '30 minutes ago' --no-pager \
        > "$incident_dir/journal.txt" 2>&1 || true
    nginx -t > "$incident_dir/nginx-config-test.txt" 2>&1 || true

    if [ -r "$nginx_error_log" ]; then
        tail -n 400 "$nginx_error_log" > "$incident_dir/nginx-error.log" 2>&1 || true
    fi

    if [ -r "$nginx_access_log" ]; then
        tail -n 400 "$nginx_access_log" > "$incident_dir/nginx-access.log" 2>&1 || true
    fi
}

headers="$(mktemp)"
body="$(mktemp)"
cleanup() {
    rm -f -- "$headers" "$body"
}
trap cleanup EXIT

set +e
status="$(curl --silent --show-error --location --connect-timeout 10 --max-time 20 \
    --dump-header "$headers" --output "$body" --write-out '%{http_code}' "$site_url")"
curl_exit=$?
set -e

if [ "$curl_exit" -eq 0 ] && [ "$status" = '200' ]; then
    rm -f -- "$state_file"
    exit 0
fi

failure_count=$(( $(read_failure_count) + 1 ))
printf '%s\n' "$failure_count" > "$state_file"
incident_dir="$state_dir/incidents/$(date -u +%Y%m%dT%H%M%SZ)-failure-${failure_count}"
capture_incident "$incident_dir" "$status" "$curl_exit" "$headers" "$body"

if [ "$failure_count" -eq 1 ]; then
    notify "Future Shift Advisory public /login check failed (HTTP ${status:-none}, curl ${curl_exit}). Evidence: ${incident_dir}. PHP-FPM will only restart after ${failure_threshold} consecutive failures."
fi

if [ "$failure_count" -eq "$failure_threshold" ]; then
    if systemctl restart "$php_fpm_service"; then
        systemctl is-active --quiet "$php_fpm_service" || {
            notify "Future Shift Advisory restarted ${php_fpm_service} after ${failure_count} public-edge failures, but the service is not active. Evidence: ${incident_dir}."
            exit 1
        }

        notify "Future Shift Advisory restarted ${php_fpm_service} after ${failure_count} consecutive public /login failures. Evidence and pre-restart nginx/PHP-FPM logs: ${incident_dir}."
    else
        notify "Future Shift Advisory could not restart ${php_fpm_service} after ${failure_count} consecutive public /login failures. Evidence: ${incident_dir}."
        exit 1
    fi
fi
