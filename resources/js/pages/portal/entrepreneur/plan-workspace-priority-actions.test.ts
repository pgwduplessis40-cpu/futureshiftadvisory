import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldShowPlanWorkspacePriorityActions } from './plan-workspace-priority-actions';

test('Idea Validation-only packages omit plan-oriented priority cards', () => {
    assert.equal(shouldShowPlanWorkspacePriorityActions(true, false), false);
});

test('packages that include the plan retain priority cards', () => {
    assert.equal(shouldShowPlanWorkspacePriorityActions(true, true), true);
    assert.equal(shouldShowPlanWorkspacePriorityActions(false, true), true);
});
