import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send, UserPlus } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Props = {
    userTypes: string[];
    backUrl: string;
};

export default function InvitationsCreate({ userTypes, backUrl }: Props) {
    const form = useForm({
        email: '',
        target_user_type: userTypes[0] ?? '',
        target_role: userTypes[0] ?? '',
        return_to: backUrl,
    });

    return (
        <>
            <Head title="New invitation" />

            <div className="space-y-6">
                <PageHeader
                    eyebrow="Administration"
                    icon={UserPlus}
                    title="New invitation"
                    description="Issue a secure invitation for a new user account."
                    actions={
                        <Button asChild size="sm" variant="outline">
                            <Link href={backUrl}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Back
                            </Link>
                        </Button>
                    }
                />

                <form
                    className="max-w-lg space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/admin/invitations');
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label>User type</Label>
                        <Select
                            value={form.data.target_user_type}
                            onValueChange={(value) => {
                                form.setData('target_user_type', value);
                                form.setData('target_role', value);
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {userTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.target_user_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="target_role">Role</Label>
                        <Input
                            id="target_role"
                            value={form.data.target_role}
                            onChange={(event) =>
                                form.setData('target_role', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.target_role} />
                    </div>

                    <InputError message={form.errors.return_to} />

                    <Button type="submit" disabled={form.processing}>
                        <Send className="size-4" aria-hidden="true" />
                        Issue invite
                    </Button>
                </form>
            </div>
        </>
    );
}
