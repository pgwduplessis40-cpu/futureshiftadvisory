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
    assert.match(html, /twenty enabled rules/);
    assert.match(html, /Saved but not selected/);
});

test('uncaptured sections are reported rather than left blank', async () => {
    const html = await render();
    assert.match(html, /Trade terms/);
    assert.match(html, /Not captured/);
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
