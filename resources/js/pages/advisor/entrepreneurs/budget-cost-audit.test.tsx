import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { BudgetCostAudit } from './budget-cost-audit';
import type { EntrepreneurDetail } from './types';

type Budget = NonNullable<EntrepreneurDetail['latest_plan']>['budget'];

const budget: Budget = {
    status: 'complete',
    expected_runway_months: null,
    calculated_runway_months: null,
    runway_open_ended: false,
    break_even_month: null,
    available_after_launch: null,
    active_flags: [],
    fixed_cost_trace: [],
    fixed_cost_cadence_repair_url: null,
    fixed_cost_cadence_repair_all_url: null,
    scheduled_cost_trace: [
        {
            schedule: 'one_off',
            label: 'Trademark example',
            amount: 100,
            quantity: 1,
            total: 100,
            month: 4,
            year: null,
            recurring: false,
            repeat_every_years: null,
            confidence: 'estimate',
        },
        {
            schedule: 'future',
            label: 'Trademark renewal',
            amount: 85.5,
            quantity: 2,
            total: 171,
            month: null,
            year: 10,
            recurring: false,
            repeat_every_years: 10,
            confidence: 'known',
        },
    ],
};

test('advisors can see a one-off trademark payment and its separate renewal schedule', () => {
    const html = renderToStaticMarkup(<BudgetCostAudit budget={budget} />);

    assert.match(html, /One-off costs/);
    assert.match(html, /Trademark example/);
    assert.match(html, /\$100\.00/);
    assert.match(html, /Forecast month 4 · once/);
    assert.match(html, /estimate/);
    assert.match(html, /Future costs and renewals/);
    assert.match(html, /\$85\.50/);
    assert.match(html, /\$171\.00/);
    assert.match(html, /Forecast year 10 · every 10 years/);
    assert.match(html, /Reassess after Budget changes/);
    assert.doesNotMatch(html, /Fixed-cost calculation audit/);
});

test('empty schedules are explicit and fixed costs remain separately visible', () => {
    const html = renderToStaticMarkup(
        <BudgetCostAudit
            budget={{
                ...budget,
                scheduled_cost_trace: [],
                fixed_cost_trace: [
                    {
                        index: 0,
                        label: 'Insurance',
                        rate: 120,
                        quantity: 1,
                        cadence: 'annual',
                        cadence_confirmed: true,
                        monthly_equivalent: 10,
                        corrected_monthly_equivalent: null,
                        duplicate_cadence_quantity: false,
                    },
                ],
            }}
        />,
    );

    assert.equal(html.match(/No saved entries\./g)?.length, 2);
    assert.match(html, /Fixed-cost calculation audit/);
    assert.match(html, /Insurance/);
    assert.match(html, /\$10/);
});
