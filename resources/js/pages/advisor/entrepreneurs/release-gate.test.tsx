import assert from 'node:assert/strict';
import test from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import {
    ClaimCompanionFields,
    ClaimMeasurementFields,
    ConfirmedClaims,
} from './release-gate-claim-fields';
import type { ConfirmedClaim, GateProps, Subcheck } from './release-gate-types';
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
    claims: [],
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

// PM-10 was registered while no advisor could record a runway, because the
// claim whitelist did not carry its keys. The figure and the two inclusions
// that say what it counts are confirmed together: a runway that counts future
// receipts and one that does not are different measures, and the rule refuses
// to compare unlike ones.
test('a runway and the inclusions that give it meaning can be confirmed', async () => {
    const html = await render({
        can_confirm: true,
        sections: [
            { id: 'answer-1', title: 'Cash position', hash: 'saved-answer' },
        ],
    });
    assert.match(html, /Runway in months/);
    // The inclusions are not offered as statements of their own. Confirmed
    // separately they would sit at a coordinate the figure need not share,
    // and the server refuses one recorded alone.
    assert.doesNotMatch(html, /Cash the runway starts from/);
    assert.doesNotMatch(html, /Runway counts owner pay/);
    assert.doesNotMatch(html.replace(/<[^>]*>/g, ''), /runway\./);
});

// They are asked for beside the figure instead, as one act.
test('a runway is confirmed together with the statements that give it meaning', async () => {
    const fields = (claimKey: string) =>
        renderToString(
            <ClaimCompanionFields
                claimKey={claimKey}
                values={{}}
                onChange={() => {}}
            />,
        );

    const runway = fields('runway.months');
    assert.match(runway, /Cash the runway starts from/);
    assert.match(runway, /Runway counts future customer receipts/);
    assert.match(runway, /Runway counts owner pay/);
    // Every one is required: left blank, an inclusion reaches the server as an
    // empty string, which reads as "not counted" rather than as unanswered.
    assert.equal(runway.match(/required=""/g)?.length, 3);
    assert.doesNotMatch(runway.replace(/<[^>]*>/g, ''), /runway\./);

    // A claim that travels alone brings no companions with it.
    assert.equal(fields('plan.opening_cash'), '');
});

// The two bases the model measures are offered by name, and the description
// box is withdrawn. The server refuses a runway stated on anything else, so a
// free text box here would take an answer the rule could never read and the
// advisor would learn that only from a rejection.
test('a runway basis is chosen from what the model measures, not described', async () => {
    const basisFor = (claimKey: string) =>
        renderToString(
            <ClaimMeasurementFields
                claimKey={claimKey}
                data={{ period: 'current', unit: 'months', basis: '' }}
                setData={() => {}}
            />,
        );

    const runway = basisFor('runway.months');
    assert.match(runway, /Essential costs with no sales/);
    assert.match(runway, /Forecast cash while trading/);
    assert.doesNotMatch(runway, /Basis description/);
    assert.doesNotMatch(runway.replace(/<[^>]*>/g, ''), /runway\.|_no_sales/);

    // Every other claim keeps the description box it has always had.
    const other = basisFor('plan.opening_cash');
    assert.match(other, /Basis description/);
    assert.doesNotMatch(other, /Essential costs with no sales/);
});

const confirmed: ConfirmedClaim = {
    key: 'runway.months',
    value: '12',
    entity: 'business',
    period: 'current',
    unit: 'months',
    basis: 'essential_cost_no_sales',
    as_of: null,
    revision: 1,
    section_title: 'Cash position',
    applies: true,
    confirmed_by: 'A. Advisor',
    confirmed_at: '2026-11-19T00:00:00+00:00',
    reason: 'Confirmed against the runway paragraph.',
};

// A confirmed claim binds to the wording it was confirmed against, so one
// whose plan answer has since been edited is no longer read. Before this the
// advisor saw the claim saved and then saw the rule report its evidence
// missing, with no way to connect the two.
test('a claim the gate has stopped reading says so rather than disappearing', async () => {
    const html = renderToString(
        <ConfirmedClaims
            claims={[
                confirmed,
                {
                    ...confirmed,
                    key: 'runway.includes_owner_pay',
                    value: false,
                    unit: null,
                    applies: false,
                },
            ]}
        />,
    );

    // Stripped of tags, because React puts comment markers between adjacent
    // text expressions and the advisor reads the text, not the markup.
    const text = html.replace(/<[^>]*>/g, '');
    assert.match(text, /Runway in months: 12 months/);
    // A boolean reads as the answer it is, not as true or false.
    assert.match(text, /Runway counts owner pay: Not counted/);
    assert.match(text, /Read by the gate\./);
    assert.match(text, /No longer read: the plan answer behind this changed/);
    assert.match(text, /1 confirmed statement is no longer read/);
    assert.match(text, /A\. Advisor/);
    assert.doesNotMatch(text, /runway\.|applies/);
});

test('a plan with nothing confirmed says so rather than showing an empty list', async () => {
    assert.match(
        renderToString(<ConfirmedClaims claims={[]} />),
        /No statements have been confirmed on this plan yet/,
    );
    // No warning where there is nothing to warn about.
    assert.doesNotMatch(
        renderToString(<ConfirmedClaims claims={[confirmed]} />),
        /no longer read/,
    );
});
