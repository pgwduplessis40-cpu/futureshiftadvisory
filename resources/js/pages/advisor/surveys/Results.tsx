import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type SurveyOption = {
    id: string;
    title: string;
    version: string;
};

type AssignmentItem = {
    id: string;
    survey_title: string | null;
    status: string;
    activated_at: string | null;
    due_at: string | null;
    completed_at: string | null;
    deliverable_count: number;
    response: {
        overall_score: number | null;
        nps_score: number | null;
        submitted_at: string | null;
    } | null;
};

type Props = {
    subject: {
        type: string;
        name: string;
        back_url: string;
        activation_url: string;
    };
    surveys: SurveyOption[];
    results: {
        summary: {
            assignments: number;
            completed: number;
            average_score: number | null;
            average_nps: number | null;
        };
        items: AssignmentItem[];
    };
};

export default function AdvisorSurveyResults({
    subject,
    surveys,
    results,
}: Props) {
    const form = useForm({
        survey_id: surveys[0]?.id ?? '',
        due_at: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(subject.activation_url);
    };

    return (
        <>
            <Head title={`${subject.name} surveys`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={subject.back_url}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-xl font-semibold">
                            {subject.name} surveys
                        </h1>
                    </div>
                    <form
                        onSubmit={submit}
                        className="grid gap-3 rounded-md border bg-background p-3 sm:grid-cols-[1fr_12rem_auto]"
                    >
                        <div className="grid gap-1">
                            <Label htmlFor="survey_id">Survey</Label>
                            <select
                                id="survey_id"
                                value={form.data.survey_id}
                                onChange={(event) =>
                                    form.setData(
                                        'survey_id',
                                        event.target.value,
                                    )
                                }
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {surveys.map((survey) => (
                                    <option key={survey.id} value={survey.id}>
                                        {survey.title} v{survey.version}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="due_at">Due</Label>
                            <Input
                                id="due_at"
                                type="date"
                                value={form.data.due_at}
                                onChange={(event) =>
                                    form.setData('due_at', event.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.survey_id === ''
                                }
                            >
                                <Send className="size-4" aria-hidden="true" />
                                Activate
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    <Metric
                        label="Assignments"
                        value={results.summary.assignments}
                    />
                    <Metric
                        label="Completed"
                        value={results.summary.completed}
                    />
                    <Metric
                        label="Average score"
                        value={formatScore(results.summary.average_score)}
                    />
                    <Metric
                        label="Average NPS"
                        value={formatScore(results.summary.average_nps)}
                    />
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
                                    Deliverables
                                </th>
                                <th className="px-3 py-2 font-medium">Score</th>
                                <th className="px-3 py-2 font-medium">NPS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {results.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td
                                        className="px-3 py-2"
                                        data-label="Survey"
                                    >
                                        <div className="font-medium">
                                            {item.survey_title}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {formatDate(item.activated_at)}
                                        </div>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Status"
                                    >
                                        <Badge variant="secondary">
                                            {item.status}
                                        </Badge>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Deliverables"
                                    >
                                        {item.deliverable_count}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Score"
                                    >
                                        {formatScore(
                                            item.response?.overall_score,
                                        )}
                                    </td>
                                    <td className="px-3 py-2" data-label="NPS">
                                        {formatScore(item.response?.nps_score)}
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

function Metric({ label, value }: { label: string; value: number | string }) {
    return (
        <div className="rounded-md border bg-background p-4">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-2 text-2xl font-semibold">{value}</div>
        </div>
    );
}

function formatScore(value: number | null | undefined) {
    return typeof value === 'number' ? value.toFixed(1) : 'n/a';
}

function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : 'n/a';
}
