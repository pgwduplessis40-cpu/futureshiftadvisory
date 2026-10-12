import assert from 'node:assert/strict';
import test from 'node:test';
import { quickAddBudgetRow } from './budget-row-state';

test('the Insurance shortcut adds a missing cost and focuses an existing one without replacing it', () => {
    const insurance = {
        label: 'Insurance',
        amount: 220,
        quantity: 1,
        confidence: 'guess' as const,
    };
    const missing = quickAddBudgetRow([], insurance, false, false, true);

    assert.equal(missing.added, true);
    assert.equal(missing.rowIndex, 0);
    assert.equal(missing.rows[0]?.cadence, 'monthly');

    const existing = quickAddBudgetRow(
        [
            {
                label: 'Insurance',
                amount: 185,
                quantity: 1,
                cadence: 'annual' as const,
                confidence: 'known' as const,
            },
        ],
        insurance,
        false,
        false,
        true,
    );

    assert.equal(existing.added, false);
    assert.equal(existing.rowIndex, 0);
    assert.equal(existing.rows[0]?.amount, 185);
    assert.equal(existing.rows[0]?.cadence, 'annual');
});
