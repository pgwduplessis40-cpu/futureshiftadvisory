import assert from 'node:assert/strict';
import test from 'node:test';
import { navGroupsFor } from './app-sidebar';

const standardAdvisoryClient = {
    engagement_type: 'standard_advisory',
    onboarding_complete: false,
} as NonNullable<Parameters<typeof navGroupsFor>[1]>;

test('Standard Advisory presents Business Plan & Budget as a request until the add-on is active', () => {
    const requestGroups = navGroupsFor(
        'client_primary',
        standardAdvisoryClient,
        {
            options: [
                {
                    service_type: 'dd_plan_budget',
                    label: 'Business Plan & Budget',
                    description: 'Request the optional planning add-on.',
                    available: true,
                    start_url: '/portal/service-activations/new/dd_plan_budget',
                },
            ],
            items: [],
        } as NonNullable<Parameters<typeof navGroupsFor>[2]>,
    );

    assert.equal(
        itemFor(requestGroups, 'Services', 'Business Plan & Budget')?.href,
        '/portal/service-activations/new/dd_plan_budget',
    );
    assert.equal(
        itemFor(requestGroups, 'Platform', 'Business Plan & Budget'),
        undefined,
    );

    const activeGroups = navGroupsFor(
        'client_primary',
        standardAdvisoryClient,
        {
            options: [
                {
                    service_type: 'dd_plan_budget',
                    label: 'Business Plan & Budget',
                    description: 'Request the optional planning add-on.',
                    available: false,
                    start_url: '/portal/service-activations/new/dd_plan_budget',
                },
            ],
            items: [
                {
                    id: 'plan-budget',
                    service_type: 'dd_plan_budget',
                    client_label: 'Business Plan & Budget',
                    status: 'active',
                    url: '/portal/service-activations/plan-budget',
                    workspace_url: '/portal/business-plan-budget',
                },
            ],
        } as NonNullable<Parameters<typeof navGroupsFor>[2]>,
        {
            active_key: 'standard_advisory',
            items: [
                {
                    key: 'standard_advisory',
                    service_type: 'standard_advisory',
                    label: 'Standard Advisory',
                    description: 'Primary advisory workspace.',
                    href: '/portal',
                    primary: true,
                    status_label: 'Original service',
                    badge_count: null,
                },
                {
                    key: 'dd_plan_budget',
                    service_type: 'dd_plan_budget',
                    label: 'Business Plan & Budget',
                    description: 'Approved planning add-on.',
                    href: '/portal/business-plan-budget',
                    primary: false,
                    status_label: 'Active',
                    badge_count: null,
                },
            ],
        } as NonNullable<Parameters<typeof navGroupsFor>[3]>,
    );

    assert.equal(
        itemFor(activeGroups, 'Platform', 'Business Plan & Budget')?.href,
        '/portal/business-plan-budget',
    );
    assert.equal(
        itemFor(activeGroups, 'Services', 'Business Plan & Budget'),
        undefined,
    );
});

function itemFor(
    groups: ReturnType<typeof navGroupsFor>,
    groupTitle: string,
    itemTitle: string,
) {
    return groups
        .find((group) => group.title === groupTitle)
        ?.items.find((item) => item.title === itemTitle);
}
