export type AcknowledgementBlocker =
    | 'advisor_package_selection_required'
    | 'payment_required'
    | 'ready';

export function acknowledgementCopy(
    blocker: AcknowledgementBlocker,
    paymentRequired: boolean,
): { description: string; blockerMessage: string | null } {
    if (blocker === 'advisor_package_selection_required') {
        return {
            description:
                'FSA will confirm the package, scope, and any payment requirement before this acknowledgement opens.',
            blockerMessage:
                'This acknowledgement is locked while FSA confirms the package and scope. You do not need to make a payment at this stage.',
        };
    }

    if (paymentRequired) {
        return {
            description:
                'Full payment must be received and confirmed first; this checkbox confirms the workspace-specific scope and GST-exclusive fee.',
            blockerMessage:
                'This acknowledgement is locked because payment is still pending. Complete the required payment step above before the service, workspace, reports, previews, downloads, or exports open.',
        };
    }

    return {
        description:
            'No payment is required before this workspace opens; this checkbox confirms the workspace-specific scope and fee waiver position.',
        blockerMessage: null,
    };
}
