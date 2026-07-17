import { Head, Link } from '@inertiajs/react';
import { ArrowRightLeft, Plus, Search, Send, UsersRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ClientSummary } from './types';

type Props = {
    clients: ClientSummary[];
    engagementFilter: {
        key: string;
        label: string;
        description: string;
        clear_url: string;
    } | null;
    exposureFilter: {
        key: string;
        label: string;
        exposed_count: number;
        unknown_count: number;
        clear_url: string;
    } | null;
    showAdvisorAssignments: boolean;
    allocationUrl: string | null;
    transferRequestUrl: string | null;
};

export default function ClientsIndex({
    clients,
    engagementFilter,
    exposureFilter,
    showAdvisorAssignments,
    allocationUrl,
    transferRequestUrl,
}: Props) {
    const pageTitle = engagementFilter?.label ?? 'Clients';
    const emptyLabel = engagementFilter
        ? `${engagementFilter.label} clients`
        : 'clients';
    const engagementQuery = engagementFilter
        ? `?engagement_type=${encodeURIComponent(engagementFilter.key)}`
        : '';
    const inviteUrl = `/advisor/clients/invite${engagementQuery}`;
    const manualCreateUrl = `/advisor/clients/create${engagementQuery}`;

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{pageTitle}</h1>
                        {engagementFilter && (
                            <p className="text-sm text-muted-foreground">
                                {engagementFilter.description}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        {allocationUrl ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={allocationUrl}>
                                    <UsersRound
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Client allocations
                                </Link>
                            </Button>
                        ) : null}
                        <Button asChild size="sm">
                            <Link href={inviteUrl}>
                                <Send className="size-4" aria-hidden="true" />
                                Invite client
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={manualCreateUrl}>
                                <Plus className="size-4" aria-hidden="true" />
                                Add manually
                            </Link>
                        </Button>
                    </div>
                </div>

                {engagementFilter && (
                    <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/30 px-3 py-2 text-sm">
                        <Badge variant="secondary">
                            {engagementFilter.label}
                        </Badge>
                        <span>{clients.length} visible</span>
                        <Button asChild size="sm" variant="outline">
                            <Link href={engagementFilter.clear_url}>Clear</Link>
                        </Button>
                    </div>
                )}

                {exposureFilter && (
                    <div className="flex flex-wrap items-center gap-2 rounded-md border bg-muted/30 px-3 py-2 text-sm">
                        <Badge variant="secondary">
                            {exposureFilter.label}
                        </Badge>
                        <span>
                            {exposureFilter.exposed_count} exposed
                            {exposureFilter.unknown_count > 0
                                ? ` / ${exposureFilter.unknown_count} unknown`
                                : ''}
                        </span>
                        <Button asChild size="sm" variant="outline">
                            <Link href={exposureFilter.clear_url}>Clear</Link>
                        </Button>
                    </div>
                )}

                <div className="overflow-hidden rounded-md border">
                    {clients.length > 0 ? (
                        <table className="fsa-responsive-table">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Client
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Engagement
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        NZBN
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Quality
                                    </th>
                                    {showAdvisorAssignments ? (
                                        <th className="px-3 py-2 font-medium">
                                            Advisors
                                        </th>
                                    ) : null}
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.map((client) => (
                                    <tr key={client.id} className="border-t">
                                        <td
                                            className="px-3 py-2"
                                            data-label="Client"
                                        >
                                            <Link
                                                href={`/advisor/clients/${client.id}`}
                                                className="font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                            >
                                                {client.legal_name}
                                            </Link>
                                            {client.trading_name && (
                                                <div className="text-xs text-muted-foreground">
                                                    {client.trading_name}
                                                </div>
                                            )}
                                            {client.is_npo && (
                                                <Badge
                                                    className="mt-1"
                                                    variant="secondary"
                                                >
                                                    NPO
                                                </Badge>
                                            )}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Engagement"
                                        >
                                            {client.engagement_type_label}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Status"
                                        >
                                            <Badge
                                                variant={
                                                    client.status ===
                                                    'suspended'
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                            >
                                                {client.status_label}
                                            </Badge>
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="NZBN"
                                        >
                                            {client.nzbn ?? '—'}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Quality"
                                        >
                                            <Badge variant="secondary">
                                                {client.data_quality}
                                            </Badge>
                                        </td>
                                        {showAdvisorAssignments ? (
                                            <td
                                                className="px-3 py-2"
                                                data-label="Advisors"
                                            >
                                                {client.advisor_assignments
                                                    ?.length ? (
                                                    <div className="space-y-1 text-sm">
                                                        {client.advisor_assignments.map(
                                                            (assignment, index) => (
                                                                <div key={`${assignment.advisor_name}-${index}`}>
                                                                    <span className="font-medium">
                                                                        {assignment.advisor_name ??
                                                                            'Unknown advisor'}
                                                                    </span>
                                                                    <span className="text-muted-foreground">
                                                                        {' '}
                                                                        ({formatRole(
                                                                            assignment.role,
                                                                        )}
                                                                        {assignment.team_name
                                                                            ? `, ${assignment.team_name}`
                                                                            : ''}
                                                                        )
                                                                    </span>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Unassigned
                                                    </span>
                                                )}
                                            </td>
                                        ) : null}
                                        <td
                                            className="px-3 py-2"
                                            data-label="Actions"
                                        >
                                            <div className="flex flex-wrap justify-start gap-2 md:justify-end">
                                                {transferRequestUrl ? (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={`${transferRequestUrl}?client_id=${encodeURIComponent(client.id)}`}
                                                        >
                                                            <ArrowRightLeft
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                            Request transfer
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={`/advisor/clients/${client.id}`}
                                                        aria-label={`Open ${client.legal_name}`}
                                                    >
                                                        <Search
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="px-4 py-10 text-sm text-muted-foreground">
                            No {emptyLabel} yet.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

ClientsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
    ],
};

function formatRole(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
