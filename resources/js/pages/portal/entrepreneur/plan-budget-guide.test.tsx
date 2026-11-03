import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { budgetToForm, cleanBudgetRows } from './plan-budget';
import { PlanBudgetStarter, applyPlanAssumption } from './plan-budget-guide';

test('plan starter identifies the source and currency before offering an explicit value', () => {
    const html = renderToStaticMarkup(
        <PlanBudgetStarter
            onFormChange={() => {}}
            guidance={{
                sources: [],
                issues: [],
                facts: [
                    {
                        key: 'opening_cash_balance',
                        label: 'Opening cash',
                        value: 7300,
                        source: 'Opening cash is $7,300',
                        requirement: 'financial-assumptions',
                    },
                ],
            }}
        />,
    );
    assert.match(html, /Opening cash: \$7,300/);
    assert.match(html, /From your plan/);
    assert.match(html, /Use this value/);
    assert.doesNotMatch(html, /Use starter template|Use plan clues/);
});

test('using a sourced value changes only that assumption and requires cash verification', () => {
    const assumptions = {
        ...budgetToForm(undefined).assumptions,
        opening_cash_balance: 0,
        opening_cash_verified: true,
        debtor_days: 0,
    };
    assert.deepEqual(
        applyPlanAssumption(assumptions, 'opening_cash_balance', 7300),
        {
            ...assumptions,
            opening_cash_balance: 7300,
            opening_cash_verified: false,
        },
    );
    assert.deepEqual(
        applyPlanAssumption(assumptions, 'unrecognised', 5000),
        assumptions,
    );
});

test('contradictory statements do not get a use-value button and source essays stay bounded', () => {
    const html = renderToStaticMarkup(
        <PlanBudgetStarter
            onFormChange={() => {}}
            guidance={{
                sources: [],
                issues: [],
                facts: [7300, 7531].map((value) => ({
                    key: 'opening_cash_balance',
                    label: 'Opening cash',
                    value,
                    source: `Cash ${value}`,
                    requirement: 'financial-assumptions',
                })),
            }}
        />,
    );
    assert.doesNotMatch(html, /Use this value/);
});

test('save round trip retains zero sales, staged costs and explicit month values', () => {
    const rows = [
        {
            label: 'Owner pay',
            amount: 550,
            cadence: 'weekly' as const,
            month: 2,
            monthly_schedule: [0, 550, 875, null],
        },
    ];
    const [saved] = cleanBudgetRows(rows, false, false, true);
    assert.deepEqual(saved.monthly_schedule, [0, 550, 875, null]);
    assert.equal(saved.month, 2);
    const form = budgetToForm({ monthly_fixed_costs: [saved] } as Parameters<
        typeof budgetToForm
    >[0]);
    assert.deepEqual(
        form.monthly_fixed_costs[0].monthly_schedule,
        rows[0].monthly_schedule,
    );
});
