import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Inbox, PauseCircle, Send, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ProspectLead = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    company: string | null;
    engagement_interest: string | null;
    message: string;
    source: string;
    status: 'new' | 'invited' | 'parked' | 'declined';
    triage_outcome: string | null;
    triage_notes: string | null;
    triaged_at: string | null;
    created_at: string | null;
    assigned_advisor_name: string | null;
    triaged_by_name: string | null;
    invite_status: string | null;
    invite_path_label: string | null;
    invite_package_scope_label: string | null;
    triage_url: string;
};

type InviteOption = {
    value: string;
    label: string;
    description: string;
    target_user_type: string;
    intended_service_type: string;
    intended_package_scope: string | null;
};

type Props = {
    leads: ProspectLead[];
    inviteOptions: InviteOption[];
    canTriage: boolean;
};

export default function ProspectInboxIndex({
    leads,
    inviteOptions,
    canTriage,
}: Props) {
    return (
        <>
            <Head title="Prospects" />

            <div className="space-y-6">
                <div>
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Inbox className="size-4" aria-hidden="true" />
                        Website integration
                    </div>
                    <h1 className="mt-1 text-xl font-semibold">Prospects</h1>
                </div>

                {leads.length === 0 ? (
                    <div className="rounded-md border bg-background p-8 text-sm text-muted-foreground">
                        No prospect leads captured.
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {leads.map((lead) => (
                            <LeadCard
                                key={lead.id}
                                lead={lead}
                                inviteOptions={inviteOptions}
                                canTriage={canTriage}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function LeadCard({
    lead,
    inviteOptions,
    canTriage,
}: {
    lead: ProspectLead;
    inviteOptions: InviteOption[];
    canTriage: boolean;
}) {
    const form = useForm({
        triage_notes: lead.triage_notes ?? '',
        invite_path: inviteOptions[0]?.value ?? 'business_idea',
    });
    const selectedInvite = inviteOptions.find(
        (option) => option.value === form.data.invite_path,
    );

    const submit = (outcome: 'invited' | 'parked' | 'declined') => {
        router.patch(
            lead.triage_url,
            {
                ...form.data,
                outcome,
            },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <article className="rounded-md border bg-background p-4">
            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="min-w-0 space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant={statusVariant(lead.status)}>
                                    {lead.status}
                                </Badge>
                                <Badge variant="outline">{lead.source}</Badge>
                                {lead.invite_status && (
                                    <Badge variant="secondary">
                                        invite {lead.invite_status}
                                    </Badge>
                                )}
                                {lead.invite_path_label && (
                                    <Badge variant="outline">
                                        {lead.invite_path_label}
                                    </Badge>
                                )}
                            </div>
                            <h2 className="mt-3 text-base font-semibold">
                                {lead.name}
                            </h2>
                            <div className="mt-1 text-sm text-muted-foreground">
                                {lead.company ?? lead.email}
                            </div>
                        </div>
                        <div className="text-sm text-muted-foreground sm:text-right">
                            <div>{formatDate(lead.created_at)}</div>
                            {lead.assigned_advisor_name && (
                                <div>{lead.assigned_advisor_name}</div>
                            )}
                        </div>
                    </div>

                    <dl className="grid gap-3 text-sm sm:grid-cols-3">
                        <div className="min-w-0">
                            <dt className="text-xs text-muted-foreground">
                                Email
                            </dt>
                            <dd className="break-words">{lead.email}</dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-xs text-muted-foreground">
                                Phone
                            </dt>
                            <dd className="break-words">
                                {lead.phone ?? 'None'}
                            </dd>
                        </div>
                        <div className="min-w-0">
                            <dt className="text-xs text-muted-foreground">
                                Interest
                            </dt>
                            <dd className="break-words">
                                {lead.engagement_interest ?? 'Unspecified'}
                            </dd>
                        </div>
                    </dl>

                    <p className="text-sm leading-6 whitespace-pre-wrap">
                        {lead.message}
                    </p>

                    {lead.triaged_at && (
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <CheckCircle2
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Triaged by {lead.triaged_by_name ?? 'advisor'} on{' '}
                            {formatDate(lead.triaged_at)}
                        </div>
                    )}
                </div>

                {canTriage && (
                    <div className="space-y-4 rounded-md border bg-muted/20 p-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`invite_path_${lead.id}`}>
                                Invite path
                            </Label>
                            <Select
                                value={form.data.invite_path}
                                onValueChange={(value) =>
                                    form.setData('invite_path', value)
                                }
                            >
                                <SelectTrigger id={`invite_path_${lead.id}`}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {inviteOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {selectedInvite ? (
                                <p className="text-xs leading-5 text-muted-foreground">
                                    {selectedInvite.description}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`triage_notes_${lead.id}`}>
                                Notes
                            </Label>
                            <textarea
                                id={`triage_notes_${lead.id}`}
                                value={form.data.triage_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'triage_notes',
                                        event.target.value,
                                    )
                                }
                                rows={4}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </div>

                        <div className="grid gap-2 sm:grid-cols-3 xl:grid-cols-1">
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => submit('invited')}
                            >
                                <Send className="size-4" aria-hidden="true" />
                                Invite
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => submit('parked')}
                            >
                                <PauseCircle
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Park
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => submit('declined')}
                            >
                                <XCircle
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Decline
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </article>
    );
}

function statusVariant(
    status: ProspectLead['status'],
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (status === 'invited') {
        return 'default';
    }

    if (status === 'parked') {
        return 'secondary';
    }

    if (status === 'declined') {
        return 'destructive';
    }

    return 'outline';
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

ProspectInboxIndex.layout = {
    breadcrumbs: [
        {
            title: 'Prospects',
            href: '/advisor/prospects',
        },
    ],
};
