import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { KnowledgeEntrySummary } from './types';

type Props = {
    entries: KnowledgeEntrySummary[];
    filters: {
        q: string;
    };
    canCreate: boolean;
    indexUrl: string;
    createUrl: string;
};

export default function KnowledgeIndex({
    entries,
    filters,
    canCreate,
    indexUrl,
    createUrl,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(indexUrl, query.trim() === '' ? {} : { q: query.trim() }, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Knowledge" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <BookOpen className="size-4" aria-hidden="true" />
                            Advisor repository
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Knowledge
                        </h1>
                    </div>
                    {canCreate && (
                        <Button asChild size="sm">
                            <Link href={createUrl}>
                                <Plus className="size-4" aria-hidden="true" />
                                New
                            </Link>
                        </Button>
                    )}
                </div>

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search methodology, risks, pricing"
                        aria-label="Search knowledge entries"
                    />
                    <Button type="submit" variant="outline">
                        <Search className="size-4" aria-hidden="true" />
                        Search
                    </Button>
                </form>

                {entries.length === 0 ? (
                    <div className="rounded-md border bg-background p-8 text-sm text-muted-foreground">
                        No knowledge entries found.
                    </div>
                ) : (
                    <div className="grid gap-3">
                        {entries.map((entry) => (
                            <article
                                key={entry.id}
                                className="rounded-md border bg-background p-4"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">
                                                {entry.category_label}
                                            </Badge>
                                            {entry.client && (
                                                <Badge variant="outline">
                                                    {entry.client.legal_name}
                                                </Badge>
                                            )}
                                            {entry.search_rank !== null && (
                                                <Badge variant="outline">
                                                    {entry.search_rank.toFixed(
                                                        2,
                                                    )}
                                                </Badge>
                                            )}
                                        </div>
                                        <h2 className="mt-3 text-base font-semibold">
                                            <Link href={entry.show_url}>
                                                {entry.title}
                                            </Link>
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {entry.body_excerpt}
                                        </p>
                                    </div>
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={entry.show_url}>Open</Link>
                                    </Button>
                                </div>

                                {entry.tags.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {entry.tags.map((tag) => (
                                            <Badge key={tag} variant="outline">
                                                {tag}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

KnowledgeIndex.layout = {
    breadcrumbs: [
        {
            title: 'Knowledge',
            href: '/advisor/knowledge',
        },
    ],
};
