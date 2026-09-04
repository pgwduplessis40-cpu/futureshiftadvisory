#!/usr/bin/env bash

# Probe the public edge from outside the production host.  This deliberately
# does not use Laravel's in-process health checks: an nginx/PHP-FPM outage can
# leave those checks looking healthy while clients receive a 502.

set -euo pipefail

production_url="${PRODUCTION_URL:-}"
expected_commit="${EXPECTED_COMMIT:-}"
expected_version="${EXPECTED_VERSION:-}"
node_binary="${NODE_BINARY:-node}"

if [ -z "$production_url" ]; then
    echo "ERROR: PRODUCTION_URL is required." >&2
    exit 64
fi

production_url="${production_url%/}"

request() {
    local path="$1"

    curl \
        --fail \
        --silent \
        --show-error \
        --location \
        --connect-timeout 10 \
        --max-time 20 \
        --retry 2 \
        --retry-delay 2 \
        "$production_url$path"
}

echo "Checking public Laravel health endpoint."
request '/up' >/dev/null

echo "Checking verified deployment identity."
deployment="$(request '/api/deployment')"

printf '%s' "$deployment" | "$node_binary" -e '
    const payload = JSON.parse(require("fs").readFileSync(0, "utf8"));
    const [expectedCommit, expectedVersion] = process.argv.slice(1);
    const valid = payload.status === "verified"
        && typeof payload.commit === "string"
        && payload.commit.length === 40
        && typeof payload.version === "string"
        && payload.version.length > 0
        && typeof payload.client_manifest_sha256 === "string"
        && payload.client_manifest_sha256.length === 64
        && typeof payload.ssr_manifest_sha256 === "string"
        && payload.ssr_manifest_sha256.length === 64
        && (!expectedCommit || payload.commit === expectedCommit)
        && (!expectedVersion || payload.version === expectedVersion);

    if (!valid) {
        process.exit(1);
    }
' "$expected_commit" "$expected_version"

echo "Checking the public server-rendered home page."
homepage="$(request '/')"
if ! printf '%s' "$homepage" | "$node_binary" -e '
    const html = require("fs").readFileSync(0, "utf8");
    process.exit(html.includes("data-server-rendered") ? 0 : 1);
'; then
    echo "ERROR: the public home page was reachable but was not server-rendered." >&2
    exit 1
fi

echo "OK: public health, deployment identity, and SSR are available."
