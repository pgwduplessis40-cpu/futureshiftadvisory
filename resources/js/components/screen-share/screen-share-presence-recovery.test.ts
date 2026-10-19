import assert from 'node:assert/strict';
import test from 'node:test';
import {
    nextScreenSharePresenceFailure,
    PRESENCE_FAILURES_BEFORE_REFRESH,
} from './screen-share-presence-recovery';

test('refreshes a stale screen-support presence after consecutive transport failures', () => {
    const statuses: string[] = [];
    let refreshes = 0;

    const once = nextScreenSharePresenceFailure(
        0,
        true,
        (status) => statuses.push(status),
        () => {
            refreshes += 1;
        },
    );
    const twice = nextScreenSharePresenceFailure(
        once,
        true,
        (status) => statuses.push(status),
        () => {
            refreshes += 1;
        },
    );

    assert.equal(PRESENCE_FAILURES_BEFORE_REFRESH, 2);
    assert.equal(twice, 2);
    assert.deepEqual(statuses, ['reconnecting', 'reconnecting']);
    assert.equal(refreshes, 1);
});
