import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Invite = {
    id: string;
    email: string;
    target_role: string;
    target_user_type: string;
    expires_at: string;
    accepted_at: string | null;
};

type Props = {
    invites: Invite[];
};

export default function InvitationsIndex({ invites }: Props) {
    return (
        <>
            <Head title="Invitations" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Invitations</h1>
                    <Button asChild size="sm">
                        <Link href="/admin/invitations/create">
                            <Plus className="size-4" aria-hidden="true" />
                            New
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Email</th>
                                <th className="px-3 py-2 font-medium">Type</th>
                                <th className="px-3 py-2 font-medium">Role</th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {invites.map((invite) => (
                                <tr key={invite.id} className="border-t">
                                    <td className="px-3 py-2">
                                        {invite.email}
                                    </td>
                                    <td className="px-3 py-2">
                                        {invite.target_user_type}
                                    </td>
                                    <td className="px-3 py-2">
                                        {invite.target_role}
                                    </td>
                                    <td className="px-3 py-2">
                                        {invite.accepted_at
                                            ? 'accepted'
                                            : 'pending'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
