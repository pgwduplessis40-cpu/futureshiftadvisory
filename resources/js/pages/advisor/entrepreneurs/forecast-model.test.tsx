import assert from 'node:assert/strict';
import test from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import type { ForecastModel, ForecastModelProps } from './forecast-model-types';
import ForecastModelReview from './ForecastModel';

const model: ForecastModel = {
    revision: 2,
    engine_version: 'fsa-forecast.2d.1',
    readable: true,
    load_error: null,
    selected: false,
    selected_at: null,
    selected_by: null,
    calendar: { start_month: '2026-01', months: 24 },
    cash_opening: {
        amount: '1200.00',
        source: 'Bank statement to 31 December 2025',
        verified: true,
    },
    reserve: {
        monthly_fixed_costs: '1000',
        months_covered: 3,
        includes_owner_pay: true,
        one_off_cash_costs: '500',
        contingency_rate: '0.10',
        basis: 'Monthly fixed costs including owner pay',
        base_reserve: '3000.00',
        contingency: '350.00',
        target: '3350.00',
        missing: [],
    },
    actuals: [
        {
            period: '2026-01',
            state: 'trading',
            reconciled: true,
            closing_bank_cash: '5000.00',
            reviewed_by: 'A. Advisor',
            reviewed_on: '2026-02-05',
            pre_trading_evidence: null,
        },
    ],
    streams: [
        {
            id: 'workshops',
            name: 'Weekend workshops',
            unit: 'workshop',
            price: '450.00',
            preparation_hours_per_unit: '4',
            delivery_hours_per_unit: '6',
            in_base_case: true,
            evidence_status: 'own_record',
            schedule: [
                {
                    period: '2026-03',
                    booked_units: '2',
                    delivery_units: '2',
                    invoiced_units: '2',
                    founder_hours: '20',
                    additional_capacity_units: null,
                    provider: null,
                    provider_unit_cost: null,
                    no_cost_basis: null,
                },
            ],
        },
    ],
    founder_months: [
        {
            period: '2026-03',
            available_hours: '120',
            administration_hours: '20',
            business_development_hours: '15',
        },
    ],
    funding_sources: [
        {
            id: 'term-loan-2026',
            source: 'Signed facility letter',
            identity: 'Westpac',
            type: 'Term loan',
            amount: '25000',
            commitment: 'committed',
            available_from: '2026-02-01',
            drawn_on: '2026-02-15',
            restriction: 'unrestricted',
            verified: true,
        },
    ],
    // Nobody has said whether the list is finished, which is the state the
    // page has to show as unanswered rather than read off the row count.
    funding_inventory_complete: null,
    trade_items: [
        {
            id: 'invoice-104',
            source: 'Sales ledger export',
            kind: 'receivable',
            raised_on: '2026-03-02',
            amount: '900.00',
            settlements: [
                {
                    id: 'invoice-104.payment',
                    source: 'Bank statement March 2026',
                    date: '2026-04-01',
                    amount: '900.00',
                    category: 'operating_receipt',
                    verified: true,
                },
            ],
        },
    ],
    owner_pay_steps: [
        {
            effective_on: '2026-07-01',
            weekly_amount: '800',
            trigger: 'trailing_three_month_average_revenue',
            threshold: '12000',
        },
    ],
    assets: [
        {
            id: 'laptop-2026',
            source: 'Supplier invoice 8831',
            cost: '2400.00',
            classification: 'capital',
            policy_source: 'Capitalisation policy v2',
            reviewed: true,
            paid_on: '2026-02-10',
            recognition_start_on: '2026-02-01',
            life_months: 36,
            residual: '200.00',
            method: 'straight_line_full_start_month',
            opening_depreciation: '0.00',
        },
    ],
    tax: {
        entity_basis: 'Company',
        rate_percent: '28',
        losses_eligible: true,
        policy_source: 'IR4 guidance',
        eligibility_source: 'Shareholder continuity note',
        version: '2026.1',
        periods: [
            {
                start_on: '2026-04-01',
                end_on: '2027-03-31',
                accounting_profit: '-4500.00',
                tax_adjustments: '500.00',
            },
        ],
    },
    projection: {
        engine_version: 'phase-2e.1',
        status: 'cannot_verify',
        months_projected: 24,
        base_revenue_months: 1,
        missing: [
            {
                reason: 'founder.available_hours',
                months: 23,
                first_period: '2026-01',
            },
        ],
        errors: [],
        refusal: null,
    },
    cash: {
        status: 'cannot_verify',
        movements_counted: 2,
        settled_before_opening: 1,
        lowest_cash: '3500.00',
        lowest_cash_date: '2026-02-10',
        cash_deficit: '0.00',
        reserve_target: '3350.00',
        reserve_shortfall: '0.00',
        missing: [
            {
                reason: 'owner_pay.source_and_first_payment_date',
                months: 0,
                first_period: '2026-01',
            },
        ],
        refusal: null,
    },
    owner_pay_source: 'Signed owner drawings policy',
    owner_pay_first_payment_on: '2026-07-03',
    opening_eligible_losses: '4500.00',
    sections: [
        {
            key: 'calendar',
            label: 'Forecast calendar',
            captured: true,
            editable: true,
            detail: '2026-01 for 24 months.',
        },
        {
            key: 'streams',
            label: 'Revenue streams',
            captured: true,
            editable: true,
            detail: '1 stream captured.',
        },
        {
            key: 'trade_items',
            label: 'Trade terms',
            captured: true,
            editable: true,
            detail: '1 item captured.',
        },
        {
            key: 'bank_opening',
            label: 'Bank reconciliation opening',
            captured: false,
            editable: false,
            detail: 'Not captured.',
        },
    ],
};
const props: ForecastModelProps = {
    model,
    engine: {
        engine_version: 'fsa-forecast.2d.1',
        components: { revenue: 'entrepreneur.driver_revenue' },
    },
    can_edit: false,
    urls: {
        review: '/model',
        save: '/model',
        drivers: '/model/drivers',
        terms: '/model/terms',
        selection: '/model/selection',
        gate: '/gate',
        workspace: '/plan',
    },
};

async function render(overrides: Partial<ForecastModelProps> = {}) {
    const response = await createInertiaApp({
        page: {
            component: 'ForecastModel',
            props: { ...props, ...overrides, errors: {} },
            url: '/model',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            flash: {},
            rememberedState: {},
        },
        resolve: () => ForecastModelReview,
        render: renderToString,
        setup: ({ App, props: pageProps }) => <App {...pageProps} />,
    });

    return response.body;
}

test('the page states that saving a reconstruction changes no verdict', async () => {
    const html = await render();
    assert.match(html, /Saving a reconstruction changes no release verdict/);
    // The banner has to say what selecting actually does, now that it moves
    // a verdict rather than merely recording a model.
    assert.match(html, /Selecting one does/);
    assert.match(html, /elapsed-actuals check/);
    assert.match(html, /hold up an external release/);
    // Selecting now takes the cash curve off the budget, so the banner has to
    // name both halves of that: what the ledger check gains and what the
    // budget's scenario reconciliation loses.
    assert.match(html, /cash-ledger check/);
    assert.match(html, /scenario reconciliation stops counting/);
    assert.match(html, /Saved but not selected/);
});

test('funding is asked for separately from the opening balance it is not part of', async () => {
    const html = await render({ can_edit: true });
    assert.match(html, /Funding sources/);
    assert.match(html, /Who is providing it/);
    // Both halves of why a draw outside the calendar is refused, because an
    // advisor meeting the refusal needs to know which one they hit.
    assert.match(html, /counting it in both places counts it twice/);
    assert.match(html, /never arrives inside the horizon/);
    // The completeness question is the advisor's to answer, and the page says
    // what staying silent costs rather than inferring an answer from the rows.
    assert.match(html, /Is every funding source recorded\?/);
    assert.match(html, /the sum of a partial list is not a\s+total/);
});

test('uncaptured sections are reported rather than left blank', async () => {
    const html = await render();
    assert.match(html, /Trade terms/);
    assert.match(html, /Not captured/);
    // The bank reconciliation opening is held by the reconstruction and
    // captured nowhere on this screen, so the table has to say so rather
    // than let the list read as complete.
    assert.match(html, /Bank reconciliation opening/);
    assert.match(html, /On a later screen/);
});

test('the base-case question offers an unanswered state of its own', async () => {
    const html = await render({ can_edit: true });
    assert.match(html, /Counted in the base case/);
    assert.match(html, /Not yet decided/);
    assert.match(html, /Yes, part of the base case/);
    assert.match(html, /No, upside only/);
    // An unanswered stream must not be presentable as a decision; the page
    // says what the consequence of leaving it unanswered actually is.
    assert.match(html, /reported as unverified/);
    assert.match(html, /never treated/);
});

test('stored streams and founder hours load into the driver form', async () => {
    const html = await render({ can_edit: true });
    assert.match(html, /Revenue streams and founder capacity/);
    assert.match(html, /value="Weekend workshops"/);
    assert.match(html, /value="workshops"/);
    assert.match(html, /value="120"/);
    assert.match(html, /Save streams and capacity/);
});

test('the driver form is withheld until a calendar exists to hang months on', async () => {
    const html = await render({ can_edit: true, model: null });
    assert.match(html, /A monthly schedule only means something inside a/);
    assert.doesNotMatch(html, /Save streams and capacity/);
});

test('a settlement is offered only the categories its trade item can carry', async () => {
    const html = await render({ can_edit: true });
    assert.match(html, /Customer receipt/);
    assert.match(html, /Tax refund/);
    // A receivable brings cash in, so the outgoing categories are not on
    // offer at all and the direction is never asked for.
    assert.doesNotMatch(html, /Supplier or operating payment/);
    assert.doesNotMatch(html, /Debt principal repaid/);
});

test('the asset and tax questions each keep an unanswered state', async () => {
    const html = await render({ can_edit: true });
    assert.match(html, /Not yet classified/);
    assert.match(html, /Capital, depreciated over its life/);
    assert.match(html, /Classification reviewed/);
    assert.match(html, /reported as unclassified, not quietly/);
    assert.match(html, /Losses available to carry forward/);
});

test('stored trade, pay, asset and tax values load into the terms form', async () => {
    const html = await render({ can_edit: true });
    assert.match(html, /Trade terms, owner pay, assets and tax/);
    assert.match(html, /value="invoice-104"/);
    assert.match(html, /value="laptop-2026"/);
    assert.match(html, /value="36"/);
    // A loss keeps its sign on the way back to the form.
    assert.match(html, /value="-4500.00"/);
    assert.match(html, /Save trade, pay, assets and tax/);
});

test('a revenue threshold is asked for only by the trigger that uses one', async () => {
    const staged = await render({ can_edit: true });
    assert.match(staged, /Revenue threshold/);

    const dated = await render({
        can_edit: true,
        model: {
            ...model,
            owner_pay_steps: [
                {
                    effective_on: '2026-07-01',
                    weekly_amount: '800',
                    trigger: 'base',
                    threshold: null,
                },
            ],
        },
    });
    // The engine refuses a base step that carries a threshold, so the form
    // never offers one to attach.
    assert.doesNotMatch(dated, /Revenue threshold/);
    assert.match(dated, /Its date alone/);
});

test('the terms form is withheld until a calendar exists to project across', async () => {
    const html = await render({ can_edit: true, model: null });
    assert.match(html, /Depreciation and tax are both projected across a/);
    assert.doesNotMatch(html, /Save trade, pay, assets and tax/);
});

test('the engine verdict is reported beside what has been recorded', async () => {
    const html = await render();
    assert.match(html, /What the engine makes of it/);
    assert.match(html, /cannot read|cannot complete/);
    assert.match(html, /1 of 24 months have a base revenue figure/);
    // Gaps are counted by reason, not repeated once per month.
    assert.match(html, /founder.available_hours/);
    assert.match(html, /in 23 months, first 2026-01/);
});

test('a reconstruction the engine refuses to project says so', async () => {
    const html = await render({
        model: {
            ...model,
            projection: {
                engine_version: 'phase-2e.1',
                status: 'fail',
                months_projected: 0,
                base_revenue_months: 0,
                missing: [],
                errors: [],
                refusal:
                    'A schedule extends beyond the declared calendar; do not silently discard required periods.',
            },
        },
    });
    assert.match(html, /cannot project this reconstruction as it stands/);
    assert.match(html, /The engine refused it/);
    assert.match(html, /extends beyond the declared calendar/);
});

test('the dated cash run reports its two funding measures apart', async () => {
    const html = await render();
    assert.match(html, /Dated cash/);
    assert.match(html, /2 dated movements reached the ledger/);
    // Cash already in the opening balance is counted out, not counted twice.
    assert.match(html, /1 settled before the forecast opens/);
    assert.match(html, /Lowest cash 3500.00 NZD on 2026-02-10/);
    // The deficit sits inside the shortfall, so the page says not to add them.
    assert.match(html, /never added/);
    assert.match(html, /owner_pay.source_and_first_payment_date/);
});

test('a ledger the engine refuses to position says so', async () => {
    const html = await render({
        model: {
            ...model,
            cash: {
                ...model.cash!,
                refusal:
                    'Provide unique same-day ordering and carry historical cash in the opening balance.',
                lowest_cash: null,
            },
        },
    });
    assert.match(html, /The ledger refused it/);
    assert.match(html, /unique same-day ordering/);
});

test('the owner pay start and the losses carried in are editable', async () => {
    const html = await render({ can_edit: true });
    assert.match(html, /First payment lands on/);
    assert.match(html, /Where the arrangement is recorded/);
    assert.match(html, /value="Signed owner drawings policy"/);
    assert.match(html, /Losses carried into the first period/);
    assert.match(html, /value="4500.00"/);
    assert.match(html, /before any pay reaches the cash ledger/);
});

test('the reserve target is shown as a calculated figure with its parts', async () => {
    const html = await render();
    assert.match(html, /Reserve target 3350.00 NZD/);
    assert.match(html, /base reserve of 3000.00/);
    const incomplete = await render({
        model: {
            ...model,
            reserve: {
                ...model.reserve,
                target: null,
                base_reserve: null,
                contingency: null,
                missing: ['reserve.basis'],
            },
        },
    });
    assert.match(incomplete, /Reserve target not calculated/);
    assert.match(incomplete, /reserve.basis/);
});

test('an advisor without edit authority sees no form and no selection control', async () => {
    const html = await render();
    assert.doesNotMatch(html, /Save reconstruction/);
    assert.doesNotMatch(html, /Save streams and capacity/);
    assert.doesNotMatch(html, /Save trade, pay, assets and tax/);
    assert.doesNotMatch(html, /Select as the plan financial model/);
    const editable = await render({ can_edit: true });
    assert.match(editable, /Save reconstruction/);
    assert.match(editable, /Select as the plan financial model/);
    assert.match(
        editable,
        /will read as Cannot verify rather than as agreement/,
    );
});

test('a reconstruction the engine cannot read is reported and not offered for selection', async () => {
    const html = await render({
        can_edit: true,
        model: {
            ...model,
            readable: false,
            load_error: 'Stored forecast value "calendar" is missing.',
        },
    });
    assert.match(html, /cannot be read back by the engine/);
    assert.match(html, /It cannot be selected until it is corrected/);
});

test('a plan with no reconstruction says so rather than showing an empty model', async () => {
    const html = await render({ model: null });
    assert.match(html, /No reconstruction has been saved for this plan/);
    assert.match(html, /uses the legacy budget/);
    assert.doesNotMatch(html, /Revision/);
});
