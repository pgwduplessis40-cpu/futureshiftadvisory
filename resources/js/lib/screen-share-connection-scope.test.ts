import assert from 'node:assert/strict';
import test from 'node:test';
import { screenShareConnectionScopeKey } from './screen-share-connection-scope';

test('a renewed portal context retains the established screen-share connection', () => {
    const first = screenShareConnectionScopeKey({
        connection_url: '/portal/screen-share/connections',
        portal_context_token: 'first-one-time-token',
    });
    const refreshed = screenShareConnectionScopeKey({
        connection_url: '/portal/screen-share/connections',
        portal_context_token: 'fresh-one-time-token',
    });

    assert.equal(refreshed, first);
});

test('a different portal connection endpoint starts a new screen-share scope', () => {
    assert.notEqual(
        screenShareConnectionScopeKey({
            connection_url: '/portal/screen-share/connections',
            portal_context_token: 'client-token',
        }),
        screenShareConnectionScopeKey({
            connection_url: '/portal/entrepreneur-screen-share/connections',
            portal_context_token: 'entrepreneur-token',
        }),
    );
});
