import { useEffect } from 'react';

const HIGHLIGHT_CLASSES = [
    'ring-2',
    'ring-primary',
    'ring-offset-2',
    'ring-offset-background',
    'transition-shadow',
    'scroll-mt-24',
];

export function useDrillFocus() {
    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const focus = params.get('focus');
        const highlight = params.get('highlight');
        const highlightElement = highlight
            ? document.getElementById(highlight)
            : null;
        const sectionElement = focus
            ? document.getElementById(`section-${focus}`)
            : null;
        const target = highlightElement ?? sectionElement;

        if (!target) {
            return;
        }

        const previousTabIndex = target.getAttribute('tabindex');
        const hadTabIndex = target.hasAttribute('tabindex');

        target.setAttribute('tabindex', previousTabIndex ?? '-1');
        target.classList.add(...HIGHLIGHT_CLASSES);
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        target.focus({ preventScroll: true });

        const timeout = window.setTimeout(() => {
            target.classList.remove(...HIGHLIGHT_CLASSES);

            if (hadTabIndex && previousTabIndex !== null) {
                target.setAttribute('tabindex', previousTabIndex);
            } else {
                target.removeAttribute('tabindex');
            }
        }, 2000);

        return () => window.clearTimeout(timeout);
    }, []);
}
