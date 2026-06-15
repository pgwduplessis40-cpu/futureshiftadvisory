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
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            NPO board portal
                        </div>
                        <h1 className="mt-1 text-xl font-semibold">
                            {client.trading_name || client.legal_name}
                        </h1>
                        <div className="mt-2 flex flex-wrap gap-2">
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
                        </div>
                    </div>

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
                                <Bell className="size-4" aria-hidden="true" />
                                Notifications
                            </Link>
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-3">
                    <MetricCard
                        label="Reports"
                        value={reports.length}
                        detail="Board-visible reports"
                    />
                    <MetricCard
                        label="Documents"
                        value={documents.length}
                        detail="Clean board records"
                    />
                    <MetricCard
                        label="Status"
                        value={engagement.conversion_status ?? 'Active'}
                        detail={
                            engagement.reengagement_due_at
                                ? `Due ${formatDate(engagement.reengagement_due_at)}`
                                : 'Current engagement'
                        }
                    />
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <BoardPanel
                        icon={FileText}
                        title="Reports"
                        empty="No reviewed NPO reports are available yet."
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
                    >
                        {documents.map((document) => (
                            <ListLink
                                key={document.id}
                                href={document.url}
                                title={document.filename}
                                meta={`${formatCategory(document.category)} / ${formatDate(document.uploaded_at)}`}
                            />
                        ))}
                    </BoardPanel>
                </section>
            </main>
        </>
    );
}

function MetricCard({
    label,
    value,
    detail,
}: {
    label: string;
    value: string | number;
    detail: string;
}) {
    return (
        <article className="rounded-md border bg-background p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-2xl font-semibold">{value}</div>
            <div className="mt-1 text-sm text-muted-foreground">{detail}</div>
        </article>
    );
}

function BoardPanel({
    icon: Icon,
    title,
    empty,
    children,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    empty: string;
    children: ReactNode[];
}) {
    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex items-center gap-2">
                <Icon className="size-4" aria-hidden={true} />
                <h2 className="text-sm font-medium">{title}</h2>
            </div>
            {children.length === 0 ? (
                <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                    {empty}
                </div>
            ) : (
                <div className="divide-y rounded-md border">{children}</div>
            )}
        </section>
    );
}

function ListLink({
    href,
    title,
    meta,
}: {
    href: string;
    title: string;
    meta: string;
}) {
    return (
        <Link
            href={href}
            className="block p-3 transition-colors outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
            <div className="font-medium">{title}</div>
            <div className="mt-1 text-xs text-muted-foreground">{meta}</div>
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
