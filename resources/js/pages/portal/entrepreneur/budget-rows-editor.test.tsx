import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { BudgetRowsEditor } from './budget-rows-editor';

test('fixed-cost guidance targets the visible Add another cost button', () => {
    const html = renderToStaticMarkup(
        <BudgetRowsEditor
            title="Monthly fixed costs"
            group="monthly_fixed_costs"
            rows={[]}
            fixedCost
            focusTarget="fixed-costs"
            onFormChange={() => undefined}
        />,
    );

    assert.match(html, /data-founder-action-target="fixed-costs-add-row"/);
    assert.match(html, /data-co-browse-target="entrepreneur.plan.fixed-costs"/);
    assert.match(html, /Add another cost/);
});
