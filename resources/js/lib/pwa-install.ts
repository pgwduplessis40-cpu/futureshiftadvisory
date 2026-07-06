import { useEffect, useState } from 'react';

export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
};

export type PwaInstallInstructionMode = 'browser' | 'ios';

type PwaInstallSnapshot = {
    canPrompt: boolean;
    dismissedUntil: number;
    instructionMode: PwaInstallInstructionMode;
    isInstalled: boolean;
    isIosSafari: boolean;
    isLikelyMobile: boolean;
};

export const PWA_INSTALL_DISMISSED_UNTIL_KEY =
    'fsa.pwa.install.dismissed_until';
export const PWA_INSTALL_DISMISS_MS = 1000 * 60 * 60 * 24 * 7;

let deferredPrompt: BeforeInstallPromptEvent | null = null;
let installedInSession = false;
let listening = false;
const listeners = new Set<() => void>();

function hasWindow(): boolean {
    return typeof window !== 'undefined';
}

function notify(): void {
    listeners.forEach((listener) => listener());
}

function storageNumber(key: string): number {
    if (!hasWindow()) {
        return 0;
    }

    try {
        return Number(window.localStorage.getItem(key) ?? 0);
    } catch {
        return 0;
    }
}

function setStorageNumber(key: string, value: number): void {
    if (!hasWindow()) {
        return;
    }

    try {
        window.localStorage.setItem(key, String(value));
    } catch {
        return;
    }
}

export function isPwaStandalone(): boolean {
    if (!hasWindow()) {
        return false;
    }

    const navigatorWithStandalone = window.navigator as Navigator & {
        standalone?: boolean;
    };

    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        navigatorWithStandalone.standalone === true
    );
}

export function isLikelyMobileDevice(): boolean {
    if (!hasWindow()) {
        return false;
    }

    return (
        window.matchMedia('(max-width: 768px)').matches ||
        /Android|iPhone|iPad|iPod/i.test(window.navigator.userAgent)
    );
}

export function isIosSafariBrowser(): boolean {
    if (!hasWindow()) {
        return false;
    }

    const userAgent = window.navigator.userAgent;

    return (
        /iPhone|iPad|iPod/i.test(userAgent) &&
        /Safari/i.test(userAgent) &&
        !/CriOS|FxiOS|EdgiOS/i.test(userAgent)
    );
}

function snapshot(): PwaInstallSnapshot {
    const isInstalled = installedInSession || isPwaStandalone();
    const isIosSafari = isIosSafariBrowser();
    const isLikelyMobile = isLikelyMobileDevice();

    return {
        canPrompt: deferredPrompt !== null && !isInstalled,
        dismissedUntil: storageNumber(PWA_INSTALL_DISMISSED_UNTIL_KEY),
        instructionMode: isIosSafari && isLikelyMobile ? 'ios' : 'browser',
        isInstalled,
        isIosSafari,
        isLikelyMobile,
    };
}

export function ensurePwaInstallListeners(): void {
    if (!hasWindow() || listening) {
        return;
    }

    listening = true;
    installedInSession = isPwaStandalone();

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        notify();
    });

    window.addEventListener('appinstalled', () => {
        installedInSession = true;
        deferredPrompt = null;
        notify();
    });
}

export function subscribePwaInstall(listener: () => void): () => void {
    ensurePwaInstallListeners();
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function dismissPwaInstallPrompt(
    milliseconds = PWA_INSTALL_DISMISS_MS,
): void {
    setStorageNumber(
        PWA_INSTALL_DISMISSED_UNTIL_KEY,
        Date.now() + milliseconds,
    );
    notify();
}

export function clearPwaInstallDismissal(): void {
    setStorageNumber(PWA_INSTALL_DISMISSED_UNTIL_KEY, 0);
    notify();
}

export async function promptPwaInstall(): Promise<
    'accepted' | 'dismissed' | 'error' | 'installed' | 'unavailable'
> {
    ensurePwaInstallListeners();

    if (isPwaStandalone()) {
        installedInSession = true;
        notify();

        return 'installed';
    }

    if (!deferredPrompt) {
        return 'unavailable';
    }

    const promptEvent = deferredPrompt;

    try {
        await promptEvent.prompt();
        const choice = await promptEvent.userChoice;

        deferredPrompt = null;
        notify();

        return choice.outcome;
    } catch {
        deferredPrompt = null;
        notify();

        return 'error';
    }
}

export function usePwaInstall() {
    const [state, setState] = useState<PwaInstallSnapshot>(() => snapshot());

    useEffect(() => {
        if (!hasWindow()) {
            return;
        }

        ensurePwaInstallListeners();
        setState(snapshot());

        return subscribePwaInstall(() => {
            setState(snapshot());
        });
    }, []);

    return {
        ...state,
        clearDismissal: clearPwaInstallDismissal,
        dismiss: dismissPwaInstallPrompt,
        install: promptPwaInstall,
    };
}
