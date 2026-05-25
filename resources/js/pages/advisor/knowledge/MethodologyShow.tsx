import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    LibraryBig,
    SlidersHorizontal,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type WhereUsed = {
    key: string;
    label: string;
};

type MethodologyParameter = {
    key: string;
    value: unknown;
};

type MethodologyDetail = {
    id: string;
    area: string;
    name: string;
    summary: string;
    formula: string;
    inputs: string[];
    config_refs: string[];
    parameters: MethodologyParameter[];
    where_used: WhereUsed[];
    sources: string[];
    owning_service: string;
    version: string;
    show_url: string;
};

type Props = {
    entry: MethodologyDetail;
    indexUrl: string;
    knowledgeIndexUrl: string;
};

export default function MethodologyShow({
    entry,
    indexUrl,
    knowledgeIndexUrl,
}: Props) {
    return (
        <>
            <Head title={entry.name} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <LibraryBig className="size-4" aria-hidden="true" />
                            Platform methodologies
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            {entry.name}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={indexUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Platform
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={knowledgeIndexUrl}>Knowledge</Link>
                        </Button>
                    </div>
                </div>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">{entry.area}</Badge>
                        <Badge variant="outline">{entry.id}</Badge>
                        <Badge variant="outline">{entry.version}</Badge>
                    </div>
                    <p className="text-sm leading-6 text-muted-foreground">
                        {entry.summary}
                    </p>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <BookOpen
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h2 className="text-sm font-medium">Formula</h2>
                    </div>
                    <div className="rounded-md border bg-background p-4 text-sm leading-6">
                        {entry.formula}
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <SlidersHorizontal
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h2 className="text-sm font-medium">Inputs</h2>
                    </div>
                    <div className="rounded-md border bg-background p-4">
                        <ul className="list-disc space-y-2 pl-5 text-sm">
                            {entry.inputs.map((input) => (
                                <li key={input}>{input}</li>
                            ))}
                        </ul>
                    </div>
                </section>

                {entry.parameters.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="text-sm font-medium">Live parameters</h2>
                        <div className="grid gap-3">
                            {entry.parameters.map((parameter) => (
                                <div
                                    key={parameter.key}
                                    className="rounded-md border bg-background p-4"
                                >
                                    <div className="text-sm font-medium">
                                        {parameter.key}
                                    </div>
                                    <pre className="mt-3 overflow-auto rounded-md bg-muted p-3 text-xs leading-5">
                                        {JSON.stringify(
                                            parameter.value,
                                            null,
                                            2,
                                        )}
                                    </pre>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Where Used</h2>
                    <div className="flex flex-wrap gap-2">
                        {entry.where_used.map((usage) => (
                            <Badge key={usage.key} variant="outline">
                                {usage.label}
                            </Badge>
                        ))}
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Source</h2>
                    <div className="rounded-md border bg-background p-4 text-sm">
                        <div className="break-all text-muted-foreground">
                            {entry.owning_service}
                        </div>
                        {entry.sources.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {entry.sources.map((source) => (
                                    <Badge key={source} variant="outline">
                                        {source}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

MethodologyShow.layout = {
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
