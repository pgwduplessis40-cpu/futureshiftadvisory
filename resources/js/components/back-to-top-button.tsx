import { ArrowUp } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

type BackToTopButtonProps = {
    threshold?: number;
};

export function BackToTopButton({ threshold = 420 }: BackToTopButtonProps) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        let frame = 0;
        let resizeObserver: ResizeObserver | null = null;

        const update = () => {
            frame = 0;

            const page = document.documentElement;
            const isScrollable = page.scrollHeight > window.innerHeight + 80;

            setVisible(isScrollable && window.scrollY > threshold);
        };

        const scheduleUpdate = () => {
            if (frame !== 0) {
                return;
            }

            frame = window.requestAnimationFrame(update);
        };

        update();
        window.addEventListener('scroll', scheduleUpdate, { passive: true });
        window.addEventListener('resize', scheduleUpdate);

        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(scheduleUpdate);
            resizeObserver.observe(document.body);
        }

        return () => {
            window.removeEventListener('scroll', scheduleUpdate);
            window.removeEventListener('resize', scheduleUpdate);
            resizeObserver?.disconnect();

            if (frame !== 0) {
                window.cancelAnimationFrame(frame);
            }
        };
    }, [threshold]);

    const scrollToTop = () => {
        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        window.scrollTo({
            top: 0,
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
        });
    };

    return (
        <button
            type="button"
            aria-label="Back to top"
            title="Back to top"
            tabIndex={visible ? 0 : -1}
            onClick={scrollToTop}
            className={cn(
                'fixed right-4 bottom-4 z-50 inline-flex size-11 items-center justify-center rounded-full border border-white/30 bg-primary text-primary-foreground shadow-lg shadow-black/15',
                'transition-[opacity,transform,background-color,color,box-shadow] duration-200 hover:bg-primary/90 hover:shadow-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none sm:right-6 sm:bottom-6',
                visible
                    ? 'translate-y-0 opacity-100'
                    : 'pointer-events-none translate-y-3 opacity-0',
            )}
        >
            <ArrowUp className="size-5" aria-hidden="true" />
        </button>
    );
}
