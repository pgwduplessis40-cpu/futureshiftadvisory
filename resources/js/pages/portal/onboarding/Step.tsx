import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    Check,
    ClipboardList,
    FileText,
    Flag,
    ShieldCheck,
    Upload,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
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
    documentUploadUrl: string;
    documentCount: number;
    submitUrl: string;
    dashboardUrl: string;
    authUser: {
        name: string;
        email: string;
    };
};

type OnboardingForm = {
    acknowledged: boolean;
    name: string;
    email: string;
    snapshot_confirmed: boolean;
    primary_goal: string;
    success_measure: string;
    questionnaire_set_acknowledged: boolean;
    phase_three_acknowledged: boolean;
    answers: QuestionnaireAnswers;
    documents_acknowledged: boolean;
    review_confirmed: boolean;
};

export default function OnboardingStep({
    client,
    step,
    steps,
    welcomeMessage,
    state,
    stepData,
    progress,
    questionnaire,
    documentUploadUrl,
    documentCount,
    submitUrl,
    dashboardUrl,
    authUser,
}: Props) {
    const form = useForm<OnboardingForm>({
        acknowledged: booleanValue(stepData.acknowledged),
        name: stringValue(stepData.name, authUser.name),
        email: stringValue(stepData.email, authUser.email),
        snapshot_confirmed: booleanValue(stepData.snapshot_confirmed),
        primary_goal: stringValue(stepData.primary_goal),
        success_measure: stringValue(stepData.success_measure),
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
                    <div className="flex items-center justify-between gap-4">
                        <h2
                            id="wizard-stepper-heading"
                            className="text-sm font-medium"
                        >
                            Onboarding
                        </h2>
                        <span className="text-sm text-muted-foreground">
                            {progress.percentage}%
                        </span>
                    </div>
                    <ol className="mt-4 grid gap-2 md:grid-cols-7">
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
                            documentUploadUrl={documentUploadUrl}
                            documentCount={documentCount}
                            authUser={authUser}
                        />
                    </section>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                        <Button asChild variant="outline">
                            <Link href={dashboardUrl}>Save for later</Link>
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
    documentUploadUrl,
    documentCount,
    authUser,
}: {
    client: ClientPayload;
    step: WizardStep;
    welcomeMessage: WelcomeMessage;
    state: WizardState;
    form: ReturnType<typeof useForm<OnboardingForm>>;
    errors: Record<string, string | undefined>;
    questionnaire: Questionnaire;
    documentUploadUrl: string;
    documentCount: number;
    authUser: { name: string; email: string };
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
        case 'identity':
            return (
                <ContentShell
                    icon={UserRoundCheck}
                    title="Identity verification"
                    description="MFA is complete. Confirm the account details for this client portal."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Name" id="name" error={form.errors.name}>
                            <Input
                                id="name"
                                value={form.data.name}
                                placeholder={authUser.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            label="Email"
                            id="email"
                            error={form.errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                placeholder={authUser.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                required
                            />
                        </Field>
                    </div>
                </ContentShell>
            );
        case 'business-snapshot':
            return (
                <ContentShell
                    icon={Building2}
                    title="Business snapshot"
                    description="Review the registry snapshot for this engagement."
                >
                    <dl className="grid gap-3 text-sm md:grid-cols-2">
                        <Detail label="Legal name" value={client.legal_name} />
                        <Detail
                            label="Trading name"
                            value={client.trading_name}
                        />
                        <Detail label="NZBN" value={client.nzbn} />
                        <Detail label="Entity" value={client.entity_type} />
                        <Detail
                            label="GST"
                            value={client.gst_registered ? 'registered' : 'no'}
                        />
                        <Detail label="Filing" value={client.filing_status} />
                    </dl>
                    <CheckboxField
                        id="snapshot_confirmed"
                        label="The business snapshot is ready for onboarding."
                        checked={form.data.snapshot_confirmed}
                        onCheckedChange={(checked) =>
                            form.setData('snapshot_confirmed', checked)
                        }
                        error={form.errors.snapshot_confirmed}
                    />
                </ContentShell>
            );
        case 'goals':
            return (
                <ContentShell
                    icon={Flag}
                    title="Goals"
                    description="Capture the immediate business priorities for this engagement."
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
        case 'questionnaire':
            return (
                <ContentShell
                    icon={ClipboardList}
                    title={questionnaire.title}
                    description={questionnaire.description}
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
                    </div>
                    {questionnaire.available && questionnaire.schema ? (
                        <QuestionnaireRenderer
                            schema={questionnaire.schema}
                            answers={form.data.answers}
                            errors={errors}
                            uploadUrl={documentUploadUrl}
                            clientId={client.id}
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
                >
                    <dl className="grid gap-3 text-sm md:grid-cols-2">
                        <Detail
                            label="Identity"
                            value={summaryValue(state, 'identity', 'name')}
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
                            label="Data quality"
                            value={client.data_quality}
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
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-5">
            <div className="flex items-start gap-3">
                <div className="rounded-md bg-[var(--fs-linen)] p-2 text-[var(--fs-admiralty)]">
                    <Icon className="size-5" aria-hidden={true} />
                </div>
                <div>
                    <h2 className="text-sm font-medium">{title}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            {children}
        </div>
    );
}

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
