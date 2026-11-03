import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { FounderBriefReconciliation } from './founder-brief-reconciliation';
import { reconciliationFixture } from './founder-brief-reconciliation.test-fixtures';

test('the reconciliation shows saved sources, facts and an explicit unresolved decision', () => {
    const html = renderToStaticMarkup(
        <FounderBriefReconciliation
            reconciliation={reconciliationFixture}
            saveUrl="/reconcile"
        />,
    );
    assert.match(html, /Workshops: \$1,000 monthly/);
    assert.match(html, /Only workshops are included/);
    assert.match(html, /Assumptions agreed as at/);
    assert.match(html, /value="unresolved" selected/);
    assert.match(html, /0 of 1 topics resolved/);
    assert.match(html, /Save financial reconciliation/);
    assert.match(html, /unresolved topics keep the brief in draft/);
});

test('source changes retain old notes as reference and clearly require new decisions', () => {
    const html = renderToStaticMarkup(
        <FounderBriefReconciliation
            reconciliation={{
                ...reconciliationFixture,
                saved_at: '2026-11-03',
                topics: [
                    {
                        ...reconciliationFixture.topics[0],
                        note: 'Earlier agreed scope and reference.',
                    },
                ],
            }}
            saveUrl="/reconcile"
        />,
    );
    assert.match(html, /sources changed after the previous review/);
    assert.match(html, /Earlier agreed scope and reference/);
    assert.match(html, /every topic needs a fresh decision/);
});
