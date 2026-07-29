import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    BarChart3,
    ClipboardList,
    Eye,
    FilePlus,
    Pencil,
    Send,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type SurveySummary = {
    id: string;
    key: string;
    version: string;
    type: 'general_experience' | 'service_improvement';
    title: string;
    description: string | null;
    status: string;
    published_at: string | null;
    questions_count: number;
    assignments_count: number;
    responses_count: number;
    show_url: string;
    edit_url: string;
    publish_url: string;
    archive_url: string;
    results_url: string;
};

type Props = {
    surveys: SurveySummary[];
    storeUrl: string;
    serviceAssignmentsUrl: string;
};

export default function SurveysIndex({
    surveys,
    storeUrl,
    serviceAssignmentsUrl,
}: Props) {
    const form = useForm({
        key: 'client_experience',
        version: nextVersion(surveys),
        type: 'general_experience' as SurveySummary['type'],
        title: 'Client experience survey',
        description: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(storeUrl);
    };

    return (
        <>
            <Head title="Surveys" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Surveys</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Govern general experience and service improvement feedback.
                        </p>
                    </div>

                    <div className="flex flex-col gap-3 sm:items-end">
                        <Button asChild variant="outline">
                            <Link href={serviceAssignmentsUrl}>
                                <ClipboardList
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Issue service survey
                            </Link>
                        </Button>
                        <form
                            onSubmit={submit}
                            className="grid gap-3 rounded-md border bg-background p-3 sm:grid-cols-[10rem_1fr_7rem_auto]"
                        >
                        <div className="grid gap-1">
                            <Label htmlFor="survey-type">Type</Label>
                            <select
                                id="survey-type"
                                value={form.data.type}
                                onChange={(event) => {
                                    const type = event.target
                                        .value as SurveySummary['type'];
                                    form.setData({
                                        ...form.data,
                                        type,
                                        key:
                                            type === 'service_improvement'
                                                ? 'service_improvement'
                                                : 'client_experience',
                                        title:
                                            type === 'service_improvement'
                                                ? 'Service improvement survey'
                                                : 'Client experience survey',
                                        version: nextVersion(surveys, type),
                                    });
                                }}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="general_experience">
                                    General experience
                                </option>
                                <option value="service_improvement">
                                    Service improvement
                                </option>
                            </select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="survey-title">Title</Label>
                            <Input
                                id="survey-title"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="survey-version">Version</Label>
                            <Input
                                id="survey-version"
                                value={form.data.version}
                                onChange={(event) =>
                                    form.setData('version', event.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            <Button type="submit" disabled={form.processing}>
                                <FilePlus
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Draft
                            </Button>
                        </div>
                        </form>
                    </div>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Survey
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Type
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Questions
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Assignments
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Responses
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {surveys.map((survey) => (
                                <tr key={survey.id} className="border-t">
                                    <td
                                        className="px-3 py-2"
                                        data-label="Survey"
                                    >
                                        <div className="font-medium">
                                            {survey.title}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {survey.key} v{survey.version}
                                        </div>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Status"
                                    >
                                        <Badge
                                            variant={
                                                survey.status === 'published'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {survey.status}
                                        </Badge>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Type"
                                    >
                                        <Badge variant="outline">
                                            {formatType(survey.type)}
                                        </Badge>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Questions"
                                    >
                                        {survey.questions_count}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Assignments"
                                    >
                                        {survey.assignments_count}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Responses"
                                    >
                                        {survey.responses_count}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Actions"
                                    >
                                        <div className="flex justify-start gap-2 md:justify-end">
                                            <ActionTooltip
                                                label={`View ${survey.title}`}
                                            >
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={survey.show_url}
                                                        aria-label={`View ${survey.title}`}
                                                    >
                                                        <Eye
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                            </ActionTooltip>
                                            <ActionTooltip
                                                label={`View results for ${survey.title}`}
                                            >
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={
                                                            survey.results_url
                                                        }
                                                        aria-label={`View results for ${survey.title}`}
                                                    >
                                                        <BarChart3
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                            </ActionTooltip>
                                            {survey.status === 'draft' && (
                                                <>
                                                    <ActionTooltip
                                                        label={`Edit ${survey.title}`}
                                                    >
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={
                                                                    survey.edit_url
                                                                }
                                                                aria-label={`Edit ${survey.title}`}
                                                            >
                                                                <Pencil
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Link>
                                                        </Button>
                                                    </ActionTooltip>
                                                    <ActionTooltip
                                                        label={`Publish ${survey.title}`}
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            type="button"
                                                            aria-label={`Publish ${survey.title}`}
                                                            onClick={() =>
                                                                router.post(
                                                                    survey.publish_url,
                                                                )
                                                            }
                                                        >
                                                            <Send
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </ActionTooltip>
                                                </>
                                            )}
                                            {survey.status !== 'archived' && (
                                                <ActionTooltip
                                                    label={`Archive ${survey.title}`}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        type="button"
                                                        aria-label={`Archive ${survey.title}`}
                                                        onClick={() =>
                                                            router.post(
                                                                survey.archive_url,
                                                            )
                                                        }
                                                    >
                                                        <Archive
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                </ActionTooltip>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function nextVersion(
    surveys: SurveySummary[],
    type: SurveySummary['type'] = 'general_experience',
) {
    const versions = surveys
        .filter((survey) => survey.type === type)
        .map((survey) => Number.parseFloat(survey.version))
        .filter(Number.isFinite);

    if (versions.length === 0) {
        return '1.0';
    }

    return (Math.max(...versions) + 0.1).toFixed(1);
}

function formatType(type: SurveySummary['type']) {
    return type === 'service_improvement'
        ? 'Service improvement'
        : 'General experience';
}

function ActionTooltip({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side="top">{label}</TooltipContent>
        </Tooltip>
    );
}
