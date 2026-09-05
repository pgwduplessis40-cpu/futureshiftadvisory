import { router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
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
    const [supportingDocumentIds, setSupportingDocumentIds] = useState<
        string[]
    >([]);
    const [uploadingSupportingDocument, setUploadingSupportingDocument] =
        useState(false);
    const [supportingKey, setSupportingKey] = useState(0);
    const [sectionError, setSectionError] = useState<string | null>(null);
    const [savingSection, setSavingSection] = useState(false);
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
                attached_document_ids: supportingDocumentIds,
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
        supportingDocumentIds,
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
                document?: { id?: string };
            };
            const documentId = payload.document?.id;

            if (!documentId) {
                setSectionError(
                    'The uploaded supporting document could not be linked.',
                );

                return;
            }

            const attachedDocumentIds = [
                ...new Set([...supportingDocumentIds, documentId]),
            ];
            setSupportingDocumentIds(attachedDocumentIds);
            setSupportingFile(null);
            setSupportingKey((key) => key + 1);
            setSectionAutosaveState('saving');

            const attached = await postSectionAutosave(urls.sectionStore, {
                phase_key: selectedRequirement.phase_key,
                requirement_key: selectedRequirement.key,
                title: sectionTitle,
                body: sectionBody,
                attached_document_ids: attachedDocumentIds,
            });

            setSectionAutosaveState(attached ? 'saved' : 'error');

            if (!attached) {
                setSectionError(
                    'Document uploaded, but could not yet be linked to this plan section. Retry the upload.',
                );
            }
        } catch {
            setSectionError(
                'Supporting document upload could not reach the server.',
            );
        } finally {
            setUploadingSupportingDocument(false);
        }
    };

    const saveSection = async () => {
        if (!selectedRequirement) {
            return;
        }

        setSavingSection(true);
        setSectionError(null);
        const attachedIds = [...supportingDocumentIds];

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
        urls,
        activeTab,
        setActiveTab,
        companyNameForm,
        ideaForm,
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
        uploadingSupportingDocument,
        uploadSupportingDocument,
        supportingKey,
        sectionError,
        savingSection,
        assistingSection,
        assistantNotice,
        budgetForm,
        setBudgetForm,
        savingBudget,
        sectionAutosaveState,
        budgetAutosaveState,
        submitIdea,
        recallIdeaForRevision,
        restoreIdeaVersion,
        rememberWorkspacePosition,
        startPlan,
        submitPlan,
        requestAdvisory,
        requestGamificationDisablement,
        assistRequirement,
        saveSection,
        saveBudget,
        acknowledgeBudgetFlag,
        dismissBudgetAdvisorNudge,
    };
}

export type PlanWorkspace = ReturnType<typeof usePlanWorkspace>;
