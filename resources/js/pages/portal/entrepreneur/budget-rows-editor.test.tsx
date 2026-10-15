import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { BudgetRowsEditor } from './budget-rows-editor';

test('fixed-cost guidance uses a visible responsive Add another cost button', () => {
    const html = renderToStaticMarkup(
        <BudgetRowsEditor
            title="Monthly fixed costs"
            group="monthly_fixed_costs"
            fixedCost
            rows={[
                {
                    label: 'Insurance',
                    amount: 220,
                    quantity: 1,
                    cadence: 'annual',
                    confidence: 'known',
                    source_type: 'owner_record',
                    source_reference: 'Invoice',
                    source_confirmed: true,
                },
            ]}
            focusTarget="fixed-costs"
            onFormChange={() => undefined}
        />,
    );

    assert.match(html, /data-founder-action-target="fixed-costs-add-row"/);
    assert.match(html, /data-co-browse-target="entrepreneur.plan.fixed-costs"/);
    assert.match(html, /Add another cost/);
    assert.match(html, /sm:grid-cols-2 lg:grid-cols-3/);
    assert.match(html, /Evidence reference/);
    assert.doesNotMatch(html, /Scroll Monthly fixed costs fields/);
});
