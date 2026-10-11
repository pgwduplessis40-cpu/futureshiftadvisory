import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { PlanBudgetCoherencePanel } from './plan-budget-coherence';

test('plan-budget actions explain autosave once and do not show manual-save completion text', () => {
    const html = renderToStaticMarkup(
        <PlanBudgetCoherencePanel
            historicalMessage="No historic assessment is available."
            coherence={{
                status_label: 'Blocked — action needed',
                summary: 'The Budget needs a small update.',
                approval_available: false,
                budget_support: {
                    summary: 'A Budget input needs attention.',
                },
                plan_correlation: {
                    summary: 'The written plan needs a matching Budget row.',
                },
                findings: [],
                actions: [
                    {
                        key: 'forecast_calendar',
                        severity: 'review',
                        title: 'Set the forecast start month',
                        destination: 'Budget > Financial assumptions',
                        steps: ['Choose the first forecast month.'],
                    },
                ],
            }}
        />,
    );

    assert.match(html, /Your Budget changes save automatically/);
    assert.match(html, /All changes saved/);
    assert.match(html, /Run reassessment/);
    assert.doesNotMatch(html, /When done:/);
    assert.doesNotMatch(html, /Save the financial assumptions/);
});
