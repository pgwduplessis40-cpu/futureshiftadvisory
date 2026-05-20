import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    securityUrl: string;
};

export default function MfaSetup({ securityUrl }: Props) {
    return (
        <>
            <Head title="Set up MFA" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center gap-3">
                    <ShieldCheck className="size-6" aria-hidden="true" />
                    <div>
                        <h1 className="text-lg font-semibold">
                            Multi-factor authentication
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Set up an authenticator app before continuing.
                        </p>
                    </div>
                </div>

                <Button asChild>
                    <Link href={securityUrl}>Open security settings</Link>
                </Button>
            </div>
        </>
    );
}

MfaSetup.layout = {
    title: 'Set up MFA',
    description: 'Secure your account',
};
