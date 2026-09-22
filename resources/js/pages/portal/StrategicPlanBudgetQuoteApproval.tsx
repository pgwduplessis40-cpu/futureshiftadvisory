import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    CheckCircle2,
    ExternalLink,
    FileSpreadsheet,
    LockKeyhole,
    Send,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { WorkspaceSwitcher } from '@/components/portal/WorkspaceSwitcher';
import type { WorkspaceSwitcherPayload } from '@/components/portal/WorkspaceSwitcher';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type: string;
    engagement_type_label: string;
};

type AccessPayload = {
    allowed: boolean;
    state:
        | 'not_required'
        | 'not_requested'
        | 'quote_requested'
        | 'payment_due'
        | 'acceptance_due'
        | 'active_add_on'
        | (string & {});
    label: string;
    message: string;
    request_url: string;
    activation_id: string | null;
    activation_status: string | null;
    activation_status_label: string | null;
    activation_url: string | null;
    package_label: string | null;
    fixed_fee: number | null;
    currency: string;
    payment_status: string | null;
    payment_status_label: string | null;
};

type TargetPayload = {
    name: string | null;
    vendor_name: string | null;
    industry: string | null;
    asking_price: number | string | null;
};

type Props = {
    client: ClientPayload;
    access: AccessPayload;
    target: TargetPayload;
    dashboardUrl: string;
    ddWorkspaceUrl: string;
    requestQuoteUrl: string;
    workspaces: WorkspaceSwitcherPayload;
};

export default function StrategicPlanBudgetQuoteApproval({
    client,
    access,
    target,
    dashboardUrl,
    ddWorkspaceUrl,
    requestQuoteUrl,
    workspaces,
}: Props) {
    const form = useForm({
        confirm_quote_request: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(requestQuoteUrl);
    }

    const hasQuoteInProgress = access.activation_url !== null;

    return (
        <>
            <Head title="Business Plan & Budget" />

            <section
                aria-labelledby="business-plan-budget-heading"
                className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6"
            >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={dashboardUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Dashboard
                            </Link>
                        </Button>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <h1
                                id="business-plan-budget-heading"
                                className="text-xl font-semibold"
                            >
                                Business Plan & Budget
                            </h1>
                            <Badge variant="secondary">{access.label}</Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {client.trading_name ?? client.legal_name} is a DD
                            client. This workspace opens after FSA confirms the
                            fixed Business Plan & Budget fee, payment is
                            complete, and the client accepts the service scope.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={ddWorkspaceUrl}>
                            <BriefcaseBusiness
                                className="size-4"
                                aria-hidden="true"
                            />
                            Due Diligence workspace
                        </Link>
                    </Button>
                </div>

                <WorkspaceSwitcher workspaces={workspaces} />

                <section className="rounded-xl border bg-background p-5 shadow-sm">
                    <div className="grid gap-5 lg:grid-cols-[1.25fr_0.75fr]">
                        <div>
                            <div className="flex size-12 items-center justify-center rounded-full bg-amber-100 text-amber-800">
                                <LockKeyhole
                                    className="size-6"
                                    aria-hidden="true"
                                />
                            </div>
                            <h2 className="mt-4 text-lg font-semibold">
                                Fixed-fee service required
                            </h2>
                            <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                                {access.message}
                            </p>

                            <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                <StepCard
                                    icon={Send}
                                    title="1. Request service"
                                    body="The client asks FSA for the fixed-fee Business Plan & Budget service."
                                    active={access.state === 'not_requested'}
                                />
                                <StepCard
                                    icon={FileSpreadsheet}
                                    title="2. FSA confirms fee"
                                    body="FSA confirms the fixed Business Plan & Budget fee and service scope."
                                    active={[
                                        'quote_requested',
                                        'payment_due',
                                    ].includes(access.state)}
                                />
                                <StepCard
                                    icon={CheckCircle2}
                                    title="3. Client accepts"
                                    body="After payment and scope acceptance, the plan and budget module opens."
                                    active={access.state === 'acceptance_due'}
                                />
                            </div>
                        </div>

                        <aside className="rounded-lg border bg-muted/25 p-4">
                            <div className="flex items-center gap-2">
                                <FileSpreadsheet
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                <h3 className="font-medium">
                                    Acquisition context
                                </h3>
                            </div>
                            <dl className="mt-4 space-y-3 text-sm">
                                <Detail
                                    label="Target"
                                    value={target.name ?? 'Not captured yet'}
                                />
                                <Detail
                                    label="Vendor"
                                    value={target.vendor_name}
                                />
                                <Detail
                                    label="Industry"
                                    value={target.industry}
                                />
                                <Detail
                                    label="Asking price"
                                    value={formatMaybeMoney(
                                        target.asking_price,
                                    )}
                                />
                            </dl>
                        </aside>
                    </div>
                </section>

                <section className="rounded-xl border bg-background p-5 shadow-sm">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="font-semibold">
                                {hasQuoteInProgress
                                    ? 'Service confirmation is in progress'
                                    : 'Request Business Plan & Budget'}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {hasQuoteInProgress
                                    ? 'Use the service page to review the selected fixed-fee package, payment status, and client acceptance step.'
                                    : 'This does not unlock the module immediately. FSA will confirm the fixed Business Plan & Budget package before payment and access.'}
                            </p>
                        </div>

                        {hasQuoteInProgress && access.activation_url ? (
                            <Button asChild>
                                <Link href={access.activation_url}>
                                    Open service request
                                    <ExternalLink
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        ) : null}
                    </div>

                    {hasQuoteInProgress ? (
                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <Detail
                                label="Status"
                                value={access.activation_status_label}
                            />
                            <Detail
                                label="Package"
                                value={access.package_label}
                            />
                            <Detail
                                label="Business Plan & Budget fee"
                                value={
                                    access.fixed_fee !== null
                                        ? formatMoney(
                                              access.fixed_fee,
                                              access.currency,
                                          )
                                        : 'To be confirmed by FSA'
                                }
                            />
                        </dl>
                    ) : null}

                    {hasQuoteInProgress ? null : (
                        <form
                            className="mt-4 space-y-4"
                            onSubmit={submit}
                            noValidate
                        >
                            <label
                                htmlFor="confirm_quote_request"
                                className="flex items-start gap-3 rounded-lg border bg-muted/20 p-3 text-sm"
                            >
                                <input
                                    id="confirm_quote_request"
                                    name="confirm_quote_request"
                                    type="checkbox"
                                    className="mt-1 size-4 rounded border-border"
                                    checked={form.data.confirm_quote_request}
                                    onChange={(event) =>
                                        form.setData(
                                            'confirm_quote_request',
                                            event.target.checked,
                                        )
                                    }
                                />
                                <span>
                                    I understand Business Plan & Budget is a
                                    separate fixed-fee service. FSA must confirm
                                    the package before payment and access.
                                </span>
                            </label>
                            <InputError
                                message={form.errors.confirm_quote_request}
                            />
                            <Button type="submit" disabled={form.processing}>
                                <Send className="size-4" aria-hidden="true" />
                                Request Business Plan & Budget
                            </Button>
                        </form>
                    )}
                </section>
            </section>
        </>
    );
}

function StepCard({
    icon: Icon,
    title,
    body,
    active,
}: {
    icon: typeof Send;
    title: string;
    body: string;
    active: boolean;
}) {
    return (
        <div
            className={`rounded-lg border p-3 ${
                active ? 'border-primary bg-primary/5' : 'bg-muted/20'
            }`}
        >
            <Icon className="size-4 text-primary" aria-hidden="true" />
            <h3 className="mt-2 text-sm font-medium">{title}</h3>
            <p className="mt-1 text-xs text-muted-foreground">{body}</p>
        </div>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div>
            <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 font-medium">{value ?? 'Not confirmed yet'}</dd>
        </div>
    );
}

function formatMaybeMoney(value: number | string | null): string | null {
    if (value === null || value === '') {
        return null;
    }

    const numericValue =
        typeof value === 'number'
            ? value
            : Number(String(value).replace(/[^0-9.-]/g, ''));

    if (!Number.isFinite(numericValue)) {
        return String(value);
    }

    return formatMoney(numericValue, 'NZD');
}

function formatMoney(value: number, currency: string): string {
    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(value);
}
