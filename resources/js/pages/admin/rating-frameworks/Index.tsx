import { Head, router } from '@inertiajs/react';
import { FilePenLine, Rocket, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    ExplainedSectionHeader,
    Explainer,
} from '@/components/explainer';
import type { Explanation } from '@/components/explainer';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Criterion = {
    id: string;
    number: number;
    name: string;
    weight: number;
    descriptors: Record<string, string>;
    is_placeholder: boolean;
};

type Framework = {
    id: string;
    version: number;
    status: string;
    production_ready: boolean;
    published_at: string | null;
    publish_url: string | null;
    criteria: Criterion[];
};

type CriterionForm = {
    number: number;
    name: string;
    weight: string;
    descriptors: Record<string, string>;
};

type Props = {
    frameworks: Framework[];
    draft_url: string;
};

const bands = [
    ['exceptional', 'Exceptional'],
    ['strong', 'Strong'],
    ['developing', 'Developing'],
    ['needs_work', 'Needs work'],
] as const;

export default function RatingFrameworksIndex({
    frameworks,
    draft_url,
}: Props) {
    const current =
        frameworks.find((framework) => framework.status === 'published') ??
        frameworks[0];
    const [criteria, setCriteria] = useState<CriterionForm[]>(
        () => current?.criteria.map(formFromCriterion) ?? [],
    );
    const totalWeight = useMemo(
        () =>
            criteria.reduce(
                (total, criterion) =>
                    total + (Number.parseFloat(criterion.weight) || 0),
                0,
            ),
        [criteria],
    );
    const canSave = Math.abs(totalWeight - 100) < 0.01;

    const updateCriterion = (index: number, patch: Partial<CriterionForm>) => {
        setCriteria((currentRows) =>
            currentRows.map((criterion, rowIndex) =>
                rowIndex === index ? { ...criterion, ...patch } : criterion,
            ),
        );
    };

    const updateDescriptor = (index: number, band: string, value: string) => {
        setCriteria((currentRows) =>
            currentRows.map((criterion, rowIndex) =>
                rowIndex === index
                    ? {
                          ...criterion,
                          descriptors: {
                              ...criterion.descriptors,
                              [band]: value,
                          },
                      }
                    : criterion,
            ),
        );
    };

    return (
        <>
            <Head title="Rating framework" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Admin maintained"
                    icon={ShieldCheck}
                    title="Entrepreneur rating framework"
                    actions={
                        <>
                            <Badge variant={canSave ? 'secondary' : 'outline'}>
                                {totalWeight.toFixed(1)}%
                            </Badge>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    router.post(draft_url, {
                                        criteria: criteria.map((criterion) => ({
                                            ...criterion,
                                            weight:
                                                Number.parseFloat(
                                                    criterion.weight,
                                                ) || 0,
                                        })),
                                    })
                                }
                                disabled={!canSave}
                            >
                                <FilePenLine
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Save draft
                            </Button>
                        </>
                    }
                />

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <ExplainedSectionHeader
                        title="Current draft values"
                        description="Published frameworks remain fixed; changes are saved as a new draft version."
                        explanation={frameworkExplanations.currentDraft}
                        actions={
                            <Badge variant="outline">
                                Base v{current?.version ?? '-'}
                            </Badge>
                        }
                    />

                    <div className="space-y-3">
                        {criteria.map((criterion, index) => (
                            <article
                                key={criterion.number}
                                className="space-y-3 rounded-md border p-3"
                            >
                                <div className="grid gap-3 md:grid-cols-[72px_minmax(0,1fr)_120px]">
                                    <div className="grid gap-1 text-xs">
                                        <FieldLabel
                                            explanation={
                                                frameworkExplanations.number
                                            }
                                        >
                                            #
                                        </FieldLabel>
                                        <input
                                            type="number"
                                            aria-label="Criterion number"
                                            min={1}
                                            value={criterion.number}
                                            onChange={(event) =>
                                                updateCriterion(index, {
                                                    number:
                                                        Number.parseInt(
                                                            event.target.value,
                                                            10,
                                                        ) || 0,
                                                })
                                            }
                                            className="h-9 rounded-md border bg-background px-2 text-sm"
                                        />
                                    </div>
                                    <div className="grid gap-1 text-xs">
                                        <FieldLabel
                                            explanation={
                                                frameworkExplanations.criterion
                                            }
                                        >
                                            Criterion
                                        </FieldLabel>
                                        <input
                                            aria-label="Criterion name"
                                            value={criterion.name}
                                            onChange={(event) =>
                                                updateCriterion(index, {
                                                    name: event.target.value,
                                                })
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        />
                                    </div>
                                    <div className="grid gap-1 text-xs">
                                        <FieldLabel
                                            explanation={
                                                frameworkExplanations.weight
                                            }
                                        >
                                            Weight %
                                        </FieldLabel>
                                        <input
                                            type="number"
                                            aria-label="Criterion weight percentage"
                                            min={0}
                                            max={100}
                                            step="0.1"
                                            value={criterion.weight}
                                            onChange={(event) =>
                                                updateCriterion(index, {
                                                    weight: event.target.value,
                                                })
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2 lg:grid-cols-2">
                                    {bands.map(([band, label]) => (
                                        <div
                                            key={band}
                                            className="grid gap-1 text-xs"
                                        >
                                            <FieldLabel
                                                explanation={descriptorExplanation(
                                                    label,
                                                )}
                                            >
                                                {label}
                                            </FieldLabel>
                                            <textarea
                                                aria-label={`${label} descriptor`}
                                                value={
                                                    criterion.descriptors[
                                                        band
                                                    ] ?? ''
                                                }
                                                onChange={(event) =>
                                                    updateDescriptor(
                                                        index,
                                                        band,
                                                        event.target.value,
                                                    )
                                                }
                                                rows={2}
                                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                            />
                                        </div>
                                    ))}
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <ExplainedSectionHeader
                        title="Versions"
                        explanation={frameworkExplanations.versions}
                    />
                    <div className="divide-y rounded-md border">
                        {frameworks.map((framework) => (
                            <div
                                key={framework.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm"
                            >
                                <div>
                                    <div className="font-medium">
                                        Version {framework.version}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {framework.criteria.length} criteria ·{' '}
                                        {framework.published_at
                                            ? formatDate(framework.published_at)
                                            : 'Draft'}
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            framework.status === 'published'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {formatLabel(framework.status)}
                                    </Badge>
                                    {framework.publish_url ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    framework.publish_url ?? '',
                                                )
                                            }
                                        >
                                            <Rocket
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Publish
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

function formFromCriterion(criterion: Criterion): CriterionForm {
    return {
        number: criterion.number,
        name: criterion.name,
        weight: String(criterion.weight),
        descriptors: {
            exceptional: criterion.descriptors.exceptional ?? '',
            strong: criterion.descriptors.strong ?? '',
            developing: criterion.descriptors.developing ?? '',
            needs_work: criterion.descriptors.needs_work ?? '',
        },
    };
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function FieldLabel({
    children,
    explanation,
}: {
    children: string;
    explanation: Explanation;
}) {
    return (
        <span className="flex items-center gap-2 text-muted-foreground">
            {children}
            <Explainer explanation={explanation} />
        </span>
    );
}

function descriptorExplanation(label: string): Explanation {
    return {
        title: `${label} descriptor`,
        what: `The plain-English scoring description for a ${label.toLowerCase()} rating band.`,
        action: 'Write wording that lets advisors score consistently against observable evidence.',
        why: 'Descriptor quality directly affects whether two advisors would reach the same rating for the same plan.',
    };
}

const frameworkExplanations = {
    currentDraft: {
        title: 'Current draft values',
        what: 'The editable criteria, weights, and descriptors for the next entrepreneur rating framework version.',
        action: 'Keep total weight at 100% before saving the draft.',
        why: 'This framework governs plan assessment scoring, so draft changes should be deliberate and auditable.',
    },
    number: {
        title: 'Criterion number',
        what: 'The display order for the criterion in the rating framework.',
        action: 'Use a stable order that matches how advisors naturally review a plan.',
        why: 'Consistent ordering makes assessments easier to compare across clients and framework versions.',
    },
    criterion: {
        title: 'Criterion',
        what: 'The assessment area being scored, such as market, model, evidence, or execution readiness.',
        action: 'Name the criterion clearly enough that an advisor can identify the evidence needed.',
        why: 'Ambiguous criteria create inconsistent scoring and weaker client feedback.',
    },
    weight: {
        title: 'Weight',
        what: 'The percentage influence this criterion has on the overall rating.',
        action: 'Adjust weights carefully and keep the total at exactly 100%.',
        why: 'Weights change the final score, so they need to reflect the relative importance of each assessment area.',
    },
    versions: {
        title: 'Framework versions',
        what: 'The draft and published versions of the rating framework.',
        action: 'Publish only when the descriptors and weights are ready for live assessments.',
        why: 'Versioning preserves the scoring method used for older assessments while allowing governed improvement.',
    },
} satisfies Record<string, Explanation>;
