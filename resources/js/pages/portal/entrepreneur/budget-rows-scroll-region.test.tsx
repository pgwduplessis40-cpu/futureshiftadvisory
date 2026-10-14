import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { BudgetRowsScrollRegion } from './budget-rows-scroll-region';

test('budget rows provide one native horizontal input region without a duplicate scroll rail', () => {
    const html = renderToStaticMarkup(
        <BudgetRowsScrollRegion title="Monthly fixed costs">
            <div className="min-w-[56rem]">Cost fields</div>
        </BudgetRowsScrollRegion>,
    );

    assert.match(html, /Monthly fixed costs inputs\. Scroll horizontally/);
    assert.doesNotMatch(html, /All fields are visible/);
    assert.doesNotMatch(html, /Scroll Monthly fixed costs fields horizontally/);
});
