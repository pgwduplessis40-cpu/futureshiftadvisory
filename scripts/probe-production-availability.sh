#!/usr/bin/env bash

# Probe the public edge from outside the production host.  This deliberately
# does not use Laravel's in-process health checks: an nginx/PHP-FPM outage can
# leave those checks looking healthy while clients receive a 502.

set -euo pipefail

production_url="${PRODUCTION_URL:-}"
expected_commit="${EXPECTED_COMMIT:-}"
expected_version="${EXPECTED_VERSION:-}"

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

jq_filter='
    .status == "verified"
    and (.commit | type == "string" and length == 40)
    and (.version | type == "string" and length > 0)
    and (.client_manifest_sha256 | type == "string" and length == 64)
    and (.ssr_manifest_sha256 | type == "string" and length == 64)
'

if [ -n "$expected_commit" ]; then
    jq_filter+=" and .commit == \$expected_commit"
fi

if [ -n "$expected_version" ]; then
    jq_filter+=" and .version == \$expected_version"
fi

jq -e \
    --arg expected_commit "$expected_commit" \
    --arg expected_version "$expected_version" \
    "$jq_filter" <<<"$deployment" >/dev/null

echo "Checking the public server-rendered home page."
homepage="$(request '/')"
if ! grep --fixed-strings --quiet 'data-server-rendered' <<<"$homepage"; then
    echo "ERROR: the public home page was reachable but was not server-rendered." >&2
    exit 1
fi

echo "OK: public health, deployment identity, and SSR are available."
