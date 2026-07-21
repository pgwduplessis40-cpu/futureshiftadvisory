import { Head, Link, router } from '@inertiajs/react';
import {
    Bell,
    BriefcaseBusiness,
    ClipboardCheck,
    Eye,
    FileText,
    Flame,
    Hourglass,
    MessageSquare,
    Settings,
    Trophy,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { InspirationCard } from '@/components/inspiration/InspirationCard';
import type { InspirationPost } from '@/components/inspiration/InspirationCard';
import { ClientSupport } from '@/components/screen-share/ClientSupport';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type EntrepreneurDashboardTab = 'actions' | 'information';

type PendingSurveysPayload = {
    total_open: number;
    index_url: string;
    items: PendingSurvey[];
};

type PendingSurvey = {
    id: string;
    survey_title: string;
    status: string;
    due_at: string | null;
    url: string;
};

type PendingOutcomeFollowUpsPayload = {
    total_open: number;
    items: PendingOutcomeFollowUp[];
};

type PendingOutcomeFollowUp = {
    id: string;
    subject_type: string;
    subject_label: string;
    subject_name: string;
    cadence_month: number;
    due_at: string | null;
    url: string;
};

type GamificationPayload = {
    enabled: boolean;
    seen_url?: string;
    current_level?: {
        stage: string;
        stage_label: string;
        phase: number | null;
        label: string;
    };
    plan_completion?: {
        total: number;
        completed: number;
        percent: number;
    };
    points?: {
        total: number;
        milestone_count: number;
    };
    current_streak?: number;
    last_active_at?: string | null;
    new_badge_count?: number;
    badges?: {
        id: string;
        key: string;
        label: string;
        earned_at: string | null;
        earned_at_estimated: boolean;
        seen_at: string | null;
    }[];
    next_milestone?: {
        key: string;
        label: string;
        progress_percent: number;
    } | null;
    next_quest?: {
        key: string;
        label: string;
        points: number;
        description: string;
    } | null;
};

type WelcomeMessage = {
    has_message: boolean;
    html: string;
    version: number | null;
};

type ScreenShareConfig = {
    portal_context_token: string;
    connection_url: string;
    prompt_url: string;
    connection_heartbeat_url: string;
    response_url: string;
    browser_permission_url: string;
    ice_servers_url: string;
    active_url: string;
    signal_url: string;
    heartbeat_url: string;
    end_url: string;
    heartbeat_seconds: number;
    warning_at_minutes: number;
} | null;

type Props = {
    profile: EntrepreneurProfile;
    inspirationBoard: InspirationPost | null;
    messagesUrl: string;
    planWorkspaceUrl: string;
    buyingBusinessServiceUrl: string;
    documentUploadUrl: string;
    notificationsUrl: string;
    settingsUrl: string;
    surveys: PendingSurveysPayload;
    outcomeFollowUps: PendingOutcomeFollowUpsPayload;
    gamification: GamificationPayload;
    welcomeMessage: WelcomeMessage;
    screenShare: ScreenShareConfig;
};

export default function EntrepreneurDashboard({
    profile,
    inspirationBoard,
    messagesUrl,
    planWorkspaceUrl,
    buyingBusinessServiceUrl,
    documentUploadUrl,
    notificationsUrl,
    settingsUrl,
    surveys,
    outcomeFollowUps,
    gamification,
    welcomeMessage,
    screenShare,
}: Props) {
    const [documents, setDocuments] = useState<UploadedDocument[]>(
        profile?.latest_documents ?? [],
    );
    const [file, setFile] = useState<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploadKey, setUploadKey] = useState(0);
    const [activeTab, setActiveTab] =
        useState<EntrepreneurDashboardTab>('actions');
    const latestAssessment = profile?.latest_plan?.latest_assessment ?? null;
    const readiness = profile?.advisory_readiness_signal ?? null;
    const nextSurvey = surveys.items[0] ?? null;
    const nextOutcomeFollowUp = outcomeFollowUps.items[0] ?? null;
    const hasPlan = Boolean(profile?.latest_plan);
    const journeyPrompt = hasPlan
        ? {
              badge: 'Continue',
              title: 'Continue the business plan',
              body: 'Keep building the next incomplete plan section, then submit it for advisor assessment when every requirement is complete.',
              action: 'Open workspace',
          }
        : {
              badge: 'Step 1',
              title: 'Start with idea validation',
              body: 'Validate the customer problem, solution, demand, and revenue logic first. The plan sections open after advisor review.',
              action: 'Start idea validation',
          };
    const planActionTitle = hasPlan ? 'Business plan' : 'Idea validation';
    const planActionValue = hasPlan
        ? formatLabel(profile?.latest_plan?.status ?? '')
        : 'Start here';
    const planActionExplanation = hasPlan
        ? 'Business plan opens the guided workspace for plan sections, preview, and advisory request.'
        : 'Idea validation is the first milestone before the business plan sections open.';

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
            <ClientSupport config={screenShare} />

            <div className="space-y-6">
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

                {inspirationBoard ? (
                    <InspirationCard post={inspirationBoard} />
                ) : null}

                {welcomeMessage.has_message ? (
                    <WelcomeBanner welcomeMessage={welcomeMessage} />
                ) : null}

                {gamification.enabled ? (
                    <GamificationPanel gamification={gamification} />
                ) : null}

                <JourneyPrompt
                    prompt={journeyPrompt}
                    planWorkspaceUrl={planWorkspaceUrl}
                />

                <DashboardTabList
                    activeTab={activeTab}
                    onChange={setActiveTab}
                />

                {activeTab === 'actions' ? (
                    <>
                        <DashboardSection
                            title="Priority actions"
                            description="Start with idea validation, then move into plan evidence, assessment, and advisor messages."
                        >
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                <ActionPanel
                                    icon={MessageSquare}
                                    title="Messages"
                                    value={messageSummary(profile)}
                                    explanation="Messages opens the secure conversation with your advisor and highlights unread threads."
                                >
                                    <Button asChild size="sm">
                                        <Link href={messagesUrl}>
                                            Message advisor
                                        </Link>
                                    </Button>
                                </ActionPanel>

                                {surveys.total_open > 0 && nextSurvey ? (
                                    <ActionPanel
                                        icon={ClipboardCheck}
                                        title="Feedback survey"
                                        value={`${surveys.total_open} pending`}
                                        explanation={`Please complete ${nextSurvey.survey_title}. Your feedback helps us understand whether the support delivered was received, accessible, and useful.`}
                                    >
                                        <Button asChild size="sm">
                                            <Link href={nextSurvey.url}>
                                                {surveys.total_open > 1
                                                    ? 'Start first'
                                                    : 'Start survey'}
                                            </Link>
                                        </Button>
                                    </ActionPanel>
                                ) : null}

                                {outcomeFollowUps.total_open > 0 &&
                                nextOutcomeFollowUp ? (
                                    <ActionPanel
                                        icon={Hourglass}
                                        title="Outcome follow-up"
                                        value={`${outcomeFollowUps.total_open} pending`}
                                        explanation={`Please complete the ${nextOutcomeFollowUp.cadence_month} month outcome follow-up for ${nextOutcomeFollowUp.subject_name}. It helps measure whether the advice changed the commercial outcome.`}
                                    >
                                        <Button asChild size="sm">
                                            <Link
                                                href={nextOutcomeFollowUp.url}
                                            >
                                                {outcomeFollowUps.total_open > 1
                                                    ? 'Start first'
                                                    : 'Complete'}
                                            </Link>
                                        </Button>
                                    </ActionPanel>
                                ) : null}

                                <ActionPanel
                                    icon={FileText}
                                    title={planActionTitle}
                                    value={planActionValue}
                                    explanation={planActionExplanation}
                                >
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={planWorkspaceUrl}>
                                            <Eye
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {hasPlan
                                                ? 'Open workspace'
                                                : 'Start idea validation'}
                                        </Link>
                                    </Button>
                                </ActionPanel>

                                <ActionPanel
                                    icon={BriefcaseBusiness}
                                    title="Explore buying a business"
                                    value="Due diligence workspace"
                                    explanation="Explore buying a business opens the acquisition due-diligence service request, then follows advisor package selection and the standard fee/scope acceptance flow."
                                >
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={buyingBusinessServiceUrl}>
                                            <Eye
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Open service
                                        </Link>
                                    </Button>
                                </ActionPanel>

                                <ActionPanel
                                    icon={Upload}
                                    title="Plan evidence"
                                    value={`${documents.length} recent uploads`}
                                    explanation="Plan evidence lets you upload supporting documents for advisor review and future assessment updates."
                                >
                                    <div className="grid gap-2">
                                        <FileDropzone
                                            key={uploadKey}
                                            id="entrepreneur_document"
                                            files={file ? [file] : []}
                                            label="Upload document"
                                            onFilesChange={(files) =>
                                                setFile(files[0] ?? null)
                                            }
                                        />
                                        <InputError
                                            message={uploadError ?? undefined}
                                        />
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={!file || uploading}
                                            onClick={() =>
                                                void uploadDocument()
                                            }
                                        >
                                            <Upload
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            {uploading ? 'Uploading' : 'Upload'}
                                        </Button>
                                    </div>
                                </ActionPanel>

                                <ActionPanel
                                    icon={ClipboardCheck}
                                    title="Assessment"
                                    value={
                                        latestAssessment
                                            ? `${latestAssessment.weighted_score.toFixed(1)}/100`
                                            : readiness
                                              ? `${readiness.score.toFixed(1)}/100`
                                              : 'Not completed'
                                    }
                                    explanation="Assessment shows the latest scored plan review and links to the detail when available."
                                >
                                    {latestAssessment ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={latestAssessment.url}>
                                                <Eye
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                View assessment
                                            </Link>
                                        </Button>
                                    ) : readiness?.assessment_url ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={readiness.assessment_url}
                                            >
                                                <Eye
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                View score detail
                                            </Link>
                                        </Button>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Your score will appear once an
                                            assessment is available.
                                        </p>
                                    )}
                                </ActionPanel>

                                <ActionPanel
                                    icon={Bell}
                                    title="Notifications"
                                    value="Alerts and updates"
                                    explanation="Notifications contain advisor updates, document outcomes, and account prompts."
                                >
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={notificationsUrl}>
                                                Open
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
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
                        </DashboardSection>

                        <DashboardSection
                            title="Progress and readiness"
                            description="Review the plan state and the score that explains advisory readiness."
                        >
                            <section className="space-y-4 rounded-md border bg-background p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <Hourglass
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2 className="text-sm font-medium">
                                            Progress
                                        </h2>
                                    </div>
                                    {latestAssessment ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
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
                                            value={formatLabel(
                                                profile.latest_plan.status,
                                            )}
                                        />
                                        <Detail
                                            label="Grade"
                                            value={
                                                latestAssessment?.overall_grade
                                                    ? gradeLabel(
                                                          latestAssessment.overall_grade,
                                                      )
                                                    : profile.latest_plan
                                                            .latest_grade
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
                                        Your invite is active. The next step is
                                        idea validation, then advisor review,
                                        then the business plan sections.
                                    </p>
                                )}
                            </section>

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
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={
                                                        readiness.assessment_url
                                                    }
                                                >
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
                                                    ? gradeLabel(
                                                          readiness.grade,
                                                      )
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
                                            value={formatDate(
                                                readiness.surfaced_at,
                                            )}
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
                                                                {
                                                                    criterion.number
                                                                }
                                                                .{' '}
                                                                {criterion.name}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    criterion.source_label
                                                                }
                                                            </div>
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-2 md:justify-end">
                                                            <Badge variant="outline">
                                                                {criterion.score.toFixed(
                                                                    1,
                                                                )}
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
                        </DashboardSection>
                    </>
                ) : (
                    <>
                        <DashboardSection
                            title="Information"
                            description="Use these panels for profile context and recently uploaded plan evidence."
                        >
                            <div className="grid gap-6 lg:grid-cols-2">
                                <section className="space-y-4 rounded-md border bg-background p-4">
                                    <div className="flex items-center gap-2">
                                        <ClipboardCheck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2 className="text-sm font-medium">
                                            Profile
                                        </h2>
                                    </div>
                                    <dl className="grid gap-3 text-sm">
                                        <Detail
                                            label="Email"
                                            value={profile?.email}
                                        />
                                        <Detail
                                            label="Advisor"
                                            value={
                                                profile?.assigned_advisor?.name
                                            }
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
                                        <Badge variant="outline">
                                            {documents.length}
                                        </Badge>
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
                                                            {
                                                                document.original_filename
                                                            }
                                                        </div>
                                                        <div className="mt-1 text-xs text-muted-foreground">
                                                            {formatLabel(
                                                                document.category,
                                                            )}
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
                                                                    href={
                                                                        document.url
                                                                    }
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
                                            No plan evidence has been uploaded
                                            yet.
                                        </p>
                                    )}
                                </section>
                            </div>
                        </DashboardSection>
                    </>
                )}
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

function WelcomeBanner({ welcomeMessage }: { welcomeMessage: WelcomeMessage }) {
    const storageKey = `fs-entrepreneur-welcome-dismissed-v${welcomeMessage.version ?? 0}`;
    const [dismissed, setDismissed] = useState<boolean>(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch {
            return false;
        }
    });

    if (dismissed) {
        return null;
    }

    const dismiss = () => {
        setDismissed(true);

        try {
            window.localStorage.setItem(storageKey, '1');
        } catch {
            // Dismissal is best-effort if browser storage is unavailable.
        }
    };

    return (
        <section
            aria-label="Welcome message"
            className="rounded-md border border-[var(--fs-linen)] bg-[var(--fs-linen)]/50 p-5"
        >
            <div
                className="text-sm leading-relaxed text-foreground [&_a]:text-[var(--fs-admiralty)] [&_a]:underline [&_p]:mb-3 [&_p:last-child]:mb-0 [&_strong]:font-semibold"
                dangerouslySetInnerHTML={{ __html: welcomeMessage.html }}
            />
            <div className="mt-4 flex justify-end">
                <Button variant="ghost" size="sm" onClick={dismiss}>
                    Dismiss
                </Button>
            </div>
        </section>
    );
}

function JourneyPrompt({
    prompt,
    planWorkspaceUrl,
}: {
    prompt: {
        badge: string;
        title: string;
        body: string;
        action: string;
    };
    planWorkspaceUrl: string;
}) {
    return (
        <section className="rounded-md border bg-background p-4">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <Trophy className="size-4" aria-hidden="true" />
                        <Badge variant="outline">{prompt.badge}</Badge>
                        <h2 className="text-sm font-medium">{prompt.title}</h2>
                    </div>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {prompt.body}
                    </p>
                </div>
                <Button asChild size="sm">
                    <Link href={planWorkspaceUrl}>{prompt.action}</Link>
                </Button>
            </div>
        </section>
    );
}

function DashboardSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

function DashboardTabList({
    activeTab,
    onChange,
}: {
    activeTab: EntrepreneurDashboardTab;
    onChange: (tab: EntrepreneurDashboardTab) => void;
}) {
    return (
        <div
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Entrepreneur dashboard sections"
        >
            <DashboardTabButton
                active={activeTab === 'actions'}
                onClick={() => onChange('actions')}
            >
                Actions
            </DashboardTabButton>
            <DashboardTabButton
                active={activeTab === 'information'}
                onClick={() => onChange('information')}
            >
                Information
            </DashboardTabButton>
        </div>
    );
}

function DashboardTabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            className={cn(
                'flex-1 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                active && 'bg-background text-foreground shadow-xs',
            )}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function GamificationPanel({
    gamification,
}: {
    gamification: GamificationPayload;
}) {
    const badges = gamification.badges ?? [];
    const newBadgeCount = gamification.new_badge_count ?? 0;
    const markSeen = () => {
        if (!gamification.seen_url) {
            return;
        }

        router.post(gamification.seen_url, {}, { preserveScroll: true });
    };

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Trophy className="size-4" aria-hidden="true" />
                    <h2 className="text-base font-semibold">Journey</h2>
                </div>
                {newBadgeCount > 0 ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={markSeen}
                    >
                        {newBadgeCount === 1
                            ? 'Mark badge seen'
                            : 'Mark badges seen'}
                    </Button>
                ) : null}
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-md border bg-background p-4">
                    <div className="text-xs text-muted-foreground">Level</div>
                    <div className="mt-2 text-sm font-medium">
                        {journeyLevelLabel(gamification.current_level)}
                    </div>
                </div>
                <div className="rounded-md border bg-background p-4">
                    <div className="text-xs text-muted-foreground">
                        Plan completion
                    </div>
                    <div className="mt-2 text-sm font-medium">
                        {gamification.plan_completion?.percent ?? 0}%
                    </div>
                    <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-emerald-500"
                            style={{
                                width: `${Math.min(100, Math.max(0, gamification.plan_completion?.percent ?? 0))}%`,
                            }}
                        />
                    </div>
                </div>
                <div className="rounded-md border bg-background p-4">
                    <div className="text-xs text-muted-foreground">
                        Journey points
                    </div>
                    <div className="mt-2 text-sm font-medium">
                        {gamification.points?.total ?? 0} points
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {gamification.points?.milestone_count ?? 0} verified
                        {' '}
                        milestone
                        {(gamification.points?.milestone_count ?? 0) === 1
                            ? ''
                            : 's'}
                    </div>
                </div>
                <div className="rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Flame className="size-3.5" aria-hidden="true" />
                        Streak
                    </div>
                    <div className="mt-2 text-sm font-medium">
                        {gamification.current_streak ?? 0} days
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        Last active{' '}
                        {formatDate(gamification.last_active_at ?? null)}
                    </div>
                </div>
            </div>

            {gamification.next_quest ? (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-muted/30 p-3">
                    <div>
                        <div className="text-sm font-medium">
                            Next quest: {gamification.next_quest.label}
                        </div>
                        <div className="mt-1 text-sm text-muted-foreground">
                            {gamification.next_quest.description}
                        </div>
                    </div>
                    <Badge variant="outline">
                        {gamification.next_quest.points} points
                    </Badge>
                </div>
            ) : null}

            {badges.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {badges.map((badge) => (
                        <Badge
                            key={badge.id}
                            variant={badge.seen_at ? 'secondary' : 'default'}
                            title={
                                badge.earned_at_estimated
                                    ? `${formatDate(badge.earned_at)} estimated`
                                    : formatDate(badge.earned_at)
                            }
                        >
                            {badge.label}
                        </Badge>
                    ))}
                </div>
            ) : gamification.next_milestone ? (
                <div className="text-sm text-muted-foreground">
                    Next: {nextMilestoneLabel(gamification.next_milestone)}
                </div>
            ) : null}
        </section>
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
    const stageLabel = displayStageLabel(
        profile?.stage,
        profile?.stage_label ?? 'Onboarding',
    );
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

function displayStageLabel(
    stage: string | null | undefined,
    label: string | null | undefined,
): string {
    if (stage === 'onboarding' || label === 'Onboarding') {
        return 'Getting started';
    }

    return label ?? '-';
}

function journeyLevelLabel(
    level: GamificationPayload['current_level'] | undefined,
): string {
    if (!level) {
        return '-';
    }

    if (level.stage === 'onboarding') {
        return level.phase
            ? `Getting started phase ${level.phase}`
            : 'Getting started';
    }

    return level.label;
}

function nextMilestoneLabel(
    milestone: NonNullable<GamificationPayload['next_milestone']>,
): string {
    return milestone.key === 'idea_validated'
        ? 'Idea validation'
        : milestone.label;
}

function ActionPanel({
    icon: Icon,
    title,
    value,
    explanation,
    children,
}: {
    icon: typeof MessageSquare;
    title: string;
    value: ReactNode;
    explanation: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
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
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
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
