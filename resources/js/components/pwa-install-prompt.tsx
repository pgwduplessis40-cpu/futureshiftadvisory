import { useEffect, useMemo, useState } from 'react';
import { Download, Share2, X } from 'lucide-react';

import { BrandMark } from '@/components/public/brand-mark';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
};

const DISMISSED_UNTIL_KEY = 'fsa.pwa.install.dismissed_until';
const DISMISS_MS = 1000 * 60 * 60 * 24 * 7;

function storageNumber(key: string): number {
    try {
        return Number(window.localStorage.getItem(key) ?? 0);
    } catch {
        return 0;
    }
}

function setStorageNumber(key: string, value: number): void {
    try {
        window.localStorage.setItem(key, String(value));
    } catch {
        return;
    }
}

function isStandalone(): boolean {
    const navigatorWithStandalone = window.navigator as Navigator & {
        standalone?: boolean;
    };

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        navigatorWithStandalone.standalone === true
    );
}

function isLikelyMobile(): boolean {
    return (
        window.matchMedia('(max-width: 768px)').matches ||
        /Android|iPhone|iPad|iPod/i.test(window.navigator.userAgent)
    );
}

function isIosSafari(): boolean {
    const userAgent = window.navigator.userAgent;

    return (
        /iPhone|iPad|iPod/i.test(userAgent) &&
        /Safari/i.test(userAgent) &&
        !/CriOS|FxiOS|EdgiOS/i.test(userAgent)
    );
}

export function PwaInstallPrompt() {
    const [deferredPrompt, setDeferredPrompt] =
        useState<BeforeInstallPromptEvent | null>(null);
    const [visible, setVisible] = useState(false);
    const [helpMode, setHelpMode] = useState<'browser' | 'ios' | null>(null);

    const iosSafari = useMemo(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return isIosSafari();
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        if (isStandalone()) {
            return;
        }

        if (storageNumber(DISMISSED_UNTIL_KEY) > Date.now()) {
            return;
        }

        let fallbackTimer: number | undefined;

        const handleBeforeInstallPrompt = (event: Event) => {
            event.preventDefault();

            if (fallbackTimer) {
                window.clearTimeout(fallbackTimer);
                fallbackTimer = undefined;
            }

            setDeferredPrompt(event as BeforeInstallPromptEvent);
            setHelpMode(null);
            setVisible(true);
        };

        const handleAppInstalled = () => {
            setDeferredPrompt(null);
            setVisible(false);
        };

        window.addEventListener(
            'beforeinstallprompt',
            handleBeforeInstallPrompt,
        );
        window.addEventListener('appinstalled', handleAppInstalled);

        if (isIosSafari() && isLikelyMobile()) {
            setHelpMode('ios');
            setVisible(true);
        } else {
            fallbackTimer = window.setTimeout(() => {
                setHelpMode('browser');
                setVisible(true);
            }, 1500);
        }

        return () => {
            if (fallbackTimer) {
                window.clearTimeout(fallbackTimer);
            }

            window.removeEventListener(
                'beforeinstallprompt',
                handleBeforeInstallPrompt,
            );
            window.removeEventListener('appinstalled', handleAppInstalled);
        };
    }, []);

    const dismiss = () => {
        setVisible(false);
        setStorageNumber(DISMISSED_UNTIL_KEY, Date.now() + DISMISS_MS);
    };

    const install = async () => {
        if (!deferredPrompt) {
            setHelpMode(iosSafari ? 'ios' : 'browser');
            return;
        }

        try {
            await deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;

            setDeferredPrompt(null);

            if (choice.outcome === 'accepted') {
                setVisible(false);
                return;
            }

            dismiss();
            return;
        } catch {
            setDeferredPrompt(null);
            setHelpMode(iosSafari ? 'ios' : 'browser');
        }
    };

    if (!visible) {
        return null;
    }

    const canInstallDirectly = deferredPrompt !== null;

    return (
        <div
            className={cn(
                'fixed inset-x-3 bottom-4 z-50 md:inset-x-auto md:right-5 md:w-96',
                'rounded-md border bg-background p-3 shadow-lg',
            )}
            role="region"
            aria-label="Install Future Shift Advisory"
        >
            <div className="flex items-start gap-3">
                <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <BrandMark showWordmark={false} width={20} />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="text-sm font-semibold">Install FSA</div>
                    <div className="mt-1 text-xs leading-5 text-muted-foreground">
                        Add the portal to this device for quick access.
                    </div>
                    {helpMode === 'ios' ? (
                        <div className="mt-2 flex items-center gap-2 text-xs leading-5 text-muted-foreground">
                            <Share2 className="size-3.5" aria-hidden="true" />
                            <span>Tap Share, then Add to Home Screen.</span>
                        </div>
                    ) : null}
                    {helpMode === 'browser' ? (
                        <div className="mt-2 text-xs leading-5 text-muted-foreground">
                            Use your browser install icon or menu, then choose
                            Install app or Add to Home screen.
                        </div>
                    ) : null}
                    <div className="mt-3 flex gap-2">
                        {canInstallDirectly ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={install}
                                aria-label="Install Future Shift Advisory"
                            >
                                <Download
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Install
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                canInstallDirectly ? 'outline' : 'secondary'
                            }
                            onClick={dismiss}
                            aria-label="Dismiss install prompt"
                        >
                            {canInstallDirectly ? 'Later' : 'Got it'}
                        </Button>
                    </div>
                </div>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="-mt-2 -mr-2 size-8"
                    onClick={dismiss}
                    aria-label="Dismiss install prompt"
                >
                    <X className="size-4" aria-hidden="true" />
                </Button>
            </div>
        </div>
    );
}
