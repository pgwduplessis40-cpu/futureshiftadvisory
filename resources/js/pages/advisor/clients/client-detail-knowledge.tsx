import { useForm } from '@inertiajs/react';
import {
    Brain,
    CheckCircle2,
    ChevronDown,
    MessageSquare,
    PencilLine,
    Star,
} from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
    formatDate,
    formatLabel,
    severityVariant,
} from './client-detail-presenters';
import type {
    AnalysisFindingFeedback,
    ClientDetail,
    FeedbackPayload,
    KnowledgeAssessmentForm,
} from './client-detail-types';
export function ConsentSelect({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="undecided">Undecided</option>
                <option value="opt_in">Opt in</option>
                <option value="opt_out">Opt out</option>
            </select>
            <InputError message={error} />
        </div>
    );
}

export function KnowledgeAssessmentPanel({ client }: { client: ClientDetail }) {
    const latest = client.latest_knowledge_assessment;
    const form = useForm<KnowledgeAssessmentForm>({
        financial_literacy: latest?.financial_literacy ?? 3,
        strategic_awareness: latest?.strategic_awareness ?? 3,
        leadership: latest?.leadership ?? 3,
    });

    const submit = () => {
        form.post(client.knowledge_assessment_store_url, {
            preserveScroll: true,
        });
    };

    return (
        <section
            id="section-knowledge"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Brain className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Knowledge assessment
                    </h2>
                </div>
                {latest && (
                    <Badge variant="outline">
                        {formatDate(latest.assessed_at)}
                    </Badge>
                )}
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <ScoreInput
                    id="financial_literacy"
                    label="Financial literacy"
                    value={form.data.financial_literacy}
                    error={form.errors.financial_literacy}
                    onChange={(value) =>
                        form.setData('financial_literacy', value)
                    }
                />
                <ScoreInput
                    id="strategic_awareness"
                    label="Strategic awareness"
                    value={form.data.strategic_awareness}
                    error={form.errors.strategic_awareness}
                    onChange={(value) =>
                        form.setData('strategic_awareness', value)
                    }
                />
                <ScoreInput
                    id="leadership"
                    label="Leadership"
                    value={form.data.leadership}
                    error={form.errors.leadership}
                    onChange={(value) => form.setData('leadership', value)}
                />
            </div>

            {latest && (
                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary">
                        {formatLabel(
                            String(
                                latest.calibration.language_depth ?? 'standard',
                            ),
                        )}
                    </Badge>
                    <Badge variant="outline">
                        {formatLabel(
                            String(
                                latest.calibration.financial_detail ??
                                    'balanced',
                            ),
                        )}
                    </Badge>
                    <Badge variant="outline">
                        {formatLabel(
                            String(
                                latest.calibration.leadership_context ??
                                    'standard',
                            ),
                        )}
                    </Badge>
                </div>
            )}

            <div className="flex justify-end">
                <Button
                    type="button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={submit}
                >
                    Save assessment
                </Button>
            </div>
        </section>
    );
}

export function ScoreInput({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: number;
    error?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <input
                id={id}
                type="number"
                min={1}
                max={5}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
            <InputError message={error} />
        </div>
    );
}

export function FindingFeedbackCard({
    finding,
}: {
    finding: AnalysisFindingFeedback;
}) {
    const feedbackForm = useForm<FeedbackPayload>({
        decision: 'confirm',
        rating: null,
        corrected_body: '',
        note: '',
    });

    const submitFeedback = (payload: FeedbackPayload) => {
        feedbackForm.transform(() => payload);
        feedbackForm.post(finding.feedback_store_url, {
            preserveScroll: true,
            onSuccess: () => {
                feedbackForm.reset();
                feedbackForm.setData({
                    decision: 'confirm',
                    rating: null,
                    corrected_body: '',
                    note: '',
                });
            },
            onFinish: () => feedbackForm.transform((data) => data),
        });
    };

    return (
        <article
            id={finding.id}
            className="rounded-md border bg-background px-3 py-2"
        >
            <Collapsible>
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        className="group grid w-full gap-2 rounded-sm text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                    >
                        <span className="min-w-0">
                            <span className="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary">
                                    {formatLabel(finding.module ?? 'analysis')}
                                </Badge>
                                <Badge variant="outline">
                                    {formatLabel(finding.lens)}
                                </Badge>
                                <Badge
                                    variant={severityVariant(finding.severity)}
                                >
                                    {formatLabel(finding.severity)}
                                </Badge>
                                {finding.feedback_count > 0 && (
                                    <Badge variant="outline">Reviewed</Badge>
                                )}
                            </span>
                            <span className="mt-1 block text-sm font-medium">
                                {finding.title}
                            </span>
                        </span>
                        <span className="flex items-center justify-between gap-2 text-xs text-muted-foreground md:justify-end">
                            <span>{formatDate(finding.created_at)}</span>
                            <ChevronDown
                                className="size-4 transition-transform group-data-[state=open]:rotate-180"
                                aria-hidden="true"
                            />
                        </span>
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent className="pt-4">
                    <div className="space-y-4 border-t pt-4">
                        <p className="text-sm leading-6 text-muted-foreground">
                            {finding.body}
                        </p>

                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">
                                {formatLabel(finding.document_support)}
                            </Badge>
                            {finding.uncertainty && (
                                <Badge variant="outline">
                                    {formatLabel(finding.uncertainty)}{' '}
                                    uncertainty
                                </Badge>
                            )}
                            {finding.attributions
                                .slice(0, 3)
                                .map((attribution, index) => (
                                    <Badge key={index} variant="outline">
                                        {attribution.source_reference ??
                                            'source'}
                                    </Badge>
                                ))}
                        </div>

                        {finding.data_quality_disclaimer && (
                            <p className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
                                {finding.data_quality_disclaimer}
                            </p>
                        )}

                        {finding.latest_feedback.length > 0 && (
                            <div className="space-y-2 text-xs text-muted-foreground">
                                {finding.latest_feedback.map((feedback) => (
                                    <div
                                        key={feedback.id}
                                        className="flex flex-wrap items-center gap-2"
                                    >
                                        <Badge variant="outline">
                                            {formatLabel(feedback.decision)}
                                        </Badge>
                                        {feedback.rating && (
                                            <span>{feedback.rating}/5</span>
                                        )}
                                        {feedback.has_correction && (
                                            <span>corrected</span>
                                        )}
                                        {feedback.note && (
                                            <span>{feedback.note}</span>
                                        )}
                                        <span>
                                            {feedback.advisor_name ?? 'Advisor'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="grid gap-4 lg:grid-cols-2">
                            <div className="space-y-3">
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={feedbackForm.processing}
                                        onClick={() =>
                                            submitFeedback({
                                                decision: 'confirm',
                                                rating: null,
                                                corrected_body: null,
                                                note: null,
                                            })
                                        }
                                    >
                                        <CheckCircle2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Confirm
                                    </Button>
                                    {[1, 2, 3, 4, 5].map((rating) => (
                                        <Button
                                            key={rating}
                                            type="button"
                                            size="icon"
                                            variant={
                                                feedbackForm.data.rating ===
                                                rating
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                            disabled={feedbackForm.processing}
                                            onClick={() =>
                                                submitFeedback({
                                                    decision: 'rate',
                                                    rating,
                                                    corrected_body: null,
                                                    note: null,
                                                })
                                            }
                                            aria-label={`Rate ${rating}`}
                                        >
                                            <Star
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    ))}
                                </div>
                                <InputError
                                    message={feedbackForm.errors.rating}
                                />
                            </div>

                            <div className="grid gap-3">
                                <Label htmlFor={`correction_${finding.id}`}>
                                    Correction
                                </Label>
                                <textarea
                                    id={`correction_${finding.id}`}
                                    value={
                                        feedbackForm.data.corrected_body ?? ''
                                    }
                                    onChange={(event) =>
                                        feedbackForm.setData(
                                            'corrected_body',
                                            event.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={feedbackForm.processing}
                                        onClick={() =>
                                            submitFeedback({
                                                decision: 'correct',
                                                rating: null,
                                                corrected_body:
                                                    feedbackForm.data
                                                        .corrected_body,
                                                note: null,
                                            })
                                        }
                                    >
                                        <PencilLine
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Save correction
                                    </Button>
                                </div>
                                <InputError
                                    message={feedbackForm.errors.corrected_body}
                                />
                            </div>

                            <div className="grid gap-3 lg:col-span-2">
                                <Label htmlFor={`context_${finding.id}`}>
                                    Context
                                </Label>
                                <textarea
                                    id={`context_${finding.id}`}
                                    value={feedbackForm.data.note ?? ''}
                                    onChange={(event) =>
                                        feedbackForm.setData(
                                            'note',
                                            event.target.value,
                                        )
                                    }
                                    rows={2}
                                    className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={feedbackForm.processing}
                                        onClick={() =>
                                            submitFeedback({
                                                decision: 'add_context',
                                                rating: null,
                                                corrected_body: null,
                                                note: feedbackForm.data.note,
                                            })
                                        }
                                    >
                                        <MessageSquare
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Add context
                                    </Button>
                                </div>
                                <InputError
                                    message={feedbackForm.errors.note}
                                />
                            </div>
                        </div>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </article>
    );
}

export function RollupPanel({
    title,
    description,
    meta,
    defaultOpen = false,
    className,
    children,
}: {
    title: string;
    description?: string;
    meta?: ReactNode;
    defaultOpen?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Collapsible
            defaultOpen={defaultOpen}
            className={cn('rounded-md border p-3', className)}
        >
            <CollapsibleTrigger asChild>
                <button
                    type="button"
                    className="group flex w-full items-start justify-between gap-3 rounded-sm text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <span className="min-w-0">
                        <span className="block text-sm font-medium">
                            {title}
                        </span>
                        {description ? (
                            <span className="mt-1 block text-sm text-muted-foreground">
                                {description}
                            </span>
                        ) : null}
                    </span>
                    <span className="flex shrink-0 items-center gap-2">
                        {meta}
                        <ChevronDown
                            className="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                            aria-hidden="true"
                        />
                    </span>
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent className="pt-3">
                <div className="space-y-3">{children}</div>
            </CollapsibleContent>
        </Collapsible>
    );
}
