import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { AutosaveStatus } from './strategic-plan-budget-autosave-status';

test('an autosave failure remains visible with a retry action', () => {
    const html = renderToStaticMarkup(
        <AutosaveStatus
            state="error"
            error="The draft server is unavailable."
            onRetry={() => undefined}
        />,
    );

    assert.match(html, /Autosave failed/);
    assert.match(html, /Retry save/);
});
