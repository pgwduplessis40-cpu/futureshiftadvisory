import type { EntrepreneurDetail } from './types';

export function shouldOpenIdeaValidation(
    ideaValidation: EntrepreneurDetail['idea_validation'],
): boolean {
    if (!ideaValidation) {
        return true;
    }

    if (
        ideaValidation.advisor_gate_status === 'changes_requested' ||
        ideaValidation.advisor_gate_status === 'recalled'
    ) {
        return true;
    }

    return ideaValidation.viability_gate.status !== 'green';
}
