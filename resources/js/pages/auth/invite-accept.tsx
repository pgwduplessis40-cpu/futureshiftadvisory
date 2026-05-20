import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    token: string;
    email: string;
    targetRole: string;
    targetUserType: string;
    passwordRules: string;
};

export default function InviteAccept({
    token,
    email,
    targetRole,
    targetUserType,
    passwordRules,
}: Props) {
    const form = useForm({
        name: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <>
            <Head title="Accept invitation" />

            <form
                className="flex flex-col gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/invite/${token}`);
                }}
            >
                <div className="grid gap-1 text-sm text-muted-foreground">
                    <span>{email}</span>
                    <span>
                        {targetUserType} / {targetRole}
                    </span>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                        autoFocus
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                        id="password"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        required
                        autoComplete="new-password"
                    />
                    <p className="text-xs text-muted-foreground">
                        {passwordRules}
                    </p>
                    <InputError message={form.errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">
                        Confirm password
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        value={form.data.password_confirmation}
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        required
                        autoComplete="new-password"
                    />
                </div>

                <Button type="submit" disabled={form.processing}>
                    Continue
                </Button>
            </form>
        </>
    );
}

InviteAccept.layout = {
    title: 'Accept invitation',
    description: 'Create your account password',
};
