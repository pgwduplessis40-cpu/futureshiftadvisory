import { useEffect, useState } from 'react';
import { Download, Share2, X } from 'lucide-react';

import { BrandMark } from '@/components/public/brand-mark';
import { Button } from '@/components/ui/button';
import { usePwaInstall } from '@/lib/pwa-install';
import { cn } from '@/lib/utils';

export function PwaInstallPrompt() {
    const installState = usePwaInstall();
    const [visible, setVisible] = useState(false);
    const [helpMode, setHelpMode] = useState<'browser' | 'ios' | null>(null);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        if (installState.isInstalled) {
            setVisible(false);
            return;
        }

        if (installState.dismissedUntil > Date.now()) {
            setVisible(false);
            return;
        }

        if (installState.canPrompt) {
            setHelpMode(null);
            setVisible(true);
            return;
        }

        if (
            installState.instructionMode === 'ios' &&
            installState.isLikelyMobile
        ) {
            setHelpMode('ios');
            setVisible(true);
            return;
        }

        const fallbackTimer = window.setTimeout(() => {
            setHelpMode('browser');
            setVisible(true);
        }, 1500);

        return () => {
            window.clearTimeout(fallbackTimer);
        };
    }, [
        installState.canPrompt,
        installState.dismissedUntil,
        installState.instructionMode,
        installState.isInstalled,
        installState.isLikelyMobile,
    ]);

    const dismiss = () => {
        setVisible(false);
        installState.dismiss();
    };

    const install = async () => {
        if (!installState.canPrompt) {
            setHelpMode(installState.instructionMode);
            return;
        }

        const outcome = await installState.install();

        if (outcome === 'accepted' || outcome === 'installed') {
            setVisible(false);
            return;
        }

        if (outcome === 'dismissed') {
            dismiss();
            return;
        }

        setHelpMode(installState.instructionMode);
    };

    if (!visible) {
        return null;
    }

    const canInstallDirectly = installState.canPrompt;

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
