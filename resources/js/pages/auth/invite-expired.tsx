import { Head } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

type Props = {
    email: string;
    expiredAt: string | null;
    acceptedAt: string | null;
    isAccepted: boolean;
};

export default function InviteExpired({
    email,
    expiredAt,
    acceptedAt,
    isAccepted,
}: Props) {
    return (
        <>
            <Head title="Invitation unavailable" />

            <div className="flex flex-col gap-4">
                <div className="flex items-center gap-3">
                    <AlertTriangle className="size-5" aria-hidden="true" />
                    <h1 className="text-xl font-semibold">
                        Invitation unavailable
                    </h1>
                </div>
                <div className="space-y-2 text-sm text-muted-foreground">
                    <p>
                        The invitation for {email} is no longer available.
                        {isAccepted && acceptedAt
                            ? ` It was accepted ${formatDateTime(acceptedAt)}.`
                            : null}
                        {!isAccepted && expiredAt
                            ? ` It expired ${formatDateTime(expiredAt)}.`
                            : null}
                    </p>
                    <p>
                        Contact your Future Shift Advisory advisor for a fresh
                        invite link.
                    </p>
                </div>
            </div>
        </>
    );
}

InviteExpired.layout = {
    title: 'Invitation unavailable',
    description: 'Ask your advisor for a new secure invite link',
};

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
