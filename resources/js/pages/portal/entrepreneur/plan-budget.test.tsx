import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { fixedCostQuantityWarning } from './fixed-cost-cadence';
import { BudgetMetric, cleanFutureCostRows } from './plan-budget';

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

test('one-off future costs do not gain a renewal cycle', () => {
    const [oneOffCost] = cleanFutureCostRows([
        {
            label: 'Website refresh',
            amount: '900',
            quantity: 1,
            year: 3,
            recurring: false,
            repeat_every_years: '',
            classification: 'operating',
            useful_life_years: 3,
            confidence: 'estimate',
        },
    ]);

    assert.equal(oneOffCost.repeat_every_years, null);
});

test('budget outcome terms attach founder-friendly explanations', () => {
    const html = renderToStaticMarkup(
        <BudgetMetric label="Break-even year" value="Year 2" />,
    );

    assert.match(html, /aria-label="Explain Break-even year"/);
});
