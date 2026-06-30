import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ExternalLink } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Activation = {
    id: string;
    service_type: 'due_diligence' | 'entrepreneur';
    client_label: string;
    status: string;
    status_label: string;
    intake: Record<string, string | number | null>;
    package: null | {
        client_label?: string;
        package_name?: string;
        billing_model?: string;
        fixed_fee?: number | null;
        currency?: string;
        scope_description?: string;
    };
    accepted_at: string | null;
    acceptance_text: string | null;
    workspace_ready: boolean;
    workspace_url: string;
    message_thread_url: string | null;
};

type Props = {
    activation: Activation;
    urls: {
        dashboard: string;
        accept: string;
        ddWorkspace: string;
        ideaWorkspace: string;
    };
};

export default function ServiceActivation({ activation, urls }: Props) {
    const form = useForm({
        confirm_fee_scope: false,
    });
    const selectedPackage = activation.package;
    const canAccept =
        activation.status === 'package_selected' && selectedPackage !== null;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(urls.accept);
    }

    return (
        <>
            <Head title={activation.client_label} />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={urls.dashboard}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Dashboard
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-xl font-semibold">
                            {activation.client_label}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Workspace request and fee/scope acknowledgement.
                        </p>
                    </div>
                    <Badge variant="secondary">{activation.status_label}</Badge>
                </div>

                <section className="rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Request details</h2>
                    <dl className="mt-3 grid gap-3 sm:grid-cols-2">
                        {Object.entries(activation.intake).map(
                            ([key, value]) => (
                                <div key={key}>
                                    <dt className="text-xs text-muted-foreground">
                                        {formatLabel(key)}
                                    </dt>
                                    <dd className="text-sm">
                                        {String(value ?? '-')}
                                    </dd>
                                </div>
                            ),
                        )}
                    </dl>
                </section>

                <section className="rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">
                        Package, scope, and fee
                    </h2>
                    {selectedPackage ? (
                        <div className="mt-3 space-y-3">
                            <div>
                                <div className="font-medium">
                                    {selectedPackage.client_label ??
                                        selectedPackage.package_name}
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {selectedPackage.scope_description}
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Metric
                                    label="Fee ex GST"
                                    value={
                                        selectedPackage.fixed_fee !== null &&
                                        selectedPackage.fixed_fee !== undefined
                                            ? formatMoney(
                                                  selectedPackage.fixed_fee,
                                                  selectedPackage.currency ??
                                                      'NZD',
                                              )
                                            : 'Proposal'
                                    }
                                />
                                <Metric
                                    label="Billing"
                                    value={formatLabel(
                                        selectedPackage.billing_model ??
                                            'fixed_fee',
                                    )}
                                />
                                <Metric
                                    label="Source"
                                    value="Admin Service Rates"
                                />
                            </div>
                        </div>
                    ) : (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Your advisor is reviewing this request and will
                            select the GST-exclusive package/scope/pricing from
                            the active Admin Service Rates table.
                        </p>
                    )}
                </section>

                {activation.workspace_ready ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <CheckCircle2
                                    className="mt-0.5 size-4 text-emerald-600"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h2 className="text-sm font-medium">
                                        Workspace active
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {activation.acceptance_text}
                                    </p>
                                </div>
                            </div>
                            <Button asChild>
                                <Link href={activation.workspace_url}>
                                    Open workspace
                                    <ExternalLink
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </div>
                    </section>
                ) : (
                    <form
                        onSubmit={submit}
                        className="rounded-md border bg-background p-4"
                    >
                        <h2 className="text-sm font-medium">
                            Fee/scope acknowledgement
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            The standard Terms and Conditions already accepted
                            for portal access continue to apply. This checkbox
                            confirms the workspace-specific scope and
                            GST-exclusive fee.
                        </p>
                        <label className="mt-4 flex gap-3 text-sm">
                            <input
                                type="checkbox"
                                disabled={!canAccept}
                                checked={form.data.confirm_fee_scope}
                                onChange={(event) =>
                                    form.setData(
                                        'confirm_fee_scope',
                                        event.target.checked,
                                    )
                                }
                            />
                            <span>
                                I accept the selected package, scope, and
                                GST-exclusive fee for this workspace.
                            </span>
                        </label>
                        <InputError
                            message={form.errors.confirm_fee_scope}
                            className="mt-2"
                        />
                        <div className="mt-4 flex justify-end">
                            <Button
                                type="submit"
                                disabled={!canAccept || form.processing}
                            >
                                Accept and open workspace
                            </Button>
                        </div>
                    </form>
                )}

                {activation.message_thread_url ? (
                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <Link href={activation.message_thread_url}>
                                Open message thread
                            </Link>
                        </Button>
                    </div>
                ) : null}
            </main>
        </>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}
