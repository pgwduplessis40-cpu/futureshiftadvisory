import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BriefcaseBusiness, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Activation = {
    id: string;
    client_id: string;
    client_name: string | null;
    client_label: string;
    service_type: 'due_diligence' | 'entrepreneur';
    status: string;
    status_label: string;
    intake: Record<string, string | number | null>;
    package: PackagePayload | null;
    accepted_at: string | null;
    workspace: {
        dd_engagement_id: string | null;
        entrepreneur_profile_id: string | null;
    };
};

type PackagePayload = {
    id: string;
    service_type: string;
    package_name: string;
    client_label: string;
    billing_model: string;
    fixed_fee: number | null;
    hourly_rate: number | null;
    retainer_amount: number | null;
    purchase_price_min: number | null;
    purchase_price_max: number | null;
    currency: string;
    scope_description: string;
    is_active: boolean;
};

type Props = {
    activation: Activation;
    packages: PackagePayload[];
    urls: {
        index: string;
        package: string;
        client: string;
    };
};

export default function ServiceActivationShow({
    activation,
    packages,
    urls,
}: Props) {
    const form = useForm({
        service_rate_package_id: activation.package?.id ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(urls.package);
    }

    return (
        <>
            <Head title={activation.client_label} />

            <main className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={urls.index}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Requests
                            </Link>
                        </Button>
                        <div className="mt-3 flex items-center gap-2">
                            <BriefcaseBusiness
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <h1 className="text-xl font-semibold">
                                {activation.client_label}
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {activation.client_name ?? 'Client'} requested a
                            cross-service workspace.
                        </p>
                    </div>
                    <Badge variant="secondary">{activation.status_label}</Badge>
                </div>

                <section className="rounded-md border bg-background p-4">
                    <h2 className="text-sm font-medium">Client intake</h2>
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

                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-md border bg-background p-4"
                >
                    <div>
                        <h2 className="text-sm font-medium">Select package</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            The client can accept the workspace only after you
                            select one active package from Admin Service Rates.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="service_rate_package_id">
                            Active package
                        </Label>
                        <select
                            id="service_rate_package_id"
                            value={form.data.service_rate_package_id}
                            onChange={(event) =>
                                form.setData(
                                    'service_rate_package_id',
                                    event.target.value,
                                )
                            }
                            className="h-10 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Choose a package</option>
                            {packages.map((servicePackage) => (
                                <option
                                    key={servicePackage.id}
                                    value={servicePackage.id}
                                >
                                    {servicePackage.client_label} /{' '}
                                    {packageFee(servicePackage)}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={form.errors.service_rate_package_id}
                        />
                    </div>

                    {packages.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No active package exists for this service. Add one
                            under Admin / Service Rates before approving the
                            request.
                        </p>
                    ) : null}

                    <div className="grid gap-3 lg:grid-cols-2">
                        {packages.map((servicePackage) => (
                            <div
                                key={servicePackage.id}
                                className="rounded-md border p-3"
                            >
                                <div className="font-medium">
                                    {servicePackage.client_label}
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    {packageFee(servicePackage)}
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {servicePackage.scope_description}
                                </p>
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-between gap-3">
                        <Button asChild variant="outline">
                            <Link href={urls.client}>Open client</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.service_rate_package_id === ''
                            }
                        >
                            <Save className="size-4" aria-hidden="true" />
                            Save package
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

function packageFee(servicePackage: PackagePayload) {
    if (servicePackage.billing_model === 'fixed_fee') {
        return servicePackage.fixed_fee !== null
            ? `${formatMoney(servicePackage.fixed_fee, servicePackage.currency)} ex GST`
            : 'Fixed fee not set';
    }

    if (servicePackage.billing_model === 'hourly_retainer') {
        const hourly =
            servicePackage.hourly_rate !== null
                ? `${formatMoney(servicePackage.hourly_rate, servicePackage.currency)} ex GST`
                : 'Hourly not set';
        const retainer =
            servicePackage.retainer_amount !== null
                ? formatMoney(
                      servicePackage.retainer_amount,
                      servicePackage.currency,
                  ) + ' ex GST'
                : 'retainer not set';

        return `${hourly} / ${retainer}`;
    }

    return 'Proposal flow';
}

function formatMoney(value: number, currency: string) {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatLabel(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

ServiceActivationShow.layout = {
    breadcrumbs: [
        {
            title: 'Service activations',
            href: '/advisor/service-activations',
        },
    ],
};
