import { Head, Link } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { request } from '@/routes/password';

type Props = {
    email: string;
};

export default function InviteAccepted({ email }: Props) {
    return (
        <>
            <Head title="Account already activated" />

            <div className="flex flex-col gap-6">
                <div className="space-y-3 text-sm text-muted-foreground">
                    <p>
                        Open FSA from your desktop or taskbar to continue, or
                        use the button below to log in through your browser.
                    </p>
                    <p>
                        If prompted, log in with{' '}
                        <span className="font-medium break-all text-foreground">
                            {email}
                        </span>{' '}
                        and the password you created.
                    </p>
                    <p>
                        Haven&apos;t installed FSA yet? After signing in, go to{' '}
                        <span className="font-medium text-foreground">
                            Settings → Install app
                        </span>{' '}
                        for installation and taskbar pinning instructions.
                    </p>
                </div>

                <div className="flex flex-col items-center gap-4">
                    <Button asChild className="w-full">
                        <Link href={login()}>Log in on the website</Link>
                    </Button>
                    <TextLink href={request()} className="text-sm">
                        Forgot password?
                    </TextLink>
                </div>
            </div>
        </>
    );
}

InviteAccepted.layout = {
    title: 'Your account has already been activated',
    description: 'This invitation was for your initial account setup only.',
};
