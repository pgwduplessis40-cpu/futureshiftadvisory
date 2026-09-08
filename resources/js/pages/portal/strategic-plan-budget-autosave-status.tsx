import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type AutosaveState = 'saved' | 'pending' | 'saving' | 'error';

export function AutosaveStatus({
    state,
    error,
    onRetry,
}: {
    state: AutosaveState;
    error: string | null;
    onRetry: () => void;
}) {
    if (state === 'error') {
        return (
            <span className="inline-flex items-center gap-1" role="alert">
                <Badge variant="destructive" title={error ?? undefined}>
                    Autosave failed
                </Badge>
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    className="h-auto px-0 text-xs text-destructive"
                    onClick={onRetry}
                >
                    Retry save
                </Button>
            </span>
        );
    }

    if (state === 'saving') {
        return <Badge variant="outline">Autosaving…</Badge>;
    }

    if (state === 'pending') {
        return <Badge variant="outline">Saving shortly…</Badge>;
    }

    return <Badge variant="secondary">Saved automatically</Badge>;
}
