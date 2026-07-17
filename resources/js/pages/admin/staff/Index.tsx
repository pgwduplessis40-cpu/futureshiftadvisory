import { Head, Link, useForm } from '@inertiajs/react';
import { Save, UserPlus, UsersRound } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StaffUser = {
    id: string | number;
    name: string;
    email: string;
    user_type: string;
    primary_role: string;
    session_timeout_minutes: number | null;
    advisor_client_capacity_limit: number | null;
    client_capacity: {
        active_count: number;
        limit: number;
        warning_threshold: number;
        remaining: number;
        warning: boolean;
        blocked: boolean;
    } | null;
    mfa_enabled_at: string | null;
    suspended_at: string | null;
    suspended_reason: string | null;
    update_url: string;
};

type StaffInvite = {
    id: string;
    email: string;
    target_role: string;
    target_user_type: string;
    expires_at: string | null;
    accepted_at: string | null;
};

type StaffForm = {
    name: string;
    user_type: string;
    primary_role: string;
    session_timeout_minutes: number;
    advisor_client_capacity_limit: number;
    suspended: boolean;
    suspended_reason: string;
};

type Props = {
    staff: StaffUser[];
    pendingInvites: StaffInvite[];
    staffTypes: string[];
    inviteUrl: string;
};

export default function StaffIndex({
    staff,
    pendingInvites,
    staffTypes,
    inviteUrl,
}: Props) {
    return (
        <>
            <Head title="Staff" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={UsersRound}
                    title="Staff & advisors"
                    description="Maintain internal access for admins, advisors, junior advisors, and entrepreneur mentors."
                    actions={
                        <Button asChild size="sm">
                            <Link href={inviteUrl}>
                                <UserPlus
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Invite staff
                            </Link>
                        </Button>
                    }
                />

                <section className="overflow-hidden rounded-md border">
                    <table className="fsa-responsive-table">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="w-[18%] px-3 py-2 font-medium">
                                    Name
                                </th>
                                <th className="w-[18%] px-3 py-2 font-medium">
                                    Log on detail
                                </th>
                                <th className="w-[15%] px-3 py-2 font-medium">
                                    User type
                                </th>
                                <th className="w-[15%] px-3 py-2 font-medium">
                                    Primary role
                                </th>
                                <th className="w-[12%] px-3 py-2 font-medium">
                                    Timeout (minutes)
                                </th>
                                <th className="w-[14%] px-3 py-2 font-medium">
                                    Client capacity
                                </th>
                                <th className="w-[12%] px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="w-[8%] px-3 py-2 text-right font-medium">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {staff.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-3 py-4 text-muted-foreground"
                                    >
                                        No staff accounts yet.
                                    </td>
                                </tr>
                            ) : (
                                staff.map((user) => (
                                    <StaffRow
                                        key={user.id}
                                        user={user}
                                        staffTypes={staffTypes}
                                    />
                                ))
                            )}
                        </tbody>
                    </table>
                </section>

                <section className="space-y-3">
                    <div>
                        <h2 className="text-sm font-medium">
                            Pending staff invites
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Open invitations issued for staff and advisor roles.
                        </p>
                    </div>
                    <div className="overflow-hidden rounded-md border">
                        <table className="fsa-responsive-table">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Email
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        User type
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Role
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendingInvites.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-3 py-4 text-muted-foreground"
                                        >
                                            No pending staff invites.
                                        </td>
                                    </tr>
                                ) : (
                                    pendingInvites.map((invite) => (
                                        <tr
                                            key={invite.id}
                                            className="border-t"
                                        >
                                            <td
                                                className="px-3 py-2"
                                                data-label="Email"
                                            >
                                                {invite.email}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                data-label="User type"
                                            >
                                                {formatRole(
                                                    invite.target_user_type,
                                                )}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                data-label="Role"
                                            >
                                                {formatRole(invite.target_role)}
                                            </td>
                                            <td
                                                className="px-3 py-2"
                                                data-label="Status"
                                            >
                                                <Badge
                                                    variant={
                                                        invite.accepted_at
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {invite.accepted_at
                                                        ? 'Accepted'
                                                        : 'Pending'}
                                                </Badge>
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

function StaffRow({
    user,
    staffTypes,
}: {
    user: StaffUser;
    staffTypes: string[];
}) {
    const form = useForm<StaffForm>({
        name: user.name,
        user_type: user.user_type,
        primary_role: user.primary_role,
        session_timeout_minutes: user.session_timeout_minutes ?? 30,
        advisor_client_capacity_limit:
            user.advisor_client_capacity_limit ?? user.client_capacity?.limit ?? 30,
        suspended: user.suspended_at !== null,
        suspended_reason: user.suspended_reason ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(user.update_url, { preserveScroll: true });
    }

    return (
        <tr className="border-t align-top">
            <td className="px-3 py-3" data-label="Name">
                <form id={`staff-${user.id}`} onSubmit={submit} />
                <div className="grid gap-2">
                    <Input
                        form={`staff-${user.id}`}
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <InputError message={form.errors.name} />
                </div>
            </td>
            <td className="px-3 py-3" data-label="Log on detail">
                <div className="text-sm break-all">{user.email}</div>
            </td>
            <td className="px-3 py-3" data-label="User type">
                <select
                    form={`staff-${user.id}`}
                    value={form.data.user_type}
                    onChange={(event) => {
                        form.setData('user_type', event.target.value);
                        form.setData('primary_role', event.target.value);
                    }}
                    className="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    {staffTypes.map((type) => (
                        <option key={type} value={type}>
                            {formatRole(type)}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.user_type} />
            </td>
            <td className="px-3 py-3" data-label="Primary role">
                <select
                    form={`staff-${user.id}`}
                    value={form.data.primary_role}
                    onChange={(event) =>
                        form.setData('primary_role', event.target.value)
                    }
                    className="h-10 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    {staffTypes.map((type) => (
                        <option key={type} value={type}>
                            {formatRole(type)}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.primary_role} />
            </td>
            <td className="px-3 py-3" data-label="Timeout (minutes)">
                <Input
                    form={`staff-${user.id}`}
                    type="number"
                    min="5"
                    max="240"
                    aria-label="Session timeout in minutes"
                    value={form.data.session_timeout_minutes}
                    onChange={(event) =>
                        form.setData(
                            'session_timeout_minutes',
                            Number(event.target.value),
                        )
                    }
                />
                <InputError message={form.errors.session_timeout_minutes} />
            </td>
            <td className="px-3 py-3" data-label="Client capacity">
                {isAdvisor(form.data.user_type) ? (
                    <div className="grid gap-2">
                        <Input
                            form={`staff-${user.id}`}
                            type="number"
                            min="1"
                            max="500"
                            aria-label="Active client capacity"
                            value={form.data.advisor_client_capacity_limit}
                            onChange={(event) =>
                                form.setData(
                                    'advisor_client_capacity_limit',
                                    Number(event.target.value),
                                )
                            }
                        />
                        {user.client_capacity ? (
                            <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                <span>
                                    {user.client_capacity.active_count} active /{' '}
                                    {form.data.advisor_client_capacity_limit}
                                </span>
                                <Badge
                                    variant={
                                        user.client_capacity.blocked
                                            ? 'destructive'
                                            : user.client_capacity.warning
                                              ? 'secondary'
                                              : 'outline'
                                    }
                                >
                                    {user.client_capacity.remaining} remaining
                                </Badge>
                            </div>
                        ) : null}
                        <InputError
                            message={form.errors.advisor_client_capacity_limit}
                        />
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">Not applicable</span>
                )}
            </td>
            <td className="px-3 py-3" data-label="Status">
                <div className="grid gap-2">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={`suspended-${user.id}`}
                            checked={form.data.suspended}
                            onCheckedChange={(checked) =>
                                form.setData('suspended', checked === true)
                            }
                        />
                        <Label htmlFor={`suspended-${user.id}`}>
                            Suspended
                        </Label>
                        <Badge
                            variant={
                                user.mfa_enabled_at ? 'secondary' : 'outline'
                            }
                        >
                            {user.mfa_enabled_at ? 'MFA' : 'MFA pending'}
                        </Badge>
                    </div>
                    {form.data.suspended ? (
                        <Input
                            form={`staff-${user.id}`}
                            value={form.data.suspended_reason}
                            placeholder="Suspension reason"
                            onChange={(event) =>
                                form.setData(
                                    'suspended_reason',
                                    event.target.value,
                                )
                            }
                        />
                    ) : null}
                    <InputError message={form.errors.suspended} />
                    <InputError message={form.errors.suspended_reason} />
                </div>
            </td>
            <td className="px-3 py-3 text-right" data-label="Action">
                <Button
                    form={`staff-${user.id}`}
                    type="submit"
                    size="sm"
                    disabled={form.processing}
                >
                    <Save className="size-4" aria-hidden="true" />
                    Save
                </Button>
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

function isAdvisor(userType: string) {
    return userType === 'advisor' || userType === 'junior_advisor';
}
