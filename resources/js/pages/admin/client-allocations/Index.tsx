import { Head, useForm } from '@inertiajs/react';
import { ArrowRightLeft, Check, ShieldCheck, X } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Advisor = {
    id: number;
    name: string;
    user_type: string;
    capacity: {
        active_count: number;
        limit: number;
        remaining: number;
        warning: boolean;
        blocked: boolean;
    };
};

type Client = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type: string;
    status: string;
    primary_advisor_name: string | null;
    advisor_team_name: string | null;
    assignments: Array<{
        advisor_name: string | null;
        role: string;
        team_name: string | null;
    }>;
    reassign_url: string;
};

type TransferRequest = {
    id: string;
    client_label: string | null;
    requested_by_name: string | null;
    target_advisor_name: string | null;
    reason: string;
    created_at: string | null;
    approve_url: string;
    reject_url: string;
};

type Props = {
    clients: Client[];
    advisors: Advisor[];
    pendingRequests: TransferRequest[];
};

export default function ClientAllocationsIndex({
    clients,
    advisors,
    pendingRequests,
}: Props) {
    return (
        <>
            <Head title="Client allocations" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={ShieldCheck}
                    title="Client allocations"
                    description="See every client’s advisor ownership, review transfer requests, and reassign clients with an auditable reason."
                />

                <section className="space-y-3">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h2 className="text-lg font-semibold">Transfer requests</h2>
                            <p className="text-sm text-muted-foreground">
                                Approval moves the client to the requested advisor and preserves the approval record.
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {pendingRequests.length} awaiting review
                        </Badge>
                    </div>
                    <div className="overflow-hidden border">
                        <table className="fsa-responsive-table">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Client</th>
                                    <th className="px-3 py-2 font-medium">Requested by</th>
                                    <th className="px-3 py-2 font-medium">Receiving advisor</th>
                                    <th className="px-3 py-2 font-medium">Reason</th>
                                    <th className="px-3 py-2 font-medium">Decision note</th>
                                    <th className="px-3 py-2 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendingRequests.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-3 py-4 text-sm text-muted-foreground">
                                            No client transfer requests are awaiting review.
                                        </td>
                                    </tr>
                                ) : (
                                    pendingRequests.map((transfer) => (
                                        <TransferRequestRow key={transfer.id} transfer={transfer} />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="space-y-3">
                    <div>
                        <h2 className="text-lg font-semibold">Client ownership</h2>
                        <p className="text-sm text-muted-foreground">
                            Reassignment replaces the current advisor assignment. Client contacts and engagement records remain unchanged.
                        </p>
                    </div>
                    <div className="overflow-hidden border">
                        <table className="fsa-responsive-table">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Client</th>
                                    <th className="px-3 py-2 font-medium">Engagement</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 font-medium">Primary advisor</th>
                                    <th className="px-3 py-2 font-medium">Advisor team</th>
                                    <th className="px-3 py-2 font-medium">Assigned advisors</th>
                                    <th className="px-3 py-2 font-medium">Reassign to</th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-3 py-4 text-sm text-muted-foreground">
                                            No clients are available.
                                        </td>
                                    </tr>
                                ) : (
                                    clients.map((client) => (
                                        <ClientAllocationRow key={client.id} client={client} advisors={advisors} />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

function TransferRequestRow({ transfer }: { transfer: TransferRequest }) {
    const form = useForm({ decision_reason: '' });

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3" data-label="Client">{transfer.client_label}</td>
            <td className="px-3 py-3" data-label="Requested by">{transfer.requested_by_name}</td>
            <td className="px-3 py-3" data-label="Receiving advisor">{transfer.target_advisor_name}</td>
            <td className="px-3 py-3" data-label="Reason">{transfer.reason}</td>
            <td className="px-3 py-3" data-label="Decision note">
                <Textarea
                    value={form.data.decision_reason}
                    onChange={(event) => form.setData('decision_reason', event.target.value)}
                    rows={2}
                    aria-label={`Decision note for ${transfer.client_label ?? 'transfer request'}`}
                />
                <InputError message={form.errors.decision_reason} />
            </td>
            <td className="px-3 py-3" data-label="Actions">
                <div className="flex flex-wrap gap-2 md:justify-end">
                    <Button
                        type="button"
                        size="sm"
                        disabled={form.processing}
                        onClick={() => form.patch(transfer.approve_url, { preserveScroll: true })}
                    >
                        <Check className="size-4" aria-hidden="true" />
                        Approve
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => form.patch(transfer.reject_url, { preserveScroll: true })}
                    >
                        <X className="size-4" aria-hidden="true" />
                        Reject
                    </Button>
                </div>
            </td>
        </tr>
    );
}

function ClientAllocationRow({
    client,
    advisors,
}: {
    client: Client;
    advisors: Advisor[];
}) {
    const form = useForm({ target_advisor_id: '', reason: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(client.reassign_url, { preserveScroll: true });
    }

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3" data-label="Client">
                <div className="font-medium">{client.legal_name}</div>
                {client.trading_name ? <div className="text-xs text-muted-foreground">{client.trading_name}</div> : null}
            </td>
            <td className="px-3 py-3" data-label="Engagement">{client.engagement_type}</td>
            <td className="px-3 py-3" data-label="Status"><Badge variant="outline">{client.status}</Badge></td>
            <td className="px-3 py-3" data-label="Primary advisor">
                {client.primary_advisor_name ?? <span className="text-muted-foreground">Unassigned</span>}
            </td>
            <td className="px-3 py-3" data-label="Advisor team">
                {client.advisor_team_name ?? <span className="text-muted-foreground">No team</span>}
            </td>
            <td className="px-3 py-3" data-label="Assigned advisors">
                {client.assignments.length === 0 ? (
                    <span className="text-muted-foreground">Unassigned</span>
                ) : (
                    <div className="space-y-1 text-sm">
                        {client.assignments.map((assignment, index) => (
                            <div key={`${assignment.advisor_name}-${index}`}>
                                {assignment.advisor_name ?? 'Unknown advisor'}
                                <span className="text-muted-foreground"> ({formatRole(assignment.role)})</span>
                            </div>
                        ))}
                    </div>
                )}
            </td>
            <td className="px-3 py-3" data-label="Reassign to">
                <form onSubmit={submit} className="grid min-w-56 gap-2">
                    <Label className="sr-only" htmlFor={`client-allocation-${client.id}-advisor`}>
                        Receiving advisor
                    </Label>
                    <select
                        id={`client-allocation-${client.id}-advisor`}
                        value={form.data.target_advisor_id}
                        onChange={(event) => form.setData('target_advisor_id', event.target.value)}
                        className="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="">Select advisor</option>
                        {advisors.map((advisor) => (
                            <option key={advisor.id} value={advisor.id} disabled={advisor.capacity.blocked}>
                                {advisor.name} ({advisor.capacity.remaining} remaining)
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.target_advisor_id} />
                    <Label className="sr-only" htmlFor={`client-allocation-${client.id}-reason`}>
                        Reassignment reason
                    </Label>
                    <Input
                        id={`client-allocation-${client.id}-reason`}
                        value={form.data.reason}
                        placeholder="Reason for reassignment"
                        onChange={(event) => form.setData('reason', event.target.value)}
                    />
                    <InputError message={form.errors.reason} />
                    <Button type="submit" size="sm" disabled={form.processing}>
                        <ArrowRightLeft className="size-4" aria-hidden="true" />
                        Reassign
                    </Button>
                </form>
            </td>
        </tr>
    );
}

function formatRole(value: string) {
    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
