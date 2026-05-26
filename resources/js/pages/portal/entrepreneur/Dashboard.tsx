import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    ClipboardCheck,
    Eye,
    FileText,
    Hourglass,
    MessageSquare,
    Settings,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type UploadedDocument = {
    id: string;
    original_filename: string;
    category: string;
    scanner_result: string;
    uploaded_at: string | null;
    url: string;
};

type AssessmentLink = {
    id: string;
    round: number;
    status: string;
    overall_grade: string;
    weighted_score: number;
    url: string;
};

type ReadinessCriterion = {
    number: number;
    name: string;
    weight: number;
    score: number;
    contribution: number;
    source_label: string;
    rationale: string;
};

type EntrepreneurProfile = {
    id: string;
    name: string;
    email: string;
    stage: string;
    stage_label: string;
    concept_summary: string | null;
    assigned_advisor: {
        id: number;
        name: string;
        email: string;
    } | null;
    latest_plan: {
        id: string;
        status: string;
        assessment_count: number;
        completed_assessment_count: number;
        latest_grade: string | null;
        latest_assessment: AssessmentLink | null;
        living_plan_next_update_at: string | null;
        living_plan_divergence_flags: {
            diverged?: boolean;
            remaining_gap_count?: number;
            advisory_readiness_attention?: boolean;
        } | null;
    } | null;
    advisory_readiness_signal: {
        score: number;
        surfaced_at: string | null;
        threshold: number | null;
        grade: string | null;
        explanation: string;
        assessment_url: string | null;
        criteria: ReadinessCriterion[];
    } | null;
    latest_documents: UploadedDocument[];
    message_summary: {
        threads_count: number;
        unread_count: number;
    };
} | null;

type Props = {
    profile: EntrepreneurProfile;
    messagesUrl: string;
    documentUploadUrl: string;
    notificationsUrl: string;
    settingsUrl: string;
};

export default function EntrepreneurDashboard({
    profile,
    messagesUrl,
    documentUploadUrl,
    notificationsUrl,
    settingsUrl,
}: Props) {
    const [documents, setDocuments] = useState<UploadedDocument[]>(
        profile?.latest_documents ?? [],
    );
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploadKey, setUploadKey] = useState(0);
    const latestAssessment = profile?.latest_plan?.latest_assessment ?? null;
    const readiness = profile?.advisory_readiness_signal ?? null;

    const uploadDocument = async () => {
        if (!file) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('category', 'plan_attachment');
        formData.append(
            'claim_value',
            'Plan evidence uploaded from the entrepreneur dashboard.',
        );
        formData.append(
            'question_prompt',
            'Entrepreneur dashboard document upload',
        );

        const response = await fetch(documentUploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: formData,
        });

        setUploading(false);

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;
            setUploadError(payload?.message ?? 'Upload failed.');

            return;
        }

        const payload = (await response.json()) as {
            document?: UploadedDocument;
        };

        if (!payload.document) {
            setUploadError('Upload response was missing document details.');

            return;
        }

        setDocuments((current) =>
            [
                payload.document as UploadedDocument,
                ...current.filter(
                    (document) => document.id !== payload.document?.id,
                ),
            ].slice(0, 5),
        );
        setFile(null);
        setUploadKey((key) => key + 1);
    };

    return (
        <>
            <Head title="Entrepreneur dashboard" />

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Entrepreneur dashboard
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {profile?.name ?? 'Profile pending'}
                        </div>
                    </div>
                    <StatusBadge
                        profile={profile}
                        latestAssessment={latestAssessment}
                        readiness={readiness}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <ActionPanel
                        icon={MessageSquare}
                        title="Messages"
                        value={messageSummary(profile)}
                    >
                        <Button asChild size="sm">
                            <Link href={messagesUrl}>Message advisor</Link>
                        </Button>
                    </ActionPanel>

                    <ActionPanel
                        icon={Upload}
                        title="Plan evidence"
                        value={`${documents.length} recent uploads`}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="entrepreneur_document">
                                Upload document
                            </Label>
                            <Input
                                key={uploadKey}
                                id="entrepreneur_document"
                                type="file"
                                onChange={(event) =>
                                    setFileFromInput(event, setFile)
                                }
                            />
                            <InputError message={uploadError ?? undefined} />
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled={!file || uploading}
                                onClick={() => void uploadDocument()}
                            >
                                <Upload className="size-4" aria-hidden="true" />
                                {uploading ? 'Uploading' : 'Upload'}
                            </Button>
                        </div>
                    </ActionPanel>

                    <ActionPanel
                        icon={Bell}
                        title="Notifications"
                        value="Alerts and updates"
                    >
                        <div className="flex flex-wrap gap-2">
                            <Button asChild size="sm" variant="outline">
                                <Link href={notificationsUrl}>Open</Link>
                            </Button>
                            <Button asChild size="sm" variant="ghost">
                                <Link href={settingsUrl}>
                                    <Settings
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Settings
                                </Link>
                            </Button>
                        </div>
                    </ActionPanel>
                </div>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Hourglass className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Progress</h2>
                        </div>
                        {latestAssessment ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={latestAssessment.url}>
                                    <Eye
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    View assessment
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                    {profile?.latest_plan ? (
                        <dl className="grid gap-3 text-sm md:grid-cols-2">
                            <Detail
                                label="Plan"
                                value={formatLabel(profile.latest_plan.status)}
                            />
                            <Detail
                                label="Grade"
                                value={
                                    latestAssessment?.overall_grade
                                        ? gradeLabel(
                                              latestAssessment.overall_grade,
                                          )
                                        : profile.latest_plan.latest_grade
                                          ? gradeLabel(
                                                profile.latest_plan
                                                    .latest_grade,
                                            )
                                          : null
                                }
                            />
                            <Detail
                                label="Assessments"
                                value={`${profile.latest_plan.assessment_count} total, ${profile.latest_plan.completed_assessment_count} completed`}
                            />
                            <Detail
                                label="Assessment score"
                                value={
                                    latestAssessment
                                        ? `${latestAssessment.weighted_score.toFixed(1)}/100`
                                        : null
                                }
                            />
                            <Detail
                                label="Next update"
                                value={formatDate(
                                    profile.latest_plan
                                        .living_plan_next_update_at,
                                )}
                            />
                        </dl>
                    ) : (
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Your invite is active and the entrepreneur module is
                            ready for your next step.
                        </p>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <ClipboardCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">Profile</h2>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Email" value={profile?.email} />
                            <Detail
                                label="Advisor"
                                value={profile?.assigned_advisor?.name}
                            />
                            <Detail
                                label="Concept"
                                value={profile?.concept_summary}
                            />
                        </dl>
                    </section>

                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <FileText
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Recent documents
                                </h2>
                            </div>
                            <Badge variant="outline">{documents.length}</Badge>
                        </div>

                        {documents.length > 0 ? (
                            <div className="divide-y rounded-md border">
                                {documents.map((document) => (
                                    <article
                                        key={document.id}
                                        className="flex flex-wrap items-center justify-between gap-3 p-3"
                                    >
                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-medium">
                                                {document.original_filename}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {formatLabel(document.category)}
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline">
                                                {formatLabel(
                                                    document.scanner_result,
                                                )}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                {formatDate(
                                                    document.uploaded_at,
                                                )}
                                            </span>
                                            {document.scanner_result ===
                                            'clean' ? (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a
                                                        href={document.url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        View
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No plan evidence has been uploaded yet.
                            </p>
                        )}
                    </section>
                </div>

                {readiness ? (
                    <section className="space-y-4 rounded-md border bg-background p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <ClipboardCheck
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Advisory readiness
                                </h2>
                            </div>
                            {readiness.assessment_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link href={readiness.assessment_url}>
                                        <Eye
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        View score detail
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                        <dl className="grid gap-3 text-sm md:grid-cols-2">
                            <Detail
                                label="Score"
                                value={`${readiness.score.toFixed(1)}/100`}
                            />
                            <Detail
                                label="Grade"
                                value={
                                    readiness.grade
                                        ? gradeLabel(readiness.grade)
                                        : null
                                }
                            />
                            <Detail
                                label="Threshold"
                                value={
                                    readiness.threshold
                                        ? `${readiness.threshold.toFixed(0)}/100`
                                        : null
                                }
                            />
                            <Detail
                                label="Surfaced"
                                value={formatDate(readiness.surfaced_at)}
                            />
                        </dl>
                        <p className="max-w-4xl text-sm text-muted-foreground">
                            {readiness.explanation}
                        </p>
                        {readiness.criteria.length > 0 ? (
                            <div className="divide-y rounded-md border">
                                {readiness.criteria
                                    .slice(0, 4)
                                    .map((criterion) => (
                                        <div
                                            key={criterion.number}
                                            className="grid gap-2 p-3 text-sm md:grid-cols-[1fr_auto]"
                                        >
                                            <div className="min-w-0">
                                                <div className="font-medium">
                                                    {criterion.number}.{' '}
                                                    {criterion.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {criterion.source_label}
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2 md:justify-end">
                                                <Badge variant="outline">
                                                    {criterion.score.toFixed(1)}
                                                    /100
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {criterion.weight.toFixed(
                                                        1,
                                                    )}
                                                    % weight,{' '}
                                                    {criterion.contribution.toFixed(
                                                        1,
                                                    )}{' '}
                                                    pts
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        ) : null}
                    </section>
                ) : null}
            </div>
        </>
    );
}

function StatusBadge({
    profile,
    latestAssessment,
    readiness,
}: {
    profile: EntrepreneurProfile;
    latestAssessment: AssessmentLink | null;
    readiness: NonNullable<EntrepreneurProfile>['advisory_readiness_signal'];
}) {
    const status = statusDetails(profile, latestAssessment, readiness);

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        className={cn(
                            'inline-flex items-center gap-2 rounded-md border px-2.5 py-1 text-xs font-medium shadow-xs transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            status.className,
                        )}
                        aria-label={`${status.label}: ${status.summary}`}
                    >
                        <span
                            className={cn(
                                'size-2 rounded-full',
                                status.dotClassName,
                            )}
                            aria-hidden="true"
                        />
                        {status.label}
                    </button>
                </TooltipTrigger>
                <TooltipContent
                    align="end"
                    className="w-80 border bg-background p-3 text-left text-foreground shadow-lg"
                    side="bottom"
                >
                    <div className="space-y-3">
                        <div>
                            <div className="text-sm font-medium">
                                {status.summary}
                            </div>
                            <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                {status.description}
                            </p>
                        </div>

                        <div className="space-y-2">
                            <div className="text-xs font-medium">
                                Readiness scale
                            </div>
                            <div className="grid grid-cols-4 gap-1">
                                {readinessScale.map((step) => (
                                    <div key={step.label} className="space-y-1">
                                        <div
                                            className={cn(
                                                'h-1.5 rounded-full',
                                                step.barClassName,
                                            )}
                                        />
                                        <div className="text-[10px] leading-tight text-muted-foreground">
                                            {step.range}
                                        </div>
                                        <div className="text-[10px] leading-tight font-medium">
                                            {step.label}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

const readinessScale = [
    {
        range: '0-59',
        label: 'Needs work',
        barClassName: 'bg-red-500',
    },
    {
        range: '60-74',
        label: 'Developing',
        barClassName: 'bg-amber-500',
    },
    {
        range: '75-89',
        label: 'Ready',
        barClassName: 'bg-emerald-500',
    },
    {
        range: '90-100',
        label: 'Exceptional',
        barClassName: 'bg-sky-500',
    },
];

function statusDetails(
    profile: EntrepreneurProfile,
    latestAssessment: AssessmentLink | null,
    readiness: NonNullable<EntrepreneurProfile>['advisory_readiness_signal'],
) {
    const stageLabel = profile?.stage_label ?? 'Onboarding';
    const threshold = readiness?.threshold ?? 75;
    const score =
        latestAssessment?.weighted_score ??
        (typeof readiness?.score === 'number' ? readiness.score : null);

    if (score !== null) {
        if (score >= 90) {
            return {
                label: 'Exceptional',
                summary: `Current readiness score: ${score.toFixed(1)}/100`,
                description:
                    'Your latest assessment is above the advisory-ready threshold with exceptional evidence strength. Your advisor can use this to move into advisory conversion or next-stage planning.',
                className: 'border-sky-200 bg-sky-50 text-sky-800',
                dotClassName: 'bg-sky-500',
            };
        }

        if (score >= threshold) {
            return {
                label: 'Advisory ready',
                summary: `Current readiness score: ${score.toFixed(1)}/100`,
                description: `Your latest assessment is at or above the ${threshold.toFixed(0)}/100 advisory-ready threshold. This means the plan evidence is strong enough for advisor-led next steps.`,
                className: 'border-emerald-200 bg-emerald-50 text-emerald-800',
                dotClassName: 'bg-emerald-500',
            };
        }

        if (score >= 60) {
            return {
                label: 'Developing',
                summary: `Current readiness score: ${score.toFixed(1)}/100`,
                description: `Your latest assessment is below the ${threshold.toFixed(0)}/100 advisory-ready threshold. The plan is progressing, but more evidence or revision is still needed.`,
                className: 'border-amber-200 bg-amber-50 text-amber-800',
                dotClassName: 'bg-amber-500',
            };
        }

        return {
            label: 'Needs work',
            summary: `Current readiness score: ${score.toFixed(1)}/100`,
            description: `Your latest assessment is below the ${threshold.toFixed(0)}/100 advisory-ready threshold. Focus on improving the plan evidence before this is treated as advisory ready.`,
            className: 'border-red-200 bg-red-50 text-red-800',
            dotClassName: 'bg-red-500',
        };
    }

    if (profile?.stage === 'advisory_ready') {
        return {
            label: stageLabel,
            summary: 'Your profile is marked advisory ready',
            description:
                'This status means the entrepreneur profile has been advanced to the advisor-ready stage. A score will appear here once assessment data is available.',
            className: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            dotClassName: 'bg-emerald-500',
        };
    }

    if (
        ['submitted', 'assessment', 'revising'].includes(profile?.stage ?? '')
    ) {
        return {
            label: stageLabel,
            summary: 'Your plan is in review',
            description:
                'Your plan is being assessed or revised. The readiness colour will update when the latest score is available.',
            className: 'border-amber-200 bg-amber-50 text-amber-800',
            dotClassName: 'bg-amber-500',
        };
    }

    return {
        label: stageLabel,
        summary: 'Your profile is in progress',
        description:
            'This status reflects your current entrepreneur journey stage. The readiness colour will update when assessment evidence is scored.',
        className: 'border-slate-200 bg-slate-50 text-slate-700',
        dotClassName: 'bg-slate-400',
    };
}

function ActionPanel({
    icon: Icon,
    title,
    value,
    children,
}: {
    icon: typeof MessageSquare;
    title: string;
    value: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2 text-sm font-medium">
                        <Icon className="size-4" aria-hidden="true" />
                        {title}
                    </div>
                    <div className="mt-2 text-sm text-muted-foreground">
                        {value}
                    </div>
                </div>
            </div>
            {children}
        </section>
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
        <div className="grid grid-cols-[130px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function setFileFromInput(
    event: ChangeEvent<HTMLInputElement>,
    setFile: (file: File | null) => void,
) {
    setFile(event.target.files?.[0] ?? null);
}

function messageSummary(profile: EntrepreneurProfile): string {
    const threads = profile?.message_summary.threads_count ?? 0;
    const unread = profile?.message_summary.unread_count ?? 0;

    if (unread > 0) {
        return `${unread} unread across ${threads} threads`;
    }

    return threads === 1 ? '1 active thread' : `${threads} active threads`;
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

function gradeLabel(value: string): string {
    return formatLabel(value);
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

EntrepreneurDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneur dashboard',
            href: '/portal/entrepreneur',
        },
    ],
};
