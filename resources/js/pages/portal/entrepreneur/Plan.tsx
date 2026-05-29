import { Head, Link, router } from '@inertiajs/react';
import {
    Bot,
    CheckCircle2,
    ClipboardCheck,
    Eye,
    FileText,
    MessageSquare,
    RefreshCw,
    Send,
    Upload,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type ProfilePayload = {
    id: string;
    name: string;
    email: string;
    stage: string;
    stage_label: string;
    concept_summary: string | null;
};

type ReadinessPayload = {
    completed: boolean;
    score: number | null;
    outcome: string | null;
    assessed_at: string | null;
    personal_barriers: string[];
};

type ReadinessField = {
    key: string;
    label: string;
};

type IdeaValidationPayload = {
    id: string;
    problem: string;
    target_customer: string;
    solution: string;
    value_proposition: string;
    demand_signal: string;
    revenue_model: string;
    summary: string;
    viability_alerts: {
        message?: string;
        severity?: string;
    }[];
    evaluated_at: string | null;
    advisor_gate_passed_at: string | null;
    advisor_gate_note: string | null;
    plan_builder_unlocked: boolean;
} | null;

type BusinessPlanPayload = {
    id: string;
    title: string;
    status: string;
    completed_at: string | null;
    updated_at: string | null;
    requirements_complete: boolean;
    missing_requirements: string[];
    latest_assessment: {
        id: string;
        round: number;
        status: string;
        overall_grade: string;
        finalised_at: string | null;
        url: string;
    } | null;
    phases: PlanPhasePayload[];
} | null;

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
    section_title?: string | null;
};

type PlanSectionPayload = {
    id: string;
    title: string;
    body: string;
    source_type: string;
    completeness_status: string;
    attached_document_ids: string[];
    predictive_score: {
        score?: number;
        band?: string;
        gaps?: string[];
        reasons?: string[];
    } | null;
    guidance: {
        summary?: string;
        ai_summary?: string;
        predictive_score?: {
            score?: number;
            band?: string;
            gaps?: string[];
        };
    } | null;
    requirement_key: string | null;
    guidance_url: string;
};

type ReportPayload = {
    id: string;
    title: string;
    type: string;
    generated_at: string | null;
    download_url: string;
};

type AdvisoryRequestPayload = {
    available: boolean;
    requested: boolean;
    request_url: string;
    thread_url: string | null;
    blockers: string[];
};

type Props = {
    profile: ProfilePayload;
    readiness: ReadinessPayload;
    readinessFields: ReadinessField[];
    ideaValidation: IdeaValidationPayload;
    plan: BusinessPlanPayload;
    planTemplate: PlanTemplatePhasePayload[];
    reports: ReportPayload[];
    advisoryRequest: AdvisoryRequestPayload;
    urls: {
        dashboard: string;
        readiness: string;
        ideaValidation: string;
        startPlan: string;
        sectionStore: string;
        preview: string;
        submit: string;
        documentUpload: string;
        messages: string;
        advisoryRequest: string;
    };
};

type Tab = 'actions' | 'information';

export default function EntrepreneurPlan({
    profile,
    readiness,
    readinessFields,
    ideaValidation,
    plan,
    planTemplate,
    reports,
    advisoryRequest,
    urls,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('actions');
    const [readinessForm, setReadinessForm] = useState<Record<string, string>>(
        () =>
            Object.fromEntries(
                readinessFields.map((field) => [field.key, '3']),
            ),
    );
    const [personalBarriers, setPersonalBarriers] = useState('');
    const [ideaForm, setIdeaForm] = useState({
        problem: ideaValidation?.problem ?? '',
        target_customer: ideaValidation?.target_customer ?? '',
        solution: ideaValidation?.solution ?? '',
        value_proposition: ideaValidation?.value_proposition ?? '',
        demand_signal: ideaValidation?.demand_signal ?? '',
        revenue_model: ideaValidation?.revenue_model ?? '',
    });
    const phases = plan?.phases ?? planTemplate;
    const requirements = useMemo(
        () => phases.flatMap((phase) => phase.requirements),
        [phases],
    );
    const firstMissingRequirement =
        requirements.find((requirement) => !requirement.complete) ??
        requirements[0] ??
        null;
    const [selectedKey, setSelectedKey] = useState<string | null>(
        firstMissingRequirement ? requirementId(firstMissingRequirement) : null,
    );
    const selectedRequirement =
        requirements.find(
            (requirement) => requirementId(requirement) === selectedKey,
        ) ??
        firstMissingRequirement ??
        null;
    const selectedSection = selectedRequirement
        ? findSection(plan, selectedRequirement)
        : null;
    const [sectionTitle, setSectionTitle] = useState('');
    const [sectionBody, setSectionBody] = useState('');
    const [supportingFile, setSupportingFile] = useState<File | null>(null);
    const [supportingKey, setSupportingKey] = useState(0);
    const [sectionError, setSectionError] = useState<string | null>(null);
    const [savingSection, setSavingSection] = useState(false);

    useEffect(() => {
        if (!selectedRequirement) {
            return;
        }

        const section = findSection(plan, selectedRequirement);
        // Intentionally sync the editable form state to the selected
        // requirement (and re-sync when the plan refreshes after a save).
        /* eslint-disable react-hooks/set-state-in-effect */
        setSectionTitle(section?.title ?? selectedRequirement.title);
        setSectionBody(section?.body ?? '');
        setSupportingFile(null);
        setSupportingKey((key) => key + 1);
        setSectionError(null);
        /* eslint-enable react-hooks/set-state-in-effect */
    }, [selectedRequirement, plan]);

    const submitReadiness = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(
            urls.readiness,
            {
                ...readinessForm,
                personal_barriers: personalBarriers,
            },
            { preserveScroll: true },
        );
    };

    const submitIdea = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(urls.ideaValidation, ideaForm, { preserveScroll: true });
    };

    const startPlan = () => {
        router.post(urls.startPlan, {}, { preserveScroll: true });
    };

    const submitPlan = () => {
        router.post(urls.submit, {}, { preserveScroll: true });
    };

    const requestAdvisory = () => {
        router.post(urls.advisoryRequest, {}, { preserveScroll: true });
    };

    const saveSection = async () => {
        if (!selectedRequirement) {
            return;
        }

        setSavingSection(true);
        setSectionError(null);
        const attachedIds: string[] = [];

        if (supportingFile) {
            const formData = new FormData();
            formData.append('file', supportingFile);
            formData.append('category', 'plan_attachment');
            formData.append('claim_value', sectionBody);
            formData.append('question_prompt', selectedRequirement.title);

            const response = await fetch(urls.documentUpload, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            if (!response.ok) {
                setSavingSection(false);
                setSectionError('Supporting document upload failed.');

                return;
            }

            const payload = (await response.json()) as {
                document?: { id?: string };
            };

            if (payload.document?.id) {
                attachedIds.push(payload.document.id);
            }
        }

        router.post(
            urls.sectionStore,
            {
                phase_key: selectedRequirement.phase_key,
                requirement_key: selectedRequirement.key,
                title: sectionTitle,
                body: sectionBody,
                attached_document_ids: attachedIds,
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingSection(false),
            },
        );
    };

    return (
        <TooltipProvider>
            <Head title="Business plan" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Business plan workspace
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {profile.name} / {profile.stage_label}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <a
                                href={urls.preview}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Eye className="size-4" aria-hidden="true" />
                                Preview business plan
                            </a>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={urls.messages}>
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
                    <div className="space-y-6">
                        <section className="space-y-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Priority actions
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Complete readiness, validate the idea, build
                                    the plan, then request advisory when
                                    assessment feedback is ready.
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                <ActionPanel
                                    icon={ClipboardCheck}
                                    title="Readiness"
                                    value={
                                        readiness.completed
                                            ? `${readiness.score?.toFixed(1)}/100`
                                            : 'Not completed'
                                    }
                                    explanation="Readiness checks whether the founder has the clarity, capacity, evidence, and support to keep building."
                                >
                                    <Badge variant="outline">
                                        {readiness.outcome
                                            ? formatLabel(readiness.outcome)
                                            : 'Needed'}
                                    </Badge>
                                </ActionPanel>

                                <ActionPanel
                                    icon={Bot}
                                    title="Idea validation"
                                    value={
                                        ideaValidation
                                            ? ideaValidation.plan_builder_unlocked
                                                ? 'Advisor approved'
                                                : 'Awaiting advisor gate'
                                            : 'Not submitted'
                                    }
                                    explanation="Idea validation captures the customer problem, solution, demand, and revenue logic before the plan builder opens."
                                >
                                    {ideaValidation?.plan_builder_unlocked ? (
                                        <Badge variant="secondary">
                                            Builder unlocked
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Advisor review
                                        </Badge>
                                    )}
                                </ActionPanel>

                                <ActionPanel
                                    icon={FileText}
                                    title="Plan completion"
                                    value={
                                        plan
                                            ? plan.requirements_complete
                                                ? 'Complete'
                                                : `${plan.missing_requirements.length} gaps`
                                            : 'Not started'
                                    }
                                    explanation="Plan completion is based on all required business plan sections, not merely one section per phase."
                                >
                                    {plan ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={submitPlan}
                                            disabled={
                                                !plan.requirements_complete
                                            }
                                        >
                                            <Send
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Submit for assessment
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={startPlan}
                                            disabled={
                                                !ideaValidation?.plan_builder_unlocked
                                            }
                                        >
                                            <RefreshCw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Start plan
                                        </Button>
                                    )}
                                </ActionPanel>

                                <ActionPanel
                                    icon={Eye}
                                    title="Assessment"
                                    value={
                                        plan?.latest_assessment
                                            ? `${formatLabel(plan.latest_assessment.overall_grade)}`
                                            : 'Pending'
                                    }
                                    explanation="Assessment appears once your advisor scores the submitted plan and finalises feedback."
                                >
                                    {plan?.latest_assessment ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={
                                                    plan.latest_assessment.url
                                                }
                                            >
                                                View assessment
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Badge variant="outline">
                                            Advisor action
                                        </Badge>
                                    )}
                                </ActionPanel>

                                <ActionPanel
                                    icon={CheckCircle2}
                                    title="Advisory"
                                    value={
                                        advisoryRequest.requested
                                            ? 'Requested'
                                            : advisoryRequest.available
                                              ? 'Available'
                                              : 'Locked'
                                    }
                                    explanation="Request advisory once the plan has been assessed as advisory ready. This asks your advisor to convert the plan into a standard advisory engagement."
                                >
                                    {advisoryRequest.requested &&
                                    advisoryRequest.thread_url ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={
                                                    advisoryRequest.thread_url
                                                }
                                            >
                                                Open request
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={
                                                !advisoryRequest.available
                                            }
                                            onClick={requestAdvisory}
                                        >
                                            Request advisory
                                        </Button>
                                    )}
                                </ActionPanel>
                            </div>
                        </section>

                        <div className="grid gap-6 xl:grid-cols-[0.95fr_1.4fr]">
                            <section className="space-y-4 rounded-md border bg-background p-4">
                                <div>
                                    <h2 className="text-sm font-medium">
                                        Readiness assessment
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Rate each area from 0 to 5. The score
                                        decides whether to proceed, develop
                                        first, or pause.
                                    </p>
                                </div>
                                <form
                                    className="space-y-3"
                                    onSubmit={submitReadiness}
                                >
                                    {readinessFields.map((field) => (
                                        <label
                                            key={field.key}
                                            className="grid gap-1 text-sm"
                                        >
                                            <span>{field.label}</span>
                                            <input
                                                type="number"
                                                min={0}
                                                max={5}
                                                step={1}
                                                value={
                                                    readinessForm[field.key] ??
                                                    '3'
                                                }
                                                onChange={(event) =>
                                                    setReadinessForm(
                                                        (current) => ({
                                                            ...current,
                                                            [field.key]:
                                                                event.target
                                                                    .value,
                                                        }),
                                                    )
                                                }
                                                className="h-9 rounded-md border bg-background px-3 text-sm"
                                            />
                                        </label>
                                    ))}
                                    <label className="grid gap-1 text-sm">
                                        <span>Personal barriers</span>
                                        <textarea
                                            value={personalBarriers}
                                            onChange={(event) =>
                                                setPersonalBarriers(
                                                    event.target.value,
                                                )
                                            }
                                            rows={3}
                                            className="rounded-md border bg-background px-3 py-2 text-sm"
                                            placeholder="Optional: time, confidence, support, runway, wellbeing, or other constraints."
                                        />
                                    </label>
                                    <Button type="submit" size="sm">
                                        Save readiness
                                    </Button>
                                </form>
                            </section>

                            <section className="space-y-4 rounded-md border bg-background p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-sm font-medium">
                                            Idea validation
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Capture the assumptions your advisor
                                            must approve before plan building
                                            opens.
                                        </p>
                                    </div>
                                    {ideaValidation?.advisor_gate_passed_at ? (
                                        <Badge variant="secondary">
                                            Gate passed
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Advisor gate required
                                        </Badge>
                                    )}
                                </div>
                                <form
                                    className="grid gap-3 lg:grid-cols-2"
                                    onSubmit={submitIdea}
                                >
                                    {ideaFields.map((field) => (
                                        <label
                                            key={field.key}
                                            className="grid gap-1 text-sm"
                                        >
                                            <span>{field.label}</span>
                                            <textarea
                                                value={
                                                    ideaForm[
                                                        field.key as keyof typeof ideaForm
                                                    ]
                                                }
                                                onChange={(event) =>
                                                    setIdeaForm((current) => ({
                                                        ...current,
                                                        [field.key]:
                                                            event.target.value,
                                                    }))
                                                }
                                                rows={4}
                                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                                placeholder={field.placeholder}
                                            />
                                        </label>
                                    ))}
                                    <div className="lg:col-span-2">
                                        <Button type="submit" size="sm">
                                            Submit idea validation
                                        </Button>
                                    </div>
                                </form>
                            </section>
                        </div>

                        <section className="space-y-4 rounded-md border bg-background p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-medium">
                                        Plan requirements
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Complete every required section and
                                        attach supporting evidence where it
                                        helps the advisor rely on the plan.
                                    </p>
                                </div>
                                {!plan ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={startPlan}
                                        disabled={
                                            !ideaValidation?.plan_builder_unlocked
                                        }
                                    >
                                        Start plan
                                    </Button>
                                ) : null}
                            </div>

                            <div className="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                                <div className="space-y-3">
                                    {phases.map((phase) => (
                                        <div
                                            key={phase.key}
                                            className="space-y-2"
                                        >
                                            <div className="text-xs font-medium text-muted-foreground">
                                                {phase.title}
                                            </div>
                                            {phase.requirements.map(
                                                (requirement) => (
                                                    <button
                                                        key={requirementId(
                                                            requirement,
                                                        )}
                                                        type="button"
                                                        className={cn(
                                                            'w-full rounded-md border p-3 text-left text-sm transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                            selectedRequirement &&
                                                                requirementId(
                                                                    requirement,
                                                                ) ===
                                                                    requirementId(
                                                                        selectedRequirement,
                                                                    ) &&
                                                                'border-foreground',
                                                        )}
                                                        onClick={() =>
                                                            setSelectedKey(
                                                                requirementId(
                                                                    requirement,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div>
                                                                <div className="font-medium">
                                                                    {
                                                                        requirement.title
                                                                    }
                                                                </div>
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    {
                                                                        requirement.description
                                                                    }
                                                                </p>
                                                            </div>
                                                            <Badge
                                                                variant={
                                                                    requirement.complete
                                                                        ? 'secondary'
                                                                        : 'outline'
                                                                }
                                                            >
                                                                {requirement.complete
                                                                    ? 'Complete'
                                                                    : 'Needed'}
                                                            </Badge>
                                                        </div>
                                                    </button>
                                                ),
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div className="space-y-4 rounded-md border p-4">
                                    {selectedRequirement ? (
                                        <>
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h3 className="text-sm font-medium">
                                                        Complete requirement
                                                    </h3>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {
                                                            selectedRequirement.description
                                                        }
                                                    </p>
                                                </div>
                                                {selectedSection ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                selectedSection.guidance_url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Bot
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Generate guidance
                                                    </Button>
                                                ) : null}
                                            </div>
                                            <label className="grid gap-1 text-sm">
                                                <span>Section title</span>
                                                <input
                                                    value={sectionTitle}
                                                    onChange={(event) =>
                                                        setSectionTitle(
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="h-9 rounded-md border bg-background px-3 text-sm"
                                                />
                                            </label>
                                            <label className="grid gap-1 text-sm">
                                                <span>Plan detail</span>
                                                <textarea
                                                    value={sectionBody}
                                                    onChange={(event) =>
                                                        setSectionBody(
                                                            event.target.value,
                                                        )
                                                    }
                                                    rows={8}
                                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                                    placeholder="Add the context, evidence, assumptions, decisions, and risks your advisor should rely on."
                                                />
                                            </label>
                                            <FileDropzone
                                                key={supportingKey}
                                                id="entrepreneur-plan-support"
                                                files={
                                                    supportingFile
                                                        ? [supportingFile]
                                                        : []
                                                }
                                                label="Attach supporting document"
                                                onFilesChange={(files) =>
                                                    setSupportingFile(
                                                        files[0] ?? null,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    sectionError ?? undefined
                                                }
                                            />
                                            {selectedSection?.guidance ? (
                                                <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                                    <div className="font-medium">
                                                        AI guidance
                                                    </div>
                                                    <p className="mt-1 text-muted-foreground">
                                                        {
                                                            selectedSection
                                                                .guidance
                                                                .summary
                                                        }
                                                    </p>
                                                </div>
                                            ) : null}
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() =>
                                                    void saveSection()
                                                }
                                                disabled={
                                                    !plan || savingSection
                                                }
                                            >
                                                <Upload
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {savingSection
                                                    ? 'Saving'
                                                    : 'Save requirement'}
                                            </Button>
                                        </>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Select a requirement to start
                                            completing the business plan.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </section>
                    </div>
                ) : (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <section className="space-y-4 rounded-md border bg-background p-4">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-sm font-medium">
                                    Assessment reports
                                </h2>
                                <Badge variant="outline">
                                    {reports.length}
                                </Badge>
                            </div>
                            {reports.length > 0 ? (
                                <div className="divide-y rounded-md border">
                                    {reports.map((report) => (
                                        <article
                                            key={report.id}
                                            className="flex flex-wrap items-center justify-between gap-3 p-3"
                                        >
                                            <div>
                                                <div className="text-sm font-medium">
                                                    {report.title}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        report.generated_at,
                                                    )}
                                                </div>
                                            </div>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={report.download_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Open
                                                </a>
                                            </Button>
                                        </article>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Reports appear after your advisor finalises
                                    an assessment.
                                </p>
                            )}
                        </section>

                        <section className="space-y-4 rounded-md border bg-background p-4">
                            <h2 className="text-sm font-medium">
                                Current profile
                            </h2>
                            <dl className="grid gap-3 text-sm">
                                <Detail label="Email" value={profile.email} />
                                <Detail
                                    label="Stage"
                                    value={profile.stage_label}
                                />
                                <Detail
                                    label="Concept"
                                    value={profile.concept_summary}
                                />
                                <Detail
                                    label="Plan status"
                                    value={
                                        plan ? formatLabel(plan.status) : null
                                    }
                                />
                            </dl>
                            {advisoryRequest.blockers.length > 0 ? (
                                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                                    {advisoryRequest.blockers.join(' ')}
                                </div>
                            ) : null}
                        </section>
                    </div>
                )}
            </div>
        </TooltipProvider>
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
            className="inline-flex w-full max-w-md rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Business plan sections"
        >
            {(['actions', 'information'] as Tab[]).map((tab) => (
                <button
                    key={tab}
                    type="button"
                    role="tab"
                    aria-selected={activeTab === tab}
                    className={cn(
                        'flex-1 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        activeTab === tab &&
                            'bg-background text-foreground shadow-xs',
                    )}
                    onClick={() => onChange(tab)}
                >
                    {formatLabel(tab)}
                </button>
            ))}
        </div>
    );
}

function ActionPanel({
    icon: Icon,
    title,
    value,
    explanation,
    children,
}: {
    icon: typeof ClipboardCheck;
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
        <div className="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{value || '-'}</dd>
        </div>
    );
}

function findSection(
    plan: BusinessPlanPayload,
    requirement: PlanRequirementPayload,
): PlanSectionPayload | null {
    return (
        plan?.phases
            .flatMap((phase) => phase.sections)
            .find(
                (section) =>
                    section.requirement_key === requirement.key ||
                    section.id === requirement.section_id,
            ) ?? null
    );
}

function requirementId(requirement: PlanRequirementPayload): string {
    return `${requirement.phase_key}:${requirement.key}`;
}

function formatLabel(value: string): string {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

const ideaFields = [
    {
        key: 'problem',
        label: 'Problem',
        placeholder: 'What specific customer problem are you solving?',
    },
    {
        key: 'target_customer',
        label: 'Target customer',
        placeholder: 'Who has this problem and how do you know?',
    },
    {
        key: 'solution',
        label: 'Solution',
        placeholder: 'What will you offer and how will it work?',
    },
    {
        key: 'value_proposition',
        label: 'Value proposition',
        placeholder: 'Why would the customer choose this over alternatives?',
    },
    {
        key: 'demand_signal',
        label: 'Demand signal',
        placeholder: 'What evidence shows people want or need this?',
    },
    {
        key: 'revenue_model',
        label: 'Revenue model',
        placeholder: 'How will the business earn, collect, and retain revenue?',
    },
];

EntrepreneurPlan.layout = {
    breadcrumbs: [
        {
            title: 'Business Plan',
            href: '/portal/entrepreneur/plan',
        },
    ],
};
