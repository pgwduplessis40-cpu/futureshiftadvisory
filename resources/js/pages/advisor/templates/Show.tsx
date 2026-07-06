import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, Eye, FileText, Save, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { TemplateDetail, TemplateFormData, TemplateOption } from './types';

type Props = {
    template: TemplateDetail;
    categories: TemplateOption[];
    statuses: TemplateOption[];
    reportTypes: TemplateOption[];
    canManage: boolean;
    indexUrl: string;
};

export default function TemplateShow({
    template,
    categories,
    statuses,
    reportTypes,
    canManage,
    indexUrl,
}: Props) {
    const form = useForm<TemplateFormData>({
        category: template.category,
        title: template.title,
        body: template.body,
        status: template.status,
        report_type: template.report_type ?? '',
        accent_color: template.layout?.accent_color ?? '#2f6f5e',
        file: null,
    });
    const selectedFileName = form.data.file?.name ?? null;

    const updateTemplate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({ ...data, _method: 'patch' }));
        form.post(template.update_url, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return (
        <>
            <Head title={template.title} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <FileText className="size-4" aria-hidden="true" />
                            Template
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            {template.title}
                        </h1>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href={indexUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <section className="space-y-4 rounded-md border bg-background p-4">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {template.category_label}
                        </Badge>
                        <Badge variant="outline">{template.usage_label}</Badge>
                        <Badge
                            variant={
                                template.status === 'active'
                                    ? 'default'
                                    : 'outline'
                            }
                        >
                            {template.status}
                        </Badge>
                        <Badge variant="outline">v{template.version}</Badge>
                        {template.creator_name && (
                            <Badge variant="outline">
                                {template.creator_name}
                            </Badge>
                        )}
                        <Badge variant="outline">
                            {template.uploaded_file
                                ? 'source file'
                                : 'source missing'}
                        </Badge>
                        {template.report_type && (
                            <Badge variant="outline">
                                {formatReportType(template.report_type)}
                            </Badge>
                        )}
                    </div>

                    <div className="flex flex-col gap-3 rounded-md border bg-muted/30 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0 text-sm">
                            <div className="flex items-center gap-2 font-medium">
                                <Upload className="size-4" aria-hidden="true" />
                                {template.uploaded_file ? (
                                    template.uploaded_file.original_name
                                ) : (
                                    <>No source file attached</>
                                )}
                            </div>
                            {template.uploaded_file && (
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {formatBytes(
                                        template.uploaded_file.byte_size,
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {template.view_url && (
                                <Button asChild size="sm" variant="outline">
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
                                <Button asChild size="sm" variant="outline">
                                    <a href={template.download_url}>
                                        <Download
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Download
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="prose prose-sm max-w-none text-sm leading-6 whitespace-pre-wrap">
                        {template.body}
                    </div>
                </section>

                {canManage && (
                    <form
                        onSubmit={updateTemplate}
                        className="grid gap-4 rounded-md border bg-background p-4"
                    >
                        <div className="grid gap-3 lg:grid-cols-[11rem_1fr_9rem_10rem_8rem]">
                            <label className="grid gap-1 text-sm">
                                <span className="text-xs text-muted-foreground">
                                    Category
                                </span>
                                <select
                                    className="h-9 rounded-md border bg-background px-3"
                                    value={form.data.category}
                                    onChange={(event) =>
                                        form.setData(
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
                                <InputError message={form.errors.category} />
                            </label>

                            <label className="grid gap-1 text-sm">
                                <span className="text-xs text-muted-foreground">
                                    Title
                                </span>
                                <Input
                                    value={form.data.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.title} />
                            </label>

                            <label className="grid gap-1 text-sm">
                                <span className="text-xs text-muted-foreground">
                                    Status
                                </span>
                                <select
                                    className="h-9 rounded-md border bg-background px-3"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {statuses.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.status} />
                            </label>

                            {form.data.category === 'report' && (
                                <>
                                    <label className="grid gap-1 text-sm">
                                        <span className="text-xs text-muted-foreground">
                                            Report type
                                        </span>
                                        <select
                                            className="h-9 rounded-md border bg-background px-3"
                                            value={form.data.report_type}
                                            onChange={(event) =>
                                                form.setData(
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
                                            message={form.errors.report_type}
                                        />
                                    </label>

                                    <label className="grid gap-1 text-sm">
                                        <span className="text-xs text-muted-foreground">
                                            Accent
                                        </span>
                                        <Input
                                            type="color"
                                            value={form.data.accent_color}
                                            onChange={(event) =>
                                                form.setData(
                                                    'accent_color',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors.accent_color}
                                        />
                                    </label>
                                </>
                            )}
                        </div>

                        <label className="grid gap-1 text-sm">
                            <span className="text-xs text-muted-foreground">
                                Body
                            </span>
                            <textarea
                                className="min-h-64 rounded-md border bg-background px-3 py-2"
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                            />
                            <InputError message={form.errors.body} />
                        </label>

                        <label className="grid gap-1 text-sm">
                            <span className="text-xs text-muted-foreground">
                                {template.uploaded_file
                                    ? 'Replace source file'
                                    : 'Attach source file'}
                            </span>
                            <Input
                                type="file"
                                accept=".doc,.docx,.dot,.dotx,.pdf,.png,.jpg,.jpeg"
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.file} />
                        </label>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-h-5 text-xs text-muted-foreground">
                                {selectedFileName
                                    ? `Selected file: ${selectedFileName}`
                                    : 'No source file selected'}
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                <Save className="size-4" aria-hidden="true" />
                                Save
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

TemplateShow.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: '/advisor/templates',
        },
    ],
};

function formatBytes(bytes: number) {
    return (
        new Intl.NumberFormat(undefined, {
            maximumFractionDigits: 1,
        }).format(bytes / 1024) + ' KB'
    );
}

function formatReportType(value: string) {
    return value.replaceAll('_', ' ');
}
