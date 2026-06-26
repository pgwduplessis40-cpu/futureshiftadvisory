import { Head, router } from '@inertiajs/react';
import { FilePenLine, Rocket, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
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
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-medium">
                                Current draft values
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Published frameworks remain fixed; changes are
                                saved as a new draft version.
                            </p>
                        </div>
                        <Badge variant="outline">
                            Base v{current?.version ?? '-'}
                        </Badge>
                    </div>

                    <div className="space-y-3">
                        {criteria.map((criterion, index) => (
                            <article
                                key={criterion.number}
                                className="space-y-3 rounded-md border p-3"
                            >
                                <div className="grid gap-3 md:grid-cols-[72px_minmax(0,1fr)_120px]">
                                    <label className="grid gap-1 text-xs">
                                        <span className="text-muted-foreground">
                                            #
                                        </span>
                                        <input
                                            type="number"
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
                                    </label>
                                    <label className="grid gap-1 text-xs">
                                        <span className="text-muted-foreground">
                                            Criterion
                                        </span>
                                        <input
                                            value={criterion.name}
                                            onChange={(event) =>
                                                updateCriterion(index, {
                                                    name: event.target.value,
                                                })
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        />
                                    </label>
                                    <label className="grid gap-1 text-xs">
                                        <span className="text-muted-foreground">
                                            Weight %
                                        </span>
                                        <input
                                            type="number"
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
                                    </label>
                                </div>
                                <div className="grid gap-2 lg:grid-cols-2">
                                    {bands.map(([band, label]) => (
                                        <label
                                            key={band}
                                            className="grid gap-1 text-xs"
                                        >
                                            <span className="text-muted-foreground">
                                                {label}
                                            </span>
                                            <textarea
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
                                        </label>
                                    ))}
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="space-y-3 rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Versions</h2>
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
