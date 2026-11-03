import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { reconciliationFixture } from './founder-brief-reconciliation.test-fixtures';
import { FounderReadyBriefAction } from './founder-brief-release-actions';
import type { EntrepreneurDetail } from './types';

const brief: NonNullable<EntrepreneurDetail['latest_plan']>['lender_brief'] = {
    active: false,
    label: 'Lender brief blocked',
    reasons: ['Review the funding assumption.'],
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
        findings: ['Review the funding assumption.'],
    },
    summary_recovery: {
        can_retry: true,
        state: 'failed',
        message: 'Summary generation failed. Retry it here.',
    },
};

test('a failed summary has an actionable retry without premature release approval', () => {
    const html = renderToStaticMarkup(
        <FounderReadyBriefAction lenderBrief={brief} />,
    );
    assert.match(html, /Retry summary/);
    assert.match(html, /View founder brief PDF/);
    assert.match(html, /Download founder brief PDF/);
    assert.match(html, /href="\/brief\/preview"/);
    assert.match(html, /href="\/brief\/preview\?download=1"/);
    assert.match(html, /Advisor draft available now/);
    assert.doesNotMatch(
        html,
        /Approve brief for external issue|href="\/brief"/,
    );
});

test('queued summary work is visible and cannot be submitted twice', () => {
    const html = renderToStaticMarkup(
        <FounderReadyBriefAction
            lenderBrief={{
                ...brief,
                summary_recovery: {
                    can_retry: false,
                    state: 'queued',
                    message: 'The summary is queued.',
                },
            }}
        />,
    );
    assert.match(html, /Generating summary/);
    assert.match(html, /disabled=""/);
    assert.match(html, /role="status"/);
});

test('current summary exposes the advisor explanation and acknowledgement form', () => {
    const html = renderToStaticMarkup(
        <FounderReadyBriefAction
            lenderBrief={{
                ...brief,
                review: { ...brief.review, can_approve: true },
                summary_recovery: {
                    can_retry: false,
                    state: 'complete',
                    message: 'Current',
                },
            }}
        />,
    );
    assert.match(html, /Reason for approving the brief/);
    assert.match(html, /I have reviewed all listed findings/);
    assert.match(html, /Approve brief for external issue/);
    const checkbox = html.match(/<input[^>]*type="checkbox"[^>]*>/)?.[0] ?? '';
    assert.match(checkbox, /name="acknowledged"/);
    const checkboxId = checkbox.match(/id="([^"]+)"/)?.[1];
    assert.ok(checkboxId);
    assert.ok(html.includes(`<label for="${checkboxId}"`));
    assert.doesNotMatch(html, /Retry summary/);
});

test('an approved brief exposes the PDF link and recorded decision', () => {
    const html = renderToStaticMarkup(
        <FounderReadyBriefAction
            lenderBrief={{
                ...brief,
                active: true,
                reasons: [],
                review: {
                    ...brief.review,
                    approved: true,
                    note: 'Supporting records and funding assumptions checked.',
                },
            }}
        />,
    );
    assert.match(html, /href="\/brief"/);
    assert.match(html, /href="\/brief\?download=1"/);
    assert.doesNotMatch(html, /href="\/brief\/preview/);
    assert.match(html, /Advisor release approved/);
    assert.match(html, /Supporting records and funding assumptions checked/);
    assert.doesNotMatch(html, /Approve brief for external issue|Retry summary/);
});
