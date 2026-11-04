import assert from 'node:assert/strict';
import test from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import type { GateProps, Subcheck } from './release-gate-types';
import ReleaseGateReview from './ReleaseGate';

const missing: Subcheck = {
    id: 'ledger',
    result: 'cannot_verify',
    severity: 'block',
    message: 'Supply the dated cash ledger.',
    compared: [],
    missing_inputs: ['Dated cash ledger'],
    scope_reason: null,
};
const risk: Subcheck = {
    ...missing,
    id: 'target',
    severity: 'review',
    message: 'Explain the margin target.',
    missing_inputs: ['Margin rationale'],
};
const props: GateProps = {
    run_id: 'current-run',
    can_accept: false,
    can_confirm: false,
    can_configure: false,
    sections: [],
    configuration: { thresholds: {}, severities: {}, messages: {} },
    urls: {
        review: '/gate',
        decision: '/gate/decisions',
        claim: '/gate/claims',
        policy: '/gate/policy',
        workspace: '/plan',
    },
    report: {
        document: 'plan',
        configuration_hash: 'current-policy',
        deferred: ['EV-01'],
        results: [
            {
                enabled: true,
                result: 'cannot_verify',
                rule: {
                    id: 'MI-01',
                    name: 'Cash integrity',
                    severity: 'block',
                    minimum_severity: 'block',
                    override_policy: 'never',
                },
                subchecks: [missing],
            },
            {
                enabled: true,
                result: 'cannot_verify',
                rule: {
                    id: 'PM-07',
                    name: 'Margin target',
                    severity: 'review',
                    minimum_severity: 'review',
                    override_policy: 'principal_advisor',
                },
                subchecks: [risk],
            },
        ],
        findings: [
            {
                rule_id: 'MI-01',
                subcheck: missing,
                severity: 'block',
                accepted: false,
            },
            {
                rule_id: 'PM-07',
                subcheck: risk,
                severity: 'review',
                accepted: false,
            },
        ],
    },
};

async function render(overrides: Partial<GateProps> = {}) {
    const response = await createInertiaApp({
        page: {
            component: 'ReleaseGate',
            props: { ...props, ...overrides, errors: {} },
            url: '/gate',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            flash: {},
            rememberedState: {},
        },
        resolve: () => ReleaseGateReview,
        render: renderToString,
        setup: ({ App, props: pageProps }) => <App {...pageProps} />,
    });

    return response.body;
}

test('ordinary advisors see unresolved and deferred checks without an integrity override', async () => {
    const html = await render();
    assert.match(html, /Draft - not approved for external issue/);
    assert.match(html, /Cannot verify/);
    assert.match(html, /Dated cash ledger/);
    assert.match(html, /EV-01/);
    assert.doesNotMatch(
        html,
        /Record risk acceptance|Save policy|Confirm claim/,
    );
});

test('Principal authority exposes only the eligible risk decision and retains its original result', async () => {
    const html = await render({ can_accept: true });
    assert.equal((html.match(/Record risk acceptance/g) ?? []).length, 1);
    const accepted = await render({
        can_accept: true,
        report: {
            ...props.report,
            findings: props.report.findings.map((f) => ({
                ...f,
                accepted: f.rule_id === 'PM-07',
            })),
        },
    });
    assert.match(accepted, /Original result retained/);
    assert.match(accepted, /Cannot verify/);
    assert.doesNotMatch(accepted, /Record risk acceptance/);
});

test('claim confirmation offers readable measurement choices against a saved answer', async () => {
    const html = await render({
        can_confirm: true,
        sections: [
            { id: 'answer-1', title: 'Cash position', hash: 'saved-answer' },
        ],
    });
    assert.match(html, /Cash position/);
    assert.match(html, /Annual profit/);
    assert.match(html, /Current owner-pay rate/);
    assert.match(html, /New Zealand dollars per week/);
    assert.match(html, /Save confirmed statement/);
    assert.doesNotMatch(
        html.replace(/<[^>]*>/g, ''),
        /annual_profit|current_rate|year:1/,
    );
});
