import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
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

type IndustryOption = {
    value: string;
    label: string;
};

type PartnerInviteForm = {
    business_name: string;
    contact_name: string;
    email: string;
    industry: string;
    focus: string;
    notes: string;
};

type Props = {
    title: string;
    panelType: 'broker' | 'coach';
    panelLabel: string;
    backUrl: string;
    storeUrl: string;
    industryOptions: IndustryOption[];
};

export default function PartnerInviteCreate({
    title,
    panelType,
    panelLabel,
    backUrl,
    storeUrl,
    industryOptions,
}: Props) {
    const form = useForm<PartnerInviteForm>({
        business_name: '',
        contact_name: '',
        email: '',
        industry: industryOptions[0]?.value ?? '',
        focus: '',
        notes: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeUrl);
    };

    return (
        <>
            <Head title={title} />

            <div className="space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Partners / {panelLabel}
                        </p>
                        <h1 className="text-xl font-semibold">{title}</h1>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href={backUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </header>

                <form onSubmit={submit} className="max-w-3xl space-y-5">
                    <div className="space-y-4 rounded-md border p-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="business_name">
                                    Business name
                                </Label>
                                <Input
                                    id="business_name"
                                    value={form.data.business_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'business_name',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.business_name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="contact_name">
                                    {panelLabel} name
                                </Label>
                                <Input
                                    id="contact_name"
                                    value={form.data.contact_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'contact_name',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.contact_name} />
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

                            {panelType === 'broker' ? (
                                <div className="grid gap-2">
                                    <Label>Industry</Label>
                                    <Select
                                        value={form.data.industry}
                                        onValueChange={(value) =>
                                            form.setData('industry', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {industryOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.industry} />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="focus">Focus</Label>
                                    <Input
                                        id="focus"
                                        value={form.data.focus}
                                        onChange={(event) =>
                                            form.setData(
                                                'focus',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.focus} />
                                </div>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                rows={5}
                                className="min-h-28 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        <Send className="size-4" aria-hidden="true" />
                        Send invite
                    </Button>
                </form>
            </div>
        </>
    );
}

PartnerInviteCreate.layout = {
    breadcrumbs: [
        {
            title: 'Partners',
            href: '/advisor/partners/brokers',
        },
    ],
};
