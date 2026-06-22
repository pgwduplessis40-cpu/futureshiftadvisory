import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type PartnerSummary = {
    id: string;
    panel_type: 'broker' | 'coach';
    panel_label: string;
    business_name: string;
    contact_name: string;
    email: string | null;
    status: string;
    status_label: string;
    industry_label: string;
    regions: string[];
    specialties: string[];
    referrals_count: number;
    active_referrals_count: number;
    show_url: string;
};

type Props = {
    title: string;
    description: string;
    panelType: 'broker' | 'coach';
    panelLabel: string;
    industryColumnLabel: string;
    createUrl: string;
    partners: PartnerSummary[];
};

export default function PartnersIndex({
    title,
    description,
    panelType,
    panelLabel,
    industryColumnLabel,
    createUrl,
    partners,
}: Props) {
    return (
        <>
            <Head title={title} />

            <div className="space-y-6">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Partners
                        </p>
                        <h1 className="text-xl font-semibold">{title}</h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">
                            {partners.length} total
                        </Badge>
                        <Button asChild size="sm">
                            <Link href={createUrl}>
                                <Plus className="size-4" aria-hidden="true" />
                                Invite {panelLabel.toLowerCase()}
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="overflow-hidden rounded-md border">
                    {partners.length > 0 ? (
                        <table className="fsa-responsive-table">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Business
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        {panelType === 'broker'
                                            ? 'Broker'
                                            : 'Coach'}
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        {industryColumnLabel}
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Referrals
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {partners.map((partner) => (
                                    <tr key={partner.id} className="border-t">
                                        <td
                                            className="px-3 py-2"
                                            data-label="Business"
                                        >
                                            <Link
                                                href={partner.show_url}
                                                className="font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                            >
                                                {partner.business_name}
                                            </Link>
                                            {partner.regions.length > 0 && (
                                                <div className="text-xs text-muted-foreground">
                                                    {partner.regions.join(', ')}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label={
                                                panelType === 'broker'
                                                    ? 'Broker'
                                                    : 'Coach'
                                            }
                                        >
                                            <Link
                                                href={partner.show_url}
                                                className="font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                            >
                                                {partner.contact_name}
                                            </Link>
                                            {partner.email && (
                                                <div className="text-xs text-muted-foreground">
                                                    {partner.email}
                                                </div>
                                            )}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label={industryColumnLabel}
                                        >
                                            {partner.industry_label}
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Status"
                                        >
                                            <Badge
                                                variant={statusVariant(
                                                    partner.status,
                                                )}
                                            >
                                                {partner.status_label}
                                            </Badge>
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Referrals"
                                        >
                                            <div className="font-medium">
                                                {
                                                    partner.active_referrals_count
                                                }{' '}
                                                active
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {partner.referrals_count} total
                                            </div>
                                        </td>
                                        <td
                                            className="px-3 py-2"
                                            data-label="Actions"
                                        >
                                            <div className="flex justify-start md:justify-end">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={partner.show_url}
                                                        aria-label={`Open ${partner.business_name}`}
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
                            No {title.toLowerCase()} have been added yet.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'active' || status === 'approved') {
        return 'default';
    }

    if (status === 'suspended' || status === 'declined') {
        return 'destructive';
    }

    if (status === 'approved_pending_agreement') {
        return 'secondary';
    }

    return 'outline';
}

PartnersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Partners',
            href: '/advisor/partners/brokers',
        },
    ],
};
