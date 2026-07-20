# Client Screen Support Test Environment

This runbook enables the native, view-only WebRTC screen-support feature in the
test environment. It does not enable recording, remote control, a data channel,
or a media endpoint in the Laravel application. SDP and ICE signaling pass
through Reverb; screen media is peer-to-peer or, when necessary, through the
FSA-operated coturn relay.

## Prerequisites

- Test runs behind HTTPS with a browser-trusted certificate. getDisplayMedia
  will not run in an insecure context.
- A Reverb process, a dedicated realtime queue worker, and the Laravel
  scheduler are supervised independently from the web process.
- coturn is on FSA-controlled infrastructure with a trusted TLS certificate,
  UDP 3478 and TLS 5349 reachable from the internet. Use a separate public IP
  if port 443 is also required for HTTPS.
- Generate distinct test secrets. Never reuse live Reverb or TURN secrets.

## Test Environment Configuration

Set these values in the test environment's secure .env file, then build
assets so the VITE values are compiled into the browser bundle.

~~~dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<test-app-id>
REVERB_APP_KEY=<test-app-key>
REVERB_APP_SECRET=<test-app-secret>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=<test-domain>
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=https://<test-domain>
VITE_REVERB_APP_KEY="\${REVERB_APP_KEY}"
VITE_REVERB_HOST="\${REVERB_HOST}"
VITE_REVERB_PORT="\${REVERB_PORT}"
VITE_REVERB_SCHEME="\${REVERB_SCHEME}"

SCREEN_SHARE_REQUEST_TIMEOUT_SECONDS=60
SCREEN_SHARE_PICKER_TIMEOUT_SECONDS=90
SCREEN_SHARE_MAX_DURATION_MINUTES=30
SCREEN_SHARE_WARNING_AT_MINUTES=25
SCREEN_SHARE_HEARTBEAT_INTERVAL_SECONDS=10
SCREEN_SHARE_RECONNECT_GRACE_SECONDS=15
SCREEN_SHARE_STUN_URLS=stun:stun.l.google.com:19302
SCREEN_SHARE_TURN_URLS=turn:<turn-host>:3478?transport=udp,turns:<turn-host>:5349?transport=tcp
SCREEN_SHARE_TURN_SHARED_SECRET=<unique-coturn-static-auth-secret>
SCREEN_SHARE_TURN_TTL_SECONDS=600
~~~

The SCREEN_SHARE_TURN_SHARED_SECRET remains server-side. Laravel mints
time-limited coturn REST credentials for an approved session; do not add a
VITE variable for that secret.

## coturn

Configure coturn with the REST/time-limited credential mechanism. Keep normal
operational logs limited to service health and connection metadata; do not
enable packet capture or any relay-payload recording.

~~~conf
realm=<test-domain>
use-auth-secret
static-auth-secret=<same-as-SCREEN_SHARE_TURN_SHARED_SECRET>
fingerprint
lt-cred-mech
listening-port=3478
tls-listening-port=5349
cert=/etc/letsencrypt/live/<turn-host>/fullchain.pem
pkey=/etc/letsencrypt/live/<turn-host>/privkey.pem
no-stdout-log
~~~

The TURN host must be reachable from the public internet over UDP 3478 and
TLS/TCP 5349. Test a restrictive corporate network as well as a normal home
network; direct peer-to-peer success alone does not validate relay fallback.

## Nginx And Workers

Reverse proxy Reverb's websocket endpoint from the public test origin:

~~~nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
}
~~~

Supervise these separate processes. The realtime worker is intentionally
isolated so delayed connection-loss jobs do not wait behind mail or bulk work.

~~~ini
[program:fsa-reverb]
command=php /var/www/futureshiftadvisory/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/futureshiftadvisory
autostart=true
autorestart=true

[program:fsa-queue-realtime]
command=php /var/www/futureshiftadvisory/artisan queue:work database --queue=realtime --sleep=1 --tries=3 --timeout=60
directory=/var/www/futureshiftadvisory
autostart=true
autorestart=true

[program:fsa-schedule]
command=php /var/www/futureshiftadvisory/artisan schedule:work
directory=/var/www/futureshiftadvisory
autostart=true
autorestart=true
~~~

## Deploy

1. Back up the test database.
2. Deploy the application code and set the secure environment values above.
3. Run php artisan migrate --force.
4. Run npm ci and npm run build.
5. Run php artisan optimize:clear and php artisan config:cache.
6. Restart PHP-FPM, fsa-reverb, fsa-queue-realtime, and fsa-schedule.
7. Confirm php artisan schedule:list includes fsa-screen-share-expire.

## Verification

Run automated checks before browser testing:

~~~bash
php artisan test tests/Feature/ScreenShare/ScreenShareSessionTest.php
npm run screen-share:guard
npm run types:check
npm run build
~~~

Then use two desktop browser profiles:

1. Sign in as an assigned advisor and open an eligible client profile.
2. Sign in as that client user and open /portal?client=<client-uuid>.
3. Request screen support. The request must appear only in the selected
   client portal tab.
4. Approve in the FSA dialog, then use the browser picker. Verify that no
   audio option is enabled by the application.
5. Confirm the advisor can see the selected screen and cannot click, type,
   record, or control it.
6. End from both sides, repeat with a declined request, and leave the picker
   open past 90 seconds to verify timeout cleanup.
7. Disconnect either participant for more than the configured grace period.
   The realtime worker should end the session; the once-per-minute sweep is
   the backstop for worker or transport failure.
8. Repeat from a restrictive network and confirm TURN relay fallback.

Monitor Reverb process restarts, the realtime queue depth and latency, coturn
allocation failures, and screen_share audit events. If TURN is unavailable,
disable requests in the test environment until fixed rather than describing
direct peer-to-peer as a guaranteed fallback.

## Rollback

Set BROADCAST_CONNECTION=log, restart the web and realtime processes, and
stop Reverb request traffic. Existing browser media ends at the WebRTC layer;
the scheduled expiry command releases any remaining non-terminal session rows.
