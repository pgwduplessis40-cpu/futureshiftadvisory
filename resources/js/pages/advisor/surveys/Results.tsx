import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { ExplainedMetricCard } from '@/components/explainer';
import type { Explanation } from '@/components/explainer';
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
    const subjectLabel =
        subject.type === 'entrepreneur' ? 'founder' : 'client';
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
                            {subject.name} feedback surveys
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Post-delivery feedback on support, sentiment, and
                            delivered materials.
                        </p>
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
                        explanation={surveyExplanations.assignments}
                    />
                    <Metric
                        label="Completed"
                        value={results.summary.completed}
                        explanation={surveyExplanations.completed}
                    />
                    <Metric
                        label="Average score"
                        value={formatScore(results.summary.average_score)}
                        explanation={surveyExplanations.averageScore}
                    />
                    <Metric
                        label="Average NPS"
                        value={formatScore(results.summary.average_nps)}
                        explanation={surveyExplanations.averageNps}
                    />
                </div>

                {results.items.length === 0 ? (
                    <EmptyState
                        icon={ClipboardCheck}
                        title="No feedback surveys assigned yet."
                        description={`Feedback surveys are sent after advisory work or deliverables, so there are no responses to review for this ${subjectLabel} yet.`}
                    />
                ) : (
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
                                    <th className="px-3 py-2 font-medium">
                                        Score
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        NPS
                                    </th>
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
                                        <td
                                            className="px-3 py-2"
                                            data-label="NPS"
                                        >
                                            {formatScore(
                                                item.response?.nps_score,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    explanation,
}: {
    label: string;
    value: number | string;
    explanation: Explanation;
}) {
    return (
        <ExplainedMetricCard
            label={label}
            value={<span className="text-2xl font-semibold">{value}</span>}
            explanation={explanation}
        />
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

const surveyExplanations = {
    assignments: {
        title: 'Survey assignments',
        what: 'The number of survey requests activated for this client or subject.',
        action: 'Use this to confirm how many survey opportunities have been sent.',
        why: 'Assignment count gives context before interpreting completion rates or averages.',
    },
    completed: {
        title: 'Completed surveys',
        what: 'The number of activated surveys with submitted responses.',
        action: 'Compare completed against assignments to see whether follow-up is needed.',
        why: 'Low completion can make average scores less reliable.',
    },
    averageScore: {
        title: 'Average score',
        what: 'The average overall score from submitted survey responses.',
        action: 'Review individual responses when the average is low or based on a small number of submissions.',
        why: 'Average score helps identify whether advice was received as useful and accessible.',
    },
    averageNps: {
        title: 'Average NPS',
        what: 'The average net-promoter-style score from submitted responses.',
        action: 'Use this as a directional client sentiment signal, not as a complete quality measure.',
        why: 'NPS is useful for trend and sentiment, but it needs response-count context.',
    },
} satisfies Record<string, Explanation>;
