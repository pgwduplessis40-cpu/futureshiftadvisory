import { Head, useForm } from '@inertiajs/react';
import { History, Save, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type GovernanceVersion = {
    id: string;
    version: number;
    principles: string[];
    roles: string[];
    notes: string | null;
    is_active: boolean;
    activated_at: string | null;
    created_at: string | null;
    created_by?: string | null;
};

type Props = {
    current: GovernanceVersion | null;
    defaults: {
        principles: string[];
        roles: string[];
    };
    history: GovernanceVersion[];
    storeUrl: string;
};

export default function PrinciplesRolesIndex({
    current,
    defaults,
    history,
    storeUrl,
}: Props) {
    const form = useForm({
        principles_text: joinLines(current?.principles ?? defaults.principles),
        roles_text: joinLines(current?.roles ?? defaults.roles),
        notes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => form.reset('notes'),
        });
    }

    return (
        <>
            <Head title="Principles & Roles" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={ShieldCheck}
                    title="Principles & Roles"
                    description="Set the non-negotiable rules and system roles the platform and AI assistant must follow."
                    actions={
                        <Badge variant="outline">
                            Version {current?.version ?? 1}
                        </Badge>
                    }
                />

                <Alert>
                    <ShieldCheck className="size-4" aria-hidden="true" />
                    <AlertTitle>
                        Critical - Non-Negotiable Platform Principle
                    </AlertTitle>
                    <AlertDescription>
                        The active version is injected into AI prompt envelopes
                        and must be followed by the system and AI assistant.
                        These rules are honest, evidence-based, accurate, free
                        from bias, truthful, and explicit about advisor
                        visibility where facts conflict.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.55fr)]">
                    <form
                        onSubmit={submit}
                        className="space-y-4 rounded-md border bg-background p-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="principles_text">
                                Principles
                            </Label>
                            <textarea
                                id="principles_text"
                                value={form.data.principles_text}
                                onChange={(event) =>
                                    form.setData(
                                        'principles_text',
                                        event.target.value,
                                    )
                                }
                                rows={14}
                                className={textareaClassName}
                            />
                            <InputError
                                message={form.errors.principles_text}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="roles_text">Roles</Label>
                            <textarea
                                id="roles_text"
                                value={form.data.roles_text}
                                onChange={(event) =>
                                    form.setData(
                                        'roles_text',
                                        event.target.value,
                                    )
                                }
                                rows={8}
                                className={textareaClassName}
                            />
                            <InputError message={form.errors.roles_text} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Change notes</Label>
                            <Input
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                <Save className="size-4" aria-hidden="true" />
                                Save new version
                            </Button>
                        </div>
                    </form>

                    <div className="space-y-4">
                        <section className="rounded-md border bg-background p-4">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-sm font-medium">
                                    Active version
                                </h2>
                                <Badge variant="secondary">
                                    v{current?.version ?? 1}
                                </Badge>
                            </div>
                            <div className="mt-4 space-y-4">
                                <PreviewList
                                    title="Principles"
                                    items={
                                        current?.principles ??
                                        defaults.principles
                                    }
                                />
                                <PreviewList
                                    title="Roles"
                                    items={current?.roles ?? defaults.roles}
                                />
                            </div>
                        </section>

                        <section className="rounded-md border bg-background p-4">
                            <div className="flex items-center gap-2">
                                <History
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Version history
                                </h2>
                            </div>
                            <div className="mt-4 divide-y rounded-md border">
                                {history.length > 0 ? (
                                    history.map((version) => (
                                        <div
                                            key={version.id}
                                            className="grid gap-1 p-3 text-sm"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="font-medium">
                                                    Version {version.version}
                                                </span>
                                                {version.is_active ? (
                                                    <Badge variant="secondary">
                                                        Active
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {formatDate(
                                                    version.activated_at ??
                                                        version.created_at,
                                                )}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {version.principles.length}{' '}
                                                principles /{' '}
                                                {version.roles.length} roles
                                            </div>
                                            {version.notes ? (
                                                <p className="text-muted-foreground">
                                                    {version.notes}
                                                </p>
                                            ) : null}
                                        </div>
                                    ))
                                ) : (
                                    <div className="p-3 text-sm text-muted-foreground">
                                        No governance versions yet.
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}

function PreviewList({ title, items }: { title: string; items: string[] }) {
    return (
        <div>
            <h3 className="text-sm font-medium">{title}</h3>
            <ul className="mt-2 space-y-2 text-sm text-muted-foreground">
                {items.map((item, index) => (
                    <li key={`${title}-${index}`} className="flex gap-2">
                        <span className="mt-1 size-1.5 shrink-0 rounded-full bg-muted-foreground" />
                        <span>{item}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function joinLines(items: string[]): string {
    return items.join('\n');
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Date not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

const textareaClassName = cn(
    'min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
);
