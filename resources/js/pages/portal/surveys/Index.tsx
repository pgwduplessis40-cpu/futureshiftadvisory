import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type AssignmentPayload = {
    id: string;
    survey_title: string;
    status: string;
    is_open: boolean;
    activated_at: string | null;
    due_at: string | null;
    completed_at: string | null;
    deliverables: unknown[];
    url: string;
    response: {
        overall_score: number | null;
        nps_score: number | null;
        submitted_at: string | null;
    } | null;
};

type Props = {
    subject: {
        name: string;
    };
    assignments: AssignmentPayload[];
    dashboardUrl: string;
};

export default function PortalSurveyIndex({
    subject,
    assignments,
    dashboardUrl,
}: Props) {
    return (
        <>
            <Head title="Feedback" />

            <main className="space-y-6">
                <div>
                    <Link
                        href={dashboardUrl}
                        className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />
                        Dashboard
                    </Link>
                    <div className="mt-3 flex items-center gap-2">
                        <ClipboardCheck
                            className="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h1 className="text-xl font-semibold">Feedback</h1>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {subject.name}
                    </p>
                </div>

                <div className="grid gap-3">
                    {assignments.length === 0 && (
                        <div className="rounded-md border bg-background p-6 text-sm text-muted-foreground">
                            No surveys are assigned right now.
                        </div>
                    )}
                    {assignments.map((assignment) => (
                        <div
                            key={assignment.id}
                            className="flex flex-col gap-4 rounded-md border bg-background p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="font-medium">
                                        {assignment.survey_title}
                                    </h2>
                                    <Badge variant="secondary">
                                        {assignment.status}
                                    </Badge>
                                </div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    {assignment.deliverables.length}{' '}
                                    deliverables
                                    {assignment.due_at
                                        ? ` · due ${formatDate(
                                              assignment.due_at,
                                          )}`
                                        : ''}
                                </div>
                                {assignment.response && (
                                    <div className="mt-2 text-sm">
                                        Score{' '}
                                        {formatScore(
                                            assignment.response.overall_score,
                                        )}
                                        {' · '}NPS{' '}
                                        {formatScore(
                                            assignment.response.nps_score,
                                        )}
                                    </div>
                                )}
                            </div>
                            <Button
                                asChild
                                variant={
                                    assignment.is_open ? 'default' : 'outline'
                                }
                            >
                                <Link href={assignment.url}>
                                    {assignment.is_open ? 'Respond' : 'View'}
                                </Link>
                            </Button>
                        </div>
                    ))}
                </div>
            </main>
        </>
    );
}

function formatScore(value: number | null | undefined) {
    return typeof value === 'number' ? value.toFixed(1) : 'n/a';
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );
}
