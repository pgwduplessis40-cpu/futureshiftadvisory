import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EngagementTypeOption } from './types';

type Defaults = {
    email: string;
    engagement_type: string;
    return_to: string;
};

type ServiceOfferPackage = {
    id: string;
    label: string;
    description: string;
    fee: number | null;
    currency: string;
    scope_label: string;
};

type ClientInviteForm = Defaults & {
    due_diligence_package_id: string;
    dd_plan_budget_package_id: string;
};

type Props = {
    engagementTypes: EngagementTypeOption[];
    serviceOfferPackages: {
        due_diligence: ServiceOfferPackage[];
        dd_plan_budget: ServiceOfferPackage[];
    };
    defaults: Defaults;
};

export default function ClientsInvite({
    engagementTypes,
    serviceOfferPackages,
    defaults,
}: Props) {
    const form = useForm<ClientInviteForm>({
        ...defaults,
        due_diligence_package_id: '',
        dd_plan_budget_package_id: '',
    });
    const selectedEngagement = engagementTypes.find(
        (type) => type.value === form.data.engagement_type,
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/advisor/clients/invite');
    };

    return (
        <>
            <Head title="Invite client" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Invite client</h1>
                    <Button asChild size="sm" variant="outline">
                        <Link href={defaults.return_to || '/advisor/clients'}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <form onSubmit={submit} className="space-y-6">
                        <div className="space-y-4 rounded-md border p-4">
                            <div className="flex items-center gap-2">
                                <UserPlus
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">Invite</h2>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="engagement_type">
                                    Client pathway
                                </Label>
                                <Select
                                    value={form.data.engagement_type}
                                    onValueChange={(value) => {
                                        form.setData('engagement_type', value);

                                        if (value !== 'due_diligence') {
                                            form.setData(
                                                'due_diligence_package_id',
                                                '',
                                            );
                                            form.setData(
                                                'dd_plan_budget_package_id',
                                                '',
                                            );
                                        }
                                    }}
                                >
                                    <SelectTrigger
                                        id="engagement_type"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {engagementTypes.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedEngagement ? (
                                    <p className="text-sm text-muted-foreground">
                                        {selectedEngagement.description}
                                    </p>
                                ) : null}
                                <InputError
                                    message={form.errors.engagement_type}
                                />
                            </div>

                            {form.data.engagement_type === 'due_diligence' ? (
                                <div className="space-y-4 rounded-md border border-[var(--fs-linen)] bg-muted/30 p-4">
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            Included service and agreed price
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Select the exact DD package the
                                            client will see and agree to during
                                            onboarding. Other services remain
                                            available only by request.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="due_diligence_package_id">
                                            Due Diligence package
                                        </Label>
                                        <Select
                                            value={
                                                form.data
                                                    .due_diligence_package_id
                                            }
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'due_diligence_package_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="due_diligence_package_id">
                                                <SelectValue placeholder="Select the agreed DD package" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {serviceOfferPackages.due_diligence.map(
                                                    (servicePackage) => (
                                                        <SelectItem
                                                            key={
                                                                servicePackage.id
                                                            }
                                                            value={
                                                                servicePackage.id
                                                            }
                                                        >
                                                            {
                                                                servicePackage.label
                                                            }{' '}
                                                            —{' '}
                                                            {formatMoney(
                                                                servicePackage.fee,
                                                                servicePackage.currency,
                                                            )}{' '}
                                                            ex GST
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <PackageHint
                                            packages={
                                                serviceOfferPackages.due_diligence
                                            }
                                            id={
                                                form.data
                                                    .due_diligence_package_id
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors
                                                    .due_diligence_package_id
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="dd_plan_budget_package_id">
                                            Business Plan &amp; Budget add-on
                                            <span className="ml-1 font-normal text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <Select
                                            value={
                                                form.data
                                                    .dd_plan_budget_package_id ||
                                                'not-included'
                                            }
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'dd_plan_budget_package_id',
                                                    value === 'not-included'
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="dd_plan_budget_package_id">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="not-included">
                                                    Not included — client can
                                                    request it later
                                                </SelectItem>
                                                {serviceOfferPackages.dd_plan_budget.map(
                                                    (servicePackage) => (
                                                        <SelectItem
                                                            key={
                                                                servicePackage.id
                                                            }
                                                            value={
                                                                servicePackage.id
                                                            }
                                                        >
                                                            {
                                                                servicePackage.label
                                                            }{' '}
                                                            —{' '}
                                                            {formatMoney(
                                                                servicePackage.fee,
                                                                servicePackage.currency,
                                                            )}{' '}
                                                            ex GST
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <PackageHint
                                            packages={
                                                serviceOfferPackages.dd_plan_budget
                                            }
                                            id={
                                                form.data
                                                    .dd_plan_budget_package_id
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors
                                                    .dd_plan_budget_package_id
                                            }
                                        />
                                    </div>
                                </div>
                            ) : null}
                        </div>

                        <InputError message={form.errors.return_to} />

                        <Button type="submit" disabled={form.processing}>
                            <Send className="size-4" aria-hidden="true" />
                            Send invite
                        </Button>
                    </form>

                    <aside className="space-y-3 rounded-md border p-4 text-sm text-muted-foreground">
                        <h2 className="text-sm font-medium text-foreground">
                            Client-led setup
                        </h2>
                        <p>
                            The client receives a secure account invitation and
                            completes their own onboarding details.
                        </p>
                    </aside>
                </div>
            </div>
        </>
    );
}

function PackageHint({
    packages,
    id,
}: {
    packages: ServiceOfferPackage[];
    id: string;
}) {
    const servicePackage = packages.find((item) => item.id === id);

    if (!servicePackage) {
        return null;
    }

    return (
        <p className="text-xs text-muted-foreground">
            {servicePackage.scope_label}: {servicePackage.description} The
            client will be asked to acknowledge{' '}
            {formatMoney(servicePackage.fee, servicePackage.currency)} ex GST
            during onboarding.
        </p>
    );
}

function formatMoney(amount: number | null, currency: string): string {
    if (amount === null) {
        return 'Fee to be confirmed';
    }

    return new Intl.NumberFormat('en-NZ', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount);
}

ClientsInvite.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
        {
            title: 'Invite client',
            href: '/advisor/clients/invite',
        },
    ],
};
