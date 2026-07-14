import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ClipboardList,
    FileText,
    Flag,
    Globe2,
    ShieldCheck,
    Upload,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ComponentType, FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import { ExplainedSectionHeader } from '@/components/explainer';
import type { Explanation } from '@/components/explainer';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { QuestionnaireRenderer } from '@/components/questionnaires/QuestionnaireRenderer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { queueQuestionnaireSubmission } from '@/lib/portal-offline';
import { cn } from '@/lib/utils';
import type { QuestionnaireAnswers } from '@/types/questionnaire';
import type {
    ClientPayload,
    Progress,
    Questionnaire,
    WizardState,
    WizardStep,
} from './types';

type WelcomeMessage = {
    has_message: boolean;
    html: string;
    version: number | null;
};

type Props = {
    client: ClientPayload;
    step: WizardStep;
    steps: WizardStep[];
    welcomeMessage: WelcomeMessage;
    state: WizardState;
    stepData: Record<string, unknown>;
    progress: Progress;
    questionnaire: Questionnaire;
    website: WebsiteSubmission;
    documentUploadUrl: string;
    documentCount: number;
    submitUrl: string;
    questionnaireDraftUrl: string;
    dashboardUrl: string;
};

type WebsiteSubmission = {
    url: string | null;
    status: 'advisor_confirmed' | 'awaiting_advisor_confirmation' | 'not_listed';
};

type OnboardingForm = {
    acknowledged: boolean;
    primary_goal: string;
    success_measure: string;
    website_url: string;
    website_skipped: boolean;
    questionnaire_set_acknowledged: boolean;
    phase_three_acknowledged: boolean;
    answers: QuestionnaireAnswers;
    documents_acknowledged: boolean;
    review_confirmed: boolean;
};

type QuestionnaireDraftStatus =
    | 'idle'
    | 'saving'
    | 'saved'
    | 'offline'
    | 'error';

export default function OnboardingStep({
    client,
    step,
    steps,
    welcomeMessage,
    state,
    stepData,
    progress,
    questionnaire,
    website,
    documentUploadUrl,
    documentCount,
    submitUrl,
    questionnaireDraftUrl,
    dashboardUrl,
}: Props) {
    const form = useForm<OnboardingForm>({
        acknowledged: booleanValue(stepData.acknowledged),
        primary_goal: stringValue(stepData.primary_goal),
        success_measure: stringValue(stepData.success_measure),
        website_url: stringValue(stepData.website_url, website.url ?? ''),
        website_skipped: booleanValue(stepData.website_skipped),
        questionnaire_set_acknowledged: booleanValue(
            stepData.questionnaire_set_acknowledged,
        ),
        phase_three_acknowledged: booleanValue(
            stepData.phase_three_acknowledged,
        ),
        answers: questionnaire.answers ?? {},
        documents_acknowledged: booleanValue(stepData.documents_acknowledged),
        review_confirmed: booleanValue(stepData.review_confirmed),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const [questionnaireDraftStatus, setQuestionnaireDraftStatus] =
        useState<QuestionnaireDraftStatus>(
            questionnaire.draft_saved_at ? 'saved' : 'idle',
        );
    const savedQuestionnaireAnswers = useRef(
        JSON.stringify(questionnaire.answers ?? {}),
    );
    const saveQuestionnaireDraft = useCallback(
        async (answers: QuestionnaireAnswers): Promise<boolean> => {
            if (!questionnaireDraftUrl || !navigator.onLine) {
                setQuestionnaireDraftStatus('offline');

                return false;
            }

            setQuestionnaireDraftStatus('saving');

            try {
                const response = await fetch(questionnaireDraftUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ answers }),
                });

                if (!response.ok) {
                    throw new Error('Draft save failed.');
                }

                savedQuestionnaireAnswers.current = JSON.stringify(answers);
                setQuestionnaireDraftStatus('saved');

                return true;
            } catch {
                setQuestionnaireDraftStatus('error');

                return false;
            }
        },
        [questionnaireDraftUrl],
    );
    const questionnaireAnswerSignature = JSON.stringify(form.data.answers);

    useEffect(() => {
        if (
            step.slug !== 'questionnaire' ||
            !questionnaire.available ||
            !questionnaire.schema ||
            questionnaireAnswerSignature === savedQuestionnaireAnswers.current
        ) {
            return;
        }

        const timer = window.setTimeout(() => {
            void saveQuestionnaireDraft(form.data.answers);
        }, 800);

        return () => window.clearTimeout(timer);
    }, [
        form.data.answers,
        questionnaire.available,
        questionnaire.schema,
        questionnaireAnswerSignature,
        saveQuestionnaireDraft,
        step.slug,
    ]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (step.slug === 'questionnaire' && !navigator.onLine) {
            void queueQuestionnaireSubmission(submitUrl, form.data, client.id)
                .then(() => {
                    toast.success('Questionnaire saved offline.');
                })
                .catch(() => {
                    toast.error('Offline save failed.');
                });

            return;
        }

        form.post(submitUrl, {
            preserveScroll: true,
        });
    };
    const saveDraftAndExit = async () => {
        if (step.slug === 'questionnaire') {
            const saved = await saveQuestionnaireDraft(form.data.answers);

            if (!saved) {
                toast.error('Your draft could not be saved.');

                return;
            }
        }

        router.visit(dashboardUrl);
    };

    return (
        <>
            <Head title={`${step.title} - Onboarding`} />

            <main className="flex-1 space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link
                            href={dashboardUrl}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Dashboard
                        </Link>
                        <h1 className="mt-3 text-xl font-semibold">
                            {step.title}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {client.trading_name || client.legal_name}
                        </p>
                    </div>
                    <Badge variant="secondary">
                        Step {step.number} of {progress.total}
                    </Badge>
                </div>

                <section
                    className="rounded-md border bg-background p-4"
                    aria-labelledby="wizard-stepper-heading"
                >
                    <ExplainedSectionHeader
                        title={
                            <span id="wizard-stepper-heading">Onboarding</span>
                        }
                        description="The steps collect your goals, website, questionnaire answers, and evidence for advisor review."
                        explanation={{
                            title: 'Onboarding progress',
                            what: 'This shows where you are in the client onboarding workflow and which steps are available.',
                            action: 'Complete each available step, then save and continue. Locked steps open when the prior requirement is ready.',
                            why: 'Complete onboarding gives your advisor enough verified context to start analysis without repeated follow-up.',
                        }}
                        actions={
                            <span className="text-sm text-muted-foreground">
                                {progress.percentage}%
                            </span>
                        }
                    />
                    <ol className="mt-4 grid gap-2 md:grid-cols-6">
                        {steps.map((item) => (
                            <li key={item.slug}>
                                {item.locked ? (
                                    <span
                                        className="flex min-h-14 items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground"
                                        aria-disabled="true"
                                    >
                                        <StepIcon step={item} />
                                        <span>{item.title}</span>
                                    </span>
                                ) : (
                                    <Link
                                        href={item.href}
                                        aria-current={
                                            item.slug === step.slug
                                                ? 'step'
                                                : undefined
                                        }
                                        className={cn(
                                            'flex min-h-14 items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                            item.slug === step.slug
                                                ? 'border-[var(--fs-admiralty)] bg-[var(--fs-linen)] font-medium text-[var(--fs-admiralty)]'
                                                : 'hover:bg-muted',
                                        )}
                                    >
                                        <StepIcon step={item} />
                                        <span>{item.title}</span>
                                    </Link>
                                )}
                            </li>
                        ))}
                    </ol>
                </section>

                <form onSubmit={submit} className="space-y-6">
                    <section className="rounded-md border bg-background p-4">
                        <StepContent
                            client={client}
                            step={step}
                            welcomeMessage={welcomeMessage}
                            state={state}
                            form={form}
                            errors={errors}
                            questionnaire={questionnaire}
                            website={website}
                            documentUploadUrl={documentUploadUrl}
                            documentCount={documentCount}
                            questionnaireDraftStatus={questionnaireDraftStatus}
                            onSaveQuestionnaireDraft={() =>
                                void saveQuestionnaireDraft(form.data.answers)
                            }
                        />
                    </section>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={questionnaireDraftStatus === 'saving'}
                            onClick={() => void saveDraftAndExit()}
                        >
                            {step.slug === 'questionnaire'
                                ? 'Save draft and exit'
                                : 'Back to dashboard'}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {step.slug === 'review-submit'
                                ? 'Submit onboarding'
                                : 'Save and continue'}
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

function StepContent({
    client,
    step,
    welcomeMessage,
    state,
    form,
    errors,
    questionnaire,
    website,
    documentUploadUrl,
    documentCount,
    questionnaireDraftStatus,
    onSaveQuestionnaireDraft,
}: {
    client: ClientPayload;
    step: WizardStep;
    welcomeMessage: WelcomeMessage;
    state: WizardState;
    form: ReturnType<typeof useForm<OnboardingForm>>;
    errors: Record<string, string | undefined>;
    questionnaire: Questionnaire;
    website: WebsiteSubmission;
    documentUploadUrl: string;
    documentCount: number;
    questionnaireDraftStatus: QuestionnaireDraftStatus;
    onSaveQuestionnaireDraft: () => void;
}) {
    const [documentFile, setDocumentFile] = useState<File | null>(null);
    const [uploadedDocumentCount, setUploadedDocumentCount] =
        useState(documentCount);
    const [uploadingDocument, setUploadingDocument] = useState(false);
    const [documentUploadError, setDocumentUploadError] = useState<
        string | null
    >(null);

    const uploadDocument = async () => {
        if (!documentFile) {
            return;
        }

        setUploadingDocument(true);
        setDocumentUploadError(null);

        const upload = new FormData();
        upload.append('file', documentFile);
        upload.append('category', 'other');
        upload.append(
            'claim_value',
            'Supporting document uploaded during onboarding.',
        );
        upload.append('question_prompt', 'Onboarding supporting document');

        const response = await fetch(documentUploadUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: upload,
        });

        setUploadingDocument(false);

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;
            setDocumentUploadError(payload?.message ?? 'Upload failed.');

            return;
        }

        setUploadedDocumentCount((count) => count + 1);
        setDocumentFile(null);
        form.setData('documents_acknowledged', true);
        toast.success('Document uploaded.');
    };

    switch (step.slug) {
        case 'welcome':
            return (
                <ContentShell
                    icon={ClipboardList}
                    title="Welcome"
                    description="Your onboarding information is saved as you progress."
                    explanation={stepExplanations.welcome}
                >
                    {welcomeMessage.has_message ? (
                        <div
                            className="rounded-md border border-[var(--fs-linen)] bg-[var(--fs-linen)]/50 p-5 text-sm leading-relaxed text-foreground [&_a]:text-[var(--fs-admiralty)] [&_a]:underline [&_p]:mb-3 [&_p:last-child]:mb-0 [&_strong]:font-semibold"
                            dangerouslySetInnerHTML={{
                                __html: welcomeMessage.html,
                            }}
                        />
                    ) : null}
                    <CheckboxField
                        id="acknowledged"
                        label="I am ready to begin onboarding."
                        checked={form.data.acknowledged}
                        onCheckedChange={(checked) =>
                            form.setData('acknowledged', checked)
                        }
                        error={form.errors.acknowledged}
                    />
                </ContentShell>
            );
        case 'goals':
            return (
                <ContentShell
                    icon={Flag}
                    title="Goals"
                    description="Capture the immediate business priorities for this engagement."
                    explanation={stepExplanations.goals}
                >
                    <Field
                        label="Primary goal"
                        id="primary_goal"
                        error={form.errors.primary_goal}
                    >
                        <textarea
                            id="primary_goal"
                            value={form.data.primary_goal}
                            rows={4}
                            onChange={(event) =>
                                form.setData('primary_goal', event.target.value)
                            }
                            className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            required
                        />
                    </Field>
                    <Field
                        label="Success measure"
                        id="success_measure"
                        error={form.errors.success_measure}
                    >
                        <textarea
                            id="success_measure"
                            value={form.data.success_measure}
                            rows={4}
                            onChange={(event) =>
                                form.setData(
                                    'success_measure',
                                    event.target.value,
                                )
                            }
                            className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </Field>
                </ContentShell>
            );
        case 'website':
            return (
                <ContentShell
                    icon={Globe2}
                    title="Your website"
                    description="Share the public website your customers use."
                    explanation={stepExplanations.website}
                >
                    <Field
                        label="Website address"
                        id="website_url"
                        error={form.errors.website_url}
                    >
                        <Input
                            id="website_url"
                            type="url"
                            value={form.data.website_url}
                            onChange={(event) =>
                                form.setData('website_url', event.target.value)
                            }
                            maxLength={2048}
                            placeholder="https://example.co.nz"
                        />
                    </Field>
                    {website.status === 'awaiting_advisor_confirmation' ? (
                        <p className="text-sm text-muted-foreground">
                            Your advisor will confirm this address before it is
                            used for the website review.
                        </p>
                    ) : website.status === 'advisor_confirmed' ? (
                        <p className="text-sm text-muted-foreground">
                            Your advisor has confirmed this address for the
                            website review.
                        </p>
                    ) : null}
                    <CheckboxField
                        id="website_skipped"
                        label="This business does not have a public website."
                        checked={form.data.website_skipped}
                        onCheckedChange={(checked) =>
                            form.setData('website_skipped', checked)
                        }
                        error={form.errors.website_skipped}
                    />
                </ContentShell>
            );
        case 'questionnaire':
            return (
                <ContentShell
                    icon={ClipboardList}
                    title={questionnaire.title}
                    description={questionnaire.description}
                    explanation={stepExplanations.questionnaire}
                >
                    <div className="flex flex-wrap gap-2">
                        <Badge
                            variant={
                                questionnaire.available
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {questionnaire.set}
                        </Badge>
                        <Badge variant="outline">{questionnaire.phase}</Badge>
                        <Badge
                            variant={
                                questionnaireDraftStatus === 'error'
                                    ? 'destructive'
                                    : 'outline'
                            }
                        >
                            {questionnaireDraftLabel(questionnaireDraftStatus)}
                        </Badge>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={questionnaireDraftStatus === 'saving'}
                            onClick={onSaveQuestionnaireDraft}
                        >
                            Save draft
                        </Button>
                    </div>
                    {questionnaire.available && questionnaire.schema ? (
                        <QuestionnaireRenderer
                            schema={questionnaire.schema}
                            answers={form.data.answers}
                            errors={errors}
                            uploadUrl={documentUploadUrl}
                            clientId={client.id}
                            collapsibleSections
                            showProgress
                            onChange={(answers) =>
                                form.setData('answers', answers)
                            }
                        />
                    ) : questionnaire.available ? (
                        <CheckboxField
                            id="questionnaire_set_acknowledged"
                            label="Use the Standard Advisory questionnaire path."
                            checked={form.data.questionnaire_set_acknowledged}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'questionnaire_set_acknowledged',
                                    checked,
                                )
                            }
                            error={form.errors.questionnaire_set_acknowledged}
                        />
                    ) : (
                        <CheckboxField
                            id="phase_three_acknowledged"
                            label="I understand this questionnaire is gated until Phase 3."
                            checked={form.data.phase_three_acknowledged}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'phase_three_acknowledged',
                                    checked,
                                )
                            }
                            error={errors.phase_three_acknowledged}
                        />
                    )}
                </ContentShell>
            );
        case 'documents':
            return (
                <ContentShell
                    icon={FileText}
                    title="Documents"
                    description="Upload the evidence your advisor needs before the Standard Advisory review can move into analysis."
                    explanation={stepExplanations.documents}
                >
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        {uploadedDocumentCount > 0
                            ? `${uploadedDocumentCount} supporting document${uploadedDocumentCount === 1 ? '' : 's'} uploaded.`
                            : 'No supporting documents uploaded yet.'}
                    </div>
                    <div className="grid gap-3">
                        <FileDropzone
                            id="onboarding_supporting_document"
                            files={documentFile ? [documentFile] : []}
                            label="Upload supporting document"
                            onFilesChange={(files) =>
                                setDocumentFile(files[0] ?? null)
                            }
                        />
                        <InputError
                            message={
                                documentUploadError ??
                                errors.supporting_documents
                            }
                        />
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!documentFile || uploadingDocument}
                                onClick={() => void uploadDocument()}
                            >
                                <Upload className="size-4" aria-hidden="true" />
                                {uploadingDocument ? 'Uploading' : 'Upload'}
                            </Button>
                        </div>
                    </div>
                    <CheckboxField
                        id="documents_acknowledged"
                        label="Supporting documents are ready for advisor review."
                        checked={form.data.documents_acknowledged}
                        onCheckedChange={(checked) =>
                            form.setData('documents_acknowledged', checked)
                        }
                        error={form.errors.documents_acknowledged}
                    />
                </ContentShell>
            );
        case 'review-submit':
            return (
                <ContentShell
                    icon={ShieldCheck}
                    title="Review and submit"
                    description="Confirm the saved onboarding summary."
                    explanation={stepExplanations['review-submit']}
                >
                    <dl className="grid gap-3 text-sm md:grid-cols-2">
                        <Detail
                            label="Website"
                            value={
                                summaryValue(
                                    state,
                                    'website',
                                    'website_url',
                                ) ?? 'No public website listed'
                            }
                        />
                        <Detail
                            label="Primary goal"
                            value={summaryValue(state, 'goals', 'primary_goal')}
                        />
                        <Detail
                            label="Questionnaire"
                            value={summaryValue(
                                state,
                                'questionnaire',
                                'questionnaire_set',
                            )}
                        />
                        <Detail
                            label="Documents"
                            value="Supporting documents uploaded for advisor review"
                        />
                    </dl>
                    <CheckboxField
                        id="review_confirmed"
                        label="The onboarding summary is ready to submit."
                        checked={form.data.review_confirmed}
                        onCheckedChange={(checked) =>
                            form.setData('review_confirmed', checked)
                        }
                        error={form.errors.review_confirmed}
                    />
                </ContentShell>
            );
        default:
            return null;
    }
}

function ContentShell({
    icon: Icon,
    title,
    description,
    explanation,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    description: string;
    explanation: Explanation;
    children: ReactNode;
}) {
    return (
        <div className="space-y-5">
            <ExplainedSectionHeader
                icon={Icon}
                title={title}
                description={description}
                explanation={explanation}
            />
            {children}
        </div>
    );
}

const stepExplanations: Record<string, Explanation> = {
    welcome: {
        title: 'Welcome step',
        what: 'This confirms you are ready to begin the onboarding workflow.',
        action: 'Read the advisor welcome message if one is present, then tick the acknowledgement when you are ready.',
        why: 'This creates a clear starting point before business and evidence information is collected.',
    },
    goals: {
        title: 'Goals',
        what: 'This captures what you want the advisory engagement to help achieve.',
        action: 'Write the main goal and, where possible, how success should be measured.',
        why: 'Clear goals let advice, reports, and follow-up outcomes connect back to what matters commercially.',
    },
    website: {
        title: 'Website',
        what: 'This records the public website your customers use to find and assess the business.',
        action: 'Enter the website address, or confirm that the business does not have a public website.',
        why: 'A confirmed website gives the advisor a reliable source for the website review and any related recommendations.',
    },
    questionnaire: {
        title: 'Questionnaire',
        what: 'This captures structured business information needed for the selected advisory pathway.',
        action: 'Answer the available questions and upload evidence where the form asks for support.',
        why: 'Structured answers help the advisor compare the business against the right methodology instead of relying on free-text notes alone.',
    },
    documents: {
        title: 'Documents',
        what: 'This collects supporting files that help evidence the onboarding answers.',
        action: 'Upload useful records such as financials, plans, agreements, or other advisor-requested documents.',
        why: 'Evidence-backed onboarding reduces rework and improves the reliability of later analysis.',
    },
    'review-submit': {
        title: 'Review and submit',
        what: 'This summarizes the onboarding information saved so far.',
        action: 'Check the summary and submit when it is ready for advisor review.',
        why: 'Submission marks the handoff from client preparation to advisor analysis.',
    },
};

function Field({
    label,
    id,
    error,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function CheckboxField({
    id,
    label,
    checked,
    error,
    onCheckedChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    error?: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-start gap-3">
                <Checkbox
                    id={id}
                    checked={checked}
                    onCheckedChange={(value) => onCheckedChange(value === true)}
                />
                <Label htmlFor={id}>{label}</Label>
            </div>
            <InputError message={error} />
        </div>
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

function StepIcon({ step }: { step: WizardStep }) {
    if (step.completed) {
        return <Check className="size-4 shrink-0" aria-hidden="true" />;
    }

    return (
        <span
            className="flex size-5 shrink-0 items-center justify-center rounded-full border text-xs"
            aria-hidden="true"
        >
            {step.number}
        </span>
    );
}

function booleanValue(value: unknown): boolean {
    return value === true;
}

function questionnaireDraftLabel(status: QuestionnaireDraftStatus): string {
    return {
        idle: 'Draft ready',
        saving: 'Saving draft',
        saved: 'Draft saved',
        offline: 'Offline changes',
        error: 'Draft needs saving',
    }[status];
}

function stringValue(value: unknown, fallback = ''): string {
    return typeof value === 'string' ? value : fallback;
}

function summaryValue(
    state: WizardState,
    step: string,
    key: string,
): string | null {
    const value = state.steps[step]?.[key];

    return typeof value === 'string' && value !== '' ? value : null;
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
