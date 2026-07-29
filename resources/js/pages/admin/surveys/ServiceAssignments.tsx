import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Survey = {
    id: string;
    title: string;
    version: string;
};

type ServiceActivation = {
    id: string;
    client_name: string;
    service_label: string;
    package_label: string | null;
    closed_at: string | null;
    has_open_survey: boolean;
    issue_url: string;
};

type Props = {
    surveys: Survey[];
    activations: ServiceActivation[];
    surveyIndexUrl: string;
};

export default function ServiceSurveyAssignments({
    surveys,
    activations,
    surveyIndexUrl,
}: Props) {
    const [surveyId, setSurveyId] = useState(surveys[0]?.id ?? '');

    return (
        <>
            <Head title="Issue service surveys" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={surveyIndexUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Surveys
                            </Link>
                        </Button>
                        <h1 className="mt-3 text-xl font-semibold">
                            Issue service surveys
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Select a completed service and send the client a
                            focused improvement survey.
                        </p>
                    </div>

                    <div className="grid min-w-72 gap-1">
                        <Label htmlFor="service-survey">Survey</Label>
                        <select
                            id="service-survey"
                            value={surveyId}
                            onChange={(event) =>
                                setSurveyId(event.target.value)
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
                </div>

                {surveys.length === 0 && (
                    <div className="border-l-4 border-[var(--fs-gold)] bg-background px-4 py-3 text-sm">
                        Publish a service improvement survey from Surveys before
                        issuing it to a client.
                    </div>
                )}

                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Client
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Service
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Closed
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {activations.map((activation) => (
                                <tr key={activation.id} className="border-t">
                                    <td
                                        className="px-3 py-2"
                                        data-label="Client"
                                    >
                                        <div className="font-medium">
                                            {activation.client_name}
                                        </div>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Service"
                                    >
                                        <div className="font-medium">
                                            {activation.service_label}
                                        </div>
                                        {activation.package_label && (
                                            <div className="text-sm text-muted-foreground">
                                                {activation.package_label}
                                            </div>
                                        )}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Closed"
                                    >
                                        {formatDate(activation.closed_at)}
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Status"
                                    >
                                        <Badge
                                            variant={
                                                activation.has_open_survey
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {activation.has_open_survey
                                                ? 'Survey open'
                                                : 'Ready'}
                                        </Badge>
                                    </td>
                                    <td
                                        className="px-3 py-2"
                                        data-label="Action"
                                    >
                                        <div className="flex justify-start md:justify-end">
                                            <Button
                                                type="button"
                                                size="sm"
                                                disabled={
                                                    surveyId === '' ||
                                                    activation.has_open_survey
                                                }
                                                onClick={() =>
                                                    router.post(
                                                        activation.issue_url,
                                                        { survey_id: surveyId },
                                                    )
                                                }
                                            >
                                                <Send
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                Send survey
                                            </Button>
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

function formatDate(value: string | null) {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
          }).format(new Date(value))
        : 'n/a';
}
