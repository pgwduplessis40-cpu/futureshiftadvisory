import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    CalendarDays,
    FileText,
    FolderOpen,
    MessageSquare,
    ShieldCheck,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import {
    ExplainedMetricCard,
    ExplainedSectionHeader,
} from '@/components/explainer';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    client: {
        id: string;
        legal_name: string;
        trading_name: string | null;
        nzbn: string | null;
    };
    membership: {
        treasurer: boolean;
        joined_at: string | null;
    };
    engagement: {
        id: string;
        sub_type: string;
        conversion_status: string | null;
        report_delivered_at: string | null;
        reengagement_due_at: string | null;
    };
    reports: Array<{
        id: string;
        type: string;
        review_status: string;
        generated_at: string | null;
        url: string;
    }>;
    documents: Array<{
        id: string;
        filename: string;
        category: string;
        uploaded_at: string | null;
        url: string;
    }>;
    links: {
        calendar: string;
        messages: string;
        notifications: string;
    };
};

export default function NpoBoardDashboard({
    client,
    membership,
    engagement,
    reports,
    documents,
    links,
}: Props) {
    return (
        <>
            <Head title="NPO board portal" />

            <main className="space-y-6">
                <PageHeader
                    eyebrow="NPO board portal"
                    icon={ShieldCheck}
                    title={client.trading_name || client.legal_name}
                    description={
                        <span className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {engagement.sub_type}
                            </Badge>
                            {membership.treasurer ? (
                                <Badge variant="outline">Treasurer</Badge>
                            ) : null}
                            {client.nzbn ? (
                                <Badge variant="outline">
                                    NZBN {client.nzbn}
                                </Badge>
                            ) : null}
                        </span>
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild size="sm" variant="outline">
                                <Link href={links.calendar}>
                                    <CalendarDays
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Calendar
                                </Link>
                            </Button>
                            <Button asChild size="sm" variant="outline">
                                <Link href={links.messages}>
                                    <MessageSquare
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Messages
                                </Link>
                            </Button>
                            <Button asChild size="sm" variant="outline">
                                <Link href={links.notifications}>
                                    <Bell
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Notifications
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <section className="grid gap-3 sm:grid-cols-3">
                    <ExplainedMetricCard
                        label="Reports"
                        value={reports.length}
                        helper="Board-visible reports"
                        className="border-[var(--fs-admiralty)] bg-[var(--fs-admiralty)] text-primary-foreground [&_button]:border-primary-foreground/25 [&_button]:bg-primary-foreground/10 [&_button]:text-primary-foreground [&_button:hover]:bg-primary-foreground/20 [&_.text-muted-foreground]:text-primary-foreground/75"
                        explanation={{
                            title: 'Board reports',
                            what: 'The number of reviewed advisory reports currently visible to the NPO board portal.',
                            action: 'Open the reports panel and review the latest board pack or advisory report before the next meeting.',
                            why: 'These reports hold the governed advice and evidence trail the board can rely on for oversight decisions.',
                        }}
                    />
                    <ExplainedMetricCard
                        label="Documents"
                        value={documents.length}
                        helper="Clean board records"
                        explanation={{
                            title: 'Board documents',
                            what: 'The number of board-visible documents made available for this engagement.',
                            action: 'Open documents when you need the underlying source records that support the advice.',
                            why: 'Board decisions are stronger when reports and supporting evidence can be read together.',
                        }}
                    />
                    <ExplainedMetricCard
                        label="Status"
                        value={engagement.conversion_status ?? 'Active'}
                        helper={
                            engagement.reengagement_due_at
                                ? `Due ${formatDate(engagement.reengagement_due_at)}`
                                : 'Current engagement'
                        }
                        explanation={{
                            title: 'Engagement status',
                            what: 'The current stage of the NPO advisory engagement and any re-engagement timing.',
                            action: 'Use this to see whether the engagement is current or needs follow-up with the advisor.',
                            why: 'Status helps the board understand whether advice is still live, delivered, or due for review.',
                        }}
                    />
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <BoardPanel
                        icon={FileText}
                        title="Reports"
                        empty="No reviewed NPO reports are available yet."
                        explanation={{
                            title: 'Reports panel',
                            what: 'Reviewed reports released for board access, including their review status and generation date.',
                            action: 'Open the latest report to read the advice and download or share it through the browser controls if needed.',
                            why: 'The report is the formal advisory output the board can refer to in governance discussions.',
                        }}
                    >
                        {reports.map((report) => (
                            <ListLink
                                key={report.id}
                                href={report.url}
                                title={report.type}
                                meta={`${report.review_status} / ${formatDate(report.generated_at)}`}
                            />
                        ))}
                    </BoardPanel>

                    <BoardPanel
                        icon={FolderOpen}
                        title="Documents"
                        empty="No board-visible documents are available yet."
                        explanation={{
                            title: 'Documents panel',
                            what: 'Supporting records released to the board, grouped by filename, category, and upload date.',
                            action: 'Open documents in a separate window when you need to inspect evidence behind a report.',
                            why: 'Evidence access lets board members verify context without needing advisor or management explanations for every item.',
                        }}
                    >
                        {documents.map((document) => (
                            <ListLink
                                key={document.id}
                                href={document.url}
                                title={document.filename}
                                meta={`${formatCategory(document.category)} / ${formatDate(document.uploaded_at)}`}
                                newWindow
                            />
                        ))}
                    </BoardPanel>
                </section>
            </main>
        </>
    );
}

function BoardPanel({
    icon: Icon,
    title,
    empty,
    explanation,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    empty: string;
    explanation: Parameters<typeof ExplainedSectionHeader>[0]['explanation'];
    children: ReactNode[];
}) {
    return (
        <section className="space-y-3 rounded-[1.25rem] border border-border/80 bg-card p-5 shadow-card">
            <ExplainedSectionHeader
                icon={Icon}
                title={title}
                explanation={explanation}
            />
            {children.length === 0 ? (
                <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                    {empty}
                </div>
            ) : (
                <div className="divide-y overflow-hidden rounded-[1rem] border">
                    {children}
                </div>
            )}
        </section>
    );
}

function ListLink({
    href,
    title,
    meta,
    newWindow = false,
}: {
    href: string;
    title: string;
    meta: string;
    newWindow?: boolean;
}) {
    const className =
        'block p-3 transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50';
    const content = (
        <>
            <div className="font-medium">{title}</div>
            <div className="mt-1 text-xs text-muted-foreground">{meta}</div>
        </>
    );

    if (newWindow) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
            >
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={className}>
            {content}
        </Link>
    );
}

function formatDate(value: string | null) {
    if (!value) {
        return 'n/a';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

function formatCategory(value: string) {
    return value.replaceAll('_', ' ');
}
