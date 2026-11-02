import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export function AdvisorPlanBudgetReview({
    reviewUrl,
    recalculateUrl,
    needsReview,
    needsRecalculation,
}: {
    reviewUrl?: string;
    recalculateUrl?: string;
    needsReview: boolean;
    needsRecalculation: boolean;
}) {
    const [note, setNote] = useState('');
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const options = {
        preserveScroll: true,
        onStart: () => {
            setPending(true);
            setError(null);
        },
        onFinish: () => setPending(false),
        onError: (errors: Record<string, string>) =>
            setError(Object.values(errors).join(' ')),
    };

    return (
        <div className="space-y-3">
            {needsRecalculation && recalculateUrl ? (
                <Button
                    type="button"
                    variant="outline"
                    disabled={pending}
                    onClick={() => router.post(recalculateUrl, {}, options)}
                >
                    Recalculate saved Budget and reassess
                </Button>
            ) : null}
            {needsReview && reviewUrl ? (
                <form
                    className="space-y-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.patch(reviewUrl, { note }, options);
                    }}
                >
                    <Label htmlFor="plan-budget-review-note">
                        Advisor decision and rationale
                    </Label>
                    <p className="text-sm text-muted-foreground">
                        Explain why these findings are acceptable for
                        progression. This records your decision for this
                        assessment only. Client corrections and calculation
                        issues still need resolution.
                    </p>
                    <Textarea
                        id="plan-budget-review-note"
                        value={note}
                        minLength={15}
                        maxLength={2000}
                        required
                        rows={3}
                        onChange={(event) => setNote(event.target.value)}
                    />
                    <Button
                        type="submit"
                        disabled={pending || note.trim().length < 15}
                    >
                        Accept advisor review findings
                    </Button>
                </form>
            ) : null}
            {error ? (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
