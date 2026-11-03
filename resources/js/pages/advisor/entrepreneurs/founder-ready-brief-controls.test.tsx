import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { reconciliationFixture } from './founder-brief-reconciliation.test-fixtures';
import {
    FounderReadyBriefAction,
    PlanBudgetFinaliseReportAction,
} from './founder-ready-brief-controls';

const assessment = {
    threshold: 75,
    finalise_url: '/assessment/finalise',
    url: '/assessment',
    finalisation_blocking_message: null as string | null,
};

test('blocked brief reasons are readable without hovering over a disabled button', () => {
    const reason = 'The automatic executive summary needs a system retry.';
    const html = renderToStaticMarkup(
        <FounderReadyBriefAction
            lenderBrief={{
                active: false,
                label: 'Lender brief blocked',
                reasons: [reason],
                document_url: '/brief',
                download_url: '/brief?download=1',
                preview_url: '/brief/preview',
                preview_download_url: '/brief/preview?download=1',
                retry_url: '/retry',
                approve_url: '/approve',
                reconciliation_url: '/reconcile',
                reconciliation: reconciliationFixture,
                approval_token: 'revision-1',
                review: {
                    can_approve: false,
                    approved: false,
                    note: null,
                    approved_at: null,
                    findings: [],
                },
                summary_recovery: {
                    can_retry: true,
                    state: 'failed',
                    message: reason,
                },
            }}
        />,
    );
    assert.match(
        html,
        /<li>The automatic executive summary needs a system retry\.<\/li>/,
    );
    assert.match(html, /separate from client progression/);
    assert.match(html, /Retry summary/);
});

test('a passing Budget does not show an enabled green finalise button while scoring review is pending', () => {
    const reason =
        'An advisor must confirm the rescored criteria before finalisation.';
    const html = renderToStaticMarkup(
        <PlanBudgetFinaliseReportAction
            assessment={{
                ...assessment,
                finalisation_blocking_message: reason,
            }}
            ready
            blocked={false}
            blockingMessage={null}
        />,
    );
    assert.match(html, /disabled=""/);
    assert.match(html, /An advisor must confirm the rescored criteria/);
    assert.match(html, /href="\/assessment"/);
    assert.match(html, /Review assessment/);
    assert.doesNotMatch(html, /bg-emerald-600/);
});

test('clear assessments explain the outcome and keep conversion separate', () => {
    const html = renderToStaticMarkup(
        <PlanBudgetFinaliseReportAction
            assessment={assessment}
            ready
            blocked={false}
            blockingMessage={null}
        />,
    );
    assert.doesNotMatch(html, /disabled=""|Review assessment/);
    assert.match(html, /bg-emerald-600/);
    assert.match(
        html,
        /creates its report and moves the client to Advisory Ready/,
    );
    assert.match(html, /Advisory conversion is a separate action/);
});

test('a blocked Budget retains its specific explanation and a review link', () => {
    const html = renderToStaticMarkup(
        <PlanBudgetFinaliseReportAction
            assessment={assessment}
            ready={false}
            blocked
            blockingMessage="Confirm the annual Insurance payment."
        />,
    );
    assert.match(html, /disabled=""/);
    assert.match(html, /Confirm the annual Insurance payment/);
    assert.match(html, /Review assessment/);
});
