import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail, UserRoundCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { EntrepreneurDetail } from './types';

type Props = {
    entrepreneur: EntrepreneurDetail;
};

export default function EntrepreneursShow({ entrepreneur }: Props) {
    return (
        <>
            <Head title={entrepreneur.name} />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {entrepreneur.name}
                        </h1>
                        <div className="text-sm text-muted-foreground">
                            {entrepreneur.email}
                        </div>
                    </div>
                    <Button asChild size="sm" variant="outline">
                        <Link href="/advisor/entrepreneurs">
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric
                        label="Stage"
                        value={entrepreneur.stage_label}
                        badge
                    />
                    <Metric
                        label="Invite"
                        value={
                            entrepreneur.invite_accepted_at
                                ? 'accepted'
                                : 'sent'
                        }
                    />
                    <Metric
                        label="Account"
                        value={entrepreneur.user_id ? 'linked' : 'pending'}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <Mail className="size-4" aria-hidden="true" />
                            <h2 className="text-sm font-medium">Invite</h2>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Email" value={entrepreneur.email} />
                            <Detail
                                label="Accepted"
                                value={formatDate(
                                    entrepreneur.invite_accepted_at,
                                )}
                            />
                            <Detail
                                label="Created"
                                value={formatDate(entrepreneur.created_at)}
                            />
                        </dl>
                    </section>

                    <section className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center gap-2">
                            <UserRoundCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">Concept</h2>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {entrepreneur.concept_summary || 'No summary yet.'}
                        </p>
                    </section>
                </div>
            </div>
        </>
    );
}

function Metric({
    label,
    value,
    badge = false,
}: {
    label: string;
    value: string;
    badge?: boolean;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">
                {badge ? <Badge variant="secondary">{value}</Badge> : value}
            </div>
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
        <div className="grid grid-cols-[110px_minmax(0,1fr)] gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

EntrepreneursShow.layout = {
    breadcrumbs: [
        {
            title: 'Entrepreneurs',
            href: '/advisor/entrepreneurs',
        },
    ],
};
