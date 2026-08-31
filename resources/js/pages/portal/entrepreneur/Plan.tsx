import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Bot,
    ChevronDown,
    ChevronUp,
    CheckCircle2,
    Eye,
    FileText,
    MessageSquare,
    Pencil,
    RefreshCw,
    Send,
    Trophy,
    Upload,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import FileDropzone from '@/components/file-dropzone';
import { FormattedTextarea } from '@/components/formatted-textarea';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TooltipProvider } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import {
    BUDGET_ASSUMPTIONS_REQUIREMENT_KEY,
    BUDGET_UNLOCK_REQUIREMENT_KEY,
    BudgetEditor,
    budgetPlanSource,
    budgetToForm,
    cleanBudgetForm,
    findSection,
    ideaValidationToForm,
    planWorkspaceKey,
    requirementId,
    sectionAutosaveStateLabel,
} from './plan-budget';
import {
    ActionPanel,
    Detail,
    IdeaValidationHistory,
    IdeaValidationSnapshot,
    PlainLanguageGuide,
    TabList,
    displayStageLabel,
    formatDate,
    formatLabel,
    ideaFields,
    journeyLevelLabel,
} from './plan-dashboard-panels';
import type { IdeaValidationVersion, Tab } from './plan-dashboard-panels';
import {
    IDEA_VALIDATION_FIELD_MAX_LENGTH,
    PLAN_SECTION_BODY_MAX_LENGTH,
} from './plan-types';
import type { BudgetFormState, IdeaValidationForm, Props } from './plan-types';
import {
    csrfToken,
    currentSectionTextareaPosition,
    localDraftIsNewer,
    postBudgetAutosave,
    postSectionAutosave,
    readPlanWorkspaceDraft,
    restoreSectionTextareaPosition,
    updatePlanWorkspaceDraft,
} from './plan-workspace-draft';
import type { AutosaveState, PlanWorkspaceDraft } from './plan-workspace-draft';

export default function EntrepreneurPlan({
    profile,
    packageAccess,
    ideaValidation,
    ideaValidationVersions,
    plan,
    planTemplate,
    reports,
    advisoryRequest,
    gamification,
    urls,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('actions');
    const companyNameForm = useForm({
        company_name: profile.company_name ?? '',
    });
    const ideaForm = useForm<IdeaValidationForm>({
        problem: ideaValidation?.problem ?? '',
        target_customer: ideaValidation?.target_customer ?? '',
        solution: ideaValidation?.solution ?? '',
        value_proposition: ideaValidation?.value_proposition ?? '',
        demand_signal: ideaValidation?.demand_signal ?? '',
        revenue_model: ideaValidation?.revenue_model ?? '',
    });
    const [showValidatedIdeaForm, setShowValidatedIdeaForm] = useState(false);
    const [recallingIdea, setRecallingIdea] = useState(false);
    const [restoringIdeaVersionId, setRestoringIdeaVersionId] = useState<
        string | null
    >(null);
    const phases = plan?.phases ?? planTemplate;
    const requirements = useMemo(
        () => phases.flatMap((phase) => phase.requirements),
        [phases],
    );
    const workspaceKey = useMemo(
        () => planWorkspaceKey(profile.id),
        [profile.id],
    );
    const initialWorkspaceDraft = useMemo(
        (): PlanWorkspaceDraft<BudgetFormState> | null =>
            readPlanWorkspaceDraft<BudgetFormState>(workspaceKey),
        [workspaceKey],
    );
    const firstMissingRequirement =
        requirements.find((requirement) => !requirement.complete) ??
        requirements[0] ??
        null;
    const [selectedKey, setSelectedKey] = useState<string | null>(() =>
        initialWorkspaceDraft?.selectedKey &&
        requirements.some(
            (requirement) =>
                requirementId(requirement) ===
                initialWorkspaceDraft.selectedKey,
        )
            ? initialWorkspaceDraft.selectedKey
            : firstMissingRequirement
              ? requirementId(firstMissingRequirement)
              : null,
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
    const budgetAutosaveUnlocked = useMemo(() => {
        if (!plan) {
            return false;
        }

        const budgetSource = budgetPlanSource(
            plan,
            BUDGET_UNLOCK_REQUIREMENT_KEY,
        );
        const assumptionsSource = budgetPlanSource(
            plan,
            BUDGET_ASSUMPTIONS_REQUIREMENT_KEY,
        );

        return (
            budgetSource.requirement?.complete === true &&
            assumptionsSource.requirement?.complete === true
        );
    }, [plan]);
    const completedRequirementCount = requirements.filter(
        (requirement) => requirement.complete,
    ).length;
    const totalRequirementCount = requirements.length;
    const planCompletion = gamification.plan_completion ?? {
        total: totalRequirementCount,
        completed: completedRequirementCount,
        percent:
            totalRequirementCount > 0
                ? Math.round(
                      (completedRequirementCount / totalRequirementCount) * 100,
                  )
                : 0,
    };
    const selectedCompletionPercent =
        totalRequirementCount > 0 && selectedRequirement
            ? Math.round(
                  ((completedRequirementCount +
                      (selectedRequirement.complete ? 0 : 1)) /
                      totalRequirementCount) *
                      100,
              )
            : planCompletion.percent;
    const includesIdeaValidation = packageAccess.includes_idea_validation;
    const includesPlanBudget = packageAccess.includes_plan_budget;
    const directPlanAccess = includesPlanBudget && !includesIdeaValidation;
    const hasIdeaValidation = Boolean(ideaValidation);
    const planBuilderUnlocked =
        directPlanAccess || Boolean(ideaValidation?.plan_builder_unlocked);
    const ideaValidationApproved = Boolean(
        ideaValidation?.advisor_gate_passed_at ||
        ideaValidation?.plan_builder_unlocked,
    );
    const ideaChangesRequested =
        ideaValidation?.advisor_gate_status === 'changes_requested';
    const ideaValidationRecalled = Boolean(ideaValidation?.recalled_at);
    const ideaUnderAdvisorReview =
        hasIdeaValidation &&
        !ideaValidationApproved &&
        !ideaChangesRequested &&
        !ideaValidationRecalled;
    const showIdeaValidationEditor =
        !hasIdeaValidation ||
        ideaChangesRequested ||
        ideaValidationRecalled ||
        (ideaValidationApproved && showValidatedIdeaForm);
    const ideaValidationSummary = ideaFields.map((field) => ({
        label: field.label,
        value: ideaValidation?.[field.key as keyof IdeaValidationForm] ?? '-',
    }));
    const hasPlan = Boolean(plan);
    const nextSmallWin =
        includesIdeaValidation && !hasIdeaValidation
            ? {
                  badge: 'Step 1',
                  title: 'Complete idea validation',
                  body: 'Answer the idea validation questions first. Your advisor reviews this before the plan sections open.',
                  action: 'Start idea validation',
              }
            : includesIdeaValidation && ideaChangesRequested
              ? {
                    badge: 'Step 1',
                    title: 'Revise idea validation',
                    body: 'Your advisor has requested changes. Update the idea validation and resubmit it for review.',
                    action: 'Revise idea validation',
                }
              : includesIdeaValidation && ideaValidationRecalled
                ? {
                      badge: 'Step 1',
                      title: 'Revise idea validation',
                      body: 'Your validation has been recalled from advisor review. Update it, then resubmit it for review.',
                      action: 'Revise idea validation',
                  }
                : includesIdeaValidation && !planBuilderUnlocked
                  ? {
                        badge: 'Step 2',
                        title: 'Advisor review',
                        body: 'Idea validation is submitted. Your advisor needs to approve it before the plan sections open.',
                        action: null,
                    }
                  : includesPlanBudget && !hasPlan
                    ? {
                          badge: includesIdeaValidation ? 'Step 3' : 'Step 1',
                          title: 'Start the business plan',
                          body: includesIdeaValidation
                              ? 'Idea validation is approved. Start the plan to unlock section-by-section guidance and AI assist.'
                              : 'Your package opens the business plan and budget workspace directly.',
                          action: 'Start plan',
                      }
                    : includesPlanBudget
                      ? {
                            badge: `${planCompletion.completed}/${planCompletion.total} sections`,
                            title: 'Next plan section',
                            body: selectedRequirement
                                ? selectedRequirement.complete
                                    ? 'This section is already complete. Choose the next needed section when you are ready.'
                                    : `Focus on "${selectedRequirement.title}" first, then save it to move the plan to ${selectedCompletionPercent}%.`
                                : 'Select one requirement and complete that section first.',
                            action: null,
                        }
                      : {
                            badge: packageAccess.package_scope_label,
                            title: hasIdeaValidation
                                ? 'Idea validation submitted'
                                : 'Complete idea validation',
                            body: hasIdeaValidation
                                ? 'Your advisor can review the validation and provide gate feedback for this package.'
                                : 'Answer the idea validation questions to test the concept before investing in detailed plan work.',
                            action: hasIdeaValidation
                                ? null
                                : 'Start idea validation',
                        };
    const [sectionTitle, setSectionTitle] = useState('');
    const [sectionBody, setSectionBody] = useState('');
    const [supportingFile, setSupportingFile] = useState<File | null>(null);
    const [supportingKey, setSupportingKey] = useState(0);
    const [sectionError, setSectionError] = useState<string | null>(null);
    const [savingSection, setSavingSection] = useState(false);
    const [assistingSection, setAssistingSection] = useState(false);
    const [generatingExecutiveSummary, setGeneratingExecutiveSummary] =
        useState(false);
    const [assistantNotice, setAssistantNotice] = useState<string | null>(null);
    const [budgetForm, setBudgetForm] = useState<BudgetFormState>(
        () => initialWorkspaceDraft?.budgetForm ?? budgetToForm(plan?.budget),
    );
    const [savingBudget, setSavingBudget] = useState(false);
    const [sectionAutosaveState, setSectionAutosaveState] =
        useState<AutosaveState>('idle');
    const [budgetAutosaveState, setBudgetAutosaveState] =
        useState<AutosaveState>('idle');
    const selectedKeyRef = useRef<string | null>(selectedKey);
    const budgetAutosaveReadyRef = useRef(false);
    const rememberWorkspacePosition = useCallback(() => {
        const key = selectedKeyRef.current;
        const position = currentSectionTextareaPosition();

        updatePlanWorkspaceDraft(workspaceKey, (draft) => ({
            ...draft,
            selectedKey: key,
            windowScrollY: window.scrollY,
            sectionPositions:
                key && position
                    ? {
                          ...(draft.sectionPositions ?? {}),
                          [key]: position,
                      }
                    : draft.sectionPositions,
        }));
    }, [workspaceKey]);

    useEffect(() => {
        if (!selectedRequirement) {
            return;
        }

        const section = findSection(plan, selectedRequirement);
        const sectionKey = requirementId(selectedRequirement);
        const draft =
            readPlanWorkspaceDraft(workspaceKey)?.sectionDrafts?.[sectionKey];
        const useLocalDraft =
            draft !== undefined &&
            localDraftIsNewer(draft.updatedAt, section?.updated_at ?? null);
        // Intentionally sync the editable form state to the selected
        // requirement (and re-sync when the plan refreshes after a save).
        /* eslint-disable react-hooks/set-state-in-effect */
        setSectionTitle(
            useLocalDraft
                ? draft.title
                : (section?.title ?? selectedRequirement.title),
        );
        setSectionBody(useLocalDraft ? draft.body : (section?.body ?? ''));
        setSupportingFile(null);
        setSupportingKey((key) => key + 1);
        setSectionError(null);
        setAssistantNotice(null);
        setSectionAutosaveState('idle');
        window.requestAnimationFrame(() =>
            restoreSectionTextareaPosition(workspaceKey, sectionKey),
        );
        /* eslint-enable react-hooks/set-state-in-effect */
    }, [selectedRequirement, plan, workspaceKey]);

    useEffect(() => {
        // Keep the editable budget form aligned with Inertia refreshes after save.
        /* eslint-disable-next-line react-hooks/set-state-in-effect */
        setBudgetForm(
            readPlanWorkspaceDraft<BudgetFormState>(workspaceKey)?.budgetForm ??
                budgetToForm(plan?.budget),
        );
    }, [plan?.budget, workspaceKey]);

    useEffect(() => {
        selectedKeyRef.current = selectedKey;
        updatePlanWorkspaceDraft(workspaceKey, (draft) => ({
            ...draft,
            selectedKey,
        }));
    }, [selectedKey, workspaceKey]);

    useEffect(() => {
        const draft = readPlanWorkspaceDraft(workspaceKey);

        if (draft?.windowScrollY !== undefined) {
            window.requestAnimationFrame(() => {
                window.scrollTo({
                    top: draft.windowScrollY ?? 0,
                    behavior: 'auto',
                });
            });
        }
    }, [workspaceKey]);

    useEffect(() => {
        const remember = () => rememberWorkspacePosition();
        const rememberOnHidden = () => {
            if (document.visibilityState === 'hidden') {
                remember();
            }
        };

        window.addEventListener('beforeunload', remember);
        document.addEventListener('visibilitychange', rememberOnHidden);

        return () => {
            window.removeEventListener('beforeunload', remember);
            document.removeEventListener('visibilitychange', rememberOnHidden);
        };
    }, [rememberWorkspacePosition]);

    useEffect(() => {
        if (selectedRequirement?.type !== 'budget') {
            budgetAutosaveReadyRef.current = false;
        }
    }, [selectedRequirement?.type]);

    useEffect(() => {
        if (!selectedRequirement || selectedRequirement.type === 'budget') {
            return;
        }

        const sectionKey = requirementId(selectedRequirement);
        const timeout = window.setTimeout(() => {
            const position = currentSectionTextareaPosition();

            updatePlanWorkspaceDraft(workspaceKey, (draft) => ({
                ...draft,
                selectedKey: sectionKey,
                windowScrollY: window.scrollY,
                sectionDrafts: {
                    ...(draft.sectionDrafts ?? {}),
                    [sectionKey]: {
                        title: sectionTitle,
                        body: sectionBody,
                        updatedAt: new Date().toISOString(),
                    },
                },
                sectionPositions:
                    position !== null
                        ? {
                              ...(draft.sectionPositions ?? {}),
                              [sectionKey]: position,
                          }
                        : draft.sectionPositions,
            }));
        }, 250);

        return () => window.clearTimeout(timeout);
    }, [sectionBody, sectionTitle, selectedRequirement, workspaceKey]);

    useEffect(() => {
        if (!selectedRequirement || selectedRequirement.type === 'budget') {
            return;
        }

        if (!plan) {
            return;
        }

        if (
            !selectedSection &&
            sectionBody.trim() === '' &&
            sectionTitle.trim() === selectedRequirement.title
        ) {
            return;
        }

        let cancelled = false;
        const timeout = window.setTimeout(() => {
            setSectionAutosaveState('saving');

            void postSectionAutosave(urls.sectionStore, {
                phase_key: selectedRequirement.phase_key,
                requirement_key: selectedRequirement.key,
                title: sectionTitle,
                body: sectionBody,
            })
                .then((saved) => {
                    if (cancelled) {
                        return;
                    }

                    setSectionAutosaveState(saved ? 'saved' : 'error');
                })
                .catch(() => {
                    if (!cancelled) {
                        setSectionAutosaveState('error');
                    }
                });
        }, 2000);

        return () => {
            cancelled = true;
            window.clearTimeout(timeout);
        };
    }, [
        plan,
        sectionBody,
        sectionTitle,
        selectedRequirement,
        selectedSection,
        urls.sectionStore,
    ]);

    useEffect(() => {
        updatePlanWorkspaceDraft<BudgetFormState>(workspaceKey, (draft) => ({
            ...draft,
            budgetForm,
        }));
    }, [budgetForm, workspaceKey]);

    useEffect(() => {
        if (
            !plan ||
            selectedRequirement?.type !== 'budget' ||
            !budgetAutosaveUnlocked
        ) {
            return;
        }

        if (!budgetAutosaveReadyRef.current) {
            budgetAutosaveReadyRef.current = true;

            return;
        }

        let cancelled = false;
        const timeout = window.setTimeout(() => {
            setBudgetAutosaveState('saving');

            void postBudgetAutosave(
                urls.budgetUpdate,
                cleanBudgetForm(budgetForm),
            )
                .then((saved) => {
                    if (cancelled) {
                        return;
                    }

                    setBudgetAutosaveState(saved ? 'saved' : 'error');
                })
                .catch(() => {
                    if (!cancelled) {
                        setBudgetAutosaveState('error');
                    }
                });
        }, 2500);

        return () => {
            cancelled = true;
            window.clearTimeout(timeout);
        };
    }, [
        budgetAutosaveUnlocked,
        budgetForm,
        plan,
        selectedRequirement?.type,
        urls.budgetUpdate,
    ]);

    useEffect(() => {
        // Keep the idea form aligned with the latest submitted validation.
        ideaForm.setData(ideaValidationToForm(ideaValidation));
        ideaForm.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        ideaValidation?.id,
        ideaValidation?.problem,
        ideaValidation?.target_customer,
        ideaValidation?.solution,
        ideaValidation?.value_proposition,
        ideaValidation?.demand_signal,
        ideaValidation?.revenue_model,
    ]);

    const validateIdeaForm = () => {
        let valid = true;
        ideaForm.clearErrors();

        for (const field of ideaFields) {
            const value = ideaForm.data[field.key].trim();

            if (value.length === 0) {
                ideaForm.setError(field.key, `${field.label} is required.`);
                valid = false;
            } else if (value.length < field.minimum) {
                ideaForm.setError(
                    field.key,
                    `${field.label} must be at least ${field.minimum} characters.`,
                );
                valid = false;
            }
        }

        return valid;
    };

    const submitIdea = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!validateIdeaForm()) {
            return;
        }

        ideaForm.post(urls.ideaValidation, {
            preserveScroll: true,
            onSuccess: () => {
                if (ideaValidationApproved) {
                    setShowValidatedIdeaForm(false);
                }
            },
        });
    };

    const recallIdeaForRevision = () => {
        setRecallingIdea(true);
        router.post(
            urls.recallIdeaValidation,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRecallingIdea(false),
            },
        );
    };

    const restoreIdeaVersion = (version: IdeaValidationVersion) => {
        if (
            !window.confirm(
                `Restore version ${version.revision_number} as a new idea validation revision? Your advisor will review the new revision.`,
            )
        ) {
            return;
        }

        setRestoringIdeaVersionId(version.id);
        router.post(
            version.restore_url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setRestoringIdeaVersionId(null),
            },
        );
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

    const requestGamificationDisablement = () => {
        router.post(
            gamification.disable_request_url,
            {},
            { preserveScroll: true },
        );
    };

    const assistRequirement = async () => {
        if (!selectedRequirement || !plan) {
            return;
        }

        setAssistingSection(true);
        setSectionError(null);
        setAssistantNotice(null);

        try {
            const response = await fetch(urls.assistRequirement, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    phase_key: selectedRequirement.phase_key,
                    requirement_key: selectedRequirement.key,
                    body: sectionBody,
                }),
            });

            if (!response.ok) {
                setSectionError(
                    'AI assist could not prepare this requirement yet.',
                );

                return;
            }

            const payload = (await response.json()) as {
                title?: string;
                draft?: string;
                summary?: string;
                checklist?: string[];
            };
            const draft = (payload.draft ?? '').trim();

            if (payload.title && !sectionTitle.trim()) {
                setSectionTitle(payload.title);
            }

            if (draft) {
                setSectionBody((current) => {
                    const existing = current.trim();

                    return existing
                        ? `${existing}\n\nAI draft to review:\n${draft}`
                        : draft;
                });
            }

            const checklist = (payload.checklist ?? [])
                .filter((item) => item.trim() !== '')
                .map((item) => `- ${item}`);
            const gamificationHint =
                gamification.enabled && selectedRequirement
                    ? `Save this one section to move plan progress to ${selectedCompletionPercent}% and keep the journey moving.`
                    : null;
            setAssistantNotice(
                [payload.summary, ...checklist, gamificationHint]
                    .filter(Boolean)
                    .join('\n'),
            );
        } catch {
            setSectionError(
                'AI assist could not prepare this requirement yet.',
            );
        } finally {
            setAssistingSection(false);
        }
    };

    const generateExecutiveSummary = () => {
        if (!plan || generatingExecutiveSummary) {
            return;
        }

        setGeneratingExecutiveSummary(true);
        setSectionError(null);
        setAssistantNotice(null);

        router.post(
            urls.executiveSummary,
            {},
            {
                preserveScroll: true,
                onFinish: () => setGeneratingExecutiveSummary(false),
            },
        );
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

    const saveBudget = () => {
        if (!plan) {
            return;
        }

        setSavingBudget(true);
        router.post(urls.budgetUpdate, cleanBudgetForm(budgetForm), {
            preserveScroll: true,
            onFinish: () => setSavingBudget(false),
        });
    };

    const acknowledgeBudgetFlag = (key: string) => {
        router.post(
            urls.budgetFlagAcknowledge,
            { key },
            { preserveScroll: true },
        );
    };

    const dismissBudgetAdvisorNudge = () => {
        router.post(
            urls.budgetAdvisorNudgeDismiss,
            {},
            { preserveScroll: true },
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
                            {profile.name} /{' '}
                            {displayStageLabel(
                                profile.stage,
                                profile.stage_label,
                            )}
                        </div>
                        {includesPlanBudget ? (
                            <form
                                className="mt-3 flex max-w-xl flex-col gap-2 sm:flex-row sm:items-end"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    companyNameForm.post(
                                        urls.companyNameUpdate,
                                        {
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                            >
                                <label className="grid flex-1 gap-1 text-xs font-medium text-muted-foreground">
                                    Company / proposed company name
                                    <input
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                                        value={
                                            companyNameForm.data.company_name
                                        }
                                        onChange={(event) =>
                                            companyNameForm.setData(
                                                'company_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="e.g. Harbour Studio Limited"
                                    />
                                </label>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={companyNameForm.processing}
                                >
                                    Save name
                                </Button>
                            </form>
                        ) : null}
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

                {plan && !plan.external_issue_readiness.external_issue_ready ? (
                    <section className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-950">
                        <div className="flex items-start gap-3">
                            <AlertTriangle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <div>
                                <div className="font-medium">
                                    {plan.external_issue_readiness.label}
                                </div>
                                <p className="mt-1 text-red-900">
                                    Evidence coverage:{' '}
                                    {
                                        plan.external_issue_readiness
                                            .evidence_supported_responses
                                    }
                                    /
                                    {
                                        plan.external_issue_readiness
                                            .completed_responses
                                    }{' '}
                                    completed responses.
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5 text-red-900">
                                    {plan.external_issue_readiness.reasons.map(
                                        (reason) => (
                                            <li key={reason}>{reason}</li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        </div>
                    </section>
                ) : null}

                <TabList activeTab={activeTab} onChange={setActiveTab} />

                {gamification.enabled ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Trophy
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    <h2 className="text-sm font-medium">
                                        Gamification enabled
                                    </h2>
                                    <Badge
                                        variant={
                                            gamification.disable_request_requested
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {gamification.disable_request_requested
                                            ? 'Disablement requested'
                                            : 'Active'}
                                    </Badge>
                                </div>
                                <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>
                                        {journeyLevelLabel(
                                            gamification.current_level,
                                        )}
                                    </span>
                                    <span>
                                        Plan{' '}
                                        {gamification.plan_completion
                                            ?.percent ?? 0}
                                        %
                                    </span>
                                    <span>
                                        Journey points{' '}
                                        {gamification.points?.total ?? 0}
                                    </span>
                                    <span>
                                        Streak{' '}
                                        {gamification.current_streak ?? 0} days
                                    </span>
                                    {(gamification.new_badge_count ?? 0) > 0 ? (
                                        <span>
                                            {gamification.new_badge_count} new
                                            badges
                                        </span>
                                    ) : null}
                                </div>
                            </div>
                            {gamification.disable_request_requested &&
                            gamification.disable_request_thread_url ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={
                                            gamification.disable_request_thread_url
                                        }
                                    >
                                        <MessageSquare
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Open request
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={requestGamificationDisablement}
                                >
                                    <MessageSquare
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Request disablement
                                </Button>
                            )}
                        </div>
                    </section>
                ) : null}

                {activeTab === 'actions' ? (
                    <div className="space-y-6">
                        <section className="space-y-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Priority actions
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {packageAccess.package_scope_label}
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <ActionPanel
                                    icon={Bot}
                                    title="Idea validation"
                                    value={
                                        !includesIdeaValidation
                                            ? 'Not included'
                                            : ideaValidation
                                              ? planBuilderUnlocked
                                                  ? 'Advisor approved'
                                                  : ideaChangesRequested
                                                    ? 'Changes requested'
                                                    : ideaValidationRecalled
                                                      ? 'Ready to revise'
                                                      : 'Awaiting advisor gate'
                                              : 'Not submitted'
                                    }
                                    explanation="Idea validation captures the customer problem, solution, demand, and revenue logic before the plan builder opens."
                                >
                                    {!includesIdeaValidation ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : !ideaValidation ? (
                                        <Button asChild size="sm">
                                            <a href="#idea-validation">
                                                Start idea validation
                                            </a>
                                        </Button>
                                    ) : planBuilderUnlocked ? (
                                        <Badge variant="secondary">
                                            Builder unlocked
                                        </Badge>
                                    ) : ideaChangesRequested ? (
                                        <Badge variant="outline">
                                            Changes requested
                                        </Badge>
                                    ) : ideaValidationRecalled ? (
                                        <Badge variant="outline">
                                            Ready to revise
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
                                        !includesPlanBudget
                                            ? 'Not included'
                                            : plan
                                              ? plan.requirements_complete
                                                  ? 'Complete'
                                                  : `${plan.missing_requirements.length} gaps`
                                              : planBuilderUnlocked
                                                ? 'Not started'
                                                : 'Locked'
                                    }
                                    explanation="Plan completion is based on all required business plan sections, not merely one section per phase."
                                >
                                    {!includesPlanBudget ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : plan ? (
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
                                            disabled={!planBuilderUnlocked}
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
                                        !includesPlanBudget
                                            ? 'Not included'
                                            : plan?.latest_assessment
                                              ? `${formatLabel(plan.latest_assessment.overall_grade)}`
                                              : 'Pending'
                                    }
                                    explanation="Assessment appears once your advisor scores the submitted plan and finalises feedback."
                                >
                                    {!includesPlanBudget ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : plan?.latest_assessment ? (
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
                                        !includesPlanBudget
                                            ? 'Not included'
                                            : advisoryRequest.requested
                                              ? 'Requested'
                                              : advisoryRequest.available
                                                ? 'Available'
                                                : 'Locked'
                                    }
                                    explanation="Request advisory once the plan has been assessed as advisory ready. This asks your advisor to convert the plan into a standard advisory engagement."
                                >
                                    {!includesPlanBudget ? (
                                        <Badge variant="outline">
                                            Not in package
                                        </Badge>
                                    ) : advisoryRequest.requested &&
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

                        <section className="rounded-md border bg-background p-4">
                            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr] lg:items-center">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Trophy
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <h2 className="text-sm font-medium">
                                            {gamification.enabled
                                                ? 'Next small win'
                                                : 'Next step'}
                                        </h2>
                                        <Badge variant="outline">
                                            {nextSmallWin.badge}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 text-sm font-medium">
                                        {nextSmallWin.title}
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {nextSmallWin.body}
                                    </p>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        AI assist can help draft or simplify
                                        wording; your advisor still reviews the
                                        evidence and agrees the next step with
                                        you.
                                    </p>
                                    {nextSmallWin.action ===
                                    'Start idea validation' ? (
                                        <div className="mt-3">
                                            <Button asChild size="sm">
                                                <a href="#idea-validation">
                                                    Start idea validation
                                                </a>
                                            </Button>
                                        </div>
                                    ) : null}
                                    {nextSmallWin.action ===
                                    'Revise idea validation' ? (
                                        <div className="mt-3">
                                            <Button asChild size="sm">
                                                <a href="#idea-validation">
                                                    Revise idea validation
                                                </a>
                                            </Button>
                                        </div>
                                    ) : null}
                                    {nextSmallWin.action === 'Start plan' ? (
                                        <div className="mt-3">
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={startPlan}
                                            >
                                                Start plan
                                            </Button>
                                        </div>
                                    ) : null}
                                    {gamification.enabled &&
                                    gamification.next_quest ? (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Next quest:{' '}
                                            {gamification.next_quest.label} for{' '}
                                            {gamification.next_quest.points}{' '}
                                            points.
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Plan progress</span>
                                        <span>{planCompletion.percent}%</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full bg-emerald-500 transition-all"
                                            style={{
                                                width: `${Math.min(100, Math.max(0, planCompletion.percent))}%`,
                                            }}
                                        />
                                    </div>
                                    {gamification.enabled ? (
                                        <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                            <span>
                                                Streak{' '}
                                                {gamification.current_streak ??
                                                    0}{' '}
                                                days
                                            </span>
                                            {(gamification.new_badge_count ??
                                                0) > 0 ? (
                                                <span>
                                                    {
                                                        gamification.new_badge_count
                                                    }{' '}
                                                    new badge
                                                    {gamification.new_badge_count ===
                                                    1
                                                        ? ''
                                                        : 's'}
                                                </span>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </section>

                        <PlainLanguageGuide />

                        {includesIdeaValidation ? (
                            <section
                                id="idea-validation"
                                className="space-y-4 rounded-md border bg-background p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline">
                                                Step 1
                                            </Badge>
                                            {ideaValidation ? (
                                                <Badge variant="outline">
                                                    Version{' '}
                                                    {
                                                        ideaValidation.revision_number
                                                    }
                                                </Badge>
                                            ) : null}
                                            <h2 className="text-sm font-medium">
                                                Idea validation
                                            </h2>
                                        </div>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Capture the customer problem,
                                            solution, demand, and revenue logic
                                            before detailed plan work starts.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {ideaValidationApproved ? (
                                            <Badge variant="secondary">
                                                Gate passed
                                            </Badge>
                                        ) : ideaChangesRequested ? (
                                            <Badge variant="outline">
                                                Changes requested
                                            </Badge>
                                        ) : ideaValidationRecalled ? (
                                            <Badge variant="outline">
                                                Ready to revise
                                            </Badge>
                                        ) : ideaValidation ? (
                                            <Badge variant="outline">
                                                Advisor review
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Not submitted
                                            </Badge>
                                        )}
                                        {ideaValidationApproved ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setShowValidatedIdeaForm(
                                                        (current) => !current,
                                                    )
                                                }
                                            >
                                                {showIdeaValidationEditor ? (
                                                    <ChevronUp
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <ChevronDown
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {showIdeaValidationEditor
                                                    ? 'Roll up'
                                                    : 'Review details'}
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>

                                {ideaChangesRequested &&
                                ideaValidation?.change_request_note ? (
                                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
                                        <div className="font-medium">
                                            Advisor feedback
                                        </div>
                                        <p className="mt-1">
                                            {ideaValidation.change_request_note}
                                        </p>
                                    </div>
                                ) : null}

                                {ideaValidationRecalled ? (
                                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                                        This validation has been removed from
                                        advisor review. Update the answers
                                        below, then resubmit it when ready.
                                    </div>
                                ) : null}

                                {ideaValidation?.restored_from_revision_number ? (
                                    <div className="text-xs text-muted-foreground">
                                        Restored from version{' '}
                                        {
                                            ideaValidation.restored_from_revision_number
                                        }
                                    </div>
                                ) : null}

                                {ideaValidationApproved &&
                                !showIdeaValidationEditor ? (
                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        {ideaValidationSummary.map((item) => (
                                            <div
                                                key={item.label}
                                                className="rounded-md border bg-muted/20 p-3"
                                            >
                                                <div className="text-xs font-medium text-muted-foreground">
                                                    {item.label}
                                                </div>
                                                <p className="mt-1 line-clamp-2 text-sm">
                                                    {item.value || '-'}
                                                </p>
                                            </div>
                                        ))}
                                        {ideaValidation?.advisor_gate_note ? (
                                            <div className="rounded-md border bg-muted/20 p-3 md:col-span-2 xl:col-span-3">
                                                <div className="text-xs font-medium text-muted-foreground">
                                                    Advisor note
                                                </div>
                                                <p className="mt-1 text-sm">
                                                    {
                                                        ideaValidation.advisor_gate_note
                                                    }
                                                </p>
                                            </div>
                                        ) : null}
                                    </div>
                                ) : showIdeaValidationEditor ? (
                                    <form
                                        className="grid gap-3 lg:grid-cols-2"
                                        onSubmit={submitIdea}
                                    >
                                        {ideaChangesRequested ||
                                        ideaValidationRecalled ? (
                                            <div className="lg:col-span-2">
                                                <IdeaValidationSnapshot
                                                    fields={
                                                        ideaValidationSummary
                                                    }
                                                    revisionNumber={
                                                        ideaValidation?.revision_number ??
                                                        null
                                                    }
                                                    submittedAt={
                                                        ideaValidation?.evaluated_at ??
                                                        null
                                                    }
                                                />
                                            </div>
                                        ) : null}
                                        {ideaFields.map((field) => {
                                            const fieldValue =
                                                ideaForm.data[field.key];
                                            const fieldId = `idea-validation-${field.key}`;

                                            return (
                                                <div
                                                    key={field.key}
                                                    className="grid gap-1 text-sm"
                                                >
                                                    <span className="flex items-center justify-between gap-3">
                                                        <label
                                                            htmlFor={fieldId}
                                                        >
                                                            {field.label}
                                                        </label>
                                                        <span className="shrink-0 text-xs font-normal text-muted-foreground tabular-nums">
                                                            {fieldValue.length}
                                                            {' / '}
                                                            {
                                                                IDEA_VALIDATION_FIELD_MAX_LENGTH
                                                            }
                                                        </span>
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {field.plain}
                                                    </span>
                                                    <FormattedTextarea
                                                        id={fieldId}
                                                        value={fieldValue}
                                                        onChange={(value) =>
                                                            ideaForm.setData(
                                                                field.key,
                                                                value,
                                                            )
                                                        }
                                                        maxLength={
                                                            IDEA_VALIDATION_FIELD_MAX_LENGTH
                                                        }
                                                        rows={4}
                                                        placeholder={
                                                            field.placeholder
                                                        }
                                                        ariaLabel={field.label}
                                                    />
                                                    <InputError
                                                        message={
                                                            ideaForm.errors[
                                                                field.key
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            );
                                        })}
                                        <div className="lg:col-span-2">
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={ideaForm.processing}
                                            >
                                                {ideaForm.processing
                                                    ? 'Submitting...'
                                                    : ideaChangesRequested ||
                                                        ideaValidationRecalled
                                                      ? 'Resubmit idea validation'
                                                      : ideaValidation
                                                        ? 'Update idea validation'
                                                        : 'Submit idea validation'}
                                            </Button>
                                            {ideaForm.recentlySuccessful ? (
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Idea validation submitted
                                                    for advisor review.
                                                </p>
                                            ) : null}
                                        </div>
                                    </form>
                                ) : ideaUnderAdvisorReview ? (
                                    <div className="space-y-3">
                                        <div className="rounded-md border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                            Your idea validation is with your
                                            advisor. They will either approve
                                            the builder gate or request changes.
                                        </div>
                                        <IdeaValidationSnapshot
                                            fields={ideaValidationSummary}
                                            revisionNumber={
                                                ideaValidation?.revision_number ??
                                                null
                                            }
                                            submittedAt={
                                                ideaValidation?.evaluated_at ??
                                                null
                                            }
                                        />
                                        <div className="flex flex-wrap items-center gap-3">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={recallingIdea}
                                                onClick={recallIdeaForRevision}
                                            >
                                                <Pencil
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                {recallingIdea
                                                    ? 'Recalling...'
                                                    : 'Recall for revision'}
                                            </Button>
                                            <p className="text-xs text-muted-foreground">
                                                Removes this submission from
                                                advisor review while you update
                                                it.
                                            </p>
                                        </div>
                                    </div>
                                ) : null}

                                {ideaValidationVersions.length > 1 ? (
                                    <IdeaValidationHistory
                                        versions={ideaValidationVersions}
                                        restoringVersionId={
                                            restoringIdeaVersionId
                                        }
                                        onRestore={restoreIdeaVersion}
                                    />
                                ) : null}
                            </section>
                        ) : null}

                        <section
                            id="business-plan-requirements"
                            className="space-y-4 rounded-md border bg-background p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-medium">
                                        Plan requirements
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {includesPlanBudget
                                            ? 'Complete every required section and attach supporting evidence where it helps the advisor rely on the plan.'
                                            : 'Business plan and budget are not included in this package.'}
                                    </p>
                                </div>
                                {!plan && includesPlanBudget ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={startPlan}
                                        disabled={!planBuilderUnlocked}
                                    >
                                        Start plan
                                    </Button>
                                ) : null}
                            </div>

                            {!includesPlanBudget ? (
                                <div className="rounded-md border border-dashed bg-muted/20 p-4">
                                    <h3 className="text-sm font-medium">
                                        Not included in this package
                                    </h3>
                                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                                        This package covers idea validation
                                        only. Your advisor can invite you to
                                        Business Plan + Budget or the bundle
                                        package if you decide to progress.
                                    </p>
                                </div>
                            ) : hasPlan ? (
                                <div className="grid items-start gap-6 xl:grid-cols-[minmax(15rem,0.58fr)_minmax(0,1.42fr)]">
                                    <div className="space-y-3 xl:sticky xl:top-24 xl:self-start">
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
                                                            onClick={() => {
                                                                rememberWorkspacePosition();
                                                                setSelectedKey(
                                                                    requirementId(
                                                                        requirement,
                                                                    ),
                                                                );
                                                            }}
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

                                    <div className="min-w-0 space-y-4 rounded-md border p-4 xl:self-start">
                                        {selectedRequirement ? (
                                            selectedRequirement.type ===
                                                'budget' && plan ? (
                                                <BudgetEditor
                                                    budget={plan.budget}
                                                    form={budgetForm}
                                                    plan={plan}
                                                    ideaValidation={
                                                        ideaValidation
                                                    }
                                                    gamification={gamification}
                                                    saving={savingBudget}
                                                    autosaveState={
                                                        budgetAutosaveState
                                                    }
                                                    onFormChange={setBudgetForm}
                                                    onSave={saveBudget}
                                                    onAcknowledgeFlag={
                                                        acknowledgeBudgetFlag
                                                    }
                                                    onDismissAdvisorNudge={
                                                        dismissBudgetAdvisorNudge
                                                    }
                                                />
                                            ) : (
                                                <>
                                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <h3 className="text-sm font-medium">
                                                                Complete
                                                                requirement
                                                            </h3>
                                                            <p className="mt-1 text-sm text-muted-foreground">
                                                                {
                                                                    selectedRequirement.description
                                                                }
                                                            </p>
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            {selectedRequirement.key ===
                                                            'executive-summary' ? (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={
                                                                        generateExecutiveSummary
                                                                    }
                                                                    disabled={
                                                                        !plan ||
                                                                        generatingExecutiveSummary ||
                                                                        !plan
                                                                            ?.executive_summary
                                                                            .can_generate
                                                                    }
                                                                >
                                                                    <RefreshCw
                                                                        className={cn(
                                                                            'size-4',
                                                                            generatingExecutiveSummary &&
                                                                                'animate-spin',
                                                                        )}
                                                                        aria-hidden="true"
                                                                    />
                                                                    {generatingExecutiveSummary
                                                                        ? 'Generating'
                                                                        : plan
                                                                                ?.executive_summary
                                                                                .present
                                                                          ? 'Refresh summary'
                                                                          : 'Generate summary'}
                                                                </Button>
                                                            ) : (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        void assistRequirement()
                                                                    }
                                                                    disabled={
                                                                        !plan ||
                                                                        assistingSection
                                                                    }
                                                                >
                                                                    <Bot
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                    {assistingSection
                                                                        ? 'Assisting'
                                                                        : 'AI assist'}
                                                                </Button>
                                                            )}
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
                                                                    Score draft
                                                                </Button>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                    {selectedRequirement.key ===
                                                        'executive-summary' &&
                                                    plan?.executive_summary ? (
                                                        <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/20 p-3 text-sm">
                                                            <Badge
                                                                variant={
                                                                    plan
                                                                        .executive_summary
                                                                        .stale
                                                                        ? 'destructive'
                                                                        : plan
                                                                                .executive_summary
                                                                                .present
                                                                          ? 'secondary'
                                                                          : 'outline'
                                                                }
                                                            >
                                                                {
                                                                    plan
                                                                        .executive_summary
                                                                        .status_label
                                                                }
                                                            </Badge>
                                                            {plan
                                                                .executive_summary
                                                                .generated_at ? (
                                                                <span className="text-muted-foreground">
                                                                    Generated{' '}
                                                                    {formatDate(
                                                                        plan
                                                                            .executive_summary
                                                                            .generated_at,
                                                                    )}
                                                                </span>
                                                            ) : null}
                                                            {plan
                                                                .executive_summary
                                                                .readiness_reason ? (
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        plan
                                                                            .executive_summary
                                                                            .readiness_reason
                                                                    }
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                    ) : null}
                                                    <label className="grid gap-1 text-sm">
                                                        <span>
                                                            Section title
                                                        </span>
                                                        <input
                                                            value={sectionTitle}
                                                            onChange={(event) =>
                                                                setSectionTitle(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                                        />
                                                    </label>
                                                    <div className="grid gap-1 text-sm">
                                                        <span className="flex items-center justify-between gap-3">
                                                            <label htmlFor="entrepreneur-plan-section-body">
                                                                Plan detail
                                                            </label>
                                                            <span className="shrink-0 text-xs font-normal text-muted-foreground tabular-nums">
                                                                {sectionAutosaveStateLabel(
                                                                    sectionAutosaveState,
                                                                )}
                                                                {sectionAutosaveState !==
                                                                'idle'
                                                                    ? ' | '
                                                                    : ''}
                                                                {
                                                                    sectionBody.length
                                                                }
                                                                {' / '}
                                                                {
                                                                    PLAN_SECTION_BODY_MAX_LENGTH
                                                                }
                                                            </span>
                                                        </span>
                                                        <FormattedTextarea
                                                            id="entrepreneur-plan-section-body"
                                                            value={sectionBody}
                                                            onChange={
                                                                setSectionBody
                                                            }
                                                            rows={8}
                                                            maxLength={
                                                                PLAN_SECTION_BODY_MAX_LENGTH
                                                            }
                                                            placeholder="Add the context, evidence, assumptions, decisions, and risks your advisor should rely on."
                                                        />
                                                    </div>
                                                    <FileDropzone
                                                        key={supportingKey}
                                                        id="entrepreneur-plan-support"
                                                        files={
                                                            supportingFile
                                                                ? [
                                                                      supportingFile,
                                                                  ]
                                                                : []
                                                        }
                                                        label="Attach supporting document"
                                                        onFilesChange={(
                                                            files,
                                                        ) =>
                                                            setSupportingFile(
                                                                files[0] ??
                                                                    null,
                                                            )
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            sectionError ??
                                                            undefined
                                                        }
                                                    />
                                                    {assistantNotice ? (
                                                        <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                                            <div className="font-medium">
                                                                AI assistant
                                                            </div>
                                                            <p className="mt-1 whitespace-pre-line text-muted-foreground">
                                                                {
                                                                    assistantNotice
                                                                }
                                                            </p>
                                                        </div>
                                                    ) : null}
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
                                                            !plan ||
                                                            savingSection
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
                                            )
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Select a requirement to start
                                                completing the business plan.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-md border border-dashed bg-muted/20 p-4">
                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <h3 className="text-sm font-medium">
                                                {planBuilderUnlocked
                                                    ? 'Plan sections are ready'
                                                    : ideaChangesRequested
                                                      ? 'Plan sections are waiting for your revised idea'
                                                      : hasIdeaValidation
                                                        ? 'Plan sections are waiting for advisor review'
                                                        : 'Plan sections unlock after idea validation'}
                                            </h3>
                                            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                                                {planBuilderUnlocked
                                                    ? 'Start the business plan to open the section checklist and AI assist.'
                                                    : ideaChangesRequested
                                                      ? 'Your advisor requested changes to the idea validation. Update and resubmit it before these sections open.'
                                                      : hasIdeaValidation
                                                        ? 'Your idea validation has been submitted. Your advisor needs to approve it before these sections open.'
                                                        : 'Complete idea validation first so your advisor can confirm the concept before detailed plan work starts.'}
                                            </p>
                                        </div>
                                        {planBuilderUnlocked ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={startPlan}
                                            >
                                                Start plan
                                            </Button>
                                        ) : !hasIdeaValidation ||
                                          ideaChangesRequested ||
                                          ideaValidationRecalled ? (
                                            <Button asChild size="sm">
                                                <a href="#idea-validation">
                                                    {ideaChangesRequested ||
                                                    ideaValidationRecalled
                                                        ? 'Revise idea validation'
                                                        : 'Start idea validation'}
                                                </a>
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            )}
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
                                                    href={
                                                        report.view_url ??
                                                        report.download_url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    View
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
                                    value={displayStageLabel(
                                        profile.stage,
                                        profile.stage_label,
                                    )}
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

EntrepreneurPlan.layout = {
    breadcrumbs: [
        {
            title: 'Business Plan',
            href: '/portal/entrepreneur/plan',
        },
    ],
};
