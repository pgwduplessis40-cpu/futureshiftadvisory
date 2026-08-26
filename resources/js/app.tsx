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
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name === 'portal/StrategicPlanBudgetDocument':
                return DocumentLayout;
            case name === 'portal/entrepreneur/Dashboard':
                return AppLayout;
            case name.startsWith('portal/messages/'):
                return AppLayout;
            case name.startsWith('portal/'):
                return AppLayout;
            case name.startsWith('advisor/'):
                return AdvisorLayout;
            case name.startsWith('notifications/'):
                return NotificationsLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
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
    progress: {
        color: '#4B5563',
    },
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
