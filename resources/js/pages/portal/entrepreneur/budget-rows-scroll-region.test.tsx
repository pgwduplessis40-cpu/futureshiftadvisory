import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { BudgetRowsScrollRegion } from './budget-rows-scroll-region';

test('budget rows provide persistent left and right controls for wide tables', () => {
    const html = renderToStaticMarkup(
        <BudgetRowsScrollRegion title="Monthly fixed costs">
            <div className="min-w-[56rem]">Cost fields</div>
        </BudgetRowsScrollRegion>,
    );

    assert.match(html, /Monthly fixed costs table controls/);
    assert.match(html, /Scroll Monthly fixed costs fields left/);
    assert.match(html, /Scroll Monthly fixed costs fields right/);
    assert.match(html, /Scroll Monthly fixed costs fields horizontally/);
});
