import { Head, useForm } from '@inertiajs/react';
import { Check, Save, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Program = {
    status: 'open' | 'closed' | 'suspended';
    updated_at: string | null;
    updated_by_name: string | null;
    update_url: string;
};

type PilotClient = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type: string;
    enabled: boolean;
    starts_at: string | null;
    expires_at: string | null;
    reason: string | null;
    approved_by_name: string | null;
    approved_at: string | null;
    active_for_new_proposals: boolean;
    update_url: string;
};

type Props = {
    program: Program;
    statuses: Program['status'][];
    clients: PilotClient[];
};

export default function PilotFeeWaiversIndex({
    program,
    statuses,
    clients,
}: Props) {
    const programForm = useForm({ status: program.status });

    function saveProgram(event: FormEvent) {
        event.preventDefault();
        programForm.patch(program.update_url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Pilot fee waivers" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={ShieldCheck}
                    title="Pilot fee waivers"
                    description="Manage the temporary fee-free pilot separately from service-rate settings. A proposal keeps the pricing terms in force when it was generated."
                />

                <section className="grid gap-4 border p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-base font-semibold">Pilot programme</h2>
                            <Badge variant={program.status === 'open' ? 'secondary' : 'outline'}>
                                {statusLabel(program.status)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Open permits new client waivers. Closed and suspended leave existing proposal terms unchanged, but stop new waived proposals.
                        </p>
                        {program.updated_at ? (
                            <p className="text-xs text-muted-foreground">
                                Last changed {formatDate(program.updated_at)}
                                {program.updated_by_name
                                    ? ` by ${program.updated_by_name}`
                                    : ''}
                            </p>
                        ) : null}
                    </div>
                    <form
                        onSubmit={saveProgram}
                        className="flex flex-wrap items-end gap-2"
                    >
                        <div className="grid gap-1">
                            <Label htmlFor="pilot-program-status">Status</Label>
                            <select
                                id="pilot-program-status"
                                value={programForm.data.status}
                                onChange={(event) =>
                                    programForm.setData(
                                        'status',
                                        event.target.value as Program['status'],
                                    )
                                }
                                className="h-10 min-w-36 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {statusLabel(status)}
                                    </option>
                                ))}
                            </select>
                            <InputError message={programForm.errors.status} />
                        </div>
                        <Button type="submit" disabled={programForm.processing}>
                            <Save className="size-4" aria-hidden="true" />
                            Save programme
                        </Button>
                    </form>
                </section>

                <section className="space-y-3">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h2 className="text-lg font-semibold">Client waivers</h2>
                            <p className="text-sm text-muted-foreground">
                                Assign an expiry and an auditable reason to every pilot client. Only an open programme applies a waiver to newly generated proposals.
                            </p>
                        </div>
                        <Badge variant="outline">
                            {clients.filter((client) => client.active_for_new_proposals).length} active
                        </Badge>
                    </div>
                    <div className="overflow-x-auto border">
                        <table className="min-w-[1100px] w-full text-left text-sm">
                            <thead className="bg-muted/60">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Client</th>
                                    <th className="px-3 py-2 font-medium">Pilot</th>
                                    <th className="px-3 py-2 font-medium">Starts</th>
                                    <th className="px-3 py-2 font-medium">Review by</th>
                                    <th className="px-3 py-2 font-medium">Approval reason</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    <th className="px-3 py-2 text-right font-medium">Save</th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.map((client) => (
                                    <PilotClientRow
                                        key={client.id}
                                        client={client}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

function PilotClientRow({ client }: { client: PilotClient }) {
    const form = useForm({
        enabled: client.enabled,
        starts_at: dateInputValue(client.starts_at),
        expires_at: dateInputValue(client.expires_at),
        reason: client.reason ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(client.update_url, { preserveScroll: true });
    }

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3">
                <div className="font-medium">{client.legal_name}</div>
                <div className="text-xs text-muted-foreground">
                    {client.trading_name ?? client.engagement_type}
                </div>
            </td>
            <td className="px-3 py-3">
                <label className="flex items-center gap-2 whitespace-nowrap">
                    <input
                        type="checkbox"
                        checked={form.data.enabled}
                        onChange={(event) =>
                            form.setData('enabled', event.target.checked)
                        }
                        className="size-4 rounded border-input text-primary focus:ring-ring"
                    />
                    <span>Waive fee</span>
                </label>
                <InputError message={form.errors.enabled} />
            </td>
            <td className="px-3 py-3">
                <Label className="sr-only" htmlFor={`pilot-start-${client.id}`}>
                    Pilot start date
                </Label>
                <Input
                    id={`pilot-start-${client.id}`}
                    type="date"
                    value={form.data.starts_at}
                    onChange={(event) =>
                        form.setData('starts_at', event.target.value)
                    }
                    disabled={!form.data.enabled}
                />
                <InputError message={form.errors.starts_at} />
            </td>
            <td className="px-3 py-3">
                <Label className="sr-only" htmlFor={`pilot-expiry-${client.id}`}>
                    Pilot review date
                </Label>
                <Input
                    id={`pilot-expiry-${client.id}`}
                    type="date"
                    value={form.data.expires_at}
                    onChange={(event) =>
                        form.setData('expires_at', event.target.value)
                    }
                    disabled={!form.data.enabled}
                />
                <InputError message={form.errors.expires_at} />
            </td>
            <td className="px-3 py-3">
                <Label className="sr-only" htmlFor={`pilot-reason-${client.id}`}>
                    Pilot approval reason
                </Label>
                <Input
                    id={`pilot-reason-${client.id}`}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    disabled={!form.data.enabled}
                    placeholder="Reason for waiver"
                />
                <InputError message={form.errors.reason} />
            </td>
            <td className="px-3 py-3">
                {client.active_for_new_proposals ? (
                    <Badge variant="secondary">Active</Badge>
                ) : client.enabled ? (
                    <Badge variant="outline">Held</Badge>
                ) : (
                    <Badge variant="outline">Standard fees</Badge>
                )}
                {client.approved_by_name ? (
                    <div className="mt-1 text-xs text-muted-foreground">
                        {client.approved_by_name}
                    </div>
                ) : null}
            </td>
            <td className="px-3 py-3 text-right">
                <form onSubmit={submit}>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        <Check className="size-4" aria-hidden="true" />
                        Save
                    </Button>
                </form>
            </td>
        </tr>
    );
}

function statusLabel(status: Program['status']) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function dateInputValue(value: string | null) {
    return value ? value.slice(0, 10) : '';
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('en-NZ', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}
