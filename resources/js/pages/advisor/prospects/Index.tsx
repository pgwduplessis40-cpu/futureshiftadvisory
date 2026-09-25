import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Inbox,
    PauseCircle,
    Send,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ProspectStatus = 'new' | 'invited' | 'parked' | 'declined';

type ProspectLead = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    company: string | null;
    engagement_interest: string | null;
    message: string;
    source: string;
    status: ProspectStatus;
    triage_outcome: string | null;
    triage_notes: string | null;
    triaged_at: string | null;
    created_at: string | null;
    assigned_advisor_name: string | null;
    triaged_by_name: string | null;
    invite_status: string | null;
    invite_path: string | null;
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

type ProspectRollup = {
    key: 'invite' | 'parked' | 'declined';
    title: string;
    description: string;
    defaultOpen: boolean;
    matches: (lead: ProspectLead) => boolean;
};

const prospectRollups: ProspectRollup[] = [
    {
        key: 'invite',
        title: 'Invite',
        description:
            'New prospects awaiting a decision and prospects already invited.',
        defaultOpen: true,
        matches: (lead) => lead.status === 'new' || lead.status === 'invited',
    },
    {
        key: 'parked',
        title: 'Parked',
        description: 'Prospects being held for a later follow-up.',
        defaultOpen: false,
        matches: (lead) => lead.status === 'parked',
    },
    {
        key: 'declined',
        title: 'Declined',
        description: 'Prospects not progressing at this time.',
        defaultOpen: false,
        matches: (lead) => lead.status === 'declined',
    },
];

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
                    <p className="mt-1 text-sm text-muted-foreground">
                        Prospects are grouped by outcome and ordered by their
                        invite path.
                    </p>
                </div>

                <div className="grid gap-4">
                    {prospectRollups.map((rollup) => (
                        <ProspectRollupTable
                            key={rollup.key}
                            rollup={rollup}
                            leads={sortByInvitePath(
                                leads.filter(rollup.matches),
                            )}
                            inviteOptions={inviteOptions}
                            canTriage={canTriage}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

function ProspectRollupTable({
    rollup,
    leads,
    inviteOptions,
    canTriage,
}: {
    rollup: ProspectRollup;
    leads: ProspectLead[];
    inviteOptions: InviteOption[];
    canTriage: boolean;
}) {
    const [open, setOpen] = useState(rollup.defaultOpen);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="rounded-md border bg-background p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <h2 className="text-base font-semibold">
                            {rollup.title}
                        </h2>
                        <Badge variant={rollupBadgeVariant(rollup.key)}>
                            {leads.length}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {rollup.description}
                    </p>
                </div>

                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        aria-label={
                            open
                                ? `Collapse ${rollup.title.toLowerCase()} prospects`
                                : `Expand ${rollup.title.toLowerCase()} prospects`
                        }
                    >
                        {open ? (
                            <ChevronUp className="size-4" aria-hidden="true" />
                        ) : (
                            <ChevronDown
                                className="size-4"
                                aria-hidden="true"
                            />
                        )}
                        {open ? 'Collapse' : 'View'}
                    </Button>
                </CollapsibleTrigger>
            </div>

            <CollapsibleContent className="pt-4">
                <div className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table table-fixed md:table-fixed">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="w-[24%] px-3 py-2 font-medium">
                                    Prospect
                                </th>
                                <th className="w-[15%] px-3 py-2 font-medium">
                                    Invite path
                                </th>
                                <th className="w-[16%] px-3 py-2 font-medium">
                                    Contact
                                </th>
                                <th className="w-[13%] px-3 py-2 font-medium">
                                    Interest
                                </th>
                                <th className="w-[17%] px-3 py-2 font-medium">
                                    Triage
                                </th>
                                <th className="w-[15%] px-3 py-2 font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {leads.length === 0 ? (
                                <tr>
                                    <td
                                        className="px-3 py-4 text-muted-foreground"
                                        colSpan={6}
                                    >
                                        No prospects in this roll-up.
                                    </td>
                                </tr>
                            ) : (
                                leads.map((lead) => (
                                    <tr key={lead.id} className="border-t">
                                        <td
                                            className="px-3 py-3 align-top"
                                            data-label="Prospect"
                                        >
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="font-medium">
                                                    {lead.name}
                                                </span>
                                                <Badge
                                                    variant={statusVariant(
                                                        lead.status,
                                                    )}
                                                >
                                                    {lead.status}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {lead.source}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {lead.company ??
                                                    'No company provided'}
                                            </p>
                                            <p className="mt-2 line-clamp-2 text-sm leading-5">
                                                {lead.message}
                                            </p>
                                        </td>
                                        <td
                                            className="px-3 py-3 align-top"
                                            data-label="Invite path"
                                        >
                                            {lead.invite_path_label ? (
                                                <div className="space-y-1">
                                                    <Badge variant="outline">
                                                        {lead.invite_path_label}
                                                    </Badge>
                                                    {lead.invite_package_scope_label ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                lead.invite_package_scope_label
                                                            }
                                                        </p>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Unassigned
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className="px-3 py-3 align-top"
                                            data-label="Contact"
                                        >
                                            <a
                                                href={`mailto:${lead.email}`}
                                                className="break-words underline-offset-4 hover:underline"
                                            >
                                                {lead.email}
                                            </a>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {lead.phone ??
                                                    'No phone provided'}
                                            </p>
                                        </td>
                                        <td
                                            className="px-3 py-3 align-top"
                                            data-label="Interest"
                                        >
                                            {lead.engagement_interest ??
                                                'Unspecified'}
                                        </td>
                                        <td
                                            className="px-3 py-3 align-top"
                                            data-label="Triage"
                                        >
                                            {lead.triaged_at ? (
                                                <div className="space-y-1 text-sm">
                                                    <p>
                                                        {lead.triaged_by_name ??
                                                            'Advisor'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatDate(
                                                            lead.triaged_at,
                                                        )}
                                                    </p>
                                                    {lead.triage_notes ? (
                                                        <p className="text-xs whitespace-pre-wrap text-muted-foreground">
                                                            {lead.triage_notes}
                                                        </p>
                                                    ) : null}
                                                    {lead.invite_status ? (
                                                        <Badge variant="secondary">
                                                            Invite{' '}
                                                            {lead.invite_status}
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Awaiting triage
                                                </span>
                                            )}
                                        </td>
                                        <td
                                            className="px-3 py-3 align-top"
                                            data-label="Actions"
                                        >
                                            {canTriage ? (
                                                <LeadTriageActions
                                                    lead={lead}
                                                    inviteOptions={
                                                        inviteOptions
                                                    }
                                                />
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    View only
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

function LeadTriageActions({
    lead,
    inviteOptions,
}: {
    lead: ProspectLead;
    inviteOptions: InviteOption[];
}) {
    const form = useForm({
        triage_notes: lead.triage_notes ?? '',
        invite_path:
            lead.invite_path ?? inviteOptions[0]?.value ?? 'business_idea',
    });

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
        <div className="grid gap-2">
            <div className="grid gap-1">
                <Label htmlFor={`invite_path_${lead.id}`} className="sr-only">
                    Invite path for {lead.name}
                </Label>
                <Select
                    value={form.data.invite_path}
                    onValueChange={(value) =>
                        form.setData('invite_path', value)
                    }
                    disabled={form.processing}
                >
                    <SelectTrigger id={`invite_path_${lead.id}`}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {inviteOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid gap-1">
                <Label htmlFor={`triage_notes_${lead.id}`} className="sr-only">
                    Triage notes for {lead.name}
                </Label>
                <textarea
                    id={`triage_notes_${lead.id}`}
                    value={form.data.triage_notes}
                    onChange={(event) =>
                        form.setData('triage_notes', event.target.value)
                    }
                    rows={2}
                    disabled={form.processing}
                    placeholder="Triage notes"
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
            </div>

            <div className="grid gap-1">
                <Button
                    type="button"
                    size="sm"
                    onClick={() => submit('invited')}
                    disabled={form.processing}
                >
                    <Send className="size-3.5" aria-hidden="true" />
                    Invite
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => submit('parked')}
                    disabled={form.processing}
                >
                    <PauseCircle className="size-3.5" aria-hidden="true" />
                    Park
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => submit('declined')}
                    disabled={form.processing}
                >
                    <XCircle className="size-3.5" aria-hidden="true" />
                    Decline
                </Button>
            </div>
        </div>
    );
}

function sortByInvitePath(leads: ProspectLead[]): ProspectLead[] {
    const invitePathOrder: Record<string, number> = {
        business_idea: 0,
        buying_business: 1,
    };

    return [...leads].sort((left, right) => {
        const pathDifference =
            (invitePathOrder[left.invite_path ?? ''] ?? 2) -
            (invitePathOrder[right.invite_path ?? ''] ?? 2);

        if (pathDifference !== 0) {
            return pathDifference;
        }

        return dateValue(right.created_at) - dateValue(left.created_at);
    });
}

function rollupBadgeVariant(
    rollup: ProspectRollup['key'],
): 'default' | 'secondary' | 'destructive' {
    if (rollup === 'invite') {
        return 'default';
    }

    if (rollup === 'parked') {
        return 'secondary';
    }

    return 'destructive';
}

function statusVariant(
    status: ProspectStatus,
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

function dateValue(value: string | null): number {
    return value ? new Date(value).getTime() : 0;
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
