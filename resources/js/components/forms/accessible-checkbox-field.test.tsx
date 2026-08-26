import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { AccessibleCheckboxField } from './accessible-checkbox-field';

test('checkbox fields have a form name and an associated accessible label', () => {
    const html = renderToStaticMarkup(
        <AccessibleCheckboxField
            checked={false}
            description="Use this only for your own client account."
            label="Share the report"
            name="share_report"
        />,
    );

    assert.match(html, /name="share_report"/);
    assert.match(html, /id="share_report"/);
    assert.match(html, /for="share_report"/);
    assert.match(html, /aria-describedby="share_report-description"/);
});
