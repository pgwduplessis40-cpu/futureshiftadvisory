import type { FinancialReconciliation } from './founder-brief-reconciliation';
export const reconciliationFixture: FinancialReconciliation = {
    source_token: 'source-1',
    available: true,
    current: false,
    complete: false,
    as_of: null,
    saved_at: null,
    topics: [
        {
            key: 'revenue_scope',
            title: 'Base revenue and other offers',
            guide: 'Agree the base case before release.',
            plan_keys: ['financial-assumptions'],
            budget_facts: ['Workshops: $1,000 monthly.'],
            state: 'unresolved',
            note: '',
        },
    ],
    sources: [
        {
            key: 'financial-assumptions',
            title: 'Financial assumptions',
            body: 'Only workshops are included in the base forecast.',
        },
    ],
};
