import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { PlanBudgetCoherencePanel } from './plan-budget-coherence';

test('clients see only their corrections, while advisors see calculation and review details', () => {
    const coherence = {
        status_label: 'Advisor action needed',
        summary: 'No Budget changes are requested from the client.',
        approval_available: false,
        budget_support: { summary: 'Technical support check.' },
        plan_correlation: { summary: 'Correlation diagnostic.' },
        findings: [],
        actions: [],
        calculation_issues: [
            {
                message: 'Fixed-cost trace mismatch: $500 versus $100.',
                next_action: 'Recalculate saved Budget.',
            },
        ],
        advisor_reviews: [
            {
                message: 'Runway exceeds the planning horizon.',
                next_action: 'Review the risk.',
            },
        ],
        advisories: [
            {
                key: 'sources',
                category: 'budget_support',
                message: 'Verify source references.',
                next_action: 'Review before external issue.',
            },
        ],
    };
    const client = renderToStaticMarkup(
        <PlanBudgetCoherencePanel coherence={coherence} historicalMessage="" />,
    );
    assert.match(client, /No Budget changes are requested from you/);
    assert.match(client, /With your advisor/);
    assert.doesNotMatch(
        client,
        /Fixed-cost trace|Runway exceeds|Verify source|Recalculate|Run reassessment|Financial assumptions/,
    );

    const advisor = renderToStaticMarkup(
        <PlanBudgetCoherencePanel
            coherence={coherence}
            historicalMessage=""
            audience="advisor"
            reviewUrl="/review"
            recalculateUrl="/recalculate"
        />,
    );
    assert.match(advisor, /Fixed-cost trace mismatch: \$500 versus \$100/);
    assert.match(advisor, /Runway exceeds the planning horizon/);
    assert.match(advisor, /Advisor decision and rationale/);
    assert.match(advisor, /Recalculate saved Budget and reassess/);
    assert.match(advisor, /do not block this assessment or client progression/);
});

test('completed review leaves no client editing instructions or pending advisor form', () => {
    const coherence = {
        status_label: 'Ready to finalise',
        summary: 'No outstanding items.',
        approval_available: true,
        budget_support: { summary: 'Resolved.' },
        plan_correlation: { summary: 'Resolved.' },
        findings: [],
        actions: [],
        review_completed: true,
        review_note: 'The reserve covers the cash exposure.',
        reviewed_at: '2026-10-28T01:00:00Z',
        review_assessment_round: 23,
        advisor_reviews: [
            {
                message: 'Cash reserve needs review.',
                next_action: 'Review the risk.',
            },
        ],
    };
    const client = renderToStaticMarkup(
        <PlanBudgetCoherencePanel coherence={coherence} historicalMessage="" />,
    );
    assert.match(client, /discuss the next stage/);
    assert.doesNotMatch(
        client,
        /Financial assumptions|Run reassessment|Cash reserve/,
    );
    const advisor = renderToStaticMarkup(
        <PlanBudgetCoherencePanel
            coherence={coherence}
            historicalMessage=""
            audience="advisor"
            reviewUrl="/review"
        />,
    );
    assert.match(advisor, /Advisor review completed/);
    assert.match(advisor, /The reserve covers the cash exposure/);
    assert.match(advisor, /28 Oct 2026/);
    assert.match(advisor, /Assessment 23/);
    assert.match(advisor, /still applies to the unchanged inputs/);
    assert.doesNotMatch(advisor, /textarea|Review the risk/);
});

test('changed decisions explain the reason to the advisor without exposing it to the client', () => {
    const coherence = {
        status_label: 'Advisor action needed',
        summary: 'One finding needs a decision.',
        approval_available: false,
        review_completed: false,
        review_status_message:
            'The plan or Budget inputs have changed since the saved advisor decision.',
        budget_support: { summary: 'Review needed.' },
        plan_correlation: { summary: 'Resolved.' },
        findings: [],
        actions: [],
    };
    const advisor = renderToStaticMarkup(
        <PlanBudgetCoherencePanel
            coherence={coherence}
            historicalMessage=""
            audience="advisor"
        />,
    );
    const client = renderToStaticMarkup(
        <PlanBudgetCoherencePanel coherence={coherence} historicalMessage="" />,
    );
    assert.match(
        advisor,
        /inputs have changed since the saved advisor decision/,
    );
    assert.doesNotMatch(client, /saved advisor decision/);
});

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
