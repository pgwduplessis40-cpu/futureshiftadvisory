import { router, useForm } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Download,
    FileCheck2,
    FileSpreadsheet,
    FileText,
    HeartPulse,
    LockKeyhole,
    Send,
    Star,
    Target,
    TrendingUp,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    formatBytes,
    formatDate,
    formatLabel,
    formatMonth,
    truncate,
} from './client-detail-presenters';
import type {
    ClientDetail,
    MeetingForm,
    ReportSummary,
} from './client-detail-types';
export function ReportsPanel({ client }: { client: ClientDetail }) {
    const generate = (
        type:
            | 'client'
            | 'advisor'
            | 'stakeholder'
            | 'trajectory'
            | 'valuation_report'
            | 'due_diligence'
            | 'acquisition_go_no_go_report'
            | 'post_acquisition_gap_report'
            | 'succession_value_gap_report'
            | 'governance_review_report'
            | 'npo_health_report'
            | 'npo_advisor_report'
            | 'social_enterprise_dual_report',
    ) => {
        router.post(
            client.report_store_url,
            { type },
            { preserveScroll: true },
        );
    };

    const review = (report: ReportSummary) => {
        const url =
            report.type === 'client' && report.release_url
                ? report.release_url
                : report.review_url;

        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-reports"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Reports</h2>
                </div>
                <Badge variant="outline">{client.reports.length}</Badge>
            </div>

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('client')}
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Client
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('advisor')}
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Advisor
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('stakeholder')}
                >
                    <FileText className="size-4" aria-hidden="true" />
                    Stakeholder
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('trajectory')}
                >
                    <TrendingUp className="size-4" aria-hidden="true" />
                    Trajectory
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('valuation_report')}
                >
                    <FileSpreadsheet className="size-4" aria-hidden="true" />
                    Valuation
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => generate('succession_value_gap_report')}
                >
                    <TrendingUp className="size-4" aria-hidden="true" />
                    Succession Gap
                </Button>
                {client.due_diligence && (
                    <>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => generate('due_diligence')}
                        >
                            <FileText className="size-4" aria-hidden="true" />
                            Due Diligence
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                generate('acquisition_go_no_go_report')
                            }
                        >
                            <Target className="size-4" aria-hidden="true" />
                            Go/No-Go
                        </Button>
                    </>
                )}
                {client.engagement_type === 'post_acquisition_advisory' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => generate('post_acquisition_gap_report')}
                    >
                        <FileText className="size-4" aria-hidden="true" />
                        Gap Report
                    </Button>
                )}
                {client.is_npo && (
                    <>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => generate('governance_review_report')}
                        >
                            <FileCheck2 className="size-4" aria-hidden="true" />
                            Governance
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => generate('npo_health_report')}
                        >
                            <HeartPulse className="size-4" aria-hidden="true" />
                            NPO Health
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => generate('npo_advisor_report')}
                        >
                            <LockKeyhole
                                className="size-4"
                                aria-hidden="true"
                            />
                            NPO Advisor
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                generate('social_enterprise_dual_report')
                            }
                        >
                            <Star className="size-4" aria-hidden="true" />
                            Dual Impact
                        </Button>
                    </>
                )}
            </div>

            {client.reports.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No reports generated yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.reports.map((report) => (
                        <article
                            key={report.id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                        >
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="text-sm font-medium">
                                        {report.type_label}
                                    </h3>
                                    <Badge variant="outline">
                                        {formatDate(report.generated_at)}
                                    </Badge>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {report.title}
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <span>
                                    PDF {formatBytes(report.pdf_byte_size)}
                                    {report.pptx_byte_size
                                        ? ` / PPTX ${formatBytes(report.pptx_byte_size)}`
                                        : ''}
                                </span>
                                {(report.view_url ?? report.download_url) && (
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={
                                                report.view_url ??
                                                report.download_url ??
                                                ''
                                            }
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <FileText
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            View PDF
                                        </a>
                                    </Button>
                                )}
                                {report.pptx_url && (
                                    <Button asChild size="sm" variant="outline">
                                        <a href={report.pptx_url}>
                                            <Download
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            PPTX
                                        </a>
                                    </Button>
                                )}
                                {report.review_status === 'pending_review' && (
                                    <Badge variant="secondary">Review</Badge>
                                )}
                                {(report.revision_count > 0 ||
                                    report.comment_count > 0) && (
                                    <Badge variant="outline">
                                        {report.revision_count} edits /{' '}
                                        {report.comment_count} comments
                                    </Badge>
                                )}
                                {report.can_review && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => review(report)}
                                    >
                                        <CheckCircle2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {report.type === 'client'
                                            ? 'Release to client'
                                            : 'Mark reviewed'}
                                    </Button>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function MeetingsBriefingsPanel({ client }: { client: ClientDetail }) {
    const form = useForm<MeetingForm>({
        title: '',
        scheduled_at: '',
        location: '',
        link: '',
        attendees: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(client.meeting_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const review = (url: string) => {
        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-meetings"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CalendarClock className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Meetings and briefs</h2>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline">
                        {client.meetings.length} meetings
                    </Badge>
                    <Badge variant="secondary">
                        {client.pre_meeting_briefs.length} briefs
                    </Badge>
                </div>
            </div>

            <form onSubmit={submit} className="grid gap-3 lg:grid-cols-4">
                <div className="grid gap-2">
                    <Label htmlFor="meeting_title">Title</Label>
                    <input
                        id="meeting_title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meeting_scheduled_at">Scheduled</Label>
                    <input
                        id="meeting_scheduled_at"
                        type="datetime-local"
                        value={form.data.scheduled_at}
                        onChange={(event) =>
                            form.setData('scheduled_at', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.scheduled_at} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meeting_location">Location</Label>
                    <input
                        id="meeting_location"
                        value={form.data.location}
                        onChange={(event) =>
                            form.setData('location', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.location} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="meeting_attendees">Attendees</Label>
                    <input
                        id="meeting_attendees"
                        value={form.data.attendees}
                        onChange={(event) =>
                            form.setData('attendees', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.attendees} />
                </div>
                <div className="grid gap-2 lg:col-span-3">
                    <Label htmlFor="meeting_link">Link</Label>
                    <input
                        id="meeting_link"
                        value={form.data.link}
                        onChange={(event) =>
                            form.setData('link', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.link} />
                </div>
                <div className="flex items-end">
                    <Button
                        type="submit"
                        size="sm"
                        disabled={form.processing}
                        className="w-full"
                    >
                        <CalendarClock className="size-4" aria-hidden="true" />
                        Add meeting
                    </Button>
                </div>
            </form>

            <div className="grid gap-4 xl:grid-cols-3">
                <BriefList
                    title="Upcoming meetings"
                    empty="No upcoming meetings."
                    items={client.meetings.map((meeting) => ({
                        id: meeting.id,
                        heading: meeting.title,
                        detail: [
                            formatDate(meeting.scheduled_at),
                            meeting.location,
                            meeting.calendar_synced
                                ? 'Calendar synced'
                                : undefined,
                            formatLabel(meeting.brief_status),
                        ]
                            .filter(Boolean)
                            .join(' - '),
                    }))}
                />
                <BriefList
                    title="Industry briefings"
                    empty="No industry briefings yet."
                    items={client.industry_briefings.map((briefing) => ({
                        id: briefing.id,
                        heading: formatMonth(briefing.period),
                        detail: `${formatLabel(briefing.status)} - ${truncate(briefing.body, 130)}`,
                        action: briefing.can_review
                            ? {
                                  label: 'Review and send',
                                  onClick: () => review(briefing.review_url),
                              }
                            : undefined,
                    }))}
                />
                <BriefList
                    title="Pre-meeting briefs"
                    empty="No pre-meeting briefs yet."
                    items={client.pre_meeting_briefs.map((brief) => ({
                        id: brief.id,
                        heading: brief.meeting_title ?? 'Meeting brief',
                        detail: `${formatDate(brief.meeting_at)} - ${brief.red_flag_count} red flags`,
                        action: brief.can_review
                            ? {
                                  label: 'Review and send',
                                  onClick: () => review(brief.review_url),
                              }
                            : undefined,
                    }))}
                />
            </div>
        </section>
    );
}

export function BriefList({
    title,
    empty,
    items,
}: {
    title: string;
    empty: string;
    items: Array<{
        id: string;
        heading: string;
        detail: string;
        action?: {
            label: string;
            onClick: () => void;
        };
    }>;
}) {
    return (
        <div className="space-y-3">
            <h3 className="text-xs font-medium text-muted-foreground uppercase">
                {title}
            </h3>
            {items.length === 0 ? (
                <p className="rounded-md border p-3 text-sm text-muted-foreground">
                    {empty}
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {items.map((item) => (
                        <article key={item.id} className="space-y-3 p-3">
                            <div>
                                <div className="text-sm font-medium">
                                    {item.heading}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    {item.detail}
                                </div>
                            </div>
                            {item.action && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={item.action.onClick}
                                >
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {item.action.label}
                                </Button>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}
