import assert from 'node:assert/strict';
import test from 'node:test';
import { planBudgetApprovalMessage } from './PlanBudgetAccess';

test('the locked Business Plan and Budget message includes the live service rate', () => {
    assert.equal(
        planBudgetApprovalMessage('$3,450 + GST'),
        'Once your idea meets the minimum criteria and your advisor approves it, you will be able to purchase Business Plan & Budget here for $3,450 + GST.',
    );
});
