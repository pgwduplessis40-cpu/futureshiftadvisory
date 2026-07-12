import { Head } from '@inertiajs/react';
import {
    CheckCircle2,
    Download,
    Info,
    MonitorSmartphone,
    Pin,
    Share2,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { BrandMark } from '@/components/public/brand-mark';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePwaInstall } from '@/lib/pwa-install';

type InstallFeedback =
    | 'accepted'
    | 'dismissed'
    | 'instructions'
    | 'installed'
    | null;

export default function InstallAppSettings() {
    const installState = usePwaInstall();
    const [feedback, setFeedback] = useState<InstallFeedback>(null);

    const install = async () => {
        installState.clearDismissal();

        if (installState.isInstalled) {
            setFeedback('installed');

            return;
        }

        if (!installState.canPrompt) {
            setFeedback('instructions');

            return;
        }

        const outcome = await installState.install();

        if (outcome === 'accepted' || outcome === 'installed') {
            setFeedback('accepted');

            return;
        }

        if (outcome === 'dismissed') {
            setFeedback('dismissed');

            return;
        }

        setFeedback('instructions');
    };

    const status = installState.isInstalled
        ? 'Installed'
        : installState.canPrompt
          ? 'Ready to install'
          : 'Manual install';

    return (
        <>
            <Head title="Install app settings" />

            <h1 className="sr-only">Install app settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Install app"
                    description="Add Future Shift Advisory to this device"
                />

                <section className="rounded-md border bg-card p-4 shadow-sm">
                    <div className="flex items-start gap-4">
                        <div className="rounded-full bg-primary/10 p-3 text-primary">
                            <BrandMark showWordmark={false} width={28} />
                        </div>
                        <div className="min-w-0 flex-1 space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        Future Shift Advisory
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Quick access from your desktop, taskbar,
                                        dock, or home screen.
                                    </p>
                                </div>
                                <Badge variant="secondary">{status}</Badge>
                            </div>

                            <Button
                                type="button"
                                onClick={install}
                                disabled={installState.isInstalled}
                            >
                                {installState.canPrompt ? (
                                    <Download
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <MonitorSmartphone
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {installState.isInstalled
                                    ? 'Installed'
                                    : installState.canPrompt
                                      ? 'Install app'
                                      : 'Show install instructions'}
                            </Button>

                            <InstallFeedbackAlert
                                feedback={feedback}
                                mode={installState.instructionMode}
                            />

                            {!installState.canPrompt &&
                            !installState.isInstalled ? (
                                <InstallInstructions
                                    mode={installState.instructionMode}
                                />
                            ) : null}

                            {!installState.canPrompt &&
                            !installState.isLikelyMobile ? (
                                <DesktopShortcutRecovery />
                            ) : null}
                        </div>
                    </div>
                </section>

                <section className="rounded-md border bg-card p-4 shadow-sm">
                    <div className="flex items-start gap-4">
                        <div className="rounded-full bg-primary/10 p-3 text-primary">
                            <Pin className="size-6" aria-hidden="true" />
                        </div>
                        <div className="min-w-0 flex-1 space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        Pin FSA to the Windows taskbar
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Windows may not show Pin to taskbar when
                                        you right-click a desktop shortcut.
                                    </p>
                                </div>
                                <Badge variant="secondary">Windows</Badge>
                            </div>

                            <ol className="space-y-2 text-sm text-muted-foreground">
                                <li>
                                    1. Install Future Shift Advisory from this
                                    page or your browser menu.
                                </li>
                                <li>
                                    2. Open Future Shift Advisory from the
                                    desktop shortcut or Start menu.
                                </li>
                                <li>
                                    3. Right-click the FSA icon while it is
                                    visible on the Windows taskbar.
                                </li>
                                <li>4. Choose Pin to taskbar.</li>
                            </ol>

                            <Alert>
                                <Info className="size-4" aria-hidden="true" />
                                <AlertTitle>Desktop shortcut menu</AlertTitle>
                                <AlertDescription>
                                    If the shortcut menu only shows Pin to
                                    Start, open FSA first. The taskbar pin is
                                    normally available from the running app icon
                                    on the taskbar.
                                </AlertDescription>
                            </Alert>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

function InstallFeedbackAlert({
    feedback,
    mode,
}: {
    feedback: InstallFeedback;
    mode: 'browser' | 'ios';
}) {
    if (!feedback) {
        return null;
    }

    if (feedback === 'accepted' || feedback === 'installed') {
        return (
            <Alert>
                <CheckCircle2 className="size-4" aria-hidden="true" />
                <AlertTitle>Install started</AlertTitle>
                <AlertDescription>
                    Future Shift Advisory is being added to this device.
                </AlertDescription>
            </Alert>
        );
    }

    if (feedback === 'dismissed') {
        return (
            <Alert>
                <Info className="size-4" aria-hidden="true" />
                <AlertTitle>Install not completed</AlertTitle>
                <AlertDescription>
                    Use this page again when you are ready to add the app.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert>
            {mode === 'ios' ? (
                <Share2 className="size-4" aria-hidden="true" />
            ) : (
                <MonitorSmartphone className="size-4" aria-hidden="true" />
            )}
            <AlertTitle>Install from your browser</AlertTitle>
            <AlertDescription>
                <InstallInstructionText mode={mode} />
            </AlertDescription>
        </Alert>
    );
}

function InstallInstructions({ mode }: { mode: 'browser' | 'ios' }) {
    return (
        <div className="rounded-md border bg-background px-4 py-3 text-sm text-muted-foreground">
            <div className="flex items-start gap-2">
                {mode === 'ios' ? (
                    <Share2
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                ) : (
                    <MonitorSmartphone
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                )}
                <p>
                    <InstallInstructionText mode={mode} />
                </p>
            </div>
        </div>
    );
}

function DesktopShortcutRecovery() {
    return (
        <Alert>
            <MonitorSmartphone className="size-4" aria-hidden="true" />
            <AlertTitle>Missing desktop shortcut?</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>
                    If Chrome does not offer Install app, Future Shift Advisory
                    may already be installed and only its desktop shortcut was
                    removed.
                </p>
                <ol className="space-y-2">
                    <li>1. Open a new Chrome tab and enter chrome://apps.</li>
                    <li>2. Right-click Future Shift Advisory.</li>
                    <li>3. Choose Create shortcut.</li>
                    <li>4. Select Desktop, then choose Create.</li>
                </ol>
            </AlertDescription>
        </Alert>
    );
}

function InstallInstructionText({ mode }: { mode: 'browser' | 'ios' }) {
    if (mode === 'ios') {
        return <>Tap Share in Safari, then Add to Home Screen.</>;
    }

    return (
        <>
            Use the install icon in the browser address bar, or open the browser
            menu and choose Install app.
        </>
    );
}

InstallAppSettings.layout = {
    breadcrumbs: [
        {
            title: 'Install app settings',
            href: '/settings/install-app',
        },
    ],
};
