import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { ClientSupport } from './ClientSupport';
import { PRESENCE_FAILURES_BEFORE_REFRESH } from './screen-share-presence-recovery';

test('client sees a recoverable screen-support connection status before an advisor requests view', () => {
    const html = renderToStaticMarkup(
        <ClientSupport
            config={{
                portal_context_token: 'test-token',
                connection_url: '/portal/entrepreneur-screen-share/connections',
                prompt_url:
                    '/screen-share/connections/__connection__/pending-prompt',
                connection_heartbeat_url:
                    '/screen-share/connections/__connection__/heartbeat',
                response_url:
                    '/portal/screen-share-sessions/__session__/response',
                browser_permission_url:
                    '/portal/screen-share-sessions/__session__/browser-permission',
                ice_servers_url:
                    '/screen-share-sessions/__session__/ice-servers',
                active_url: '/screen-share-sessions/__session__/active',
                signal_url: '/screen-share-sessions/__session__/signal',
                pending_signals_url:
                    '/screen-share-sessions/__session__/pending-signals',
                heartbeat_url: '/screen-share-sessions/__session__/heartbeat',
                end_url: '/screen-share-sessions/__session__/end',
                heartbeat_seconds: 10,
                reconnect_grace_seconds: 60,
                warning_at_minutes: 25,
            }}
        />,
    );

    assert.match(html, /role="status"/);
    assert.match(html, /Connecting screen support/);
    assert.match(html, /Retry/);
});

test('screen-support polling retries its portal presence before reporting an unavailable connection', () => {
    assert.equal(PRESENCE_FAILURES_BEFORE_REFRESH, 2);
});
