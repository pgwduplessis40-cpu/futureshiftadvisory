import { Form, Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor: boolean;
    hasPendingTwoFactorSetup: boolean;
    requiresConfirmation: boolean;
    securityUrl: string;
    twoFactorEnabled: boolean;
};

export default function MfaSetup({
    canManageTwoFactor,
    hasPendingTwoFactorSetup,
    requiresConfirmation,
    securityUrl,
    twoFactorEnabled,
}: Props) {
    const {
        qrCodeSvg,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState(
        hasPendingTwoFactorSetup,
    );

    return (
        <>
            <Head title="Set up MFA" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center gap-3">
                    <ShieldCheck className="size-6" aria-hidden="true" />
                    <div>
                        <h1 className="text-lg font-semibold">
                            Set up two-factor authentication
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Use Google Authenticator or another authenticator
                            app to secure this account.
                        </p>
                    </div>
                </div>

                {canManageTwoFactor ? (
                    hasPendingTwoFactorSetup ? (
                        <Button
                            type="button"
                            onClick={() => setShowSetupModal(true)}
                        >
                            <ShieldCheck />
                            Continue 2FA setup
                        </Button>
                    ) : (
                        <Form
                            action={enable.url()}
                            method="post"
                            onSuccess={() => setShowSetupModal(true)}
                        >
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    <ShieldCheck />
                                    Enable 2FA
                                </Button>
                            )}
                        </Form>
                    )
                ) : (
                    <Button asChild>
                        <Link href={securityUrl}>Open security settings</Link>
                    </Button>
                )}
            </div>

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </>
    );
}

MfaSetup.layout = {
    title: 'Set up MFA',
    description: 'Secure your account',
};
