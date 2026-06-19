import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
                    <Metric label="Responses" value={summary.responses} />
                    <Metric
                        label="Average score"
                        value={formatScore(summary.average_score)}
                    />
                    <Metric
                        label="Average NPS"
                        value={formatScore(summary.average_nps)}
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
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'n/a';
}
