import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CircleHelp,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    MessageSquare,
    Upload,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { WorkspaceSwitcher } from '@/components/portal/WorkspaceSwitcher';
import type { WorkspaceSwitcherPayload } from '@/components/portal/WorkspaceSwitcher';
import { QuestionnaireRenderer } from '@/components/questionnaires/QuestionnaireRenderer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePersistedWorkspaceDraft } from '@/hooks/use-persisted-workspace-draft';
import { formatNzDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    QuestionnaireAnswers,
    QuestionnaireSchema,
} from '@/types/questionnaire';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type_label: string;
};

type EngagementPayload = {
    id: string;
    status: string;
    target_name: string;
    target_details: Record<string, unknown>;
};

type ReadinessPayload = {
    questionnaire_submitted: boolean;
    questionnaire_submitted_at: string | null;
    data_room_item_count: number;
    valuation_ready: boolean;
    valuation_as_at: string | null;
    workstreams_completed: number;
    workstreams_total: number;
    advice_report_ready: boolean;
    advice_report_generated_at: string | null;
    missing: string[];
};

type CapabilityPayload = {
    mode: 'guided' | 'experienced';
    label: string;
    summary: string;
    next_step_style: string;
    dd_experience: string | null;
    business_ownership_experience: string | null;
    financial_confidence: string | null;
    preferred_guidance: string | null;
};

type DdQuestionnairePayload = {
    schema: QuestionnaireSchema;
    answers: QuestionnaireAnswers;
    submitUrl: string;
    submitted: boolean;
    submittedAt: string | null;
};

type WorkstreamOption = {
    value: string;
    label: string;
};

type UploadedDocument = {
    id: string;
    original_filename: string;
};

type Props = {
    client: ClientPayload;
    engagement: EngagementPayload;
    readiness: ReadinessPayload;
    capability: CapabilityPayload;
    workspaces: WorkspaceSwitcherPayload;
    questionnaire: DdQuestionnairePayload | null;
    questionnaireDraftUrl: string;
    businessPlanBudgetUrl: string;
    ddReportPdfUrl: string | null;
    ddReportTitle: string | null;
    documentUploadUrl: string;
    messagesUrl: string;
    workstreamOptions: WorkstreamOption[];
};

type Tab = 'actions' | 'information';
type WorkflowKey =
    | 'questions'
    | 'evidence'
    | 'financials'
    | 'review'
    | 'plan_budget';
type WorkflowStatus = 'complete' | 'current' | 'locked';

type WorkflowStep = {
    key: WorkflowKey;
    number: number;
    title: string;
    shortTitle: string;
    description: string;
    whatToDo: string;
    status: WorkflowStatus;
};

const WORKFLOW_TEMPLATE: Array<Omit<WorkflowStep, 'status'>> = [
    {
        key: 'questions',
        number: 1,
        title: 'Answer questions about the business',
        shortTitle: 'Questions',
        description:
            'Tell us what you know about the business, seller, price, and risks.',
        whatToDo:
            'Answer what you know. If something is uncertain, say that clearly so FSA can help with the next request.',
    },
    {
        key: 'evidence',
        number: 2,
        title: 'Upload evidence',
        shortTitle: 'Evidence',
        description:
            'Add documents from the seller, broker, accountant, lawyer, or your own notes.',
        whatToDo:
            'Choose the document area, select one or more files, then upload the selected files.',
    },
    {
        key: 'financials',
        number: 3,
        title: 'Check the price and money records',
        shortTitle: 'Price check',
        description:
            'Upload reports that show sales, costs, profit, cash, debt, or how the seller chose the price.',
        whatToDo:
            'If you have money records, upload them here. If you do not have them yet, ask FSA what to request from the seller.',
    },
    {
        key: 'review',
        number: 4,
        title: 'FSA checks the information',
        shortTitle: 'FSA checks',
        description:
            'FSA checks your answers, documents, price support, and any gaps.',
        whatToDo:
            'Keep an eye on messages. FSA will ask for missing items or confirm when this step is ready.',
    },
    {
        key: 'plan_budget',
        number: 5,
        title: 'Move to Business Plan & Budget',
        shortTitle: 'Plan & budget',
        description:
            'Use the DD material to create the acquisition business plan and budget.',
        whatToDo:
            'Open Business Plan & Budget and start the draft from the DD material there.',
    },
];

export default function DdBusinessPlan({
    client,
    engagement,
    readiness,
    capability,
    workspaces,
    questionnaire,
    questionnaireDraftUrl,
    businessPlanBudgetUrl,
    ddReportPdfUrl,
    ddReportTitle,
    documentUploadUrl,
    messagesUrl,
    workstreamOptions,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('actions');
    const [files, setFiles] = useState<File[]>([]);
    const [workstream, setWorkstream] = useState(
        workstreamOptions[0]?.value ?? 'financial',
    );
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploadedDocuments, setUploadedDocuments] = useState<
        UploadedDocument[]
    >([]);
    const [uploadKey, setUploadKey] = useState(0);
    const [questionnaireAnswers, setQuestionnaireAnswers] =
        useState<QuestionnaireAnswers>(questionnaire?.answers ?? {});
    const [questionnaireErrors, setQuestionnaireErrors] = useState<
        Record<string, string | undefined>
    >({});
    const [savingQuestionnaire, setSavingQuestionnaire] = useState(false);
    const [selectedStepKey, setSelectedStepKey] = useState<WorkflowKey | null>(
        null,
    );
    const questionnaireDraftState = usePersistedWorkspaceDraft({
        url: questionnaireDraftUrl,
        data: { answers: questionnaireAnswers },
        hydrate: (payload) => {
            if (payload.answers) {
                setQuestionnaireAnswers(payload.answers);
            }
        },
        enabled: questionnaire !== null && !questionnaire.submitted,
    });

    const uploadedCount =
        readiness.data_room_item_count + uploadedDocuments.length;
    const steps = useMemo(
        () => workflowSteps(readiness, uploadedCount),
        [readiness, uploadedCount],
    );
    const currentStep =
        steps.find((step) => step.status === 'current') ?? steps[0];
    const displayedStep =
        steps.find((step) => step.key === selectedStepKey) ?? currentStep;
    const completedSteps = steps.filter(
        (step) => step.status === 'complete',
    ).length;
    const progressPercent = Math.round((completedSteps / steps.length) * 100);

    const uploadEvidence = async (selectedFiles: File[] = files) => {
        if (selectedFiles.length === 0) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        const successfulUploads: UploadedDocument[] = [];

        for (const selectedFile of selectedFiles) {
            const uploaded = await uploadDocument(
                documentUploadUrl,
                selectedFile,
                workstream,
                'Due diligence evidence uploaded from the client DD workspace.',
                'DD evidence upload',
            ).catch((error: Error) => {
                setUploadError(`${selectedFile.name}: ${error.message}`);

                return null;
            });

            if (!uploaded) {
                setUploading(false);

                return;
            }

            successfulUploads.push(uploaded);
        }

        setUploadedDocuments((current) => [
            ...successfulUploads.reverse(),
            ...current,
        ]);
        setFiles([]);
        setUploadKey((key) => key + 1);
        setUploading(false);
    };

    const submitQuestionnaire = () => {
        if (!questionnaire) {
            return;
        }

        setSavingQuestionnaire(true);
        setQuestionnaireErrors({});

        router.post(
            questionnaire.submitUrl,
            { answers: questionnaireAnswers },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setQuestionnaireErrors(
                        errors as Record<string, string | undefined>,
                    ),
                onSuccess: () => setQuestionnaireErrors({}),
                onFinish: () => setSavingQuestionnaire(false),
            },
        );
    };

    const selectStep = (step: WorkflowStep) => {
        const canSelect =
            capability.mode === 'experienced' || step.status !== 'locked';

        if (canSelect) {
            setSelectedStepKey(step.key);
        }
    };

    return (
        <>
            <Head title="Prepare Due Diligence" />

            <main className="flex-1 space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="text-xl font-semibold">
                            Prepare Due Diligence
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{engagement.target_name}</span>
                            <span aria-hidden="true">/</span>
                            <span>
                                {client.trading_name || client.legal_name}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {ddReportPdfUrl && (
                            <Button asChild variant="outline">
                                <a
                                    href={ddReportPdfUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    title={ddReportTitle ?? 'View DD PDF'}
                                >
                                    <FileText
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    View DD PDF
                                </a>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={messagesUrl}>
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Messages
                            </Link>
                        </Button>
                    </div>
                </div>

                <WorkspaceSwitcher workspaces={workspaces} />

                <Section
                    title="DD progress"
                    description="Each item opens the work needed for that part of due diligence."
                >
                    <ProgressStepper
                        steps={steps}
                        progressPercent={progressPercent}
                        selectedStep={displayedStep}
                        capability={capability}
                        onSelect={selectStep}
                    />
                </Section>

                <CurrentStepPrompt
                    step={displayedStep}
                    capability={capability}
                    onOpen={() => setActiveTab('actions')}
                />

                <TabList activeTab={activeTab} onChange={setActiveTab} />

                {activeTab === 'actions' ? (
                    <Section
                        title="Current step"
                        description={
                            capability.mode === 'experienced'
                                ? 'Complete the selected step or choose another step above.'
                                : 'Complete this step first. The next step opens when it is done.'
                        }
                    >
                        <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
                            <NextStepPanel
                                step={displayedStep}
                                currentStep={currentStep}
                                capability={capability}
                                questionnaire={questionnaire}
                                questionnaireAnswers={questionnaireAnswers}
                                questionnaireErrors={questionnaireErrors}
                                savingQuestionnaire={savingQuestionnaire}
                                questionnaireDraftState={
                                    questionnaireDraftState
                                }
                                documentUploadUrl={documentUploadUrl}
                                clientId={client.id}
                                files={files}
                                workstream={workstream}
                                workstreamOptions={workstreamOptions}
                                uploadedCount={uploadedCount}
                                uploadedDocuments={uploadedDocuments}
                                uploadKey={uploadKey}
                                uploading={uploading}
                                uploadError={uploadError}
                                businessPlanBudgetUrl={businessPlanBudgetUrl}
                                messagesUrl={messagesUrl}
                                onQuestionnaireChange={setQuestionnaireAnswers}
                                onSubmitQuestionnaire={submitQuestionnaire}
                                onFilesChange={(selectedFiles) => {
                                    setFiles(selectedFiles);

                                    if (selectedFiles.length > 0) {
                                        void uploadEvidence(selectedFiles);
                                    }
                                }}
                                onWorkstreamChange={setWorkstream}
                                onUploadEvidence={() => void uploadEvidence()}
                            />
                            <HelpPanel messagesUrl={messagesUrl} />
                        </div>
                    </Section>
                ) : (
                    <Section
                        title="Information"
                        description="Target details, current status, and what FSA still needs."
                    >
                        <div className="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
                            <TargetPanel engagement={engagement} />
                            <ReadinessPanel readiness={readiness} />
                        </div>
                    </Section>
                )}
            </main>
        </>
    );
}

function CurrentStepPrompt({
    step,
    capability,
    onOpen,
}: {
    step: WorkflowStep;
    capability: CapabilityPayload;
    onOpen: () => void;
}) {
    return (
        <section className="space-y-2">
            <div className="text-sm text-muted-foreground">
                Next: {step.shortTitle}
            </div>
            <div className="flex flex-col gap-3 rounded-md border bg-background p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">Step {step.number}</Badge>
                        <h2 className="text-sm font-semibold">{step.title}</h2>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {step.description}
                    </p>
                </div>
                <Button type="button" onClick={onOpen}>
                    <ArrowRight className="size-4" aria-hidden="true" />
                    {capability.mode === 'experienced'
                        ? 'Open step'
                        : 'Continue'}
                </Button>
            </div>
        </section>
    );
}

function NextStepPanel({
    step,
    currentStep,
    capability,
    questionnaire,
    questionnaireAnswers,
    questionnaireErrors,
    savingQuestionnaire,
    questionnaireDraftState,
    documentUploadUrl,
    clientId,
    files,
    workstream,
    workstreamOptions,
    uploadedCount,
    uploadedDocuments,
    uploadKey,
    uploading,
    uploadError,
    businessPlanBudgetUrl,
    messagesUrl,
    onQuestionnaireChange,
    onSubmitQuestionnaire,
    onFilesChange,
    onWorkstreamChange,
    onUploadEvidence,
}: {
    step: WorkflowStep;
    currentStep: WorkflowStep;
    capability: CapabilityPayload;
    questionnaire: DdQuestionnairePayload | null;
    questionnaireAnswers: QuestionnaireAnswers;
    questionnaireErrors: Record<string, string | undefined>;
    savingQuestionnaire: boolean;
    questionnaireDraftState: ReturnType<typeof usePersistedWorkspaceDraft>;
    documentUploadUrl: string;
    clientId: string;
    files: File[];
    workstream: string;
    workstreamOptions: WorkstreamOption[];
    uploadedCount: number;
    uploadedDocuments: UploadedDocument[];
    uploadKey: number;
    uploading: boolean;
    uploadError: string | null;
    businessPlanBudgetUrl: string;
    messagesUrl: string;
    onQuestionnaireChange: (answers: QuestionnaireAnswers) => void;
    onSubmitQuestionnaire: () => void;
    onFilesChange: (files: File[]) => void;
    onWorkstreamChange: (workstream: string) => void;
    onUploadEvidence: () => void;
}) {
    const isCurrentStep = step.key === currentStep.key;

    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <Badge variant="outline">Step {step.number} of 5</Badge>
                    <h2 className="text-lg font-semibold">{step.title}</h2>
                    <p className="text-sm text-muted-foreground">
                        {step.description}
                    </p>
                </div>
                <Badge
                    variant={
                        step.status === 'complete' ? 'default' : 'secondary'
                    }
                >
                    {formatStatus(step.status)}
                </Badge>
            </div>

            {!isCurrentStep && capability.mode === 'experienced' ? (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    This is not the current blocker, but experienced buyers can
                    open it early to add or review information.
                </div>
            ) : null}

            <div className="rounded-md border bg-muted/20 p-3">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <CircleHelp className="size-4" aria-hidden="true" />
                    What to do here
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                    {step.whatToDo}
                </p>
            </div>

            <StepActionContent
                step={step}
                questionnaire={questionnaire}
                questionnaireAnswers={questionnaireAnswers}
                questionnaireErrors={questionnaireErrors}
                savingQuestionnaire={savingQuestionnaire}
                questionnaireDraftState={questionnaireDraftState}
                documentUploadUrl={documentUploadUrl}
                clientId={clientId}
                files={files}
                workstream={workstream}
                workstreamOptions={workstreamOptions}
                uploadedCount={uploadedCount}
                uploadedDocuments={uploadedDocuments}
                uploadKey={uploadKey}
                uploading={uploading}
                uploadError={uploadError}
                businessPlanBudgetUrl={businessPlanBudgetUrl}
                messagesUrl={messagesUrl}
                onQuestionnaireChange={onQuestionnaireChange}
                onSubmitQuestionnaire={onSubmitQuestionnaire}
                onFilesChange={onFilesChange}
                onWorkstreamChange={onWorkstreamChange}
                onUploadEvidence={onUploadEvidence}
            />
        </section>
    );
}

function StepActionContent({
    step,
    questionnaire,
    questionnaireAnswers,
    questionnaireErrors,
    savingQuestionnaire,
    questionnaireDraftState,
    documentUploadUrl,
    clientId,
    files,
    workstream,
    workstreamOptions,
    uploadedCount,
    uploadedDocuments,
    uploadKey,
    uploading,
    uploadError,
    businessPlanBudgetUrl,
    messagesUrl,
    onQuestionnaireChange,
    onSubmitQuestionnaire,
    onFilesChange,
    onWorkstreamChange,
    onUploadEvidence,
}: {
    step: WorkflowStep;
    questionnaire: DdQuestionnairePayload | null;
    questionnaireAnswers: QuestionnaireAnswers;
    questionnaireErrors: Record<string, string | undefined>;
    savingQuestionnaire: boolean;
    questionnaireDraftState: ReturnType<typeof usePersistedWorkspaceDraft>;
    documentUploadUrl: string;
    clientId: string;
    files: File[];
    workstream: string;
    workstreamOptions: WorkstreamOption[];
    uploadedCount: number;
    uploadedDocuments: UploadedDocument[];
    uploadKey: number;
    uploading: boolean;
    uploadError: string | null;
    businessPlanBudgetUrl: string;
    messagesUrl: string;
    onQuestionnaireChange: (answers: QuestionnaireAnswers) => void;
    onSubmitQuestionnaire: () => void;
    onFilesChange: (files: File[]) => void;
    onWorkstreamChange: (workstream: string) => void;
    onUploadEvidence: () => void;
}) {
    if (step.key === 'questions') {
        return (
            <QuestionnairePanel
                questionnaire={questionnaire}
                answers={questionnaireAnswers}
                errors={questionnaireErrors}
                saving={savingQuestionnaire}
                draftState={questionnaireDraftState}
                documentUploadUrl={documentUploadUrl}
                clientId={clientId}
                onChange={onQuestionnaireChange}
                onSubmit={onSubmitQuestionnaire}
            />
        );
    }

    if (step.key === 'evidence' || step.key === 'financials') {
        return (
            <EvidencePanel
                title={
                    step.key === 'financials'
                        ? 'Upload price and money records'
                        : 'Upload DD evidence'
                }
                description={
                    step.key === 'financials'
                        ? 'Useful files include sales reports, profit reports, cash records, stock reports, debt details, and notes showing how the seller chose the price.'
                        : 'Useful files include seller packs, contracts, leases, customer information, staff details, asset lists, and broker notes.'
                }
                files={files}
                workstream={workstream}
                workstreamOptions={workstreamOptions}
                uploadedCount={uploadedCount}
                uploadedDocuments={uploadedDocuments}
                uploadKey={uploadKey}
                uploading={uploading}
                uploadError={uploadError}
                onFilesChange={onFilesChange}
                onWorkstreamChange={onWorkstreamChange}
                onUpload={onUploadEvidence}
            />
        );
    }

    if (step.key === 'review') {
        return (
            <PlainActionPanel
                icon={MessageSquare}
                title="FSA is reviewing"
                description="There may be nothing for you to do until FSA asks for another document, answer, or decision."
            >
                <Button asChild>
                    <Link href={messagesUrl}>
                        <MessageSquare className="size-4" aria-hidden="true" />
                        Open messages
                    </Link>
                </Button>
            </PlainActionPanel>
        );
    }

    return (
        <PlainActionPanel
            icon={FileSpreadsheet}
            title="Create the plan and budget from DD"
            description="The DD workspace has done its job. The business-plan draft and budget should now start from the Business Plan & Budget workspace."
        >
            <Button asChild>
                <Link href={businessPlanBudgetUrl}>
                    <ArrowRight className="size-4" aria-hidden="true" />
                    Open Business Plan & Budget
                </Link>
            </Button>
        </PlainActionPanel>
    );
}

function QuestionnairePanel({
    questionnaire,
    answers,
    errors,
    saving,
    draftState,
    documentUploadUrl,
    clientId,
    onChange,
    onSubmit,
}: {
    questionnaire: DdQuestionnairePayload | null;
    answers: QuestionnaireAnswers;
    errors: Record<string, string | undefined>;
    saving: boolean;
    draftState: ReturnType<typeof usePersistedWorkspaceDraft>;
    documentUploadUrl: string;
    clientId: string;
    onChange: (answers: QuestionnaireAnswers) => void;
    onSubmit: () => void;
}) {
    if (!questionnaire) {
        return (
            <PlainActionPanel
                icon={ClipboardList}
                title="DD questions are not available"
                description="Ask FSA to publish the Due Diligence questionnaire before continuing."
            />
        );
    }

    return (
        <div className="space-y-4">
            <div className="rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
                These questions help FSA spot risk, ask for the right seller
                documents, check whether the price makes sense, and explain what
                should happen before you commit to the purchase.
            </div>
            {questionnaire.submitted ? (
                <div className="flex flex-wrap items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Submitted {formatDate(questionnaire.submittedAt)}. You can
                    update answers if something has changed.
                </div>
            ) : null}
            <QuestionnaireRenderer
                schema={questionnaire.schema}
                answers={answers}
                errors={errors}
                onChange={onChange}
                uploadUrl={documentUploadUrl}
                clientId={clientId}
                collapsibleSections
                showProgress
                helpTextLabel="Why this is needed"
                showCharacterCounts
            />
            <div className="flex flex-wrap items-center gap-3">
                <DraftStatus state={draftState} />
                <Button type="button" disabled={saving} onClick={onSubmit}>
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    {saving ? 'Saving answers' : 'Submit DD answers'}
                </Button>
            </div>
        </div>
    );
}

function DraftStatus({
    state,
}: {
    state: ReturnType<typeof usePersistedWorkspaceDraft>;
}) {
    if (state === 'idle') {
        return null;
    }

    return (
        <span className="text-xs text-muted-foreground" role="status">
            {state === 'saving'
                ? 'Saving answers…'
                : state === 'saved'
                  ? 'Answers saved'
                  : 'Answers could not be saved'}
        </span>
    );
}

function EvidencePanel({
    title,
    description,
    files,
    workstream,
    workstreamOptions,
    uploadedCount,
    uploadedDocuments,
    uploadKey,
    uploading,
    uploadError,
    onFilesChange,
    onWorkstreamChange,
    onUpload,
}: {
    title: string;
    description: string;
    files: File[];
    workstream: string;
    workstreamOptions: WorkstreamOption[];
    uploadedCount: number;
    uploadedDocuments: UploadedDocument[];
    uploadKey: number;
    uploading: boolean;
    uploadError: string | null;
    onFilesChange: (files: File[]) => void;
    onWorkstreamChange: (workstream: string) => void;
    onUpload: () => void;
}) {
    return (
        <div className="space-y-4 rounded-md border p-4">
            <div>
                <h3 className="text-sm font-medium">{title}</h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="grid gap-3 md:grid-cols-[240px_1fr]">
                <label className="grid gap-2 text-sm">
                    <span className="font-medium">Evidence area</span>
                    <Select
                        value={workstream}
                        onValueChange={onWorkstreamChange}
                    >
                        <SelectTrigger aria-label="DD evidence area">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {workstreamOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </label>
                <div className="grid gap-2">
                    <FileDropzone
                        key={uploadKey}
                        id="dd_evidence_files"
                        files={files}
                        label="Select evidence files"
                        description="Drag files here or browse"
                        multiple
                        disabled={uploading}
                        onFilesChange={onFilesChange}
                    />
                    <InputError message={uploadError ?? undefined} />
                </div>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="text-sm text-muted-foreground">
                    {uploadedCount} uploaded in this DD workspace
                </div>
                {uploading ? (
                    <span
                        className="text-xs text-muted-foreground"
                        role="status"
                    >
                        Uploading {files.length} file
                        {files.length === 1 ? '' : 's'}…
                    </span>
                ) : uploadError && files.length > 0 ? (
                    <Button type="button" onClick={onUpload}>
                        <Upload className="size-4" aria-hidden="true" />
                        Retry upload
                    </Button>
                ) : null}
            </div>
            {uploadedDocuments.length > 0 ? (
                <div className="rounded-md border bg-muted/20 p-3">
                    <div className="text-sm font-medium">
                        Uploaded this session
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {uploadedDocuments.map((document) => (
                            <Badge
                                key={document.id}
                                variant="secondary"
                                className="gap-2"
                            >
                                <CheckCircle2
                                    className="size-3"
                                    aria-hidden="true"
                                />
                                {document.original_filename}
                            </Badge>
                        ))}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function PlainActionPanel({
    icon: Icon,
    title,
    description,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    description: string;
    children?: ReactNode;
}) {
    return (
        <div className="space-y-4 rounded-md border p-4">
            <div className="flex items-start gap-3">
                <Icon className="mt-0.5 size-5" aria-hidden={true} />
                <div>
                    <h3 className="text-sm font-medium">{title}</h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            {children ? <div>{children}</div> : null}
        </div>
    );
}

function HelpPanel({ messagesUrl }: { messagesUrl: string }) {
    return (
        <aside className="space-y-4 self-start rounded-md border bg-background p-4">
            <div className="flex items-start gap-3">
                <CircleHelp className="mt-0.5 size-5" aria-hidden="true" />
                <div>
                    <h2 className="text-sm font-medium">Need help?</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        If a question is unclear, a file is missing, or you do
                        not know what to ask the seller for, message FSA before
                        you continue.
                    </p>
                </div>
            </div>
            <Button asChild variant="outline">
                <Link href={messagesUrl}>
                    <MessageSquare className="size-4" aria-hidden="true" />I
                    need help
                </Link>
            </Button>
        </aside>
    );
}

function ProgressStepper({
    steps,
    progressPercent,
    selectedStep,
    capability,
    onSelect,
}: {
    steps: WorkflowStep[];
    progressPercent: number;
    selectedStep: WorkflowStep;
    capability: CapabilityPayload;
    onSelect: (step: WorkflowStep) => void;
}) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="text-sm font-medium">
                    {progressPercent}% complete
                </div>
                <div
                    className="h-2 w-full overflow-hidden rounded-full bg-muted sm:w-80"
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={progressPercent}
                    aria-label="DD progress"
                >
                    <div
                        className="h-full rounded-full bg-foreground transition-[width]"
                        style={{ width: `${progressPercent}%` }}
                    />
                </div>
            </div>
            <div className="grid gap-3 md:grid-cols-5">
                {steps.map((step) => {
                    const canSelect =
                        capability.mode === 'experienced' ||
                        step.status !== 'locked';
                    const selected = step.key === selectedStep.key;

                    return (
                        <button
                            key={step.key}
                            type="button"
                            disabled={!canSelect}
                            className={cn(
                                'min-h-28 rounded-md border p-3 text-left transition-colors',
                                selected
                                    ? 'border-foreground bg-muted/30'
                                    : 'hover:border-foreground/50',
                                step.status === 'complete' &&
                                    'border-emerald-200 bg-emerald-50',
                                step.status === 'locked' &&
                                    !canSelect &&
                                    'cursor-not-allowed opacity-60',
                            )}
                            onClick={() => onSelect(step)}
                        >
                            <div className="flex items-center gap-2">
                                <StepStatusIcon status={step.status} />
                                <span className="text-sm font-medium">
                                    {step.shortTitle}
                                </span>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {step.description}
                            </p>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}

function StepStatusIcon({ status }: { status: WorkflowStatus }) {
    if (status === 'complete') {
        return <CheckCircle2 className="size-4 text-emerald-600" />;
    }

    if (status === 'current') {
        return (
            <span className="grid size-5 place-items-center rounded-full bg-foreground text-xs font-medium text-background">
                !
            </span>
        );
    }

    return (
        <span className="grid size-5 place-items-center rounded-full border text-xs text-muted-foreground">
            -
        </span>
    );
}

function TargetPanel({ engagement }: { engagement: EngagementPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-center gap-2">
                <FileText className="size-4" aria-hidden="true" />
                <h2 className="text-sm font-medium">Target</h2>
            </div>
            <dl className="grid gap-3 text-sm">
                <Detail label="Target" value={engagement.target_name} />
                <Detail label="Status" value={formatLabel(engagement.status)} />
                <Detail
                    label="Industry"
                    value={stringDetail(engagement.target_details.industry)}
                />
                <Detail
                    label="NZBN"
                    value={stringDetail(engagement.target_details.nzbn)}
                />
                <Detail
                    label="Vendor"
                    value={stringDetail(engagement.target_details.vendor_name)}
                />
            </dl>
        </section>
    );
}

function ReadinessPanel({ readiness }: { readiness: ReadinessPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-center gap-2">
                <ClipboardList className="size-4" aria-hidden="true" />
                <h2 className="text-sm font-medium">Where things stand</h2>
            </div>
            <div className="grid gap-3 text-sm md:grid-cols-2">
                <Detail
                    label="Questions"
                    value={
                        readiness.questionnaire_submitted
                            ? formatDate(readiness.questionnaire_submitted_at)
                            : 'Not submitted'
                    }
                />
                <Detail
                    label="Evidence"
                    value={`${readiness.data_room_item_count} item${readiness.data_room_item_count === 1 ? '' : 's'}`}
                />
                <Detail
                    label="FSA checks"
                    value={`${readiness.workstreams_completed}/${readiness.workstreams_total}`}
                />
                <Detail
                    label="Price check"
                    value={
                        readiness.valuation_ready
                            ? formatDate(readiness.valuation_as_at)
                            : 'Waiting for financials'
                    }
                />
                <Detail
                    label="FSA report"
                    value={
                        readiness.advice_report_ready
                            ? formatDate(readiness.advice_report_generated_at)
                            : 'Not ready yet'
                    }
                />
            </div>
            <ReadinessList readiness={readiness} />
        </section>
    );
}

function ReadinessList({ readiness }: { readiness: ReadinessPayload }) {
    if (readiness.missing.length === 0) {
        return (
            <p className="rounded-md border bg-emerald-50 p-3 text-sm text-emerald-900">
                DD inputs are ready for the next FSA action.
            </p>
        );
    }

    return (
        <div className="grid gap-2 text-sm text-muted-foreground">
            {readiness.missing.map((item) => (
                <div key={item} className="flex items-center gap-2">
                    <span className="size-1.5 rounded-full bg-amber-500" />
                    {plainMissingItem(item)}
                </div>
            ))}
        </div>
    );
}

function TabList({
    activeTab,
    onChange,
}: {
    activeTab: Tab;
    onChange: (tab: Tab) => void;
}) {
    return (
        <div
            className="inline-flex rounded-md border bg-background p-1"
            aria-label="Due diligence sections"
        >
            {[
                ['actions', 'Actions'],
                ['information', 'Information'],
            ].map(([value, label]) => (
                <button
                    key={value}
                    type="button"
                    className={cn(
                        'rounded-sm px-3 py-1.5 text-sm font-medium transition-colors',
                        activeTab === value
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                    onClick={() => onChange(value as Tab)}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            {children}
        </section>
    );
}

function workflowSteps(
    readiness: ReadinessPayload,
    uploadedCount: number,
): WorkflowStep[] {
    const currentIndex = currentWorkflowIndex(readiness, uploadedCount);

    return WORKFLOW_TEMPLATE.map((step, index) => ({
        ...step,
        status:
            index < currentIndex
                ? 'complete'
                : index === currentIndex
                  ? 'current'
                  : 'locked',
    }));
}

function currentWorkflowIndex(
    readiness: ReadinessPayload,
    uploadedCount: number,
): number {
    if (!readiness.questionnaire_submitted) {
        return 0;
    }

    if (uploadedCount === 0) {
        return 1;
    }

    if (!readiness.valuation_ready) {
        return 2;
    }

    if (
        readiness.workstreams_completed < readiness.workstreams_total ||
        !readiness.advice_report_ready
    ) {
        return 3;
    }

    return 4;
}

function formatStatus(status: WorkflowStatus): string {
    if (status === 'complete') {
        return 'Done';
    }

    if (status === 'current') {
        return 'Do this now';
    }

    return 'Later';
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid grid-cols-[130px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

async function uploadDocument(
    documentUploadUrl: string,
    file: File,
    workstream: string,
    claimValue: string,
    questionPrompt: string,
): Promise<UploadedDocument> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('category', 'dd_artifact');
    formData.append('workstream', workstream);
    formData.append('claim_value', claimValue);
    formData.append('question_prompt', questionPrompt);

    const response = await fetch(documentUploadUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as {
            message?: string;
        } | null;

        throw new Error(payload?.message ?? 'Upload failed.');
    }

    const payload = (await response.json()) as {
        document?: UploadedDocument;
    };

    if (!payload.document) {
        throw new Error('Upload response was missing document details.');
    }

    return payload.document;
}

function stringDetail(value: unknown): string | null {
    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return null;
}

function plainMissingItem(item: string): string {
    return item
        .replace('Submit the DD questionnaire', 'Answer the questions')
        .replace('Upload DD evidence', 'Upload evidence')
        .replace(
            'Provide valuation financials',
            'Upload price and money records',
        )
        .replace(
            'Complete DD workstream analysis',
            'FSA checks are still in progress',
        );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return formatNzDate(value, {
        dateStyle: 'medium',
    });
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
