import { Head, useForm } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';

export default function MfaChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState(false);
    const form = useForm({
        code: '',
        recovery_code: '',
    });

    const title = useMemo(
        () => (showRecoveryInput ? 'Recovery code' : 'Authentication code'),
        [showRecoveryInput],
    );

    return (
        <>
            <Head title="MFA challenge" />

            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/mfa/challenge', {
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <h1 className="text-lg font-semibold">{title}</h1>

                {showRecoveryInput ? (
                    <>
                        <Input
                            name="recovery_code"
                            value={form.data.recovery_code}
                            onChange={(event) =>
                                form.setData(
                                    'recovery_code',
                                    event.target.value,
                                )
                            }
                            required
                            autoFocus
                        />
                        <InputError message={form.errors.recovery_code} />
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center space-y-3 text-center">
                        <InputOTP
                            name="code"
                            maxLength={OTP_MAX_LENGTH}
                            value={form.data.code}
                            onChange={(value) => form.setData('code', value)}
                            disabled={form.processing}
                            pattern={REGEXP_ONLY_DIGITS}
                            autoFocus
                        >
                            <InputOTPGroup>
                                {Array.from(
                                    { length: OTP_MAX_LENGTH },
                                    (_, index) => (
                                        <InputOTPSlot
                                            key={index}
                                            index={index}
                                        />
                                    ),
                                )}
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError message={form.errors.code} />
                    </div>
                )}

                <Button
                    type="submit"
                    className="w-full"
                    disabled={form.processing}
                >
                    Continue
                </Button>

                <button
                    type="button"
                    className="w-full text-center text-sm text-muted-foreground underline underline-offset-4"
                    onClick={() => {
                        form.clearErrors();
                        form.reset();
                        setShowRecoveryInput(!showRecoveryInput);
                    }}
                >
                    {showRecoveryInput
                        ? 'Use an authentication code'
                        : 'Use a recovery code'}
                </button>
            </form>
        </>
    );
}

MfaChallenge.layout = {
    title: 'MFA challenge',
    description: 'Confirm this session',
};
