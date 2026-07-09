import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ExplainedMetricCard } from '@/components/explainer';
import type { Explanation } from '@/components/explainer';
import { Button } from '@/components/ui/button';

type ResponsePayload = {
    id: string;
    subject: string;
    submitted_by: string | null;
    submitted_at: string | null;
    overall_score: number | null;
    nps_score: number | null;
};

type Props = {
    survey: {
        title: string;
        version: string;
    };
    summary: {
        responses: number;
        average_score: number | null;
        average_nps: number | null;
    };
    responses: ResponsePayload[];
    indexUrl: string;
};

export default function SurveyResults({
    survey,
    summary,
    responses,
    indexUrl,
}: Props) {
    return (
        <>
            <Head title={`${survey.title} results`} />

            <div className="space-y-6">
                <div>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={indexUrl}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Surveys
                        </Link>
                    </Button>
                    <h1 className="mt-3 text-xl font-semibold">
                        {survey.title} v{survey.version}
                    </h1>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <Metric
                        label="Responses"
                        value={summary.responses}
                        explanation={surveyExplanations.responses}
                    />
                    <Metric
                        label="Average score"
                        value={formatScore(summary.average_score)}
                        explanation={surveyExplanations.averageScore}
                    />
                    <Metric
                        label="Average NPS"
                        value={formatScore(summary.average_nps)}
                        explanation={surveyExplanations.averageNps}
                    />
                </div>

                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Subject
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Submitted
                                </th>
                                <th className="px-3 py-2 font-medium">Score</th>
                                <th className="px-3 py-2 font-medium">NPS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {responses.map((response) => (
                                <tr key={response.id} className="border-t">
                                    <td
                                        className="px-3 py-2"
                                        data-label="Subject"
                                    >
                                        <div className="font-medium">
                                            {response.subject}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {response.submitted_by}
                                        </div>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Submitted"
                                    >
                                        {formatDate(response.submitted_at)}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Score"
                                    >
                                        {formatScore(response.overall_score)}
                                    </td>
                                    <td className="px-3 py-2" data-label="NPS">
                                        {formatScore(response.nps_score)}
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
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'n/a';
}

const surveyExplanations = {
    responses: {
        title: 'Responses',
        what: 'The number of submitted survey responses for this survey version.',
        action: 'Check the response count before relying on averages.',
        why: 'Small samples can make score and NPS averages volatile.',
    },
    averageScore: {
        title: 'Average score',
        what: 'The average overall score across submitted responses.',
        action: 'Review the response table when the average changes materially or looks inconsistent.',
        why: 'Average score is a quality signal for whether the advisory experience is landing well.',
    },
    averageNps: {
        title: 'Average NPS',
        what: 'The average net-promoter-style score across submitted responses.',
        action: 'Use this as a sentiment trend and pair it with comments or response detail.',
        why: 'NPS is helpful for trend monitoring but should not be interpreted without context.',
    },
} satisfies Record<string, Explanation>;
