import { useState } from 'react';
import { Button } from '@/components/ui/button';

type WelcomeMessage = {
    has_message: boolean;
    html: string;
    version: number | null;
};

export function WelcomeBanner({
    welcomeMessage,
}: {
    welcomeMessage: WelcomeMessage;
}) {
    const storageKey = `fs-welcome-dismissed-v${welcomeMessage.version ?? 0}`;
    const [dismissed, setDismissed] = useState<boolean>(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch {
            return false;
        }
    });

    if (dismissed) {
        return null;
    }

    const dismiss = () => {
        setDismissed(true);

        try {
            window.localStorage.setItem(storageKey, '1');
        } catch {
            // Ignore storage failures — dismissal is best-effort.
        }
    };

    return (
        <section
            aria-label="Welcome message"
            className="rounded-md border border-[var(--fs-linen)] bg-[var(--fs-linen)]/50 p-5"
        >
            <div
                className="text-sm leading-relaxed text-foreground [&_a]:text-[var(--fs-admiralty)] [&_a]:underline [&_p]:mb-3 [&_p:last-child]:mb-0 [&_strong]:font-semibold"
                dangerouslySetInnerHTML={{ __html: welcomeMessage.html }}
            />
            <div className="mt-4 flex justify-end">
                <Button variant="ghost" size="sm" onClick={dismiss}>
                    Dismiss
                </Button>
            </div>
        </section>
    );
}
