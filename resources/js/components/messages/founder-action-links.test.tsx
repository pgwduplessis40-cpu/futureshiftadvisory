import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { FounderActionLinks } from './founder-action-links';

test('founder action links make the guided destination the primary action', () => {
    const html = renderToStaticMarkup(
        <FounderActionLinks
            links={[
                {
                    key: 'regular_costs',
                    title: 'Reconcile the regular business costs',
                    description:
                        'This opens the regular-cost list and highlights the “Add another cost” button.',
                    open_url:
                        '/portal/entrepreneur/plan?requirement=financial-assumptions&focus=fixed-costs',
                    guide_url:
                        '/portal/entrepreneur/plan?requirement=financial-assumptions&focus=fixed-costs&guide=1',
                },
            ]}
        />,
    );

    assert.match(html, /Open and highlight/);
    assert.match(html, /Open without guide/);
    assert.match(html, /fixed-costs/);
    assert.match(html, /highlights the/);
});
