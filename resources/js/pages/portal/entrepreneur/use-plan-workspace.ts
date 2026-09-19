import { router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useAutoSavedForm } from '@/hooks/use-auto-saved-form';
import { usePersistedWorkspaceDraft } from '@/hooks/use-persisted-workspace-draft';
import {
    BUDGET_ASSUMPTIONS_REQUIREMENT_KEY,
    BUDGET_UNLOCK_REQUIREMENT_KEY,
    budgetPlanSource,
    budgetToForm,
    cleanBudgetForm,
    findSection,
    ideaValidationToForm,
    planWorkspaceKey,
    requirementId,
} from './plan-budget';
import { ideaFields } from './plan-dashboard-panels';
import type { IdeaValidationVersion, Tab } from './plan-dashboard-panels';
import type {
    BudgetFormState,
    IdeaValidationForm,
    PlanSectionPayload,
    Props,
} from './plan-types';
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
import type {
    AutosaveState,
    PlanWorkspaceDraft,
    SectionAutosaveResult,
} from './plan-workspace-draft';
import { syncPlanSupportingDocuments } from './plan-workspace-supporting-documents';

export function usePlanWorkspace({
    profile,
    packageAccess,
    ideaValidation,
    ideaValidationVersions,
    plan,
    planTemplate,
    reports,
    advisoryRequest,
    gamification,
    journey,
    urls,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('actions');
    const companyNameForm = useForm({
        company_name: profile.company_name ?? '',
    });
    const companyNameAutosaveState = useAutoSavedForm({
        url: urls.companyNameUpdate,
        data: companyNameForm.data,
        enabled: packageAccess.includes_plan_budget,
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
    // A local selected requirement describes where a browser last happened to
    // be, not the next journey step. Always begin at the first real gap so a
    // new BP&B workspace cannot appear to start at an unrelated risk register.
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
    const ideaDraftState = usePersistedWorkspaceDraft({
        url: urls.ideaValidationDraft,
        data: ideaForm.data,
        hydrate: (payload) =>
            ideaForm.setData({ ...ideaForm.data, ...payload }),
        enabled: includesIdeaValidation && showIdeaValidationEditor,
    });
    const ideaValidationSummary = ideaFields.map((field) => ({
        label: field.label,
        value: ideaValidation?.[field.key as keyof IdeaValidationForm] ?? '-',
    }));
    const hasPlan = Boolean(plan);
    const planIsComplete =
        includesPlanBudget &&
        planCompletion.total > 0 &&
        planCompletion.completed === planCompletion.total;
    const nextSmallWin =
        includesIdeaValidation && !hasIdeaValidation
            ? {
                  badge: 'Step 1',
                  title: 'Complete idea validation',
                  body: 'Complete the idea validation form below. Your advisor reviews it before the plan sections open.',
                  action: null,
              }
            : includesIdeaValidation && ideaChangesRequested
              ? {
                    badge: 'Step 1',
                    title: 'Revise idea validation',
                    body: 'Your advisor has requested changes. Update the idea validation and resubmit it for review.',
                    action: null,
                }
              : includesIdeaValidation && ideaValidationRecalled
                ? {
                      badge: 'Step 1',
                      title: 'Revise idea validation',
                      body: 'Your validation has been recalled from advisor review. Update it, then resubmit it for review.',
                      action: null,
                  }
                : includesIdeaValidation && !planBuilderUnlocked
                  ? {
                        badge: 'Step 2',
                        title: 'Advisor review',
                        body: 'Idea validation is submitted. Your advisor needs to approve it before the plan sections open.',
                        action: null,
                    }
                  : includesIdeaValidation &&
                      ideaValidationApproved &&
                      !includesPlanBudget
                    ? {
                          badge: 'Step 2',
                          title: 'Business Plan & Budget',
                          body: 'Your idea validation is complete. Continue to Business Plan & Budget when you are ready to turn the validated idea into a practical plan and financial forecast.',
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
                      : planIsComplete
                        ? {
                              badge: journey.assessment.finalised
                                  ? 'Assessment ready'
                                  : journey.assessment.exists
                                    ? 'Advisor review'
                                    : 'Plan complete',
                              title: journey.assessment.finalised
                                  ? 'Review Business Plan & Budget'
                                  : journey.assessment.exists
                                    ? 'Business Plan & Budget is with your advisor'
                                    : 'Review Business Plan & Budget',
                              body: journey.assessment.finalised
                                  ? advisoryRequest.available
                                      ? 'Your assessment is complete. Review the feedback and Budget Pack, then request advisory support when you are ready.'
                                      : 'Your assessment is complete. Review the feedback and Budget Pack with your advisor to agree the next step.'
                                  : journey.assessment.exists
                                    ? 'All plan sections are complete and your submitted plan is with your advisor for assessment.'
                                    : 'All plan sections are complete. Submit the plan for advisor review to receive feedback and agree the next step.',
                              action: null,
                          }
                        : includesPlanBudget
                          ? {
                                badge: `${planCompletion.completed}/${planCompletion.total} sections`,
                                title:
                                    planCompletion.completed === 0
                                        ? 'Build your business foundation'
                                        : 'Next plan section',
                                body: selectedRequirement
                                    ? selectedRequirement.complete
                                        ? 'This section is already complete. Choose the next needed section when you are ready.'
                                        : planCompletion.completed === 0
                                          ? `Your validated idea is available in the Idea Validation tab. Expand it with the operating detail for "${selectedRequirement.title}". Changes save automatically as you work.`
                                          : `Focus on "${selectedRequirement.title}" next. Changes save automatically and the progress indicator updates when the section is complete.`
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
                                    : 'Complete the idea validation form below to test the concept before investing in detailed plan work.',
                                action: null,
                            };
    const [sectionTitle, setSectionTitle] = useState('');
    const [sectionBody, setSectionBody] = useState('');
    const [supportingFile, setSupportingFile] = useState<File | null>(null);
    const [supportingDocumentIds, setSupportingDocumentIds] = useState<
        string[]
    >([]);
    const [supportingDocuments, setSupportingDocuments] = useState<
        PlanSectionPayload['attached_documents']
    >([]);
    const [supportingDocumentNotice, setSupportingDocumentNotice] = useState<
        string | null
    >(null);
    const [pendingSupportingDocument, setPendingSupportingDocument] = useState<
        PlanSectionPayload['attached_documents'][number] | null
    >(null);
    const [uploadingSupportingDocument, setUploadingSupportingDocument] =
        useState(false);
    const [supportingKey, setSupportingKey] = useState(0);
    const [sectionError, setSectionError] = useState<string | null>(null);
    const [assistingSection, setAssistingSection] = useState(false);
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
        setSupportingDocumentIds(section?.attached_document_ids ?? []);
        setSupportingDocuments(section?.attached_documents ?? []);
        setSupportingDocumentNotice(null);
        setPendingSupportingDocument(null);
        setUploadingSupportingDocument(false);
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
        // A cached draft is only safe when it was based on this exact server revision.
        // Otherwise it could overwrite a save from another tab or browser session.
        const serverForm = budgetToForm(plan?.budget);
        const localForm =
            readPlanWorkspaceDraft<BudgetFormState>(workspaceKey)?.budgetForm;

        /* eslint-disable-next-line react-hooks/set-state-in-effect */
        setBudgetForm(
            localForm?.revision === serverForm.revision
                ? localForm
                : serverForm,
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

    const refreshJourneyForSectionStatusChange = useCallback(
        (saved: SectionAutosaveResult | null) => {
            if (
                saved &&
                (saved.section.completeness_status === 'complete') !==
                    (selectedSection?.completeness_status === 'complete')
            ) {
                router.reload({
                    only: ['plan', 'gamification', 'journey'],
                });
            }
        },
        [selectedSection?.completeness_status],
    );

    const saveSectionDraft = useCallback(async () => {
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

        setSectionAutosaveState('saving');

        try {
            const saved = await postSectionAutosave(urls.sectionStore, {
                phase_key: selectedRequirement.phase_key,
                requirement_key: selectedRequirement.key,
                title: sectionTitle,
                body: sectionBody,
                attached_document_ids: supportingDocumentIds,
            });

            setSectionAutosaveState(saved ? 'saved' : 'error');
            refreshJourneyForSectionStatusChange(saved);
        } catch {
            setSectionAutosaveState('error');
        }
    }, [
        plan,
        sectionBody,
        sectionTitle,
        supportingDocumentIds,
        selectedRequirement,
        selectedSection,
        urls.sectionStore,
        refreshJourneyForSectionStatusChange,
    ]);

    useEffect(() => {
        if (!selectedRequirement || selectedRequirement.type === 'budget') {
            return;
        }

        const timeout = window.setTimeout(() => {
            void saveSectionDraft();
        }, 2000);

        return () => window.clearTimeout(timeout);
    }, [saveSectionDraft, selectedRequirement]);

    useEffect(() => {
        updatePlanWorkspaceDraft<BudgetFormState>(workspaceKey, (draft) => ({
            ...draft,
            budgetForm,
        }));
    }, [budgetForm, workspaceKey]);

    const saveBudgetDraft = useCallback(async () => {
        if (
            !plan ||
            selectedRequirement?.type !== 'budget' ||
            !budgetAutosaveUnlocked
        ) {
            return;
        }

        setBudgetAutosaveState('saving');

        try {
            const result = await postBudgetAutosave(
                urls.budgetUpdate,
                cleanBudgetForm(budgetForm),
            );

            if (result.revision !== null) {
                setBudgetForm((current) =>
                    result.revision !== null &&
                    result.revision > current.revision
                        ? { ...current, revision: result.revision }
                        : current,
                );
            }

            setBudgetAutosaveState(result.saved ? 'saved' : 'error');
        } catch {
            setBudgetAutosaveState('error');
        }
    }, [
        budgetAutosaveUnlocked,
        budgetForm,
        plan,
        selectedRequirement?.type,
        urls.budgetUpdate,
    ]);

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

        const timeout = window.setTimeout(() => {
            void saveBudgetDraft();
        }, 2500);

        return () => window.clearTimeout(timeout);
    }, [
        budgetAutosaveUnlocked,
        budgetForm,
        plan,
        saveBudgetDraft,
        selectedRequirement?.type,
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
                ideaDraftState.discardRecovery();

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
                    ? `Keep developing this section; changes save automatically and progress updates when the requirement is complete.`
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

    const syncSupportingDocuments = async (
        documentIds: string[],
        documents: PlanSectionPayload['attached_documents'],
        successNotice: string,
        failureNotice: string,
        pendingDocument:
            | PlanSectionPayload['attached_documents'][number]
            | null,
    ): Promise<boolean> =>
        syncPlanSupportingDocuments({
            selectedRequirement,
            url: urls.sectionStore,
            title: sectionTitle,
            body: sectionBody,
            documentIds,
            documents,
            successNotice,
            failureNotice,
            pendingDocument,
            onAutosaveState: setSectionAutosaveState,
            onDocumentIds: setSupportingDocumentIds,
            onDocuments: setSupportingDocuments,
            onPendingDocument: setPendingSupportingDocument,
            onNotice: setSupportingDocumentNotice,
            onError: setSectionError,
            onStatusChange: refreshJourneyForSectionStatusChange,
        });

    const attachSupportingDocument = (
        document: PlanSectionPayload['attached_documents'][number],
    ): Promise<boolean> => {
        const documentIds = [
            ...new Set([...supportingDocumentIds, document.id]),
        ];

        return syncSupportingDocuments(
            documentIds,
            [
                ...supportingDocuments.filter(({ id }) => id !== document.id),
                document,
            ],
            `“${document.original_filename}” uploaded and attached to ${selectedRequirement?.title}.`,
            `“${document.original_filename}” was uploaded, but could not yet be attached to this plan section. Retry attaching it.`,
            document,
        );
    };

    const removeSupportingDocument = (
        document: PlanSectionPayload['attached_documents'][number],
    ): Promise<boolean> => {
        const documentIds = supportingDocumentIds.filter(
            (documentId) => documentId !== document.id,
        );

        return syncSupportingDocuments(
            documentIds,
            supportingDocuments.filter(({ id }) => id !== document.id),
            `“${document.original_filename}” removed. You can now attach the correct document.`,
            `“${document.original_filename}” could not be removed. Try Remove again.`,
            null,
        );
    };

    const uploadSupportingDocument = async (
        selectedFile: File | null = supportingFile,
    ) => {
        if (!selectedRequirement || !selectedFile) {
            return;
        }

        setUploadingSupportingDocument(true);
        setSectionError(null);

        try {
            const formData = new FormData();
            formData.append('file', selectedFile);
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
                setSectionError('Supporting document upload failed.');

                return;
            }

            const payload = (await response.json()) as {
                document?: PlanSectionPayload['attached_documents'][number];
            };
            const document = payload.document;

            if (!document?.id) {
                setSectionError(
                    'The uploaded supporting document could not be linked.',
                );

                return;
            }

            setSupportingFile(null);
            setSupportingKey((key) => key + 1);
            await attachSupportingDocument(document);
        } catch {
            setSectionError(
                'Supporting document upload could not reach the server.',
            );
        } finally {
            setUploadingSupportingDocument(false);
        }
    };

    const retrySupportingDocumentAttachment = async () => {
        if (!pendingSupportingDocument) {
            return;
        }

        setSectionError(null);
        await attachSupportingDocument(pendingSupportingDocument);
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

    return {
        profile,
        packageAccess,
        ideaValidation,
        ideaValidationVersions,
        plan,
        planTemplate,
        reports,
        advisoryRequest,
        gamification,
        journey,
        urls,
        activeTab,
        setActiveTab,
        companyNameForm,
        companyNameAutosaveState,
        ideaForm,
        ideaDraftState,
        showValidatedIdeaForm,
        setShowValidatedIdeaForm,
        recallingIdea,
        restoringIdeaVersionId,
        phases,
        requirements,
        selectedKey,
        setSelectedKey,
        selectedRequirement,
        selectedSection,
        budgetAutosaveUnlocked,
        completedRequirementCount,
        totalRequirementCount,
        planCompletion,
        selectedCompletionPercent,
        includesIdeaValidation,
        includesPlanBudget,
        directPlanAccess,
        hasIdeaValidation,
        hasPlan,
        planBuilderUnlocked,
        ideaValidationApproved,
        ideaChangesRequested,
        ideaValidationRecalled,
        ideaUnderAdvisorReview,
        showIdeaValidationEditor,
        ideaValidationSummary,
        nextSmallWin,
        sectionTitle,
        setSectionTitle,
        sectionBody,
        setSectionBody,
        supportingFile,
        setSupportingFile,
        supportingDocuments,
        supportingDocumentNotice,
        pendingSupportingDocument,
        uploadingSupportingDocument,
        uploadSupportingDocument,
        retrySupportingDocumentAttachment,
        removeSupportingDocument,
        supportingKey,
        sectionError,
        assistingSection,
        assistantNotice,
        budgetForm,
        setBudgetForm,
        savingBudget,
        sectionAutosaveState,
        budgetAutosaveState,
        retrySectionAutosave: () => void saveSectionDraft(),
        retryBudgetAutosave: () => void saveBudgetDraft(),
        submitIdea,
        recallIdeaForRevision,
        restoreIdeaVersion,
        rememberWorkspacePosition,
        startPlan,
        submitPlan,
        requestAdvisory,
        requestGamificationDisablement,
        assistRequirement,
        saveBudget,
        acknowledgeBudgetFlag,
        dismissBudgetAdvisorNudge,
    };
}

export type PlanWorkspace = ReturnType<typeof usePlanWorkspace>;
