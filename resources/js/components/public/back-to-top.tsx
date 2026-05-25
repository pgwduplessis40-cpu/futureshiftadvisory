import { ArrowUp } from 'lucide-react';
import { useEffect, useState } from 'react';

type BackToTopProps = {
    /** Scroll distance (px) before the button appears. */
    threshold?: number;
};

type RGB = [number, number, number];

// Dark end over light surfaces to light end over dark surfaces.
const BG_DARK: RGB = [28, 43, 69]; // Admiralty navy
const BG_LIGHT: RGB = [232, 213, 160]; // Champagne
const ICON_DARK: RGB = [249, 246, 240]; // Parchment
const ICON_LIGHT: RGB = [28, 43, 69]; // Admiralty

const BUTTON_HEIGHT = 44; // h-11
const BUTTON_GAP = 24; // bottom-6
const MARGIN = 44; // px of soft transition at each edge of a dark band

function lerp(a: RGB, b: RGB, t: number): string {
    const r = Math.round(a[0] + (b[0] - a[0]) * t);
    const g = Math.round(a[1] + (b[1] - a[1]) * t);
    const bl = Math.round(a[2] + (b[2] - a[2]) * t);
    return `rgb(${r}, ${g}, ${bl})`;
}

function clamp01(n: number): number {
    return Math.min(1, Math.max(0, n));
}

export function BackToTop({ threshold = 400 }: BackToTopProps) {
    const [visible, setVisible] = useState(false);
    // 0 = dark over light surface, 1 = light over dark surface.
    const [tone, setTone] = useState(0);

    useEffect(() => {
        const onScroll = () => {
            setVisible(window.scrollY > threshold);

            const buttonBottomY = window.innerHeight - BUTTON_GAP;
            const buttonTopY = buttonBottomY - BUTTON_HEIGHT;
            const span = BUTTON_HEIGHT + MARGIN * 2;
            const darkBands = document.querySelectorAll<HTMLElement>(
                '[data-surface="dark"]',
            );

            let maxTone = 0;
            darkBands.forEach((band) => {
                const rect = band.getBoundingClientRect();
                const overlapTop = Math.max(rect.top, buttonTopY - MARGIN);
                const overlapBottom = Math.min(
                    rect.bottom,
                    buttonBottomY + MARGIN,
                );
                const covered = overlapBottom - overlapTop;
                maxTone = Math.max(maxTone, clamp01(covered / span));
            });

            setTone(maxTone);
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);

        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, [threshold]);

    const scrollToTop = () => {
        const prefersReduced = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        window.scrollTo({
            top: 0,
            behavior: prefersReduced ? 'auto' : 'smooth',
        });
    };

    return (
        <button
            type="button"
            onClick={scrollToTop}
            aria-label="Back to top"
            tabIndex={visible ? 0 : -1}
            style={{
                backgroundColor: lerp(BG_DARK, BG_LIGHT, tone),
                color: lerp(ICON_DARK, ICON_LIGHT, tone),
            }}
            className={[
                'fixed right-6 bottom-6 z-50 flex h-11 w-11 items-center justify-center rounded-full',
                'shadow-lg transition-[opacity,transform,background-color,color] duration-200',
                'hover:scale-105 focus-visible:ring-2 focus-visible:ring-[var(--fs-warm-gold)] focus-visible:ring-offset-2 focus-visible:ring-offset-transparent focus-visible:outline-none',
                visible
                    ? 'translate-y-0 opacity-100'
                    : 'pointer-events-none translate-y-3 opacity-0',
            ].join(' ')}
        >
            <ArrowUp className="h-5 w-5" />
        </button>
    );
}
