import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { FounderBudgetActionFocus } from './founder-budget-action-focus';

test('the visual guide identifies the live Budget area for the founder', () => {
    const html = renderToStaticMarkup(
        <FounderBudgetActionFocus focus="fixed-costs" showGuide>
            <section>Regular costs</section>
        </FounderBudgetActionFocus>,
    );

    assert.match(html, /You are here: Financial assumptions/);
    assert.match(html, /Regular business costs/);
    assert.match(html, /Add another cost/);
});
