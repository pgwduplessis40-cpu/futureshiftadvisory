import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { ExecutiveSummaryNotice } from './executive-summary-notice';

test('the executive summary notice confirms system generation follows a finalised passing assessment', () => {
    const html = renderToStaticMarkup(<ExecutiveSummaryNotice />);

    assert.match(
        html,
        /Generated automatically after a finalised, passing plan-and-budget assessment\./,
    );
});
