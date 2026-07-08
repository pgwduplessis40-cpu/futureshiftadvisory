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

type ClientInviteForm = Defaults;

type Props = {
    engagementTypes: EngagementTypeOption[];
    defaults: Defaults;
};

export default function ClientsInvite({ engagementTypes, defaults }: Props) {
    const form = useForm<ClientInviteForm>({ ...defaults });
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
                                    onValueChange={(value) =>
                                        form.setData('engagement_type', value)
                                    }
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
