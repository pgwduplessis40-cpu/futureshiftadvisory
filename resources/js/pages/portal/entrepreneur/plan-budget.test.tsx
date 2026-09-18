import assert from 'node:assert/strict';
import test from 'node:test';
import { fixedCostQuantityWarning } from './plan-budget';

test('fixed-cost quantity warnings distinguish units from billing periods', () => {
    assert.match(
        fixedCostQuantityWarning({
            label: 'Owner pay',
            amount: 850,
            quantity: 52,
            cadence: 'weekly',
        }) ?? '',
        /parallel subscriptions, people, or licences/,
    );
    assert.equal(
        fixedCostQuantityWarning({
            label: 'Owner pay',
            amount: 850,
            quantity: 1,
            cadence: 'weekly',
        }),
        null,
    );
});
