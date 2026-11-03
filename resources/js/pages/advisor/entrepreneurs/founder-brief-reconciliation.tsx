import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useId, useRef, useState } from 'react';

type DecisionState = 'unresolved' | 'aligned' | 'explained' | 'not_applicable';
type Decision = { state: DecisionState; note: string };
export type FinancialReconciliation = {
    source_token: string;
    available: boolean;
    current: boolean;
    complete: boolean;
    as_of: string | null;
    saved_at: string | null;
    topics: {
        key: string;
        title: string;
        guide: string;
        plan_keys: string[];
        budget_facts: string[];
        state: DecisionState;
        note: string;
    }[];
    sources: { key: string; title: string; body: string }[];
};

export function FounderBriefReconciliation({
    reconciliation,
    saveUrl,
}: {
    reconciliation: FinancialReconciliation;
    saveUrl: string;
}) {
    return (
        <ReconciliationForm
            key={`${reconciliation.source_token}:${reconciliation.saved_at}`}
            reconciliation={reconciliation}
            saveUrl={saveUrl}
        />
    );
}

function ReconciliationForm({
    reconciliation,
    saveUrl,
}: {
    reconciliation: FinancialReconciliation;
    saveUrl: string;
}) {
    const id = useId();
    const [asOf, setAsOf] = useState(reconciliation.as_of ?? '');
    const [decisions, setDecisions] = useState<Record<string, Decision>>(() =>
        Object.fromEntries(
            reconciliation.topics.map((topic) => [
                topic.key,
                { state: topic.state, note: topic.note },
            ]),
        ),
    );
    const [busy, setBusy] = useState(false);
    const submitting = useRef(false);
    const [error, setError] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);
    const resolved = Object.values(decisions).filter(
        (row) => row.state !== 'unresolved' && row.note.trim().length >= 15,
    ).length;
    const update = (key: string, change: Partial<Decision>) => {
        setSaved(false);
        setDecisions((previous) => ({
            ...previous,
            [key]: { ...previous[key], ...change },
        }));
    };

    return (
        <details className="max-w-4xl rounded-md border p-3">
            <summary className="cursor-pointer text-sm font-medium">
                Financial reconciliation —{' '}
                {reconciliation.complete
                    ? 'current review complete'
                    : 'review required'}
            </summary>
            <p className="mt-3 text-sm text-muted-foreground">
                Agree one dated set of assumptions before external issue.
                Compare the saved plan wording with the Budget facts below.
                Update the source plan or Budget where needed, reassess and
                regenerate the summary, then record the agreed values and
                references here. This record does not change the forecast or
                client progression.
            </p>
            {!reconciliation.current && reconciliation.saved_at ? (
                <p role="status" className="mt-2 text-sm text-amber-800">
                    The sources changed after the previous review. Earlier notes
                    are retained as a reference; every topic needs a fresh
                    decision.
                </p>
            ) : null}
            {!reconciliation.available ? (
                <p className="mt-2 text-sm">
                    Run the assessment before saving this review.
                </p>
            ) : null}
            <form
                className="mt-3 space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (submitting.current || !reconciliation.available) {
                        return;
                    }

                    submitting.current = true;
                    setBusy(true);
                    setError(null);
                    router.post(
                        saveUrl,
                        {
                            source_token: reconciliation.source_token,
                            as_of: asOf,
                            decisions,
                        },
                        {
                            preserveScroll: true,
                            onError: (errors) =>
                                setError(Object.values(errors).join(' ')),
                            onSuccess: () => setSaved(true),
                            onFinish: () => {
                                submitting.current = false;
                                setBusy(false);
                            },
                        },
                    );
                }}
            >
                <label
                    htmlFor={`${id}-as-of`}
                    className="block text-sm font-medium"
                >
                    Assumptions agreed as at
                </label>
                <input
                    id={`${id}-as-of`}
                    type="date"
                    value={asOf}
                    required
                    onChange={(event) => {
                        setAsOf(event.target.value);
                        setSaved(false);
                    }}
                    className="rounded-md border bg-background p-2 text-sm"
                />
                {reconciliation.topics.map((topic) => (
                    <details key={topic.key} className="rounded-md border p-3">
                        <summary className="cursor-pointer text-sm font-medium">
                            {topic.title}
                        </summary>
                        <p className="mt-2 text-sm">{topic.guide}</p>
                        <p className="mt-3 text-sm font-medium">
                            Saved Budget facts
                        </p>
                        <ul className="mt-1 list-disc space-y-1 pl-5 text-sm">
                            {topic.budget_facts.map((fact, index) => (
                                <li key={index}>{fact}</li>
                            ))}
                        </ul>
                        <details className="mt-3">
                            <summary className="cursor-pointer text-sm font-medium">
                                Compare saved plan responses
                            </summary>
                            {reconciliation.sources
                                .filter((source) =>
                                    topic.plan_keys.some(
                                        (key) =>
                                            source.key === key ||
                                            source.key.endsWith(`-${key}`),
                                    ),
                                )
                                .map((source, index) => (
                                    <div
                                        key={`${source.key}-${index}`}
                                        className="mt-3 rounded-md bg-muted p-3"
                                    >
                                        <p className="text-sm font-medium">
                                            {source.title}
                                        </p>
                                        <p className="mt-2 text-sm whitespace-pre-wrap">
                                            {source.body ||
                                                'No saved response.'}
                                        </p>
                                    </div>
                                ))}
                            <p className="mt-2 text-xs text-muted-foreground">
                                Review the complete plan and evidence workspace
                                for other relevant responses.
                            </p>
                        </details>
                        <label
                            htmlFor={`${id}-${topic.key}`}
                            className="mt-3 block text-sm font-medium"
                        >
                            Decision
                        </label>
                        <select
                            id={`${id}-${topic.key}`}
                            value={decisions[topic.key].state}
                            onChange={(event) =>
                                update(topic.key, {
                                    state: event.target.value as DecisionState,
                                })
                            }
                            className="mt-1 w-full rounded-md border bg-background p-2 text-sm"
                        >
                            <option value="unresolved">
                                Unresolved — correction or evidence needed
                            </option>
                            <option value="aligned">
                                Aligned — values and timing agree
                            </option>
                            <option value="explained">
                                Difference explained and disclosed
                            </option>
                            <option value="not_applicable">
                                Not applicable — explain why
                            </option>
                        </select>
                        <label
                            htmlFor={`${id}-${topic.key}-note`}
                            className="mt-2 block text-sm font-medium"
                        >
                            Agreed assumptions, resolution and source references
                        </label>
                        <textarea
                            id={`${id}-${topic.key}-note`}
                            value={decisions[topic.key].note}
                            onChange={(event) =>
                                update(topic.key, { note: event.target.value })
                            }
                            required={
                                decisions[topic.key].state !== 'unresolved'
                            }
                            minLength={
                                decisions[topic.key].state !== 'unresolved'
                                    ? 15
                                    : undefined
                            }
                            maxLength={2000}
                            className="mt-1 min-h-24 w-full rounded-md border bg-background p-2 text-sm"
                        />
                    </details>
                ))}
                <p className="text-sm">
                    {resolved} of {reconciliation.topics.length} topics
                    resolved. You can save unfinished work; unresolved topics
                    keep the brief in draft.
                </p>
                <Button
                    type="submit"
                    size="sm"
                    disabled={busy || !reconciliation.available}
                >
                    {busy
                        ? 'Saving reconciliation…'
                        : 'Save financial reconciliation'}
                </Button>
                {error ? (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                ) : null}
                {saved ? (
                    <p role="status" className="text-sm">
                        Reconciliation saved. Review the updated release status
                        before issuing the brief.
                    </p>
                ) : null}
            </form>
        </details>
    );
}
