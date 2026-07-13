import { Head, Link, router } from '@inertiajs/react';
import { Calculator, ChevronRight, Plus } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ScopeSummary = {
    id: string;
    client_name: string | null;
    status: string;
    delivery_mode: string | null;
    complexity_band: string | null;
    annual_savings: number | null;
    quoted_fee: number | null;
    url: string;
};

type ClientOption = { id: string; name: string; store_url: string };

type Props = { scopes: ScopeSummary[]; clients: ClientOption[] };

export default function IntegrationScopesIndex({ scopes, clients }: Props) {
    const [clientId, setClientId] = useState(clients[0]?.id ?? '');
    const selectedClient = clients.find((client) => client.id === clientId);

    return (
        <>
            <Head title="Integration scopes" />
            <main className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Integration scopes
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Systems, duplicate-entry waste, fixed-fee complexity,
                            and the savings case for integration work.
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <select
                            aria-label="Client for sample integration scope"
                            value={clientId}
                            onChange={(event) => setClientId(event.target.value)}
                            className="h-9 min-w-56 rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            {clients.length === 0 ? (
                                <option value="">No accessible clients</option>
                            ) : clients.map((client) => (
                                <option key={client.id} value={client.id}>{client.name}</option>
                            ))}
                        </select>
                        <Button
                            disabled={!selectedClient}
                            onClick={() => selectedClient && router.post(selectedClient.store_url, { sample: true })}
                        >
                            <Plus className="size-4" aria-hidden="true" />
                            Create sample scope
                        </Button>
                    </div>
                </div>
                <section className="overflow-hidden rounded-md border bg-background">
                    <table className="fsa-responsive-table table-fixed md:table-fixed">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="w-[25%] px-3 py-2 font-medium">Client</th>
                                <th className="w-[15%] px-3 py-2 font-medium">Band</th>
                                <th className="w-[20%] px-3 py-2 font-medium">Annual savings</th>
                                <th className="w-[20%] px-3 py-2 font-medium">Quoted fee</th>
                                <th className="w-[20%] px-3 py-2 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {scopes.length === 0 ? (
                                <tr><td className="px-3 py-3 text-muted-foreground" colSpan={5}>No integration scopes yet.</td></tr>
                            ) : scopes.map((scope) => (
                                <tr key={scope.id} className="border-t">
                                    <td className="px-3 py-3" data-label="Client">{scope.client_name ?? '-'}</td>
                                    <td className="px-3 py-3" data-label="Band"><Badge variant="secondary">{scope.complexity_band ?? scope.status}</Badge></td>
                                    <td className="px-3 py-3" data-label="Annual savings">{money(scope.annual_savings)}</td>
                                    <td className="px-3 py-3" data-label="Quoted fee">{money(scope.quoted_fee)}</td>
                                    <td className="px-3 py-3" data-label="Action">
                                        <Button asChild variant="outline" size="sm"><Link href={scope.url}><Calculator className="size-4" aria-hidden="true" />Open<ChevronRight className="size-4" aria-hidden="true" /></Link></Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </main>
        </>
    );
}

function money(value: number | null): string {
    return value === null ? '-' : new Intl.NumberFormat('en-NZ', { style: 'currency', currency: 'NZD', maximumFractionDigits: 0 }).format(value);
}

IntegrationScopesIndex.layout = { breadcrumbs: [{ title: 'Integration scopes', href: '/advisor/integration-scopes' }] };
