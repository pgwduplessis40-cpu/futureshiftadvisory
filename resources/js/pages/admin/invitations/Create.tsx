import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
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
};

export default function InvitationsCreate({ userTypes }: Props) {
    const form = useForm({
        email: '',
        target_user_type: userTypes[0] ?? '',
        target_role: userTypes[0] ?? '',
    });

    return (
        <>
            <Head title="New invitation" />

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

                <Button type="submit" disabled={form.processing}>
                    Issue invite
                </Button>
            </form>
        </>
    );
}
