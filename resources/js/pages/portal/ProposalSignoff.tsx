import { Head, Link, useForm } from '@inertiajs/react';
import { loadStripe } from '@stripe/stripe-js';
import type {
    Stripe,
    StripeElements,
    StripePaymentElement,
    SetupIntent,
} from '@stripe/stripe-js';
import {
    CheckCircle2,
    CreditCard,
    Download,
    ExternalLink,
    FileText,
    PenLine,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type ProposalPayload = {
    id: string;
    version: number;
    status: string;
    status_label: string;
    client_name: string | null;
    scope_summary: string;
    brief: string;
    suggested_mid: number | null;
    payment_terms: PaymentTermsPayload;
    roi_ratio: number;
    view_url: string;
    download_url: string;
    released_at: string | null;
    awaiting_signature_at: string | null;
    signed_at: string | null;
    consents: Record<string, string>;
};

type PaymentTermsPayload = {
    currency: string;
    cadence: string;
    cadence_label: string;
    term_months: number;
    monthly_amount: number;
    monthly_amount_including_gst: number;
    total_amount: number | null;
    total_amount_including_gst: number | null;
    gst_rate_percent: number;
    tax_mode: 'gst_exclusive';
    cancellation_notice_days: number | null;
};

type SignoffPayload = {
    steps: SignoffStep[];
    next_step: string | null;
    active_authority: boolean;
    authority_requires_token: boolean;
    payment_required: boolean;
    payment_setup_url: string;
    signature_requires_password: boolean;
    signature_requires_mfa: boolean;
};

type SignoffStep = {
    step: string;
    label: string;
    completed: boolean;
    completed_at: string | null;
    payload: Record<string, unknown>;
    url: string;
};

type SignoffForm = {
    reviewed: boolean;
    election: string;
    type: string;
    gateway: string;
    collection_day: string;
    payment_method_ref: string;
    setup_intent_ref: string;
    customer_ref: string;
    fixture_token: string;
    signature_name: string;
    accepted: boolean;
    current_password: string;
    mfa_code: string;
    confirmed: boolean;
    signoff: string;
};

type Props = {
    proposal: ProposalPayload;
    signoff: SignoffPayload;
};

type StripeSetupIntentPayload = {
    publishable_key: string;
    client_secret: string;
    setup_intent_ref: string;
    customer_ref: string;
};

type StripeErrorPayload = {
    message?: string;
    error?: {
        message?: string;
    };
    errors?: Record<string, string[]>;
};

export default function ProposalSignoff({ proposal, signoff }: Props) {
    const defaultStepName = signoff.next_step ?? signoff.steps.at(-1)?.step;
    const [selectedStepName, setSelectedStepName] = useState<
        string | undefined
    >();

    const canSelectStep = (step: SignoffStep): boolean => {
        if (step.step === 'authority' && step.completed) {
            return false;
        }

        return step.completed || step.step === signoff.next_step;
    };

    const currentStep =
        signoff.steps.find(
            (step) =>
                step.step === (selectedStepName ?? defaultStepName) &&
                canSelectStep(step),
        ) ??
        signoff.steps.find((step) => step.step === defaultStepName) ??
        signoff.steps.at(-1);
    const hasReferralConsents = signoff.steps.some(
        (step) =>
            step.step === 'insurance_consent' || step.step === 'coach_consent',
    );
    const signoffGridClass =
        signoff.steps.length <= 5
            ? 'grid gap-3 md:grid-cols-5'
            : 'grid gap-3 md:grid-cols-7';

    return (
        <>
            <Head title={`Proposal v${proposal.version}`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Proposal v{proposal.version}
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{proposal.client_name}</span>
                            <span aria-hidden="true">/</span>
                            <span>
                                {formatCurrency(proposal.suggested_mid ?? 0)} ex
                                GST
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={proposal.view_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {proposal.signed_at === null
                                    ? 'View proposal'
                                    : 'View signed proposal'}
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={proposal.download_url}>
                                <Download
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {proposal.signed_at === null
                                    ? 'Download'
                                    : 'Download signed proposal'}
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/portal">Back</Link>
                        </Button>
                    </div>
                </div>

                <section className="rounded-md border bg-background p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-2">
                            <Badge
                                variant={
                                    proposal.status === 'signed'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {proposal.status_label}
                            </Badge>
                            <p className="max-w-3xl text-sm text-muted-foreground">
                                {proposal.brief || proposal.scope_summary}
                            </p>
                        </div>
                        <div className="text-sm font-medium">
                            ROI {formatRatio(proposal.roi_ratio)}
                        </div>
                    </div>
                </section>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2">
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Sign-off</h2>
                    </div>
                    <div className={signoffGridClass}>
                        {signoff.steps.map((step) => {
                            const selectable = canSelectStep(step);
                            const selected = currentStep?.step === step.step;
                            const status = step.completed
                                ? 'Complete'
                                : step.step === signoff.next_step
                                  ? 'Open'
                                  : 'Locked';

                            return (
                                <Tooltip key={step.step}>
                                    <TooltipTrigger asChild>
                                        <button
                                            type="button"
                                            tabIndex={selectable ? 0 : -1}
                                            aria-current={
                                                selected ? 'step' : undefined
                                            }
                                            aria-disabled={!selectable}
                                            onClick={() => {
                                                if (selectable) {
                                                    setSelectedStepName(
                                                        step.step,
                                                    );
                                                }
                                            }}
                                            className={cn(
                                                'rounded-md border p-3 text-left text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                selectable &&
                                                    'hover:bg-muted/60',
                                                selected &&
                                                    'border-[var(--fs-admiralty)] bg-[var(--fs-linen)] text-[var(--fs-admiralty)]',
                                                !selectable &&
                                                    'cursor-not-allowed opacity-60',
                                            )}
                                        >
                                            <div className="font-medium">
                                                {step.label}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {status}
                                            </div>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        side="bottom"
                                        className="max-w-xs"
                                    >
                                        {step.completed
                                            ? selectable
                                                ? `${step.label} is complete and can be reviewed before final signature.`
                                                : `${step.label} is complete. Continue to the next open step.`
                                            : step.step === signoff.next_step
                                              ? `${step.label} is the current sign-off step.`
                                              : `${step.label} unlocks after the previous steps are complete.`}
                                    </TooltipContent>
                                </Tooltip>
                            );
                        })}
                    </div>
                </section>

                {hasReferralConsents ? (
                    <>
                        <ConsentUpdate
                            steps={signoff.steps}
                            proposal={proposal}
                            type="insurance_referral"
                            step="insurance_consent"
                            label="Insurance"
                            locked={signoff.next_step === null}
                        />
                        <ConsentUpdate
                            steps={signoff.steps}
                            proposal={proposal}
                            type="coach_referral"
                            step="coach_consent"
                            label="Coach"
                            locked={signoff.next_step === null}
                        />
                    </>
                ) : null}

                {currentStep && (
                    <CurrentStepPanel
                        key={`${currentStep.step}-${currentStep.completed_at ?? 'open'}`}
                        proposal={proposal}
                        steps={signoff.steps}
                        step={currentStep}
                        nextStepName={signoff.next_step}
                        activeAuthority={signoff.active_authority}
                        paymentRequired={signoff.payment_required}
                        authorityRequiresToken={
                            signoff.authority_requires_token
                        }
                        paymentSetupUrl={signoff.payment_setup_url}
                        signatureRequiresPassword={
                            signoff.signature_requires_password
                        }
                        signatureRequiresMfa={signoff.signature_requires_mfa}
                        onAdvance={(stepName) => setSelectedStepName(stepName)}
                    />
                )}
            </div>
        </>
    );
}

function CurrentStepPanel({
    proposal,
    steps,
    step,
    nextStepName,
    activeAuthority,
    paymentRequired,
    authorityRequiresToken,
    paymentSetupUrl,
    signatureRequiresPassword,
    signatureRequiresMfa,
    onAdvance,
}: {
    proposal: ProposalPayload;
    steps: SignoffStep[];
    step: SignoffStep;
    nextStepName: string | null;
    activeAuthority: boolean;
    paymentRequired: boolean;
    authorityRequiresToken: boolean;
    paymentSetupUrl: string;
    signatureRequiresPassword: boolean;
    signatureRequiresMfa: boolean;
    onAdvance: (stepName: string) => void;
}) {
    const form = useForm<SignoffForm>(
        initialSignoffData(step, proposal, steps),
    );
    const signoffComplete = nextStepName === null;
    const isReadOnlyCompletedStep = signoffComplete && step.completed;
    const requiresConfirmedPaymentMethod =
        step.step === 'authority' &&
        authorityRequiresToken &&
        form.data.payment_method_ref === '';

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isReadOnlyCompletedStep) {
            return;
        }

        form.transform((data) => payloadFor(step.step, data));
        form.post(step.url, {
            preserveScroll: true,
            onSuccess: () => {
                const next = stepAfter(steps, step.step);

                if (next !== null) {
                    onAdvance(next);
                }
            },
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-4 rounded-md border bg-background p-4"
        >
            <div className="flex items-center gap-2">
                {step.step === 'signature' ? (
                    <PenLine className="size-4" aria-hidden="true" />
                ) : step.step === 'authority' ||
                  step.step === 'payment_method' ? (
                    <CreditCard className="size-4" aria-hidden="true" />
                ) : (
                    <FileText className="size-4" aria-hidden="true" />
                )}
                <h2 className="text-sm font-medium">{step.label}</h2>
            </div>

            {isReadOnlyCompletedStep ? (
                <CompletedStepSummary proposal={proposal} step={step} />
            ) : (
                <>
                    {step.step === 'review' && (
                        <ReviewStepPanel proposal={proposal} />
                    )}

                    {(step.step === 'insurance_consent' ||
                        step.step === 'coach_consent') && (
                        <ConsentFields
                            id={`current_${step.step}`}
                            value={form.data.election}
                            error={form.errors.election}
                            onChange={(value) =>
                                form.setData('election', value)
                            }
                        />
                    )}

                    {step.step === 'payment_method' && (
                        <PaymentMethodFields
                            proposal={proposal}
                            form={form}
                            activeAuthority={activeAuthority}
                        />
                    )}

                    {step.step === 'authority' && (
                        <AuthorityFields
                            form={form}
                            requiresToken={authorityRequiresToken}
                            setupUrl={paymentSetupUrl}
                        />
                    )}

                    {step.step === 'signature' && (
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="signature_name">Name</Label>
                                <input
                                    id="signature_name"
                                    value={form.data.signature_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'signature_name',
                                            event.target.value,
                                        )
                                    }
                                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    message={form.errors.signature_name}
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.accepted}
                                    onChange={(event) =>
                                        form.setData(
                                            'accepted',
                                            event.target.checked,
                                        )
                                    }
                                />
                                I accept proposal v{proposal.version}
                            </label>
                            <InputError message={form.errors.accepted} />

                            {signatureRequiresPassword && (
                                <div className="grid gap-4 rounded-md border bg-muted/30 p-4">
                                    <div className="flex items-center gap-2">
                                        <ShieldCheck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <div className="text-sm font-medium">
                                            Verify identity to sign
                                        </div>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {paymentRequired
                                            ? 'Confirm your identity before authorising the payment terms and signing this proposal.'
                                            : 'Confirm your identity before signing this proposal.'}
                                    </p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="current_password">
                                                Password
                                            </Label>
                                            <input
                                                id="current_password"
                                                type="password"
                                                value={
                                                    form.data.current_password
                                                }
                                                autoComplete="current-password"
                                                onChange={(event) =>
                                                    form.setData(
                                                        'current_password',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <InputError
                                                message={
                                                    form.errors.current_password
                                                }
                                            />
                                        </div>

                                        {signatureRequiresMfa && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="mfa_code">
                                                    Authenticator code
                                                </Label>
                                                <input
                                                    id="mfa_code"
                                                    value={form.data.mfa_code}
                                                    inputMode="numeric"
                                                    autoComplete="one-time-code"
                                                    maxLength={6}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'mfa_code',
                                                            event.target.value
                                                                .replace(
                                                                    /\D/g,
                                                                    '',
                                                                )
                                                                .slice(0, 6),
                                                        )
                                                    }
                                                    className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                />
                                                <InputError
                                                    message={
                                                        form.errors.mfa_code
                                                    }
                                                />
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {step.step === 'confirmation' && (
                        <ConfirmationSummary proposal={proposal} />
                    )}
                </>
            )}

            <InputError message={form.errors.signoff} />

            {!isReadOnlyCompletedStep && (
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={
                            form.processing || requiresConfirmedPaymentMethod
                        }
                    >
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        {primaryActionLabel(step, nextStepName)}
                    </Button>
                </div>
            )}
        </form>
    );
}

function primaryActionLabel(step: SignoffStep, nextStepName: string | null) {
    if (step.completed && nextStepName === null) {
        return 'Update';
    }

    return 'Continue';
}

function stepAfter(steps: SignoffStep[], stepName: string): string | null {
    const index = steps.findIndex((step) => step.step === stepName);

    if (index === -1 || index >= steps.length - 1) {
        return null;
    }

    return steps[index + 1]?.step ?? null;
}

function ReviewStepPanel({ proposal }: { proposal: ProposalPayload }) {
    return (
        <div className="grid gap-3 rounded-md border bg-muted/30 p-4 sm:grid-cols-[1fr_auto] sm:items-center">
            <div className="space-y-1">
                <div className="text-sm font-medium">
                    Proposal v{proposal.version}
                </div>
                <p className="text-sm text-muted-foreground">
                    Open the proposal before continuing sign-off.
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="button" asChild variant="outline">
                    <a
                        href={proposal.view_url}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <ExternalLink className="size-4" aria-hidden="true" />
                        {proposal.signed_at === null
                            ? 'View proposal'
                            : 'View signed proposal'}
                    </a>
                </Button>
                <Button type="button" asChild variant="outline">
                    <a href={proposal.download_url}>
                        <Download className="size-4" aria-hidden="true" />
                        {proposal.signed_at === null
                            ? 'Download PDF'
                            : 'Download signed proposal'}
                    </a>
                </Button>
            </div>
        </div>
    );
}

function CompletedStepSummary({
    proposal,
    step,
}: {
    proposal: ProposalPayload;
    step: SignoffStep;
}) {
    if (step.step === 'review') {
        return <ReviewStepPanel proposal={proposal} />;
    }

    if (step.step === 'insurance_consent' || step.step === 'coach_consent') {
        return (
            <div className="rounded-md border bg-muted/30 p-4">
                <PaymentTermItem
                    label="Election"
                    value={formatElection(
                        stringValue(step.payload.election, ''),
                    )}
                />
            </div>
        );
    }

    if (step.step === 'payment_method') {
        return (
            <div className="grid gap-4">
                <PaymentTermsSummary terms={proposal.payment_terms} />
                <div className="grid gap-4 rounded-md border bg-muted/30 p-4 sm:grid-cols-3">
                    <PaymentTermItem
                        label="Type"
                        value={formatPaymentType(
                            stringValue(step.payload.type, 'card'),
                        )}
                    />
                    <PaymentTermItem
                        label="Gateway"
                        value={gatewayLabel(
                            stringValue(step.payload.gateway, 'stripe'),
                        )}
                    />
                    <PaymentTermItem
                        label="Collection date"
                        value={formatCollectionDay(
                            stringValue(step.payload.collection_day, '1'),
                        )}
                    />
                </div>
            </div>
        );
    }

    if (step.step === 'authority') {
        return (
            <div className="grid gap-4 rounded-md border bg-muted/30 p-4 md:grid-cols-4">
                <PaymentTermItem label="Payment authority" value="Authorised" />
                <PaymentTermItem
                    label="Gateway"
                    value={gatewayLabel(
                        stringValue(step.payload.gateway, 'stripe'),
                    )}
                />
                <PaymentTermItem
                    label="Collection date"
                    value={formatCollectionDay(
                        stringValue(step.payload.collection_day, '1'),
                    )}
                />
                <PaymentTermItem
                    label="Reference"
                    value={stringValue(
                        step.payload.gateway_customer_ref,
                        'Stored with gateway',
                    )}
                />
            </div>
        );
    }

    if (step.step === 'signature') {
        const identity = recordValue(step.payload.identity_verification);

        return (
            <div className="grid gap-4 rounded-md border bg-muted/30 p-4 md:grid-cols-3">
                <PaymentTermItem
                    label="Signed by"
                    value={stringValue(step.payload.signature_name, 'Client')}
                />
                <PaymentTermItem
                    label="Password verified"
                    value={formatOptionalDateTime(
                        stringValue(identity.password_verified_at, ''),
                    )}
                />
                <PaymentTermItem
                    label="MFA"
                    value={
                        booleanValue(identity.mfa_required, false)
                            ? `Verified ${formatOptionalDateTime(
                                  stringValue(identity.mfa_verified_at, ''),
                              )}`
                            : 'Not required'
                    }
                />
            </div>
        );
    }

    return <ConfirmationSummary proposal={proposal} />;
}

function ConfirmationSummary({ proposal }: { proposal: ProposalPayload }) {
    return (
        <div className="grid gap-4 rounded-md border bg-muted/30 p-4 md:grid-cols-3">
            <PaymentTermItem
                label="Proposal"
                value={`Proposal v${proposal.version}`}
            />
            <PaymentTermItem label="Status" value={proposal.status_label} />
            <PaymentTermItem
                label="Signed"
                value={
                    proposal.signed_at === null
                        ? 'Complete'
                        : formatDateTime(proposal.signed_at)
                }
            />
        </div>
    );
}

function PaymentTermsSummary({ terms }: { terms: PaymentTermsPayload }) {
    return (
        <div className="grid gap-3 rounded-md border bg-muted/30 p-4 md:grid-cols-5">
            <PaymentTermItem
                label="Total proposal ex GST"
                value={
                    terms.total_amount === null
                        ? 'To be confirmed'
                        : formatCurrency(terms.total_amount)
                }
            />
            <PaymentTermItem
                label="Term"
                value={`${terms.term_months} ${terms.term_months === 1 ? 'month' : 'months'}`}
            />
            <PaymentTermItem
                label={terms.cadence_label}
                value={`${formatCurrency(terms.monthly_amount)} ex GST per month`}
            />
            <PaymentTermItem
                label={`Total incl GST (${formatPercent(terms.gst_rate_percent)})`}
                value={
                    terms.total_amount_including_gst === null
                        ? 'To be confirmed'
                        : formatCurrency(terms.total_amount_including_gst)
                }
            />
            <PaymentTermItem
                label="Cancellation notice"
                value={
                    terms.cancellation_notice_days === null
                        ? 'As agreed'
                        : `${terms.cancellation_notice_days} days`
                }
            />
        </div>
    );
}

function AuthorityFields({
    form,
    requiresToken,
    setupUrl,
}: {
    form: ReturnType<typeof useForm<SignoffForm>>;
    requiresToken: boolean;
    setupUrl: string;
}) {
    const elementRef = useRef<HTMLDivElement | null>(null);
    const stripeRef = useRef<Stripe | null>(null);
    const elementsRef = useRef<StripeElements | null>(null);
    const paymentElementRef = useRef<StripePaymentElement | null>(null);
    const [setupStatus, setSetupStatus] = useState<
        'idle' | 'loading' | 'ready' | 'saving' | 'saved' | 'error'
    >('idle');
    const [setupPayload, setSetupPayload] =
        useState<StripeSetupIntentPayload | null>(null);
    const [setupError, setSetupError] = useState<string | null>(null);
    const paymentType = form.data.type;
    const paymentGateway = form.data.gateway;
    const setFormData = form.setData;

    useEffect(() => {
        if (
            !requiresToken ||
            paymentGateway !== 'stripe' ||
            paymentType !== 'card'
        ) {
            return;
        }

        let cancelled = false;

        const mountStripeElement = async () => {
            setFormData('payment_method_ref', '');
            setFormData('setup_intent_ref', '');
            setFormData('customer_ref', '');
            setSetupStatus('loading');
            setSetupError(null);
            setSetupPayload(null);
            paymentElementRef.current?.unmount();
            paymentElementRef.current = null;
            stripeRef.current = null;
            elementsRef.current = null;

            if (setupUrl === '') {
                throw new Error(
                    'Stripe card setup is not available for this proposal.',
                );
            }

            const response = await fetch(setupUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    type: paymentType,
                    gateway: paymentGateway,
                }),
            });
            const payload = (await response.json().catch(() => null)) as
                | StripeSetupIntentPayload
                | StripeErrorPayload
                | null;

            if (!response.ok) {
                throw new Error(stripeSetupErrorMessage(payload));
            }

            if (
                payload === null ||
                !('publishable_key' in payload) ||
                !('client_secret' in payload)
            ) {
                throw new Error(
                    'Stripe did not return a usable setup session.',
                );
            }

            const stripe = await loadStripe(payload.publishable_key);

            if (stripe === null) {
                throw new Error('Stripe could not be loaded in this browser.');
            }

            const elements = stripe.elements({
                clientSecret: payload.client_secret,
            });
            const paymentElement = elements.create('payment', {
                layout: 'tabs',
            });

            if (cancelled || elementRef.current === null) {
                paymentElement.unmount();

                return;
            }

            paymentElement.mount(elementRef.current);
            stripeRef.current = stripe;
            elementsRef.current = elements;
            paymentElementRef.current = paymentElement;
            setSetupPayload(payload);
            setSetupStatus('ready');
        };

        mountStripeElement().catch((error: unknown) => {
            if (cancelled) {
                return;
            }

            setSetupStatus('error');
            setSetupError(
                error instanceof Error
                    ? error.message
                    : 'Stripe card setup could not be started.',
            );
        });

        return () => {
            cancelled = true;
            paymentElementRef.current?.unmount();
            paymentElementRef.current = null;
        };
    }, [requiresToken, setupUrl, paymentGateway, paymentType, setFormData]);

    if (!requiresToken) {
        return (
            <div className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                A test payment authority will be created when you continue. No
                live card or bank details are used in this environment.
            </div>
        );
    }

    if (paymentGateway !== 'stripe' || paymentType !== 'card') {
        return (
            <div className="rounded-md border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                Live online setup is currently available for Stripe card
                payments. Return to Payment Method and select Card with Stripe.
            </div>
        );
    }

    const savePaymentDetails = async () => {
        if (stripeRef.current === null || elementsRef.current === null) {
            setSetupError('Stripe card setup is still loading.');

            return;
        }

        setSetupStatus('saving');
        setSetupError(null);
        form.setData('payment_method_ref', '');
        form.setData('setup_intent_ref', '');
        form.setData('customer_ref', '');

        const result = await stripeRef.current.confirmSetup({
            elements: elementsRef.current,
            redirect: 'if_required',
            confirmParams: {
                return_url: window.location.href,
            },
        });

        if (result.error) {
            setSetupStatus('ready');
            setSetupError(
                result.error.message ??
                    'Stripe could not save these card details.',
            );

            return;
        }

        const setupIntent = result.setupIntent;
        const paymentMethodRef = setupIntentPaymentMethod(setupIntent);
        const customerRef =
            setupIntentCustomer(setupIntent) ??
            setupPayload?.customer_ref ??
            '';

        if (
            setupIntent.status !== 'succeeded' ||
            paymentMethodRef === '' ||
            customerRef === ''
        ) {
            setSetupStatus('ready');
            setSetupError(
                'Stripe could not confirm these card details. Please check the card details and try again.',
            );

            return;
        }

        form.setData('payment_method_ref', paymentMethodRef);
        form.setData('setup_intent_ref', setupIntent.id);
        form.setData('customer_ref', customerRef);
        setSetupStatus('saved');
    };

    return (
        <div className="grid gap-4">
            <div className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                Card details are entered directly into Stripe. Continue unlocks
                after Stripe confirms the payment method for the agreed terms.
            </div>

            <div className="grid gap-3 rounded-md border bg-background p-4">
                <Label>Card details</Label>
                <div ref={elementRef} className="min-h-10" />
                {setupStatus === 'loading' && (
                    <p className="text-sm text-muted-foreground">
                        Loading secure Stripe card fields...
                    </p>
                )}
                {setupStatus === 'saved' && (
                    <p className="text-sm font-medium text-emerald-700">
                        Card details saved securely with Stripe.
                    </p>
                )}
                <InputError
                    message={
                        form.errors.payment_method_ref ??
                        setupError ??
                        undefined
                    }
                />
                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            setupStatus === 'loading' ||
                            setupStatus === 'saving'
                        }
                        onClick={savePaymentDetails}
                    >
                        <CreditCard className="size-4" aria-hidden="true" />
                        {setupStatus === 'saving'
                            ? 'Saving card details'
                            : 'Save card details'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function initialSignoffData(
    step: SignoffStep,
    proposal: ProposalPayload,
    steps: SignoffStep[] = [],
): SignoffForm {
    const payload = initialPayloadForStep(step, steps);

    return {
        reviewed: booleanValue(payload.reviewed, true),
        election: initialElection(step, proposal),
        type: stringValue(payload.type, 'card'),
        gateway: stringValue(payload.gateway, 'stripe'),
        collection_day: stringValue(payload.collection_day, '1'),
        payment_method_ref: '',
        setup_intent_ref: '',
        customer_ref: '',
        fixture_token: '',
        signature_name: stringValue(payload.signature_name, ''),
        accepted: booleanValue(payload.accepted, false),
        current_password: '',
        mfa_code: '',
        confirmed: booleanValue(payload.confirmed, true),
        signoff: '',
    };
}

function initialPayloadForStep(
    step: SignoffStep,
    steps: SignoffStep[],
): Record<string, unknown> {
    if (step.step !== 'authority') {
        return step.payload;
    }

    const paymentMethodPayload =
        steps.find((item) => item.step === 'payment_method')?.payload ?? {};

    return {
        ...paymentMethodPayload,
        ...step.payload,
    };
}

function initialElection(step: SignoffStep, proposal: ProposalPayload): string {
    if (step.step === 'insurance_consent') {
        return proposal.consents.insurance_referral ?? 'undecided';
    }

    if (step.step === 'coach_consent') {
        return proposal.consents.coach_referral ?? 'undecided';
    }

    return stringValue(step.payload.election, 'undecided');
}

function ConsentUpdate({
    steps,
    proposal,
    type,
    step,
    label,
    locked,
}: {
    steps: SignoffStep[];
    proposal: ProposalPayload;
    type: string;
    step: string;
    label: string;
    locked: boolean;
}) {
    const signoffStep = steps.find((item) => item.step === step);
    const form = useForm<{ election: string }>({
        election: proposal.consents[type] ?? 'undecided',
    });

    if (!signoffStep?.completed) {
        return null;
    }

    if (locked) {
        return (
            <section className="rounded-md border bg-background p-4">
                <PaymentTermItem
                    label={label}
                    value={formatElection(proposal.consents[type])}
                />
            </section>
        );
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(signoffStep.url, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-md border bg-background p-4 sm:grid-cols-[1fr_auto]"
        >
            <ConsentFields
                id={`revise_${step}`}
                label={label}
                value={form.data.election}
                error={form.errors.election}
                onChange={(value) => form.setData('election', value)}
            />
            <div className="flex items-end">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    Update
                </Button>
            </div>
        </form>
    );
}

function ConsentFields({
    id,
    label = 'Election',
    value,
    error,
    onChange,
}: {
    id: string;
    label?: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <option value="undecided">Undecided</option>
                <option value="opt_in">Opt in</option>
                <option value="opt_out">Opt out</option>
            </select>
            <InputError message={error} />
        </div>
    );
}

function PaymentMethodFields({
    proposal,
    form,
    activeAuthority,
}: {
    proposal: ProposalPayload;
    form: ReturnType<typeof useForm<SignoffForm>>;
    activeAuthority: boolean;
}) {
    const terms = proposal.payment_terms;

    return (
        <div className="grid gap-4">
            <PaymentTermsSummary terms={terms} />
            <div className="text-sm text-muted-foreground">
                {gatewayLabel(form.data.gateway)} will be used to store the
                payment authority and create charges from these agreed terms
                after sign-off. The agreed amounts are GST exclusive; GST at{' '}
                {formatPercent(terms.gst_rate_percent)} is added to each final
                amount collected.
            </div>
            <CollectionDayField
                value={form.data.collection_day}
                disabled={activeAuthority}
                error={form.errors.collection_day}
                onChange={(value) => form.setData('collection_day', value)}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="payment_type">Type</Label>
                    <select
                        id="payment_type"
                        value={form.data.type}
                        disabled={activeAuthority}
                        onChange={(event) =>
                            form.setData('type', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="card">Card</option>
                        <option value="direct_debit">Direct debit</option>
                    </select>
                    <InputError message={form.errors.type} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="payment_gateway">Gateway</Label>
                    <select
                        id="payment_gateway"
                        value={form.data.gateway}
                        disabled={activeAuthority}
                        onChange={(event) =>
                            form.setData('gateway', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="stripe">Stripe</option>
                        <option value="windcave">Windcave</option>
                    </select>
                    <InputError message={form.errors.gateway} />
                </div>
            </div>
        </div>
    );
}

function CollectionDayField({
    value,
    disabled,
    error,
    onChange,
}: {
    value: string;
    disabled: boolean;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="text-sm font-medium">Collection date</legend>
            <div className="grid gap-2 sm:grid-cols-2">
                {[
                    ['1', '1st of each month'],
                    ['15', '15th of each month'],
                ].map(([day, label]) => {
                    const selected = value === day;

                    return (
                        <button
                            key={day}
                            type="button"
                            disabled={disabled}
                            aria-pressed={selected}
                            onClick={() => onChange(day)}
                            className={cn(
                                'h-10 rounded-md border px-3 text-left text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                selected
                                    ? 'border-[var(--fs-admiralty)] bg-[var(--fs-linen)] font-medium text-[var(--fs-admiralty)]'
                                    : 'border-input bg-background hover:bg-muted/60',
                                disabled && 'cursor-not-allowed opacity-60',
                            )}
                        >
                            {label}
                        </button>
                    );
                })}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}

function PaymentTermItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1">
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div className="text-sm font-semibold">{value}</div>
        </div>
    );
}

function payloadFor(step: string, data: SignoffForm): Partial<SignoffForm> {
    if (step === 'review') {
        return { reviewed: true };
    }

    if (step === 'insurance_consent' || step === 'coach_consent') {
        return { election: data.election };
    }

    if (step === 'payment_method') {
        return {
            type: data.type,
            gateway: data.gateway,
            collection_day: data.collection_day,
        };
    }

    if (step === 'authority') {
        return {
            type: data.type,
            gateway: data.gateway,
            collection_day: data.collection_day,
            payment_method_ref: data.payment_method_ref,
            setup_intent_ref: data.setup_intent_ref,
            customer_ref: data.customer_ref,
            fixture_token: data.fixture_token,
        };
    }

    if (step === 'signature') {
        return {
            signature_name: data.signature_name,
            accepted: data.accepted,
            current_password: data.current_password,
            mfa_code: data.mfa_code,
        };
    }

    return { confirmed: true };
}

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function stripeSetupErrorMessage(payload: unknown): string {
    if (payload === null || typeof payload !== 'object') {
        return 'Stripe card setup could not be started.';
    }

    const errorPayload = payload as StripeErrorPayload;

    if (
        typeof errorPayload.message === 'string' &&
        errorPayload.message !== ''
    ) {
        return errorPayload.message;
    }

    if (
        typeof errorPayload.error?.message === 'string' &&
        errorPayload.error.message !== ''
    ) {
        return errorPayload.error.message;
    }

    const firstFieldError = Object.values(errorPayload.errors ?? {})
        .flat()
        .find((message) => message !== '');

    return firstFieldError ?? 'Stripe card setup could not be started.';
}

function setupIntentPaymentMethod(setupIntent: SetupIntent): string {
    const paymentMethod = setupIntent.payment_method;

    if (typeof paymentMethod === 'string') {
        return paymentMethod;
    }

    if (
        paymentMethod !== null &&
        typeof paymentMethod === 'object' &&
        'id' in paymentMethod &&
        typeof paymentMethod.id === 'string'
    ) {
        return paymentMethod.id;
    }

    return '';
}

function setupIntentCustomer(setupIntent: SetupIntent): string | null {
    const customer = (setupIntent as SetupIntent & { customer?: unknown })
        .customer;

    if (typeof customer === 'string' && customer !== '') {
        return customer;
    }

    return null;
}

function stringValue(value: unknown, fallback: string): string {
    if (typeof value === 'string' && value !== '') {
        return value;
    }

    if (typeof value === 'number') {
        return String(value);
    }

    return fallback;
}

function booleanValue(value: unknown, fallback: boolean): boolean {
    return typeof value === 'boolean' ? value : fallback;
}

function recordValue(value: unknown): Record<string, unknown> {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        return {};
    }

    return value as Record<string, unknown>;
}

function gatewayLabel(gateway: string): string {
    if (gateway === 'windcave') {
        return 'Windcave';
    }

    return 'Stripe';
}

function formatPaymentType(value: string): string {
    if (value === 'direct_debit') {
        return 'Direct debit';
    }

    return 'Card';
}

function formatCollectionDay(value: string): string {
    if (value === '15') {
        return '15th of each month';
    }

    return '1st of each month';
}

function formatElection(value: string | undefined): string {
    if (value === 'opt_in') {
        return 'Opt in';
    }

    if (value === 'opt_out') {
        return 'Opt out';
    }

    return 'Undecided';
}

function formatCurrency(value: number): string {
    const hasCents = !Number.isInteger(value);

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        minimumFractionDigits: hasCents ? 2 : 0,
        maximumFractionDigits: hasCents ? 2 : 0,
    }).format(value);
}

function formatPercent(value: number): string {
    return `${new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

function formatRatio(value: number): string {
    return new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 2,
    }).format(value);
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatOptionalDateTime(value: string): string {
    if (value === '') {
        return 'Recorded';
    }

    return formatDateTime(value);
}
