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
    value?: number | boolean | string | null;
    comment?: string;
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
    service: {
        service_label?: string;
        package_label?: string | null;
        closed_at?: string | null;
    } | null;
    questions: Question[];
};

type Props = {
    assignment: Assignment;
    storeUrl: string;
    indexUrl: string;
};

type ScaleOption = {
    value: number;
    label: string;
};

const defaultLikertOptions: ScaleOption[] = [
    { value: 1, label: 'Very poor' },
    { value: 2, label: 'Poor' },
    { value: 3, label: 'Acceptable' },
    { value: 4, label: 'Good' },
    { value: 5, label: 'Excellent' },
];

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

    const setFlat = (questionId: string, value: number | boolean | string) => {
        form.setData('answers', {
            ...form.data.answers,
            [questionId]: {
                ...form.data.answers[questionId],
                value,
            },
        });
    };

    const setComment = (questionId: string, comment: string) => {
        form.setData('answers', {
            ...form.data.answers,
            [questionId]: {
                ...form.data.answers[questionId],
                comment,
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

                {assignment.service?.service_label && (
                    <div className="border-l-4 border-[var(--fs-teal)] bg-background px-4 py-3 text-sm">
                        <div className="font-medium text-foreground">
                            {assignment.service.service_label}
                        </div>
                        {assignment.service.package_label && (
                            <div className="mt-1 text-muted-foreground">
                                {assignment.service.package_label}
                            </div>
                        )}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    {assignment.questions.map((question) => (
                        <section
                            key={question.id}
                            className="rounded-md border bg-background p-4"
                        >
                            <h2 className="text-sm font-medium">
                                {question.prompt}
                            </h2>
                            {question.type !== 'likert' &&
                                question.type !== 'nps' &&
                                question.help_text && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {question.help_text}
                                </p>
                            )}

                            {question.type === 'likert' && (
                                <RatingQuestion
                                    question={question}
                                    answer={form.data.answers[question.id]}
                                    disabled={!assignment.is_open}
                                    onSelect={(value) =>
                                        setFlat(question.id, value)
                                    }
                                    onComment={(comment) =>
                                        setComment(question.id, comment)
                                    }
                                />
                            )}

                            {question.type === 'nps' && (
                                <RatingQuestion
                                    question={question}
                                    answer={form.data.answers[question.id]}
                                    disabled={!assignment.is_open}
                                    onSelect={(value) =>
                                        setFlat(question.id, value)
                                    }
                                    onComment={(comment) =>
                                        setComment(question.id, comment)
                                    }
                                />
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

                            {question.type === 'text' && (
                                <textarea
                                    value={
                                        (form.data.answers[question.id]
                                            ?.value as string | undefined) ?? ''
                                    }
                                    disabled={!assignment.is_open}
                                    maxLength={4000}
                                    rows={5}
                                    onChange={(event) =>
                                        setFlat(question.id, event.target.value)
                                    }
                                    className="mt-4 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50"
                                />
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
                            {(question.type === 'likert' ||
                                question.type === 'nps') && (
                                <InputError
                                    message={
                                        form.errors[
                                            `answers.${question.id}.comment` as keyof typeof form.errors
                                        ] as string | undefined
                                    }
                                />
                            )}
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

function RatingQuestion({
    question,
    answer,
    disabled,
    onSelect,
    onComment,
}: {
    question: Question;
    answer: FormAnswer | undefined;
    disabled: boolean;
    onSelect: (value: number) => void;
    onComment: (comment: string) => void;
}) {
    const options = scaleOptions(question);
    const value = typeof answer?.value === 'number' ? answer.value : null;
    const selected = options.find((option) => option.value === value) ?? null;
    const isLikert = question.type === 'likert';
    const first = options.at(0);
    const last = options.at(-1);

    return (
        <div className="mt-4 space-y-3">
            <p className="text-sm text-muted-foreground">
                {ratingGuidance(question)}
            </p>
            <div
                className={cn(
                    'grid gap-2',
                    isLikert ? 'grid-cols-5' : 'grid-cols-6 sm:grid-cols-11',
                )}
                role="group"
                aria-label={question.prompt}
            >
                {options.map((option) => (
                    <ScaleButton
                        key={option.value}
                        active={value === option.value}
                        disabled={disabled}
                        label={String(option.value)}
                        onClick={() => onSelect(option.value)}
                    />
                ))}
            </div>
            {first && last && (
                <div className="flex justify-between gap-4 text-xs text-muted-foreground">
                    <span>
                        {first.value} - {first.label}
                    </span>
                    <span className="text-right">
                        {last.value} - {last.label}
                    </span>
                </div>
            )}
            {selected && (
                <div className="space-y-2 border-l-2 border-[var(--fs-teal)] pl-3">
                    <p className="text-sm text-muted-foreground">
                        You selected {selected.value} - {selected.label}.
                    </p>
                    <label
                        htmlFor={`rating-comment-${question.id}`}
                        className="text-sm font-medium"
                    >
                        What would help us understand this rating?{' '}
                        <span className="font-normal text-muted-foreground">
                            Optional
                        </span>
                    </label>
                    <textarea
                        id={`rating-comment-${question.id}`}
                        value={answer?.comment ?? ''}
                        disabled={disabled}
                        maxLength={2000}
                        rows={3}
                        placeholder="Add the detail that gives this score its context."
                        onChange={(event) => onComment(event.target.value)}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50"
                    />
                </div>
            )}
        </div>
    );
}

function scaleOptions(question: Question): ScaleOption[] {
    if (question.type === 'likert') {
        const options = Array.isArray(question.options)
            ? question.options
                  .map((option): ScaleOption | null => {
                      if (
                          !option ||
                          typeof option !== 'object' ||
                          !('value' in option) ||
                          !('label' in option) ||
                          typeof option.value !== 'number' ||
                          typeof option.label !== 'string'
                      ) {
                          return null;
                      }

                      return { value: option.value, label: option.label };
                  })
                  .filter((option): option is ScaleOption => option !== null)
            : [];

        return options.length > 0 ? options : defaultLikertOptions;
    }

    const settings = isRecord(question.options) ? question.options : {};
    const min = typeof settings.min === 'number' ? settings.min : 0;
    const max = typeof settings.max === 'number' ? settings.max : 10;
    const minLabel =
        typeof settings.min_label === 'string'
            ? settings.min_label
            : 'Not at all likely';
    const maxLabel =
        typeof settings.max_label === 'string'
            ? settings.max_label
            : 'Extremely likely';

    return Array.from({ length: max - min + 1 }, (_, index) => {
        const value = min + index;

        return {
            value,
            label: value === min ? minLabel : value === max ? maxLabel : String(value),
        };
    });
}

function ratingGuidance(question: Question): string {
    if (question.help_text) {
        return question.help_text;
    }

    return (
        ratingQuestionGuidance[question.key] ??
        (question.type === 'nps'
            ? 'Rate how likely you would be to recommend this experience, then add context for the score you choose.'
            : 'Use 1 for very poor and 5 for excellent. Add context for the score you choose.')
    );
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

const ratingQuestionGuidance: Record<string, string> = {
    overall_experience:
        'Consider the full experience of receiving the advice and supporting material. Use 1 for very poor and 5 for excellent.',
    recommendation:
        'Consider whether you would recommend Future Shift Advisory based on this experience. Zero means not at all likely; 10 means extremely likely.',
    objectives_met:
        'Rate how well the work addressed the objective agreed for this engagement. Use 1 for very poor and 5 for excellent.',
    service_fit:
        'Rate how well this completed service addressed the need you engaged us for. Use 1 for very poor and 5 for excellent.',
    practical_value:
        'Rate how useful the advice or output has been in helping you take the next practical step. Use 1 for very poor and 5 for excellent.',
    process_clarity:
        'Rate the clarity of the process, expectations, and next steps throughout the service. Use 1 for very poor and 5 for excellent.',
    timeliness:
        'Rate whether the timing and pace of the service worked for you. Use 1 for very poor and 5 for excellent.',
    recommend_service:
        'Consider whether you would recommend this specific service to another business. Zero means not at all likely; 10 means extremely likely.',
};

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
