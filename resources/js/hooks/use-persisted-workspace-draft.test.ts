import assert from 'node:assert/strict';
import test from 'node:test';
import { resolvePersistedWorkspaceDraft } from './use-persisted-workspace-draft';

test('an edit made before the initial draft load remains dirty and keeps server fields', () => {
    const initial = {
        problem: '',
        targetCustomer: '',
    };
    const result = resolvePersistedWorkspaceDraft({
        initialData: initial,
        currentData: {
            ...initial,
            problem: 'Independent clinics lose time managing cancellations.',
        },
        serverPayload: {
            targetCustomer: 'Owner-managed allied-health clinics',
        },
        recoveryPayload: null,
        recoveryIsNewer: false,
    });

    assert.deepEqual(result.data, {
        problem: 'Independent clinics lose time managing cancellations.',
        targetCustomer: 'Owner-managed allied-health clinics',
    });
    assert.notEqual(JSON.stringify(result.data), result.serverSignature);
});

test('a null stored text field retains the form default', () => {
    const initial = {
        problem: '',
        targetCustomer: '',
    };
    const result = resolvePersistedWorkspaceDraft({
        initialData: initial,
        currentData: initial,
        serverPayload: {
            problem: null,
            targetCustomer: 'Owner-managed allied-health clinics',
        } as unknown as Partial<typeof initial>,
        recoveryPayload: null,
        recoveryIsNewer: false,
    });

    assert.deepEqual(result.data, {
        problem: '',
        targetCustomer: 'Owner-managed allied-health clinics',
    });
});
