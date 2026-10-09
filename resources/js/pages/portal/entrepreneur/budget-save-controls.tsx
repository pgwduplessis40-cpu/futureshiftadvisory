import { Link } from '@inertiajs/react';
import { Eye, RefreshCw } from 'lucide-react';
import { DraftSaveStatus } from '@/components/portal/draft-save-status';
import { Button } from '@/components/ui/button';
import type { BudgetPayload } from './plan-types';

type DraftSave = {
    state: 'idle' | 'saving' | 'saved' | 'error';
    retry: () => void;
};

export function BudgetHeaderActions({
    budget,
    budgetDraft,
}: {
    budget: BudgetPayload;
    budgetDraft: DraftSave;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <DraftSaveStatus
                draft={budgetDraft}
                idleLabel="Changes save automatically"
                savedLabel="All changes saved"
            />
            {budget.pack_available && budget.budget_pack_url ? (
                <Button type="button" size="sm" variant="outline" asChild>
                    <Link href={budget.budget_pack_url}>
                        <Eye className="size-4" aria-hidden="true" />
                        View budget pack
                    </Link>
                </Button>
            ) : null}
        </div>
    );
}

export function BudgetCalculationRefresh({
    refreshing,
    budgetDraftState,
    onRefresh,
}: {
    refreshing: boolean;
    budgetDraftState: 'idle' | 'saving' | 'saved' | 'error';
    onRefresh: () => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={onRefresh}
                disabled={
                    refreshing ||
                    budgetDraftState === 'saving' ||
                    budgetDraftState === 'error'
                }
            >
                <RefreshCw className="size-4" aria-hidden="true" />
                {refreshing
                    ? 'Refreshing calculations'
                    : 'Refresh calculations'}
            </Button>
            <span className="text-xs text-muted-foreground">
                Calculations update automatically after changes are saved.
            </span>
        </div>
    );
}
