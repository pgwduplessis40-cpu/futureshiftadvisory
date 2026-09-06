import assert from 'node:assert/strict';
import test from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { ServiceInviteOffers } from './ServiceInviteOffers';
import type { ServiceOffers } from './ServiceInviteOffers';

const offer: ServiceOffers['items'][number] = {
    id: 'dd-offer',
    label: 'Due Diligence',
    scope_label: 'Selected DD scope',
    description: 'Agreed scope',
    fixed_fee: 3200,
    currency: 'NZD',
    included_stages: ['Review'],
    acknowledged_at: null,
    activation_url: '/offer',
};

function render(serviceOffers: ServiceOffers): string {
    return renderToStaticMarkup(
        createElement(ServiceInviteOffers, {
            serviceOffers,
            checked: false,
            onCheckedChange: () => undefined,
        }),
    );
}

test('existing onboarding without invited offers adds no fee agreement UI', () => {
    assert.equal(render({ must_acknowledge: false, items: [] }), '');
});

test('selected DD and BP&B prices and their combined total remain visible', () => {
    const html = render({
        must_acknowledge: true,
        items: [
            offer,
            {
                ...offer,
                id: 'bp-offer',
                label: 'Business Plan & Budget',
                fixed_fee: 1200,
            },
        ],
    });
    assert.match(html, /Due Diligence/);
    assert.match(html, /Business Plan &amp; Budget/);
    assert.match(html, /4,400\.00/);
    assert.match(html, /3,200\.00/);
    assert.match(html, /1,200\.00/);
    assert.match(html, /service_offers_acknowledged/);
    assert.match(html, /other services remain by request/);
});

test('an already acknowledged offer retains the price without another checkbox', () => {
    const html = render({ must_acknowledge: false, items: [offer] });
    assert.match(html, /3,200\.00/);
    assert.match(html, /already acknowledged/);
    assert.doesNotMatch(html, /service_offers_acknowledged/);
});
