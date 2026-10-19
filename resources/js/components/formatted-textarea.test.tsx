import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { FormattedTextarea } from './formatted-textarea';

test('long answers retain their full text, limit and labelled expansion control during server rendering', () => {
    const answer = 'A customer problem with supporting evidence.\n'.repeat(100);
    const html = renderToStaticMarkup(
        <FormattedTextarea
            id="idea-problem"
            value={answer}
            onChange={() => undefined}
            rows={6}
            autoGrowMaxRows={12}
            expandable
            maxLength={10000}
            ariaLabel="Customer problem"
        />,
    );

    assert.ok(html.includes(answer));
    assert.match(html, /aria-controls="idea-problem"/);
    assert.match(html, /aria-expanded="false"/);
    assert.match(html, /aria-label="Expand answer: Customer problem"/);
    assert.match(html, /rows="6"/);
    assert.match(html, /maxLength="10000"/);
});

test('existing fixed-row editors retain their size and formatting controls without an expand button', () => {
    const html = renderToStaticMarkup(
        <FormattedTextarea
            value="Existing plan detail"
            onChange={() => undefined}
            rows={8}
            ariaLabel="Plan detail"
        />,
    );

    assert.match(html, /rows="8"/);
    assert.match(html, /aria-label="Bold"/);
    assert.match(html, /aria-label="Numbered list"/);
    assert.doesNotMatch(html, /Expand answer/);
});
