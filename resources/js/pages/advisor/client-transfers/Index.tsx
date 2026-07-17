import { Head, useForm } from '@inertiajs/react';
import { ArrowRightLeft, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type ClientOption = {
    id: string;
    label: string;
    engagement_type: string;
};

type AdvisorOption = {
    id: number;
    name: string;
};

type PendingRequest = {
    id: string;
    client_label: string | null;
    target_advisor_name: string | null;
    reason: string;
    created_at: string | null;
};

type Props = {
    clients: ClientOption[];
    advisors: AdvisorOption[];
    pendingRequests: PendingRequest[];
    defaults: {
        client_id: string;
        target_advisor_id: string;
        reason: string;
    };
    storeUrl: string;
};

export default function ClientTransfersIndex({
    clients,
    advisors,
    pendingRequests,
    defaults,
    storeUrl,
}: Props) {
    const form = useForm({
        client_id: defaults.client_id,
        target_advisor_id: defaults.target_advisor_id,
        reason: defaults.reason,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(storeUrl, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Client transfers" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Advisor workspace"
                    icon={ArrowRightLeft}
                    title="Request client transfer"
                    description="Ask a super administrator to move an assigned client to another advisor. The client remains with you until the request is approved."
                />

                <form
                    onSubmit={submit}
                    className="grid gap-5 border p-5 md:grid-cols-2"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="transfer-client">Client</Label>
                        <select
                            id="transfer-client"
                            value={form.data.client_id}
                            onChange={(event) =>
                                form.setData('client_id', event.target.value)
                            }
                            className="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Select a client</option>
                            {clients.map((client) => (
                                <option key={client.id} value={client.id}>
                                    {client.label} - {client.engagement_type}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.client_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="transfer-advisor">Receiving advisor</Label>
                        <select
                            id="transfer-advisor"
                            value={form.data.target_advisor_id}
                            onChange={(event) =>
                                form.setData(
                                    'target_advisor_id',
                                    event.target.value,
                                )
                            }
                            className="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Select an advisor</option>
                            {advisors.map((advisor) => (
                                <option key={advisor.id} value={advisor.id}>
                                    {advisor.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.target_advisor_id} />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="transfer-reason">Reason for transfer</Label>
                        <Textarea
                            id="transfer-reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            rows={4}
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    <div className="flex justify-end md:col-span-2">
                        <Button
                            type="submit"
                            disabled={form.processing || clients.length === 0 || advisors.length === 0}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            Send request
                        </Button>
                    </div>
                </form>

                <section className="space-y-3">
                    <div>
                        <h2 className="text-lg font-semibold">Awaiting review</h2>
                        <p className="text-sm text-muted-foreground">
                            Approved requests transfer the client and record the decision in the audit trail.
                        </p>
                    </div>
                    <div className="overflow-hidden border">
                        <table className="fsa-responsive-table">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Client</th>
                                    <th className="px-3 py-2 font-medium">Receiving advisor</th>
                                    <th className="px-3 py-2 font-medium">Reason</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendingRequests.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-3 py-4 text-sm text-muted-foreground">
                                            No transfer requests are awaiting review.
                                        </td>
                                    </tr>
                                ) : (
                                    pendingRequests.map((transfer) => (
                                        <tr key={transfer.id} className="border-t">
                                            <td className="px-3 py-2" data-label="Client">
                                                {transfer.client_label}
                                            </td>
                                            <td className="px-3 py-2" data-label="Receiving advisor">
                                                {transfer.target_advisor_name}
                                            </td>
                                            <td className="px-3 py-2" data-label="Reason">
                                                {transfer.reason}
                                            </td>
                                            <td className="px-3 py-2" data-label="Status">
                                                <Badge variant="secondary">Pending review</Badge>
                                            </td>
                                        </tr>
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

ClientTransfersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Client transfers',
            href: '/advisor/client-transfers',
        },
    ],
};
