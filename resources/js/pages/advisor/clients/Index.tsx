import { Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ClientSummary } from './types';

type Props = {
    clients: ClientSummary[];
};

export default function ClientsIndex({ clients }: Props) {
    return (
        <>
            <Head title="Clients" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Clients</h1>
                    <Button asChild size="sm">
                        <Link href="/advisor/clients/create">
                            <Plus className="size-4" aria-hidden="true" />
                            New
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-md border">
                    {clients.length > 0 ? (
                        <table className="w-full text-sm">
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
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.map((client) => (
                                    <tr key={client.id} className="border-t">
                                        <td className="px-3 py-2">
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
                                        </td>
                                        <td className="px-3 py-2">
                                            {client.engagement_type_label}
                                        </td>
                                        <td className="px-3 py-2">
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
                                        <td className="px-3 py-2">
                                            {client.nzbn ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge variant="secondary">
                                                {client.data_quality}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end">
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
                            No clients yet.
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
