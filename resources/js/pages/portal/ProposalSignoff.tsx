import { Head, Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    Download,
    ExternalLink,
    FileText,
    PenLine,
} from 'lucide-react';
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
    suggested_mid: number | null;
    roi_ratio: number;
    view_url: string;
    download_url: string;
    released_at: string | null;
    awaiting_signature_at: string | null;
    signed_at: string | null;
    consents: Record<string, string>;
};

type SignoffPayload = {
    steps: SignoffStep[];
    next_step: string | null;
    active_authority: boolean;
};

type SignoffStep = {
    step: string;
    label: string;
    completed: boolean;
    completed_at: string | null;
    url: string;
};

type SignoffForm = {
    reviewed: boolean;
    election: string;
    type: string;
    gateway: string;
    fixture_token: string;
    signature_name: string;
    accepted: boolean;
    confirmed: boolean;
    signoff: string;
};

type Props = {
    proposal: ProposalPayload;
    signoff: SignoffPayload;
};

export default function ProposalSignoff({ proposal, signoff }: Props) {
    const currentStep =
        signoff.steps.find((step) => step.step === signoff.next_step) ??
        signoff.steps.at(-1);

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
                                {formatCurrency(proposal.suggested_mid ?? 0)}
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
                                View proposal
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={proposal.download_url}>
                                <Download
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Download
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
                                {proposal.scope_summary}
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
                    <div className="grid gap-3 md:grid-cols-7">
                        {signoff.steps.map((step) => (
                            <Tooltip key={step.step}>
                                <TooltipTrigger asChild>
                                    <div
                                        tabIndex={0}
                                        className={cn(
                                            'rounded-md border p-3 text-sm transition-colors outline-none hover:bg-muted/60 focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                            step.step === signoff.next_step &&
                                                'border-[var(--fs-admiralty)] bg-[var(--fs-linen)] text-[var(--fs-admiralty)]',
                                        )}
                                        aria-current={
                                            step.step === signoff.next_step
                                                ? 'step'
                                                : undefined
                                        }
                                    >
                                        <div className="font-medium">
                                            {step.label}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {step.completed
                                                ? 'Complete'
                                                : 'Open'}
                                        </div>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent
                                    side="bottom"
                                    className="max-w-xs"
                                >
                                    {step.completed
                                        ? `${step.label} is complete. The completed response remains part of this sign-off record.`
                                        : `${step.label} is still open. Use this step to continue proposal sign-off.`}
                                </TooltipContent>
                            </Tooltip>
                        ))}
                    </div>
                </section>

                <ConsentUpdate
                    steps={signoff.steps}
                    proposal={proposal}
                    type="insurance_referral"
                    step="insurance_consent"
                    label="Insurance"
                />
                <ConsentUpdate
                    steps={signoff.steps}
                    proposal={proposal}
                    type="coach_referral"
                    step="coach_consent"
                    label="Coach"
                />

                {currentStep && (
                    <CurrentStepPanel
                        proposal={proposal}
                        step={currentStep}
                        activeAuthority={signoff.active_authority}
                    />
                )}
            </div>
        </>
    );
}

function CurrentStepPanel({
    proposal,
    step,
    activeAuthority,
}: {
    proposal: ProposalPayload;
    step: SignoffStep;
    activeAuthority: boolean;
}) {
    const form = useForm<SignoffForm>({
        reviewed: true,
        election: 'undecided',
        type: 'card',
        gateway: 'stripe',
        fixture_token: '',
        signature_name: '',
        accepted: false,
        confirmed: true,
        signoff: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => payloadFor(step.step, data));
        form.post(step.url, {
            preserveScroll: true,
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

            {step.step === 'review' && (
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
                                <ExternalLink
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                View proposal
                            </a>
                        </Button>
                        <Button type="button" asChild variant="outline">
                            <a href={proposal.download_url}>
                                <Download
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </div>
            )}

            {(step.step === 'insurance_consent' ||
                step.step === 'coach_consent') && (
                <ConsentFields
                    id={`current_${step.step}`}
                    value={form.data.election}
                    error={form.errors.election}
                    onChange={(value) => form.setData('election', value)}
                />
            )}

            {step.step === 'payment_method' && (
                <PaymentMethodFields
                    form={form}
                    activeAuthority={activeAuthority}
                />
            )}

            {step.step === 'authority' && (
                <div className="grid gap-2">
                    <Label htmlFor="fixture_token">Token</Label>
                    <input
                        id="fixture_token"
                        value={form.data.fixture_token}
                        onChange={(event) =>
                            form.setData('fixture_token', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.fixture_token} />
                </div>
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
                        <InputError message={form.errors.signature_name} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.accepted}
                            onChange={(event) =>
                                form.setData('accepted', event.target.checked)
                            }
                        />
                        I accept proposal v{proposal.version}
                    </label>
                    <InputError message={form.errors.accepted} />
                </div>
            )}

            {step.step === 'confirmation' && (
                <input type="hidden" value={String(form.data.confirmed)} />
            )}

            <InputError message={form.errors.signoff} />

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    {step.completed ? 'Update' : 'Continue'}
                </Button>
            </div>
        </form>
    );
}

function ConsentUpdate({
    steps,
    proposal,
    type,
    step,
    label,
}: {
    steps: SignoffStep[];
    proposal: ProposalPayload;
    type: string;
    step: string;
    label: string;
}) {
    const signoffStep = steps.find((item) => item.step === step);
    const form = useForm<{ election: string }>({
        election: proposal.consents[type] ?? 'undecided',
    });

    if (!signoffStep?.completed) {
        return null;
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
    form,
    activeAuthority,
}: {
    form: ReturnType<typeof useForm<SignoffForm>>;
    activeAuthority: boolean;
}) {
    return (
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
        return { type: data.type, gateway: data.gateway };
    }

    if (step === 'authority') {
        return {
            fixture_token: data.fixture_token,
        };
    }

    if (step === 'signature') {
        return {
            signature_name: data.signature_name,
            accepted: data.accepted,
        };
    }

    return { confirmed: true };
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'NZD',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatRatio(value: number): string {
    return new Intl.NumberFormat(undefined, {
        maximumFractionDigits: 2,
    }).format(value);
}
