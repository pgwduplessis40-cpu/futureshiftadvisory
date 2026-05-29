import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, FileText, Scale } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Criterion = {
    number: number;
    name: string;
    weight: number;
    score: number;
    contribution: number;
    source_label: string;
    rationale: string;
};

type Assessment = {
    id: string;
    round: number;
    status: string;
    overall_grade: string;
    weighted_score: number;
    threshold: number;
    finalised_at: string | null;
    created_at: string | null;
    document_support: {
        attached_document_count: number;
        summary: string;
    };
    mentor_notes: {
        overall_visible?: string;
        section_notes?: Record<string, string>;
    };
    criteria: Criterion[];
    explanation: string;
};

type Props = {
    profile: {
        id: string;
        name: string;
        email: string;
        assigned_advisor: {
            id: number;
            name: string;
            email: string;
        } | null;
    };
    assessment: Assessment;
    backUrl: string;
    backLabel?: string;
};

export default function EntrepreneurAssessment({
    profile,
    assessment,
    backUrl,
    backLabel = 'Dashboard',
}: Props) {
    return (
        <>
            <Head title={`Assessment round ${assessment.round}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-2">
                        <Button asChild size="sm" variant="ghost">
                            <Link href={backUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {backLabel}
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold">
                                Assessment round {assessment.round}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {profile.name}
                            </p>
                        </div>
                    </div>
                    <Badge variant="secondary">
                        {formatLabel(assessment.status)}
                    </Badge>
                </div>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2">
                        <Scale className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Score summary</h2>
                    </div>
                    <dl className="grid gap-3 text-sm md:grid-cols-2">
                        <Detail
                            label="Weighted score"
                            value={`${assessment.weighted_score.toFixed(1)}/100`}
                        />
                        <Detail
                            label="Grade"
                            value={formatLabel(assessment.overall_grade)}
                        />
                        <Detail
                            label="Threshold"
                            value={`${assessment.threshold.toFixed(0)}/100`}
                        />
                        <Detail
                            label="Completed"
                            value={formatDate(assessment.finalised_at)}
                        />
                    </dl>
                    <p className="max-w-4xl text-sm text-muted-foreground">
                        {assessment.explanation}
                    </p>
                </section>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2">
                        <FileText className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Evidence support
                        </h2>
                    </div>
                    <dl className="grid gap-3 text-sm md:grid-cols-2">
                        <Detail
                            label="Attached documents"
                            value={String(
                                assessment.document_support
                                    .attached_document_count,
                            )}
                        />
                        <Detail
                            label="Advisor"
                            value={profile.assigned_advisor?.name}
                        />
                    </dl>
                    <p className="max-w-4xl text-sm text-muted-foreground">
                        {assessment.document_support.summary}
                    </p>
                </section>

                {assessment.mentor_notes.overall_visible ? (
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">
                                Advisor notes
                            </h2>
                        </div>
                        <p className="max-w-4xl text-sm text-muted-foreground">
                            {assessment.mentor_notes.overall_visible}
                        </p>
                    </section>
                ) : null}

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">
                                Criterion scores
                            </h2>
                        </div>
                        <Badge variant="outline">
                            {assessment.criteria.length}
                        </Badge>
                    </div>

                    <div className="divide-y rounded-md border">
                        {assessment.criteria.map((criterion) => (
                            <article
                                key={criterion.number}
                                className="grid gap-3 p-3 text-sm lg:grid-cols-[1fr_auto]"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="font-medium">
                                        {criterion.number}. {criterion.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {criterion.source_label}
                                    </div>
                                    {criterion.rationale ? (
                                        <p className="max-w-3xl text-xs text-muted-foreground">
                                            {criterion.rationale}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                                    <Badge variant="outline">
                                        {criterion.score.toFixed(1)}/100
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {criterion.weight.toFixed(1)}% weight
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {criterion.contribution.toFixed(1)} pts
                                    </span>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[140px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

EntrepreneurAssessment.layout = {
    breadcrumbs: [
        {
            title: 'Assessment',
            href: '/portal/entrepreneur',
        },
    ],
};
