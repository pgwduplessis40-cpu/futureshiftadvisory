import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import {
    budgetTermForLabel,
    BudgetTermLabel,
    FounderBudgetGuide,
    FounderBudgetPathwayIntro,
} from './budget-term-help';

test('founder budget guide keeps evidence, cash, runway, and plan links in plain language', () => {
    const html = renderToStaticMarkup(<FounderBudgetGuide />);

    assert.match(html, /Founder-first budget guide/);
    assert.match(html, /Evidence source is the kind of record/);
    assert.match(html, /lowest cash point/);
    assert.match(html, /Break-even/);
    assert.match(html, /#business-plan-requirements/);
});

test('the budget entry point explains how plan evidence becomes a cash forecast', () => {
    const html = renderToStaticMarkup(<FounderBudgetPathwayIntro />);

    assert.match(html, /From approved idea to a finance-backed plan/);
    assert.match(html, /Explain the business/);
    assert.match(html, /aria-label="Explain operating model"/);
    assert.match(html, /Test the cash/);
});

test('budget field labels attach the right explainer without redefining units as a billing period', () => {
    assert.equal(
        budgetTermForLabel('Rate / billing period'),
        'rate-billing-period',
    );
    assert.equal(budgetTermForLabel('Units'), 'units');
    assert.equal(budgetTermForLabel('Unit cost'), 'unit-cost');
    assert.equal(budgetTermForLabel('Scenario'), 'funding-scenario');
    assert.equal(
        budgetTermForLabel('Evidence reference'),
        'evidence-reference',
    );

    const html = renderToStaticMarkup(
        <BudgetTermLabel term="units">Units</BudgetTermLabel>,
    );

    assert.match(html, /aria-label="Explain Units"/);
});
