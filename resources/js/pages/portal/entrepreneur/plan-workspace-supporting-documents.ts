import type { PlanSectionPayload } from './plan-types';
import { postSectionAutosave } from './plan-workspace-draft';
import type {
    AutosaveState,
    SectionAutosaveResult,
} from './plan-workspace-draft';

type SupportingDocument = PlanSectionPayload['attached_documents'][number];

type SyncPlanSupportingDocuments = {
    selectedRequirement: { phase_key: string; key: string } | null;
    url: string;
    title: string;
    body: string;
    documentIds: string[];
    documents: PlanSectionPayload['attached_documents'];
    successNotice: string;
    failureNotice: string;
    pendingDocument: SupportingDocument | null;
    onAutosaveState: (state: AutosaveState) => void;
    onDocumentIds: (documentIds: string[]) => void;
    onDocuments: (documents: PlanSectionPayload['attached_documents']) => void;
    onPendingDocument: (document: SupportingDocument | null) => void;
    onNotice: (notice: string) => void;
    onError: (message: string) => void;
    onStatusChange: (saved: SectionAutosaveResult) => void;
};

export async function syncPlanSupportingDocuments({
    selectedRequirement,
    url,
    title,
    body,
    documentIds,
    documents,
    successNotice,
    failureNotice,
    pendingDocument,
    onAutosaveState,
    onDocumentIds,
    onDocuments,
    onPendingDocument,
    onNotice,
    onError,
    onStatusChange,
}: SyncPlanSupportingDocuments): Promise<boolean> {
    if (!selectedRequirement) {
        return false;
    }

    onAutosaveState('saving');

    try {
        const saved = await postSectionAutosave(url, {
            phase_key: selectedRequirement.phase_key,
            requirement_key: selectedRequirement.key,
            title,
            body,
            attached_document_ids: documentIds,
        });

        onAutosaveState(saved ? 'saved' : 'error');

        if (!saved) {
            onPendingDocument(pendingDocument);
            onError(failureNotice);

            return false;
        }

        onDocumentIds(documentIds);
        onDocuments(documents);
        onPendingDocument(null);
        onNotice(successNotice);
        onStatusChange(saved);

        return true;
    } catch {
        onAutosaveState('error');
        onPendingDocument(pendingDocument);
        onError(failureNotice);

        return false;
    }
}
