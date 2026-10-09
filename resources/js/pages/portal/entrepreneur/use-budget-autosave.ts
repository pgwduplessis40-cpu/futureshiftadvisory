import { useCallback, useEffect, useRef, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';
import { cleanBudgetForm } from './plan-budget';
import type { BudgetFormState, BudgetPayload } from './plan-types';
import { postBudgetAutosave } from './plan-workspace-draft';
import type { AutosaveState } from './plan-workspace-draft';

type UseBudgetAutosaveOptions = {
    enabled: boolean;
    form: BudgetFormState;
    setForm: Dispatch<SetStateAction<BudgetFormState>>;
    url: string;
    onSaved: (budget: BudgetPayload) => void;
};

export function useBudgetAutosave({
    enabled,
    form,
    setForm,
    url,
    onSaved,
}: UseBudgetAutosaveOptions) {
    const [autosaveState, setAutosaveState] = useState<AutosaveState>('idle');
    const readyRef = useRef(false);

    const saveDraft = useCallback(async () => {
        if (!enabled) {
            return;
        }

        setAutosaveState('saving');

        try {
            const result = await postBudgetAutosave(url, cleanBudgetForm(form));

            if (result.budget !== null) {
                onSaved(result.budget);
                setForm((current) =>
                    result.budget !== null &&
                    result.budget.revision > current.revision
                        ? { ...current, revision: result.budget.revision }
                        : current,
                );
            }

            setAutosaveState(result.saved ? 'saved' : 'error');
        } catch {
            setAutosaveState('error');
        }
    }, [enabled, form, onSaved, setForm, url]);

    const updateForm = useCallback<Dispatch<SetStateAction<BudgetFormState>>>(
        (updater) => {
            setAutosaveState('saving');
            setForm(updater);
        },
        [setForm],
    );

    useEffect(() => {
        if (enabled) {
            return;
        }

        readyRef.current = false;
    }, [enabled]);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        if (!readyRef.current) {
            readyRef.current = true;

            return;
        }

        const timeout = window.setTimeout(() => {
            void saveDraft();
        }, 2500);

        return () => window.clearTimeout(timeout);
    }, [enabled, form, saveDraft]);

    return {
        autosaveState,
        updateForm,
        retry: () => void saveDraft(),
    };
}
