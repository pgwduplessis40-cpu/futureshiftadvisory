import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    TemplateFormData,
    TemplateOption,
    TemplateSummary,
} from './types';

type Props = {
    templates: TemplateSummary[];
    filters: {
        q: string;
        status: string;
    };
    categories: TemplateOption[];
    statuses: TemplateOption[];
    canManage: boolean;
    indexUrl: string;
    storeUrl: string;
};

export default function TemplateIndex({
    templates,
    filters,
    categories,
    statuses,
    canManage,
    indexUrl,
    storeUrl,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const createForm = useForm<TemplateFormData>({
        category: categories[0]?.value ?? 'report',
        title: '',
        body: '',
        status: 'active',
    });

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            indexUrl,
            {
                ...(query.trim() === '' ? {} : { q: query.trim() }),
                ...(filters.status === 'all' ? {} : { status: filters.status }),
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const changeStatus = (status: string) => {
        router.get(
            indexUrl,
            {
                ...(query.trim() === '' ? {} : { q: query.trim() }),
                ...(status === 'all' ? {} : { status }),
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const createTemplate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        createForm.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => createForm.reset('title', 'body'),
        });
    };

    return (
        <>
            <Head title="Templates" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <FileText className="size-4" aria-hidden="true" />
                            Practice library
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            Templates
                        </h1>
                    </div>
                </div>

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <form
                        onSubmit={submitSearch}
                        className="flex flex-col gap-2 sm:flex-row"
                    >
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search templates"
                            aria-label="Search templates"
                        />
                        <Button type="submit" variant="outline">
                            <Search className="size-4" aria-hidden="true" />
                            Search
                        </Button>
                    </form>

                    <div className="flex flex-wrap gap-2">
                        {statuses.map((status) => (
                            <Button
                                key={status.value}
                                type="button"
                                size="sm"
                                variant={
                                    filters.status === status.value
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => changeStatus(status.value)}
                            >
                                {status.label}
                            </Button>
                        ))}
                    </div>
                </div>

                {canManage && (
                    <form
                        onSubmit={createTemplate}
                        className="grid gap-3 rounded-md border bg-background p-4 lg:grid-cols-[11rem_1fr_9rem_auto] lg:items-end"
                    >
                        <label className="grid gap-1 text-sm">
                            <span className="text-xs text-muted-foreground">
                                Category
                            </span>
                            <select
                                className="h-9 rounded-md border bg-background px-3"
                                value={createForm.data.category}
                                onChange={(event) =>
                                    createForm.setData(
                                        'category',
                                        event.target.value,
                                    )
                                }
                            >
                                {categories.map((category) => (
                                    <option
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="grid gap-1 text-sm">
                            <span className="text-xs text-muted-foreground">
                                Title
                            </span>
                            <Input
                                value={createForm.data.title}
                                onChange={(event) =>
                                    createForm.setData(
                                        'title',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>

                        <label className="grid gap-1 text-sm">
                            <span className="text-xs text-muted-foreground">
                                Status
                            </span>
                            <select
                                className="h-9 rounded-md border bg-background px-3"
                                value={createForm.data.status}
                                onChange={(event) =>
                                    createForm.setData(
                                        'status',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </label>

                        <Button type="submit" disabled={createForm.processing}>
                            <Plus className="size-4" aria-hidden="true" />
                            New
                        </Button>

                        <label className="grid gap-1 text-sm lg:col-span-4">
                            <span className="text-xs text-muted-foreground">
                                Body
                            </span>
                            <textarea
                                className="min-h-24 rounded-md border bg-background px-3 py-2"
                                value={createForm.data.body}
                                onChange={(event) =>
                                    createForm.setData(
                                        'body',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                    </form>
                )}

                {templates.length === 0 ? (
                    <div className="rounded-md border bg-background p-8 text-sm text-muted-foreground">
                        No templates found.
                    </div>
                ) : (
                    <div className="grid gap-3">
                        {templates.map((template) => (
                            <article
                                key={template.id}
                                className="rounded-md border bg-background p-4"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant="secondary">
                                                {template.category_label}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    template.status === 'active'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {template.status}
                                            </Badge>
                                            <Badge variant="outline">
                                                v{template.version}
                                            </Badge>
                                        </div>
                                        <h2 className="mt-3 text-base font-semibold">
                                            <Link href={template.show_url}>
                                                {template.title}
                                            </Link>
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {template.body_excerpt}
                                        </p>
                                    </div>
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={template.show_url}>
                                            Open
                                        </Link>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

TemplateIndex.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: '/advisor/templates',
        },
    ],
};
