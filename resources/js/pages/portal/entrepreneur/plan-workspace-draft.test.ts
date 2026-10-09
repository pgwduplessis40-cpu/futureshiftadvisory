import assert from 'node:assert/strict';
import test from 'node:test';
import { postBudgetAutosave } from './plan-workspace-draft';

test('Budget autosave returns the refreshed Budget presentation', async () => {
    const originalFetch = globalThis.fetch;
    let requestBody = '';

    globalThis.fetch = async (_input, init) => {
        requestBody = String(init?.body);

        return new Response(
            JSON.stringify({
                revision: 2,
                budget: { revision: 2, status: 'partial' },
            }),
            { status: 200 },
        );
    };

    try {
        const result = await postBudgetAutosave('/budget', {
            revision: 1,
        });

        assert.equal(result.saved, true);
        assert.equal(result.budget?.revision, 2);
        assert.match(requestBody, /"_autosave":true/);
    } finally {
        globalThis.fetch = originalFetch;
    }
});
