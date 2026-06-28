import { Head, Link, useForm } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Lightbulb,
    Send,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type ServiceType = 'due_diligence' | 'entrepreneur';

type ServiceOption = {
    service_type: ServiceType;
    label: string;
    description: string;
    available: boolean;
    start_url: string;
};

type Props = {
    service: ServiceOption;
    requestUrl: string;
    dashboardUrl: string;
};

export default function ServiceActivationRequest({
    service,
    requestUrl,
    dashboardUrl,
}: Props) {
    const isDueDiligence = service.service_type === 'due_diligence';
    const form = useForm<ServiceActivationForm>({
        service_type: service.service_type,
        target_name: '',
        vendor_name: '',
        industry: '',
        asking_price: '',
        idea_name: '',
        customer: '',
        problem: '',
        timing: '',
        notes: '',
    });
    const Icon = isDueDiligence ? BriefcaseBusiness : Lightbulb;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(requestUrl);
    }

    return (
        <>
            <Head title={service.label} />

            <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6">
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
                        <div className="mt-4 flex items-start gap-3">
                            <span className="rounded-md border bg-background p-2">
                                <Icon
                                    className="size-5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </span>
                            <div>
                                <h1 className="text-xl font-semibold">
                                    {service.label}
                                </h1>
                                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                    {service.description}
                                </p>
                            </div>
                        </div>
                    </div>
                    <Badge variant="secondary">
                        {service.available ? 'Available' : 'Already open'}
                    </Badge>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-md border bg-background p-4"
                >
                    <div className="grid gap-4">
                        {isDueDiligence ? (
                            <DueDiligenceFields form={form} />
                        ) : (
                            <EntrepreneurFields form={form} />
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="service_timing">Timing</Label>
                            <Input
                                id="service_timing"
                                value={form.data.timing}
                                onChange={(event) =>
                                    form.setData(
                                        'timing',
                                        event.target.value,
                                    )
                                }
                                placeholder="Now, next month, after funding, still exploring"
                            />
                            <InputError message={form.errors.timing} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="service_notes">Notes</Label>
                            <textarea
                                id="service_notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                rows={5}
                                className={cn(
                                    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
                                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                )}
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </div>

                    <div className="mt-5 flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Send className="size-4" aria-hidden="true" />
                            Request workspace
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}

function DueDiligenceFields({
    form,
}: {
    form: InertiaFormProps<ServiceActivationForm>;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-4">
            <div className="grid gap-2 lg:col-span-2">
                <Label htmlFor="service_target_name">Target business</Label>
                <Input
                    id="service_target_name"
                    value={form.data.target_name}
                    onChange={(event) =>
                        form.setData('target_name', event.target.value)
                    }
                />
                <InputError message={form.errors.target_name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="service_vendor_name">Vendor</Label>
                <Input
                    id="service_vendor_name"
                    value={form.data.vendor_name}
                    onChange={(event) =>
                        form.setData('vendor_name', event.target.value)
                    }
                />
                <InputError message={form.errors.vendor_name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="service_asking_price">Asking price</Label>
                <Input
                    id="service_asking_price"
                    inputMode="decimal"
                    value={form.data.asking_price}
                    onChange={(event) =>
                        form.setData('asking_price', event.target.value)
                    }
                />
                <InputError message={form.errors.asking_price} />
            </div>
            <div className="grid gap-2 lg:col-span-4">
                <Label htmlFor="service_industry">Industry</Label>
                <Input
                    id="service_industry"
                    value={form.data.industry}
                    onChange={(event) =>
                        form.setData('industry', event.target.value)
                    }
                />
                <InputError message={form.errors.industry} />
            </div>
        </div>
    );
}

function EntrepreneurFields({
    form,
}: {
    form: InertiaFormProps<ServiceActivationForm>;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="grid gap-2 lg:col-span-3">
                <Label htmlFor="service_idea_name">Idea or concept</Label>
                <Input
                    id="service_idea_name"
                    value={form.data.idea_name}
                    onChange={(event) =>
                        form.setData('idea_name', event.target.value)
                    }
                />
                <InputError message={form.errors.idea_name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="service_idea_industry">Industry</Label>
                <Input
                    id="service_idea_industry"
                    value={form.data.industry}
                    onChange={(event) =>
                        form.setData('industry', event.target.value)
                    }
                />
                <InputError message={form.errors.industry} />
            </div>
            <div className="grid gap-2 lg:col-span-2">
                <Label htmlFor="service_customer">Customer</Label>
                <Input
                    id="service_customer"
                    value={form.data.customer}
                    onChange={(event) =>
                        form.setData('customer', event.target.value)
                    }
                />
                <InputError message={form.errors.customer} />
            </div>
            <div className="grid gap-2 lg:col-span-3">
                <Label htmlFor="service_problem">Problem to solve</Label>
                <textarea
                    id="service_problem"
                    value={form.data.problem}
                    onChange={(event) =>
                        form.setData('problem', event.target.value)
                    }
                    rows={4}
                    className={cn(
                        'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    )}
                />
                <InputError message={form.errors.problem} />
            </div>
        </div>
    );
}

type ServiceActivationForm = {
    service_type: ServiceType;
    target_name: string;
    vendor_name: string;
    industry: string;
    asking_price: string;
    idea_name: string;
    customer: string;
    problem: string;
    timing: string;
    notes: string;
};
