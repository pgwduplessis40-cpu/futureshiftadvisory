import assert from 'node:assert/strict';
import test from 'node:test';
import { hasActiveBusinessPlanBudgetWorkspace } from './client-detail-service-visibility';

test('Business Plan & Budget actions require an active client workspace', () => {
    assert.equal(
        hasActiveBusinessPlanBudgetWorkspace({
            active_key: 'entrepreneur',
            items: [
                {
                    key: 'entrepreneur',
                    label: 'Entrepreneur',
                    href: '/advisor/entrepreneurs/client-1',
                    active: true,
                },
            ],
        }),
        false,
    );

    assert.equal(
        hasActiveBusinessPlanBudgetWorkspace({
            active_key: 'dd_plan_budget',
            items: [
                {
                    key: 'dd_plan_budget',
                    label: 'Business Plan & Budget',
                    href: '/advisor/clients/client-1#section-strategic-budget',
                    active: true,
                },
            ],
        }),
        true,
    );
});
