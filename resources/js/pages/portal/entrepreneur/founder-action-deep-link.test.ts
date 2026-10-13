import assert from 'node:assert/strict';
import test from 'node:test';
import { founderActionDeepLink } from './founder-action-deep-link';

test('founder action deep links only accept the supported financial-assumption targets', () => {
    assert.deepEqual(
        founderActionDeepLink(
            '?requirement=financial-assumptions&focus=fixed-costs&guide=1',
        ),
        {
            requirement: 'financial-assumptions',
            focus: 'fixed-costs',
            showGuide: true,
        },
    );
    assert.equal(
        founderActionDeepLink(
            '?requirement=financial-assumptions&focus=untrusted-target',
        ),
        null,
    );
});
