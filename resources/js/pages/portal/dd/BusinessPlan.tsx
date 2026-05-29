import { Head, Link, router } from '@inertiajs/react';
import {
    Bot,
    CheckCircle2,
    ClipboardList,
    FileText,
    MessageSquare,
    PieChart,
    RefreshCw,
    Send,
    Upload,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

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

type BusinessAdvicePayload = {
    requested: boolean;
    available: boolean;
    blockers: string[];
    requestUrl: string;
    dashboardUrl: string;
    advisory_client: {
        id: string | null;
        legal_name: string | null;
        engagement_type: string | null;
    } | null;
};

type BusinessPlanPayload = {
    id: string;
    title: string;
    status: string;
    completed_at: string | null;
    updated_at: string | null;
    completion: {
        complete: boolean;
        missing_phases: string[];
        completed_phases: string[];
    };
    requirements_complete: boolean;
    missing_requirements: string[];
    phases: PlanPhasePayload[];
};

type PlanPhasePayload = {
    id: string;
    key: string;
    title: string;
    status: string;
    requirements: PlanRequirementPayload[];
    sections: PlanSectionPayload[];
};

type PlanTemplatePhasePayload = {
    key: string;
    title: string;
    requirements: PlanRequirementPayload[];
};

type PlanRequirementPayload = {
    key: string;
    phase_key: string;
    phase_title: string;
    title: string;
    description: string;
    complete: boolean;
    section_id: string | null;
    section_title: string | null;
};

type PlanSectionPayload = {
    id: string;
    title: string;
    body: string;
    source_type: string;
    completeness_status: string;
    attached_document_ids: string[];
    predictive_score: PredictiveScore | null;
    guidance: SectionGuidance | null;
    requirement_key: string | null;
    guidance_url: string;
};

type PredictiveScore = {
    score?: number;
    band?: string;
    gaps?: string[];
    reasons?: string[];
};

type SectionGuidance = {
    summary?: string;
    ai_summary?: string;
    predictive_score?: PredictiveScore;
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
    businessAdvice: BusinessAdvicePayload;
    plan: BusinessPlanPayload | null;
    planTemplate: PlanTemplatePhasePayload[];
    generateUrl: string;
    previewUrl: string;
    sectionStoreUrl: string;
    completeUrl: string;
    onboardingUrl: string;
    documentUploadUrl: string;
    messagesUrl: string;
    workstreamOptions: WorkstreamOption[];
};

type Tab = 'actions' | 'information';

export default function DdBusinessPlan({
    client,
    engagement,
    readiness,
    businessAdvice,
    plan,
    planTemplate,
    generateUrl,
    previewUrl,
    sectionStoreUrl,
    completeUrl,
    onboardingUrl,
    documentUploadUrl,
    messagesUrl,
    workstreamOptions,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('actions');
    const [file, setFile] = useState<File | null>(null);
    const [workstream, setWorkstream] = useState(
        workstreamOptions[0]?.value ?? 'financial',
    );
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [uploadedDocuments, setUploadedDocuments] = useState<
        UploadedDocument[]
    >([]);
    const [uploadKey, setUploadKey] = useState(0);
    const [selectedRequirementKey, setSelectedRequirementKey] = useState<
        string | null
    >(null);
    const [sectionTitle, setSectionTitle] = useState('');
    const [sectionBody, setSectionBody] = useState('');
    const [supportingFile, setSupportingFile] = useState<File | null>(null);
    const [supportingFileKey, setSupportingFileKey] = useState(0);
    const [sectionError, setSectionError] = useState<string | null>(null);
    const [savingSection, setSavingSection] = useState(false);

    const requirements = useMemo(
        () => plan?.phases.flatMap((phase) => phase.requirements) ?? [],
        [plan],
    );
    const firstMissingRequirement =
        requirements.find((requirement) => !requirement.complete) ??
        requirements[0] ??
        null;
    const selectedRequirement =
        requirements.find(
            (requirement) =>
                requirementKey(requirement) === selectedRequirementKey,
        ) ?? firstMissingRequirement;
    const planCompleted = plan?.status === 'founding';
    const canCompletePlan =
        Boolean(plan) && !planCompleted && Boolean(plan?.requirements_complete);

    const populateFromDd = () => {
        router.post(generateUrl, {}, { preserveScroll: true });
    };

    const completePlan = () => {
        router.post(completeUrl, {}, { preserveScroll: true });
    };

    const requestBusinessAdvice = () => {
        router.post(businessAdvice.requestUrl, {}, { preserveScroll: true });
    };

    const uploadEvidence = async () => {
        if (!file) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        const uploaded = await uploadDocument(
            documentUploadUrl,
            file,
            workstream,
            'Due diligence acquisition-plan evidence uploaded from the client portal.',
            'DD acquisition business plan evidence upload',
        ).catch((error: Error) => {
            setUploadError(error.message);

            return null;
        });

        setUploading(false);

        if (!uploaded) {
            return;
        }

        setUploadedDocuments((current) => [uploaded, ...current]);
        setFile(null);
        setUploadKey((key) => key + 1);
    };

    const saveRequirement = async () => {
        if (!selectedRequirement) {
            return;
        }

        if (sectionBody.trim().length < 80) {
            setSectionError(
                'Add at least 80 characters so the advisor has usable context.',
            );

            return;
        }

        setSavingSection(true);
        setSectionError(null);

        const attachedDocumentIds: string[] = [];

        if (supportingFile) {
            const uploaded = await uploadDocument(
                documentUploadUrl,
                supportingFile,
                workstream,
                `Supporting document for ${selectedRequirement.title}.`,
                `Acquisition plan supporting document: ${selectedRequirement.title}`,
            ).catch((error: Error) => {
                setSectionError(error.message);

                return null;
            });

            if (!uploaded) {
                setSavingSection(false);

                return;
            }

            attachedDocumentIds.push(uploaded.id);
            setUploadedDocuments((current) => [uploaded, ...current]);
        }

        router.post(
            sectionStoreUrl,
            {
                phase_key: selectedRequirement.phase_key,
                requirement_key: selectedRequirement.key,
                title: sectionTitle || selectedRequirement.title,
                body: sectionBody,
                attached_document_ids: attachedDocumentIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSectionTitle('');
                    setSectionBody('');
                    setSupportingFile(null);
                    setSupportingFileKey((key) => key + 1);
                },
                onFinish: () => setSavingSection(false),
            },
        );
    };

    return (
        <>
            <Head title="Acquisition plan" />

            <main className="flex-1 space-y-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Prepare business plan
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
                        <Button type="button" onClick={populateFromDd}>
                            <RefreshCw className="size-4" aria-hidden="true" />
                            {plan ? 'Repopulate from DD' : 'Populate from DD'}
                        </Button>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button asChild variant="outline">
                                    <a
                                        href={previewUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <FileText
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Preview business plan
                                    </a>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom" className="max-w-xs">
                                Generates a PDF from the available DD and plan
                                requirement content, with incomplete sections
                                shown as pending.
                            </TooltipContent>
                        </Tooltip>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!canCompletePlan}
                            onClick={completePlan}
                        >
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                            {planCompleted ? 'Plan completed' : 'Complete plan'}
                        </Button>
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

                <TabList activeTab={activeTab} onChange={setActiveTab} />

                {activeTab === 'actions' ? (
                    <>
                        <Section
                            title="Priority actions"
                            description="Start with DD inputs, complete the missing plan requirements, then request post-acquisition advisory when advice is ready."
                        >
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                <ActionTile
                                    icon={ClipboardList}
                                    title="DD questionnaire"
                                    value={
                                        readiness.questionnaire_submitted
                                            ? `Submitted ${formatDate(readiness.questionnaire_submitted_at)}`
                                            : 'Not submitted'
                                    }
                                    explanation="The questionnaire captures buyer context, target risk, integration assumptions, and inputs that influence the DD advice."
                                >
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={onboardingUrl}>
                                            {readiness.questionnaire_submitted
                                                ? 'Review'
                                                : 'Complete'}
                                        </Link>
                                    </Button>
                                </ActionTile>

                                <EvidenceTile
                                    file={file}
                                    workstream={workstream}
                                    workstreamOptions={workstreamOptions}
                                    uploadedCount={
                                        readiness.data_room_item_count +
                                        uploadedDocuments.length
                                    }
                                    uploadKey={uploadKey}
                                    uploading={uploading}
                                    uploadError={uploadError}
                                    onFileChange={setFile}
                                    onWorkstreamChange={setWorkstream}
                                    onUpload={() => void uploadEvidence()}
                                />

                                <ActionTile
                                    icon={CheckCircle2}
                                    title="Advice readiness"
                                    value={
                                        readiness.missing.length > 0
                                            ? `${readiness.missing.length} gaps`
                                            : readiness.advice_report_ready
                                              ? 'Advice ready'
                                              : 'Processing'
                                    }
                                    explanation="Advisor DD advice is ready after questionnaire, evidence, workstreams, and valuation inputs are all present."
                                >
                                    <ReadinessList readiness={readiness} />
                                </ActionTile>

                                <ActionTile
                                    icon={PieChart}
                                    title="Plan completion"
                                    value={
                                        plan
                                            ? plan.requirements_complete
                                                ? planCompleted
                                                    ? 'Completed'
                                                    : 'Ready to complete'
                                                : `${plan.missing_requirements.length} requirements`
                                            : 'Not populated'
                                    }
                                    explanation="The DD information pre-populates the plan; remaining requirements must be completed before the plan can be finalised."
                                >
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={!canCompletePlan}
                                        onClick={completePlan}
                                    >
                                        <CheckCircle2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {planCompleted
                                            ? 'Completed'
                                            : 'Complete plan'}
                                    </Button>
                                </ActionTile>

                                <BusinessAdviceTile
                                    businessAdvice={businessAdvice}
                                    onRequest={requestBusinessAdvice}
                                />
                            </div>
                        </Section>

                        <Section
                            title="Plan assistant"
                            description="Complete the missing plan parts with DD context, supporting evidence, and section-level guidance."
                        >
                            {plan ? (
                                <div className="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                                    <RequirementList
                                        requirements={requirements}
                                        selectedRequirement={
                                            selectedRequirement
                                        }
                                        onSelect={(requirement) =>
                                            setSelectedRequirementKey(
                                                requirementKey(requirement),
                                            )
                                        }
                                    />
                                    <RequirementEditor
                                        requirement={selectedRequirement}
                                        sectionTitle={sectionTitle}
                                        sectionBody={sectionBody}
                                        supportingFile={supportingFile}
                                        supportingFileKey={supportingFileKey}
                                        savingSection={savingSection}
                                        sectionError={sectionError}
                                        workstream={workstream}
                                        workstreamOptions={workstreamOptions}
                                        onTitleChange={setSectionTitle}
                                        onBodyChange={setSectionBody}
                                        onFileChange={setSupportingFile}
                                        onWorkstreamChange={setWorkstream}
                                        onSave={() => void saveRequirement()}
                                    />
                                </div>
                            ) : (
                                <EmptyPlanPanel
                                    readiness={readiness}
                                    onPopulate={populateFromDd}
                                />
                            )}
                        </Section>

                        {plan ? (
                            <PlanWorkspace plan={plan} />
                        ) : (
                            <PlanTemplatePreview phases={planTemplate} />
                        )}
                    </>
                ) : (
                    <Section
                        title="Information"
                        description="Review target details, DD status, and the generated acquisition plan structure."
                    >
                        <div className="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
                            <TargetPanel engagement={engagement} />
                            <ReadinessPanel readiness={readiness} />
                        </div>
                        {plan ? (
                            <PlanWorkspace plan={plan} compact />
                        ) : (
                            <PlanTemplatePreview
                                phases={planTemplate}
                                compact
                            />
                        )}
                    </Section>
                )}
            </main>
        </>
    );
}

function EvidenceTile({
    file,
    workstream,
    workstreamOptions,
    uploadedCount,
    uploadKey,
    uploading,
    uploadError,
    onFileChange,
    onWorkstreamChange,
    onUpload,
}: {
    file: File | null;
    workstream: string;
    workstreamOptions: WorkstreamOption[];
    uploadedCount: number;
    uploadKey: number;
    uploading: boolean;
    uploadError: string | null;
    onFileChange: (file: File | null) => void;
    onWorkstreamChange: (workstream: string) => void;
    onUpload: () => void;
}) {
    return (
        <ActionTile
            icon={Upload}
            title="DD evidence"
            value={`${uploadedCount} uploaded`}
            explanation="Uploaded evidence is routed into the DD data room and assigned to the selected workstream."
        >
            <div className="grid gap-2">
                <Select value={workstream} onValueChange={onWorkstreamChange}>
                    <SelectTrigger size="sm" aria-label="DD workstream">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {workstreamOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <FileDropzone
                    key={uploadKey}
                    id="dd_acquisition_plan_evidence"
                    files={file ? [file] : []}
                    label="Upload evidence"
                    onFilesChange={(files) => onFileChange(files[0] ?? null)}
                />
                <InputError message={uploadError ?? undefined} />
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={!file || uploading}
                    onClick={onUpload}
                >
                    <Upload className="size-4" aria-hidden="true" />
                    {uploading ? 'Uploading' : 'Upload'}
                </Button>
            </div>
        </ActionTile>
    );
}

function BusinessAdviceTile({
    businessAdvice,
    onRequest,
}: {
    businessAdvice: BusinessAdvicePayload;
    onRequest: () => void;
}) {
    return (
        <ActionTile
            icon={Send}
            title="Business advice"
            value={
                businessAdvice.requested
                    ? 'Requested'
                    : businessAdvice.available
                      ? 'Ready to request'
                      : `${businessAdvice.blockers.length} blockers`
            }
            explanation="Once DD advice and the acquisition business plan are ready, this requests post-acquisition advisory using the DD plan context."
        >
            {businessAdvice.requested ? (
                <Button asChild size="sm" variant="outline">
                    <Link href={businessAdvice.dashboardUrl}>Open portal</Link>
                </Button>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    disabled={!businessAdvice.available}
                    onClick={onRequest}
                >
                    <Send className="size-4" aria-hidden="true" />
                    Request advice
                </Button>
            )}
            {!businessAdvice.available && !businessAdvice.requested ? (
                <div className="grid gap-1 text-xs text-muted-foreground">
                    {businessAdvice.blockers.map((blocker) => (
                        <span key={blocker}>{blocker}</span>
                    ))}
                </div>
            ) : null}
        </ActionTile>
    );
}

function RequirementList({
    requirements,
    selectedRequirement,
    onSelect,
}: {
    requirements: PlanRequirementPayload[];
    selectedRequirement: PlanRequirementPayload | null;
    onSelect: (requirement: PlanRequirementPayload) => void;
}) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <ClipboardList className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Plan requirements</h2>
                </div>
                <Badge variant="outline">
                    {
                        requirements.filter(
                            (requirement) => requirement.complete,
                        ).length
                    }
                    /{requirements.length} complete
                </Badge>
            </div>
            <div className="grid gap-2">
                {requirements.map((requirement) => (
                    <button
                        key={requirementKey(requirement)}
                        type="button"
                        className={cn(
                            'rounded-md border p-3 text-left transition-colors',
                            selectedRequirement &&
                                requirementKey(requirement) ===
                                    requirementKey(selectedRequirement)
                                ? 'border-foreground'
                                : 'hover:border-foreground/50',
                        )}
                        onClick={() => onSelect(requirement)}
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="text-sm font-medium">
                                {requirement.title}
                            </div>
                            <Badge
                                variant={
                                    requirement.complete
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {requirement.complete ? 'Complete' : 'Needed'}
                            </Badge>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {requirement.phase_title}
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {requirement.description}
                        </p>
                    </button>
                ))}
            </div>
        </section>
    );
}

function RequirementEditor({
    requirement,
    sectionTitle,
    sectionBody,
    supportingFile,
    supportingFileKey,
    savingSection,
    sectionError,
    workstream,
    workstreamOptions,
    onTitleChange,
    onBodyChange,
    onFileChange,
    onWorkstreamChange,
    onSave,
}: {
    requirement: PlanRequirementPayload | null;
    sectionTitle: string;
    sectionBody: string;
    supportingFile: File | null;
    supportingFileKey: number;
    savingSection: boolean;
    sectionError: string | null;
    workstream: string;
    workstreamOptions: WorkstreamOption[];
    onTitleChange: (value: string) => void;
    onBodyChange: (value: string) => void;
    onFileChange: (file: File | null) => void;
    onWorkstreamChange: (value: string) => void;
    onSave: () => void;
}) {
    if (!requirement) {
        return (
            <section className="rounded-md border bg-background p-4 text-sm text-muted-foreground">
                Populate the plan from DD before completing missing sections.
            </section>
        );
    }

    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <Bot className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Complete requirement
                    </h2>
                    <Badge variant="outline">{requirement.phase_title}</Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                    {requirement.description}
                </p>
            </div>

            <div className="grid gap-3">
                <label className="grid gap-1 text-sm">
                    <span className="font-medium">Section title</span>
                    <input
                        value={sectionTitle}
                        placeholder={requirement.title}
                        onChange={(event) =>
                            onTitleChange(event.currentTarget.value)
                        }
                        className="rounded-md border bg-background px-3 py-2"
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    <span className="font-medium">Plan detail</span>
                    <textarea
                        value={sectionBody}
                        placeholder="Add the missing context, assumptions, evidence, decisions, and risks the advisor should rely on."
                        rows={8}
                        onChange={(event) =>
                            onBodyChange(event.currentTarget.value)
                        }
                        className="min-h-40 rounded-md border bg-background px-3 py-2"
                    />
                </label>
                <div className="grid gap-2">
                    <div className="grid gap-2 sm:grid-cols-[220px_1fr]">
                        <Select
                            value={workstream}
                            onValueChange={onWorkstreamChange}
                        >
                            <SelectTrigger
                                size="sm"
                                aria-label="Supporting evidence workstream"
                            >
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
                        <FileDropzone
                            key={supportingFileKey}
                            id="dd_acquisition_plan_supporting_document"
                            files={supportingFile ? [supportingFile] : []}
                            label="Attach supporting document"
                            onFilesChange={(files) =>
                                onFileChange(files[0] ?? null)
                            }
                        />
                    </div>
                </div>
                <InputError message={sectionError ?? undefined} />
                <div>
                    <Button
                        type="button"
                        disabled={savingSection}
                        onClick={onSave}
                    >
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        {savingSection ? 'Saving' : 'Save requirement'}
                    </Button>
                </div>
            </div>
        </section>
    );
}

function PlanWorkspace({
    plan,
    compact = false,
}: {
    plan: BusinessPlanPayload;
    compact?: boolean;
}) {
    return (
        <Section
            title="Plan workspace"
            description="Review the DD-populated sections, completed requirements, and assistant guidance."
        >
            <section className="space-y-4 rounded-md border bg-background p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <FileText className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">{plan.title}</h2>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">
                            {formatLabel(plan.status)}
                        </Badge>
                        <Badge
                            variant={
                                plan.requirements_complete
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {plan.requirements_complete
                                ? 'Requirements complete'
                                : `${plan.missing_requirements.length} missing`}
                        </Badge>
                    </div>
                </div>

                {!plan.requirements_complete ? (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        Missing: {plan.missing_requirements.join(', ')}
                    </div>
                ) : null}

                <div className="grid gap-3">
                    {plan.phases.map((phase) => (
                        <article
                            key={phase.id}
                            className="rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">
                                    {phase.title}
                                </h3>
                                <Badge variant="outline">
                                    {
                                        phase.requirements.filter(
                                            (requirement) =>
                                                requirement.complete,
                                        ).length
                                    }
                                    /{phase.requirements.length} requirements
                                </Badge>
                            </div>
                            <div className="mt-3 grid gap-2 md:grid-cols-2">
                                {phase.requirements.map((requirement) => (
                                    <div
                                        key={requirement.key}
                                        className="rounded-md bg-muted/40 p-3 text-sm"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium">
                                                {requirement.title}
                                            </span>
                                            <Badge
                                                variant={
                                                    requirement.complete
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {requirement.complete
                                                    ? 'Complete'
                                                    : 'Needed'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-muted-foreground">
                                            {requirement.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            {phase.sections.length === 0 ? (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    No section generated yet.
                                </p>
                            ) : (
                                <div className="mt-3 space-y-3">
                                    {phase.sections
                                        .slice(0, compact ? 1 : undefined)
                                        .map((section) => (
                                            <PlanSection
                                                key={section.id}
                                                section={section}
                                            />
                                        ))}
                                </div>
                            )}
                            {phase.requirements.some(
                                (requirement) => !requirement.complete,
                            ) ? (
                                <div className="mt-3 space-y-2">
                                    {phase.requirements
                                        .filter(
                                            (requirement) =>
                                                !requirement.complete,
                                        )
                                        .slice(0, compact ? 1 : undefined)
                                        .map((requirement) => (
                                            <PendingRequirement
                                                key={requirement.key}
                                                requirement={requirement}
                                            />
                                        ))}
                                </div>
                            ) : null}
                        </article>
                    ))}
                </div>
            </section>
        </Section>
    );
}

function PlanTemplatePreview({
    phases,
    compact = false,
}: {
    phases: PlanTemplatePhasePayload[];
    compact?: boolean;
}) {
    return (
        <Section
            title="Business plan view"
            description="The plan structure is visible before generation; DD-populated and client-completed sections replace pending items as the plan is built."
        >
            <section className="space-y-4 rounded-md border bg-background p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <FileText className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Draft acquisition plan
                        </h2>
                    </div>
                    <Badge variant="secondary">Pending generation</Badge>
                </div>

                <div className="grid gap-3">
                    {phases.map((phase) => (
                        <article
                            key={phase.key}
                            className="rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-medium">
                                    {phase.title}
                                </h3>
                                <Badge variant="outline">
                                    0/{phase.requirements.length} requirements
                                </Badge>
                            </div>
                            <div className="mt-3 space-y-2">
                                {phase.requirements
                                    .slice(0, compact ? 1 : undefined)
                                    .map((requirement) => (
                                        <PendingRequirement
                                            key={requirement.key}
                                            requirement={requirement}
                                        />
                                    ))}
                            </div>
                        </article>
                    ))}
                </div>
            </section>
        </Section>
    );
}

function PendingRequirement({
    requirement,
}: {
    requirement: PlanRequirementPayload;
}) {
    return (
        <div className="rounded-md border border-dashed bg-muted/20 p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-medium">
                    Pending: {requirement.title}
                </span>
                <Badge variant="secondary">Pending</Badge>
            </div>
            <p className="mt-1 text-muted-foreground">
                {requirement.description}
            </p>
        </div>
    );
}

function PlanSection({ section }: { section: PlanSectionPayload }) {
    const askAssistant = () => {
        router.post(section.guidance_url, {}, { preserveScroll: true });
    };

    return (
        <div className="rounded-md bg-muted/40 p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h4 className="text-sm font-medium">{section.title}</h4>
                    <div className="mt-1 flex flex-wrap gap-2">
                        <Badge variant="outline">
                            {formatLabel(section.completeness_status)}
                        </Badge>
                        {section.predictive_score?.score ? (
                            <Badge variant="secondary">
                                {section.predictive_score.score}/100
                            </Badge>
                        ) : null}
                        {section.attached_document_ids.length > 0 ? (
                            <Badge variant="outline">
                                {section.attached_document_ids.length} docs
                            </Badge>
                        ) : null}
                    </div>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={askAssistant}
                >
                    <Bot className="size-4" aria-hidden="true" />
                    Ask assistant
                </Button>
            </div>
            <p className="mt-3 text-sm whitespace-pre-line text-muted-foreground">
                {section.body}
            </p>
            {section.guidance ? (
                <div className="mt-3 rounded-md border bg-background p-3 text-sm">
                    <div className="font-medium">Assistant guidance</div>
                    <p className="mt-1 text-muted-foreground">
                        {section.guidance.summary ??
                            section.guidance.ai_summary ??
                            'Guidance generated.'}
                    </p>
                </div>
            ) : null}
        </div>
    );
}

function EmptyPlanPanel({
    readiness,
    onPopulate,
}: {
    readiness: ReadinessPayload;
    onPopulate: () => void;
}) {
    return (
        <section className="rounded-md border bg-background p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-sm font-medium">Plan not populated</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {readiness.missing.length > 0
                            ? readiness.missing.join(', ')
                            : 'The DD inputs are available.'}
                    </p>
                </div>
                <Button type="button" onClick={onPopulate}>
                    Populate from DD
                </Button>
            </div>
        </section>
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

function ActionTile({
    icon: Icon,
    title,
    value,
    explanation,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    value: ReactNode;
    explanation: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div>
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <Icon className="size-4" aria-hidden={true} />
                            {title}
                        </div>
                        <div className="mt-2 text-sm text-muted-foreground">
                            {value}
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

function TargetPanel({ engagement }: { engagement: EngagementPayload }) {
    return (
        <section className="space-y-4 rounded-md border bg-background p-4">
            <div className="flex items-center gap-2">
                <PieChart className="size-4" aria-hidden="true" />
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
                <CheckCircle2 className="size-4" aria-hidden="true" />
                <h2 className="text-sm font-medium">Readiness</h2>
            </div>
            <div className="grid gap-3 text-sm md:grid-cols-2">
                <Detail
                    label="Questionnaire"
                    value={
                        readiness.questionnaire_submitted
                            ? formatDate(readiness.questionnaire_submitted_at)
                            : 'Pending'
                    }
                />
                <Detail
                    label="Evidence"
                    value={`${readiness.data_room_item_count} item${readiness.data_room_item_count === 1 ? '' : 's'}`}
                />
                <Detail
                    label="Workstreams"
                    value={`${readiness.workstreams_completed}/${readiness.workstreams_total}`}
                />
                <Detail
                    label="Valuation"
                    value={
                        readiness.valuation_ready
                            ? formatDate(readiness.valuation_as_at)
                            : 'Pending'
                    }
                />
                <Detail
                    label="Advice report"
                    value={
                        readiness.advice_report_ready
                            ? formatDate(readiness.advice_report_generated_at)
                            : 'Pending'
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
            <p className="text-sm text-muted-foreground">
                No blocking gaps currently surfaced.
            </p>
        );
    }

    return (
        <div className="grid gap-2 text-sm text-muted-foreground">
            {readiness.missing.map((item) => (
                <div key={item} className="flex items-center gap-2">
                    <span className="size-1.5 rounded-full bg-amber-500" />
                    {item}
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
            aria-label="Acquisition plan sections"
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

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid grid-cols-[130px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function requirementKey(requirement: PlanRequirementPayload): string {
    return `${requirement.phase_key}:${requirement.key}`;
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

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
