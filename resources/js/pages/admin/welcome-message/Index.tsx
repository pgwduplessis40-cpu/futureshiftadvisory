import { Head, useForm } from '@inertiajs/react';
import { HeartHandshake, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Placeholder = {
    token: string;
    description: string;
};

type HistoryEntry = {
    id: string;
    version: number;
    is_active: boolean;
    activated_at: string | null;
    created_by: string | null;
    characters: number;
};

type Preview = {
    has_message: boolean;
    html: string;
    version: number | null;
};

type Props = {
    current: {
        version: number;
        body: string;
        activated_at: string | null;
    } | null;
    preview: Preview;
    placeholders: Placeholder[];
    history: HistoryEntry[];
    storeUrl: string;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString();
}

export default function WelcomeMessageIndex({
    current,
    preview,
    placeholders,
    history,
    storeUrl,
}: Props) {
    const form = useForm<{ body: string }>({
        body: current?.body ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(storeUrl, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Welcome message" />

            <div className="space-y-6">
                <header className="flex items-center gap-2">
                    <HeartHandshake className="size-5" aria-hidden="true" />
                    <div>
                        <h1 className="text-xl font-semibold">
                            Welcome message
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Shown to every new client on their portal and the
                            first onboarding step, before they begin.
                        </p>
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="space-y-4 lg:col-span-2">
                        <form
                            onSubmit={submit}
                            className="space-y-4 rounded-md border bg-background p-4"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="body">Message (Markdown)</Label>
                                {current ? (
                                    <Badge variant="secondary">
                                        Active version {current.version}
                                    </Badge>
                                ) : (
                                    <Badge variant="outline">
                                        No message published yet
                                    </Badge>
                                )}
                            </div>
                            <textarea
                                id="body"
                                value={form.data.body}
                                rows={16}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                className="min-h-72 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                required
                            />
                            <InputError message={form.errors.body} />
                            <p className="text-xs text-muted-foreground">
                                Saving publishes a new version and makes it the
                                active message. Previous versions are kept as
                                history and every change is audit-logged.
                            </p>
                            <div className="flex justify-end">
                                <Button type="submit" disabled={form.processing}>
                                    <Send className="size-4" aria-hidden="true" />
                                    Publish new version
                                </Button>
                            </div>
                        </form>

                        <section className="rounded-md border bg-background p-4">
                            <h2 className="text-sm font-medium">
                                Preview (sample values)
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                This renders the currently active message with
                                example placeholder values.
                            </p>
                            {preview.has_message ? (
                                <div
                                    className="mt-4 rounded-md border border-[var(--fs-linen)] bg-[var(--fs-linen)]/50 p-5 text-sm leading-relaxed text-foreground [&_a]:text-[var(--fs-admiralty)] [&_a]:underline [&_p:last-child]:mb-0 [&_p]:mb-3 [&_strong]:font-semibold"
                                    dangerouslySetInnerHTML={{
                                        __html: preview.html,
                                    }}
                                />
                            ) : (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Publish a message to see the preview.
                                </p>
                            )}
                        </section>
                    </section>

                    <aside className="space-y-6">
                        <section className="rounded-md border bg-background p-4">
                            <h2 className="text-sm font-medium">
                                Available placeholders
                            </h2>
                            <dl className="mt-3 space-y-3 text-sm">
                                {placeholders.map((placeholder) => (
                                    <div
                                        key={placeholder.token}
                                        className="space-y-1"
                                    >
                                        <dt>
                                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                {placeholder.token}
                                            </code>
                                        </dt>
                                        <dd className="text-xs text-muted-foreground">
                                            {placeholder.description}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section className="rounded-md border bg-background p-4">
                            <h2 className="text-sm font-medium">
                                Version history
                            </h2>
                            {history.length > 0 ? (
                                <ul className="mt-3 space-y-3 text-sm">
                                    {history.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="flex items-start justify-between gap-3"
                                        >
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">
                                                        Version {entry.version}
                                                    </span>
                                                    {entry.is_active ? (
                                                        <Badge variant="secondary">
                                                            Active
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        entry.activated_at,
                                                    )}
                                                    {entry.created_by
                                                        ? ` · ${entry.created_by}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                {entry.characters} chars
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    No versions yet.
                                </p>
                            )}
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}
