import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, LockKeyhole } from 'lucide-react';
import type { ReactNode } from 'react';
import { DataQualityBadge } from '@/components/data-quality/DataQualityBadge';
import type { DataQualitySummary } from '@/components/data-quality/DataQualityBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ClientSummary } from './types';

type ClientDetail = ClientSummary & {
    data_quality_summary: DataQualitySummary;
    address: Record<string, string | null> | null;
    directors: Array<Record<string, string | null>>;
    registry_sources: Record<string, string>;
    engagement_type_locked: boolean;
    created_at: string | null;
};

type ConflictDeclaration = {
    id: string;
    declaration: {
        referral_type?: string;
        existing_relationship?: boolean;
        details?: string | null;
    };
    declared_at: string;
} | null;

type Props = {
    client: ClientDetail;
    conflictDeclaration: ConflictDeclaration;
};

export default function ClientsShow({ client, conflictDeclaration }: Props) {
    return (
        <>
            <Head title={client.legal_name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {client.legal_name}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {client.engagement_type_label}
                        </div>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href="/advisor/clients">
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric label="NZBN" value={client.nzbn ?? '-'} />
                    <Metric label="Data quality">
                        <DataQualityBadge
                            summary={client.data_quality_summary}
                        />
                    </Metric>
                    <Metric
                        label="GST"
                        value={client.gst_registered ? 'registered' : 'no'}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-md border p-4">
                        <h2 className="text-sm font-medium">Registry</h2>
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Entity" value={client.entity_type} />
                            <Detail
                                label="Filing"
                                value={client.filing_status}
                            />
                            <Detail
                                label="Trading"
                                value={client.trading_name}
                            />
                        </dl>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(client.registry_sources).map(
                                ([service, badge]) => (
                                    <Badge key={service} variant="secondary">
                                        {service}: {badge}
                                    </Badge>
                                ),
                            )}
                        </div>
                    </section>

                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <h2 className="text-sm font-medium">Engagement</h2>
                            {client.engagement_type_locked && (
                                <Badge variant="outline">
                                    <LockKeyhole
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                    locked
                                </Badge>
                            )}
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label="Type"
                                value={client.engagement_type_label}
                            />
                            <Detail
                                label="Conflict"
                                value={
                                    conflictDeclaration ? 'declared' : 'missing'
                                }
                            />
                            <Detail
                                label="Relationship"
                                value={
                                    conflictDeclaration?.declaration
                                        .existing_relationship
                                        ? 'yes'
                                        : 'no'
                                }
                            />
                        </dl>
                    </section>
                </div>
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    children,
}: {
    label: string;
    value?: string;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{children ?? value}</div>
        </div>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

ClientsShow.layout = {
    breadcrumbs: [
        {
            title: 'Clients',
            href: '/advisor/clients',
        },
    ],
};
