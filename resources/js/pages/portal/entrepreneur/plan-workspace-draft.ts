export type AutosaveState = 'idle' | 'saving' | 'saved' | 'error';

export type SectionDraft = {
    title: string;
    body: string;
    updatedAt: string;
};

export type SectionTextareaPosition = {
    scrollTop: number;
    selectionStart: number;
    selectionEnd: number;
};

export type PlanWorkspaceDraft<TBudgetForm = unknown> = {
    selectedKey?: string | null;
    windowScrollY?: number;
    sectionDrafts?: Record<string, SectionDraft>;
    sectionPositions?: Record<string, SectionTextareaPosition>;
    budgetForm?: TBudgetForm;
};

export type SectionAutosavePayload = {
    phase_key: string;
    requirement_key: string;
    title: string;
    body: string;
    attached_document_ids?: string[];
};

export function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export function readPlanWorkspaceDraft<TBudgetForm = unknown>(
    key: string,
): PlanWorkspaceDraft<TBudgetForm> | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(key);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as PlanWorkspaceDraft<TBudgetForm>;

        return typeof parsed === 'object' && parsed !== null ? parsed : null;
    } catch {
        return null;
    }
}

export function updatePlanWorkspaceDraft<TBudgetForm = unknown>(
    key: string,
    updater: (
        draft: PlanWorkspaceDraft<TBudgetForm>,
    ) => PlanWorkspaceDraft<TBudgetForm>,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const current = readPlanWorkspaceDraft<TBudgetForm>(key) ?? {};

    try {
        window.localStorage.setItem(key, JSON.stringify(updater(current)));
    } catch {
        // Browsers can reject storage in private mode or when quota is full.
    }
}

export function localDraftIsNewer(
    draftUpdatedAt: string,
    serverUpdatedAt: string | null,
): boolean {
    if (!serverUpdatedAt) {
        return true;
    }

    return Date.parse(draftUpdatedAt) > Date.parse(serverUpdatedAt);
}

export function currentSectionTextareaPosition(): SectionTextareaPosition | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const textarea = document.getElementById(
        'entrepreneur-plan-section-body',
    ) as HTMLTextAreaElement | null;

    if (!textarea) {
        return null;
    }

    return {
        scrollTop: textarea.scrollTop,
        selectionStart: textarea.selectionStart,
        selectionEnd: textarea.selectionEnd,
    };
}

export function restoreSectionTextareaPosition(
    key: string,
    sectionKey: string,
): void {
    if (typeof document === 'undefined') {
        return;
    }

    const position =
        readPlanWorkspaceDraft(key)?.sectionPositions?.[sectionKey];
    const textarea = document.getElementById(
        'entrepreneur-plan-section-body',
    ) as HTMLTextAreaElement | null;

    if (!position || !textarea) {
        return;
    }

    textarea.scrollTop = position.scrollTop;
    textarea.setSelectionRange(position.selectionStart, position.selectionEnd);
}

export async function postSectionAutosave(
    url: string,
    payload: SectionAutosavePayload,
): Promise<boolean> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            ...payload,
            _autosave: true,
            attached_document_ids: payload.attached_document_ids ?? [],
        }),
    });

    return response.ok;
}

export async function postBudgetAutosave(
    url: string,
    payload: object,
): Promise<boolean> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            ...payload,
            _autosave: true,
        }),
    });

    return response.ok;
}
