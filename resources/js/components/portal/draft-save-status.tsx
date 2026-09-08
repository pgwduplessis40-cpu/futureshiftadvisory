import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type DraftState = 'idle' | 'saving' | 'saved' | 'error';

type DraftSave = {
    state: DraftState;
    hasRecovery?: boolean;
    retry: () => void;
};

type Props = {
    draft: DraftSave;
    className?: string;
    idleLabel?: string;
    savedLabel?: string;
};

/**
 * Gives every draft-capable client workspace the same honest save signal.
 * A failed request stays visible and can be retried without finalising the
 * client workflow.
 */
export function DraftSaveStatus({
    draft,
    className,
    idleLabel = 'Saves automatically',
    savedLabel = 'Draft saved automatically',
}: Props) {
    if (draft.state === 'error') {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 text-xs text-destructive',
                    className,
                )}
                role="alert"
            >
                {draft.hasRecovery
                    ? 'Draft could not be saved to Future Shift. A temporary recovery copy remains in this browser; retry before closing.'
                    : 'Draft could not be saved to Future Shift. Keep this page open and retry before closing.'}
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    className="h-auto px-0 text-xs text-destructive"
                    onClick={draft.retry}
                >
                    Retry save
                </Button>
            </span>
        );
    }

    const label =
        draft.state === 'saving'
            ? 'Saving draft…'
            : draft.state === 'saved'
              ? savedLabel
              : idleLabel;

    return (
        <span
            className={cn('text-xs text-muted-foreground', className)}
            role="status"
        >
            {label}
        </span>
    );
}
