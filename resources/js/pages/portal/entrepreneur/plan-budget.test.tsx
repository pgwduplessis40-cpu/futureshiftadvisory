import assert from 'node:assert/strict';
import test from 'node:test';
import { fixedCostQuantityWarning } from './fixed-cost-cadence';
import { cleanFutureCostRows } from './plan-budget';

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

test('future cost rows preserve a scheduled renewal cycle', () => {
    assert.deepEqual(
        cleanFutureCostRows([
            {
                label: 'Trademark renewal',
                amount: '85',
                quantity: 1,
                year: 10,
                recurring: false,
                repeat_every_years: '10',
                classification: 'operating',
                useful_life_years: 3,
                confidence: 'estimate',
            },
        ]),
        [
            {
                label: 'Trademark renewal',
                amount: 85,
                quantity: 1,
                year: 10,
                recurring: false,
                repeat_every_years: 10,
                classification: 'operating',
                useful_life_years: 3,
                confidence: 'estimate',
            },
        ],
    );
});
