import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Send, UserPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CapacitySummary } from './types';

type Props = {
    capacity: CapacitySummary;
};

type EntrepreneurForm = {
    name: string;
    email: string;
    concept_summary: string;
};

export default function EntrepreneursCreate({ capacity }: Props) {
    const form = useForm<EntrepreneurForm>({
        name: '',
        email: '',
        concept_summary: '',
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/advisor/entrepreneurs');
    };

    return (
        <>
            <Head title="Add entrepreneur" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Add entrepreneur</h1>
                    <Button asChild size="sm" variant="outline">
                        <Link href="/advisor/entrepreneurs">
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
                                <h2 className="text-sm font-medium">Profile</h2>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.name} />
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
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="concept_summary">
                                    Concept summary
                                </Label>
                                <textarea
                                    id="concept_summary"
                                    value={form.data.concept_summary}
                                    onChange={(event) =>
                                        form.setData(
                                            'concept_summary',
                                            event.target.value,
                                        )
                                    }
                                    rows={6}
                                    className="min-h-32 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <InputError
                                    message={form.errors.concept_summary}
                                />
                            </div>
                        </div>

                        <InputError message={errors.capacity} />

                        <Button
                            type="submit"
                            disabled={form.processing || capacity.blocked}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            Send invite
                        </Button>
                    </form>

                    <aside className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-sm font-medium">Capacity</h2>
                            <Badge
                                variant={
                                    capacity.blocked
                                        ? 'destructive'
                                        : capacity.warning
                                          ? 'outline'
                                          : 'secondary'
                                }
                            >
                                {capacity.active_count}/{capacity.limit}
                            </Badge>
                        </div>

                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label="Warning"
                                value={`${capacity.warning_threshold}`}
                            />
                            <Detail
                                label="Remaining"
                                value={`${capacity.remaining}`}
                            />
                        </dl>

                        {(capacity.warning || capacity.blocked) && (
                            <div className="flex gap-2 rounded-md border px-3 py-2 text-sm">
                                <AlertTriangle
                                    className="mt-0.5 size-4"
                                    aria-hidden="true"
                                />
                                <div>
                                    {capacity.blocked
                                        ? 'Capacity reached.'
                                        : 'Capacity warning reached.'}
                                </div>
                            </div>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[110px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

EntrepreneursCreate.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneurs',
            href: '/advisor/entrepreneurs',
        },
        {
            title: 'Add entrepreneur',
            href: '/advisor/entrepreneurs/create',
        },
    ],
};
