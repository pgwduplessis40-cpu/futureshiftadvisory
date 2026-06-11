const highlightClass = 'fsa-target-highlight';
const highlightableSelector =
    'section, article, div, form, main, aside, nav, header, li';
const retryDelays = [0, 50, 150, 300, 600, 1000, 1600];

let activeHighlight: HTMLElement | null = null;
let latestRequest = 0;
let completedRequest = 0;

declare global {
    interface Window {
        __fsaTargetHighlightingRegistered?: boolean;
    }
}

export function registerTargetHighlighting() {
    if (
        typeof window === 'undefined' ||
        window.__fsaTargetHighlightingRegistered
    ) {
        return;
    }

    window.__fsaTargetHighlightingRegistered = true;

    patchHistory();

    document.addEventListener('click', handleDocumentClick, true);
    window.addEventListener('hashchange', () => {
        scheduleTargetHighlight(window.location.hash);
    });
    window.addEventListener('popstate', () => {
        scheduleTargetHighlight(window.location.hash);
    });
    document.addEventListener('inertia:navigate', () => {
        scheduleTargetHighlight(window.location.hash);
    });

    if (window.location.hash) {
        window.requestAnimationFrame(() => {
            scheduleTargetHighlight(window.location.hash);
        });
    }
}

function patchHistory() {
    const originalPushState = window.history.pushState;
    const originalReplaceState = window.history.replaceState;

    window.history.pushState = function pushState(
        this: History,
        ...args: Parameters<History['pushState']>
    ) {
        const result = originalPushState.apply(this, args);
        scheduleTargetHighlightFromUrl(args[2]);

        return result;
    } as History['pushState'];

    window.history.replaceState = function replaceState(
        this: History,
        ...args: Parameters<History['replaceState']>
    ) {
        const result = originalReplaceState.apply(this, args);
        scheduleTargetHighlightFromUrl(args[2]);

        return result;
    } as History['replaceState'];
}

function handleDocumentClick(event: MouseEvent) {
    if (
        event.button !== 0 ||
        event.altKey ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey
    ) {
        return;
    }

    if (!(event.target instanceof Element)) {
        return;
    }

    const anchor = event.target.closest('a[href]');

    if (!(anchor instanceof HTMLAnchorElement)) {
        return;
    }

    if (anchor.target && anchor.target !== '_self') {
        return;
    }

    const url = parseUrl(anchor.href);

    if (!url || url.origin !== window.location.origin || !url.hash) {
        return;
    }

    scheduleTargetHighlight(url.hash);
}

function scheduleTargetHighlightFromUrl(url: string | URL | null | undefined) {
    if (url === null || url === undefined) {
        return;
    }

    const nextUrl = parseUrl(url.toString());

    if (!nextUrl || nextUrl.origin !== window.location.origin) {
        return;
    }

    if (!nextUrl.hash) {
        clearActiveHighlight();

        return;
    }

    scheduleTargetHighlight(nextUrl.hash);
}

function scheduleTargetHighlight(hash: string) {
    const targetId = targetIdFromHash(hash);

    clearActiveHighlight();

    if (!targetId) {
        return;
    }

    const request = ++latestRequest;
    completedRequest = 0;

    retryDelays.forEach((delay) => {
        window.setTimeout(() => {
            if (request !== latestRequest || request === completedRequest) {
                return;
            }

            const target = document.getElementById(targetId);

            if (!target) {
                return;
            }

            highlightTarget(target);
            completedRequest = request;
        }, delay);
    });
}

function highlightTarget(target: HTMLElement) {
    const highlightTarget = resolveHighlightTarget(target);

    highlightTarget.classList.remove(highlightClass);
    void highlightTarget.offsetWidth;
    highlightTarget.classList.add(highlightClass);
    activeHighlight = highlightTarget;

    highlightTarget.scrollIntoView({
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        block: 'start',
    });
}

function clearActiveHighlight() {
    activeHighlight?.classList.remove(highlightClass);
    activeHighlight = null;
}

function resolveHighlightTarget(target: HTMLElement) {
    if (target.matches(highlightableSelector)) {
        return target;
    }

    return target.closest<HTMLElement>(highlightableSelector) ?? target;
}

function targetIdFromHash(hash: string) {
    const rawTargetId = hash.startsWith('#') ? hash.slice(1) : hash;

    if (!rawTargetId) {
        return null;
    }

    try {
        return decodeURIComponent(rawTargetId);
    } catch {
        return rawTargetId;
    }
}

function parseUrl(url: string) {
    try {
        return new URL(url, window.location.href);
    } catch {
        return null;
    }
}

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
