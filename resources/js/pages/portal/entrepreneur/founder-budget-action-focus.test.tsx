import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { FounderBudgetActionFocus } from './founder-budget-action-focus';

test('the visual guide identifies the live Budget area for the founder', () => {
    const html = renderToStaticMarkup(
        <FounderBudgetActionFocus focus="fixed-costs" showGuide>
            <button data-founder-action-target="fixed-costs-add-row">
                Add another cost
            </button>
        </FounderBudgetActionFocus>,
    );

    assert.match(html, /You are here: Financial assumptions/);
    assert.match(html, /Regular business costs/);
    assert.match(html, /Add another cost/);
    assert.match(html, /Correct an existing row if needed/);
    assert.match(html, /only if it is missing/);
    assert.doesNotMatch(html, /Select it to add a new regular cost row/);
});
