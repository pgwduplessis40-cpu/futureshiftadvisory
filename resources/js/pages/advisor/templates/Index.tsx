import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Eye, FileText, Save, Search, Upload } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
    reportTypes: TemplateOption[];
    canManage: boolean;
    indexUrl: string;
    storeUrl: string;
    reportTemplateStatus: {
        hasActiveReportTemplate: boolean;
    };
};

export default function TemplateIndex({
    templates,
    filters,
    categories,
    statuses,
    reportTypes,
    canManage,
    indexUrl,
    storeUrl,
    reportTemplateStatus,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const createForm = useForm<TemplateFormData>({
        category: categories[0]?.value ?? 'report',
        title: '',
        body: '',
        status: 'active',
        report_type: '',
        accent_color: '#2f6f5e',
        file: null,
    });
    const selectedFileName = createForm.data.file?.name ?? null;

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
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => createForm.reset('title', 'body', 'file'),
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

                {!reportTemplateStatus.hasActiveReportTemplate && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        Reports are using the system layout because no active
                        Report template is configured.
                    </div>
                )}

                {canManage && (
                    <form
                        onSubmit={createTemplate}
                        className="grid gap-3 rounded-md border bg-background p-4 lg:grid-cols-[11rem_1fr_9rem_10rem_8rem]"
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
                            <InputError message={createForm.errors.category} />
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
                            <InputError message={createForm.errors.title} />
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
                            <InputError message={createForm.errors.status} />
                        </label>

                        {createForm.data.category === 'report' && (
                            <>
                                <label className="grid gap-1 text-sm">
                                    <span className="text-xs text-muted-foreground">
                                        Report type
                                    </span>
                                    <select
                                        className="h-9 rounded-md border bg-background px-3"
                                        value={createForm.data.report_type}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'report_type',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Generic</option>
                                        {reportTypes.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={createForm.errors.report_type}
                                    />
                                </label>

                                <label className="grid gap-1 text-sm">
                                    <span className="text-xs text-muted-foreground">
                                        Accent
                                    </span>
                                    <Input
                                        type="color"
                                        value={createForm.data.accent_color}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'accent_color',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={createForm.errors.accent_color}
                                    />
                                </label>
                            </>
                        )}

                        <label className="grid gap-1 text-sm lg:col-span-5">
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
                            <InputError message={createForm.errors.body} />
                        </label>

                        <label className="grid gap-1 text-sm lg:col-span-5">
                            <span className="text-xs text-muted-foreground">
                                Upload template file
                            </span>
                            <Input
                                type="file"
                                accept=".doc,.docx,.dot,.dotx,.pdf,.png,.jpg,.jpeg"
                                onChange={(event) =>
                                    createForm.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={createForm.errors.file} />
                        </label>

                        <div className="flex flex-col gap-3 border-t pt-3 sm:flex-row sm:items-center sm:justify-between lg:col-span-5">
                            <div className="min-h-5 text-xs text-muted-foreground">
                                {selectedFileName
                                    ? `Selected file: ${selectedFileName}`
                                    : 'Add body text or choose a file, then save the template.'}
                            </div>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="sm:self-end"
                            >
                                <Save className="size-4" aria-hidden="true" />
                                Save template
                            </Button>
                        </div>
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
                                            <Badge variant="outline">
                                                {template.usage_label}
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
                                            <Badge variant="outline">
                                                {template.uploaded_file
                                                    ? 'source file'
                                                    : 'source missing'}
                                            </Badge>
                                            {template.uploaded_file
                                                ?.is_quarantined && (
                                                <Badge variant="secondary">
                                                    Quarantined
                                                </Badge>
                                            )}
                                        </div>
                                        <h2 className="mt-3 text-base font-semibold">
                                            <Link href={template.show_url}>
                                                {template.title}
                                            </Link>
                                        </h2>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {template.body_excerpt}
                                        </p>
                                        <p className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                            <Upload
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            {template.uploaded_file ? (
                                                <>
                                                    {
                                                        template.uploaded_file
                                                            .original_name
                                                    }
                                                    {template.uploaded_file
                                                        .is_quarantined
                                                        ? ' - locked until malware scanning completes'
                                                        : ''}
                                                </>
                                            ) : (
                                                <>No source file attached</>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {template.view_url && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={template.view_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <Eye
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    View
                                                </a>
                                            </Button>
                                        )}
                                        {template.download_url && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a href={template.download_url}>
                                                    <Download
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Download
                                                </a>
                                            </Button>
                                        )}
                                        {!template.uploaded_file && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link href={template.show_url}>
                                                    <Upload
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Attach source
                                                </Link>
                                            </Button>
                                        )}
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={template.show_url}>
                                                Details
                                            </Link>
                                        </Button>
                                    </div>
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
