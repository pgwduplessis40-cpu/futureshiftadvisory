import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Question = {
    id: string;
    order: number;
    type: string;
    key: string;
    prompt: string;
    help_text: string | null;
    required: boolean;
    options: unknown;
};

type Deliverable = {
    source_type: string;
    source_id: string;
    title: string;
    label: string | null;
    delivered_at: string | null;
};

type AnchorAnswer = {
    source_type: string;
    source_id: string;
    received: boolean | null;
    accessible: boolean | null;
    met_objective: boolean | null;
};

type FormAnswer = {
    value?: number | boolean | null;
    anchors?: AnchorAnswer[];
};

type Assignment = {
    id: string;
    survey_title: string;
    survey_description: string | null;
    status: string;
    is_open: boolean;
    due_at: string | null;
    deliverables: Deliverable[];
    questions: Question[];
};

type Props = {
    assignment: Assignment;
    storeUrl: string;
    indexUrl: string;
};

const likert = [1, 2, 3, 4, 5];
const nps = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

export default function PortalSurveyShow({
    assignment,
    storeUrl,
    indexUrl,
}: Props) {
    const form = useForm<{ answers: Record<string, FormAnswer> }>({
        answers: initialAnswers(assignment),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeUrl, { preserveScroll: true });
    };

    const setFlat = (questionId: string, value: number | boolean) => {
        form.setData('answers', {
            ...form.data.answers,
            [questionId]: {
                ...form.data.answers[questionId],
                value,
            },
        });
    };

    const setAnchor = (
        questionId: string,
        sourceId: string,
        key: keyof Pick<
            AnchorAnswer,
            'received' | 'accessible' | 'met_objective'
        >,
        value: boolean,
    ) => {
        const current = form.data.answers[questionId]?.anchors ?? [];
        form.setData('answers', {
            ...form.data.answers,
            [questionId]: {
                anchors: current.map((anchor) =>
                    anchor.source_id === sourceId
                        ? { ...anchor, [key]: value }
                        : anchor,
                ),
            },
        });
    };

    return (
        <>
            <Head title={assignment.survey_title} />

            <main className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link
                            href={indexUrl}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Feedback
                        </Link>
                        <h1 className="mt-3 text-xl font-semibold">
                            {assignment.survey_title}
                        </h1>
                        {assignment.survey_description && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {assignment.survey_description}
                            </p>
                        )}
                    </div>
                    <Badge variant="secondary">{assignment.status}</Badge>
                </div>

                <div className="rounded-md border bg-background p-4 text-sm text-muted-foreground">
                    Your feedback is attributed to your account, not anonymous.
                    Honest feedback is crucial to improving the service and
                    portal, and it will never be held against you in any shape
                    or form.
                </div>

                <form onSubmit={submit} className="space-y-4">
                    {assignment.questions.map((question) => (
                        <section
                            key={question.id}
                            className="rounded-md border bg-background p-4"
                        >
                            <h2 className="text-sm font-medium">
                                {question.prompt}
                            </h2>
                            {question.help_text && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {question.help_text}
                                </p>
                            )}

                            {question.type === 'likert' && (
                                <div className="mt-4 grid grid-cols-5 gap-2">
                                    {likert.map((value) => (
                                        <ScaleButton
                                            key={value}
                                            active={
                                                form.data.answers[question.id]
                                                    ?.value === value
                                            }
                                            disabled={!assignment.is_open}
                                            label={String(value)}
                                            onClick={() =>
                                                setFlat(question.id, value)
                                            }
                                        />
                                    ))}
                                </div>
                            )}

                            {question.type === 'nps' && (
                                <div className="mt-4 grid grid-cols-6 gap-2 sm:grid-cols-11">
                                    {nps.map((value) => (
                                        <ScaleButton
                                            key={value}
                                            active={
                                                form.data.answers[question.id]
                                                    ?.value === value
                                            }
                                            disabled={!assignment.is_open}
                                            label={String(value)}
                                            onClick={() =>
                                                setFlat(question.id, value)
                                            }
                                        />
                                    ))}
                                </div>
                            )}

                            {question.type === 'boolean' && (
                                <div className="mt-4 flex gap-2">
                                    <ScaleButton
                                        active={
                                            form.data.answers[question.id]
                                                ?.value === true
                                        }
                                        disabled={!assignment.is_open}
                                        label="Yes"
                                        onClick={() =>
                                            setFlat(question.id, true)
                                        }
                                    />
                                    <ScaleButton
                                        active={
                                            form.data.answers[question.id]
                                                ?.value === false
                                        }
                                        disabled={!assignment.is_open}
                                        label="No"
                                        onClick={() =>
                                            setFlat(question.id, false)
                                        }
                                    />
                                </div>
                            )}

                            {question.type === 'anchored_matrix' && (
                                <div className="mt-4 overflow-hidden rounded-md border">
                                    <table className="fsa-responsive-table">
                                        <thead className="bg-muted/60 text-left">
                                            <tr>
                                                <th className="px-3 py-2 font-medium">
                                                    Deliverable
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Received
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Accessible
                                                </th>
                                                <th className="px-3 py-2 font-medium">
                                                    Objective
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {assignment.deliverables.map(
                                                (deliverable) => (
                                                    <tr
                                                        key={`${deliverable.source_type}:${deliverable.source_id}`}
                                                        className="border-t"
                                                    >
                                                        <td
                                                            className="px-3 py-2"
                                                            data-label="Deliverable"
                                                        >
                                                            <div className="font-medium">
                                                                {
                                                                    deliverable.title
                                                                }
                                                            </div>
                                                            <div className="text-sm text-muted-foreground">
                                                                {
                                                                    deliverable.label
                                                                }
                                                            </div>
                                                        </td>
                                                        {(
                                                            [
                                                                'received',
                                                                'accessible',
                                                                'met_objective',
                                                            ] as const
                                                        ).map((key) => (
                                                            <td
                                                                key={key}
                                                                className="px-3 py-2"
                                                                data-label={key}
                                                            >
                                                                <YesNo
                                                                    value={
                                                                        anchorValue(
                                                                            form
                                                                                .data
                                                                                .answers[
                                                                                question
                                                                                    .id
                                                                            ]
                                                                                ?.anchors,
                                                                            deliverable.source_id,
                                                                            key,
                                                                        ) ??
                                                                        null
                                                                    }
                                                                    disabled={
                                                                        !assignment.is_open
                                                                    }
                                                                    onChange={(
                                                                        value,
                                                                    ) =>
                                                                        setAnchor(
                                                                            question.id,
                                                                            deliverable.source_id,
                                                                            key,
                                                                            value,
                                                                        )
                                                                    }
                                                                />
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <InputError
                                message={
                                    form.errors[
                                        `answers.${question.id}.value` as keyof typeof form.errors
                                    ] as string | undefined
                                }
                            />
                        </section>
                    ))}

                    <div className="flex justify-end">
                        <div className="space-y-2 text-right">
                            <p className="text-sm text-muted-foreground">
                                Please answer honestly; your feedback helps
                                improve the service and will never be held
                                against you.
                            </p>
                            <Button
                                type="submit"
                                disabled={
                                    !assignment.is_open || form.processing
                                }
                            >
                                <Check className="size-4" aria-hidden="true" />
                                Submit
                            </Button>
                        </div>
                    </div>
                </form>
            </main>
        </>
    );
}

function initialAnswers(assignment: Assignment): Record<string, FormAnswer> {
    return Object.fromEntries(
        assignment.questions.map((question) => {
            if (question.type === 'anchored_matrix') {
                return [
                    question.id,
                    {
                        anchors: assignment.deliverables.map((deliverable) => ({
                            source_type: deliverable.source_type,
                            source_id: deliverable.source_id,
                            received: null,
                            accessible: null,
                            met_objective: null,
                        })),
                    },
                ];
            }

            return [
                question.id,
                {
                    value: null,
                },
            ];
        }),
    );
}

function ScaleButton({
    active,
    disabled,
    label,
    onClick,
}: {
    active: boolean;
    disabled: boolean;
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'min-h-11 rounded-md border px-3 py-2 text-sm font-medium transition-colors disabled:opacity-50',
                active
                    ? 'border-[var(--fs-admiralty)] bg-[var(--fs-linen)] text-[var(--fs-admiralty)]'
                    : 'hover:bg-muted',
            )}
        >
            {label}
        </button>
    );
}

function YesNo({
    value,
    disabled,
    onChange,
}: {
    value: boolean | null;
    disabled: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="grid grid-cols-2 gap-2">
            <ScaleButton
                active={value === true}
                disabled={disabled}
                label="Yes"
                onClick={() => onChange(true)}
            />
            <ScaleButton
                active={value === false}
                disabled={disabled}
                label="No"
                onClick={() => onChange(false)}
            />
        </div>
    );
}

function anchorValue(
    anchors: AnchorAnswer[] | undefined,
    sourceId: string,
    key: keyof Pick<AnchorAnswer, 'received' | 'accessible' | 'met_objective'>,
) {
    return anchors?.find((anchor) => anchor.source_id === sourceId)?.[key];
}
