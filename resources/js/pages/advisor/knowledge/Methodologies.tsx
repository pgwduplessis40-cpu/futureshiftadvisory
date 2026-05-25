import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    ExternalLink,
    LibraryBig,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type WhereUsed = {
    key: string;
    label: string;
};

type MethodologySummary = {
    id: string;
    area: string;
    name: string;
    summary: string;
    where_used: WhereUsed[];
    show_url: string;
};

type Props = {
    entries: MethodologySummary[];
    areas: string[];
    filters: {
        q: string;
    };
    indexUrl: string;
    knowledgeIndexUrl: string;
};

export default function Methodologies({
    entries,
    areas,
    filters,
    indexUrl,
    knowledgeIndexUrl,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const grouped = useMemo(
        () =>
            areas
                .map((area) => ({
                    area,
                    entries: entries.filter((entry) => entry.area === area),
                }))
                .filter((group) => group.entries.length > 0),
        [areas, entries],
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(indexUrl, query.trim() === '' ? {} : { q: query.trim() }, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Platform methodologies" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <LibraryBig className="size-4" aria-hidden="true" />
                            Platform methodologies
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Methodologies
                        </h1>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href={knowledgeIndexUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Knowledge
                        </Link>
                    </Button>
                </div>

                <form
                    onSubmit={submit}
                    className="flex flex-col gap-2 sm:flex-row"
                >
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search formula, area, feature"
                        aria-label="Search platform methodologies"
                    />
                    <Button type="submit" variant="outline">
                        <Search className="size-4" aria-hidden="true" />
                        Search
                    </Button>
                </form>

                {entries.length === 0 ? (
                    <div className="rounded-md border bg-background p-8 text-sm text-muted-foreground">
                        No methodologies found.
                    </div>
                ) : (
                    <div className="space-y-5">
                        {grouped.map((group) => (
                            <section key={group.area} className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <BookOpen
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <h2 className="text-sm font-medium">
                                        {group.area}
                                    </h2>
                                </div>
                                <div className="grid gap-3">
                                    {group.entries.map((entry) => (
                                        <article
                                            key={entry.id}
                                            className="rounded-md border bg-background p-4"
                                            title={entry.summary}
                                        >
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge variant="secondary">
                                                            {entry.id}
                                                        </Badge>
                                                        {entry.where_used
                                                            .slice(0, 3)
                                                            .map((usage) => (
                                                                <Badge
                                                                    key={
                                                                        usage.key
                                                                    }
                                                                    variant="outline"
                                                                >
                                                                    {
                                                                        usage.label
                                                                    }
                                                                </Badge>
                                                            ))}
                                                    </div>
                                                    <h3 className="mt-3 text-base font-semibold">
                                                        <Link
                                                            href={
                                                                entry.show_url
                                                            }
                                                        >
                                                            {entry.name}
                                                        </Link>
                                                    </h3>
                                                    <p className="mt-2 text-sm text-muted-foreground">
                                                        {entry.summary}
                                                    </p>
                                                </div>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link href={entry.show_url}>
                                                        <ExternalLink
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        Open
                                                    </Link>
                                                </Button>
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Methodologies.layout = {
    breadcrumbs: [
        {
            title: 'Knowledge',
            href: '/advisor/knowledge',
        },
        {
            title: 'Platform',
            href: '/advisor/knowledge/methodologies',
        },
    ],
};
