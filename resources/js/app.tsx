import { createInertiaApp } from '@inertiajs/react';
import { AppErrorBoundary } from '@/components/app-error-boundary';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdvisorLayout from '@/layouts/AdvisorLayout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import DocumentLayout from '@/layouts/document-layout';
import NotificationsLayout from '@/layouts/notifications-layout';
import PublicLayout from '@/layouts/public-layout';
import ScreenShareLayout from '@/layouts/screen-share-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    configureClientErrorTelemetry,
    registerGlobalClientErrorTelemetry,
} from '@/lib/client-error-telemetry';
import { registerPortalOffline } from '@/lib/portal-offline';
import { ensurePwaInstallListeners } from '@/lib/pwa-install';
import { registerTargetHighlighting } from '@/lib/target-highlight';
import buildVersion from '../../VERSION?raw';

declare const __CLIENT_RELEASE_SHA__: string;

const appName = import.meta.env.VITE_APP_NAME || 'Future Shift Advisory';

configureClientErrorTelemetry({ releaseSha: __CLIENT_RELEASE_SHA__ });
registerGlobalClientErrorTelemetry();

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('public/'):
                return [ScreenShareLayout, PublicLayout];
            case name.startsWith('auth/'):
                return [ScreenShareLayout, AuthLayout];
            case name === 'portal/StrategicPlanBudgetDocument':
                return [ScreenShareLayout, DocumentLayout];
            case name === 'portal/entrepreneur/Dashboard':
                return [ScreenShareLayout, AppLayout];
            case name.startsWith('portal/messages/'):
                return [ScreenShareLayout, AppLayout];
            case name.startsWith('portal/'):
                return [ScreenShareLayout, AppLayout];
            case name.startsWith('advisor/'):
                return [ScreenShareLayout, AdvisorLayout];
            case name.startsWith('notifications/'):
                return [ScreenShareLayout, NotificationsLayout];
            case name.startsWith('settings/'):
                return [ScreenShareLayout, AppLayout, SettingsLayout];
            default:
                return [ScreenShareLayout, AppLayout];
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <AppErrorBoundary>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </AppErrorBoundary>
        );
    },
    // Inertia's bundled progress template renders the invalid ARIA role
    // `bar`. Keep navigation accessible until the upstream template exposes
    // an accessible customization point.
    progress: false,
}).then(() => {
    if (typeof document !== 'undefined') {
        document.getElementById('app-launch-skeleton')?.remove();
        document.documentElement.dataset.buildVersion = buildVersion.trim();
    }
});

// This will set light / dark mode on load...
initializeTheme();
ensurePwaInstallListeners();
registerPortalOffline();
registerTargetHighlighting();
