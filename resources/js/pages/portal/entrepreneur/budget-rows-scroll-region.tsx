import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

type BudgetRowsScrollRegionProps = {
    title: string;
    children: ReactNode;
};

export function BudgetRowsScrollRegion({
    title,
    children,
}: BudgetRowsScrollRegionProps) {
    const rowsRef = useRef<HTMLDivElement>(null);
    const railRef = useRef<HTMLDivElement>(null);
    const contentRef = useRef<HTMLDivElement>(null);
    const [scrollWidth, setScrollWidth] = useState(0);
    const [hasOverflow, setHasOverflow] = useState(false);

    useEffect(() => {
        if (!rowsRef.current || !contentRef.current) {
            return;
        }

        const rows = rowsRef.current;
        const content = contentRef.current;
        const updateDimensions = () => {
            setScrollWidth(content.scrollWidth);
            setHasOverflow(content.scrollWidth > rows.clientWidth + 1);
        };

        updateDimensions();
        const observer = new ResizeObserver(updateDimensions);
        observer.observe(rows);
        observer.observe(content);

        return () => observer.disconnect();
    }, [children]);

    const syncScroll = (
        source: HTMLDivElement,
        target: HTMLDivElement | null,
    ) => {
        if (target && target.scrollLeft !== source.scrollLeft) {
            target.scrollLeft = source.scrollLeft;
        }
    };

    const scrollBy = (left: number) => {
        rowsRef.current?.scrollBy({ left, behavior: 'smooth' });
    };

    return (
        <div className="space-y-2">
            <div
                className="sticky top-2 z-10 hidden items-center gap-2 rounded-md border bg-background/95 px-2 py-1.5 shadow-sm backdrop-blur md:flex"
                aria-label={`${title} table controls`}
            >
                <span className="shrink-0 text-xs text-muted-foreground">
                    {hasOverflow
                        ? 'More fields to the right'
                        : 'All fields are visible'}
                </span>
                <button
                    type="button"
                    className="rounded border px-2 py-0.5 text-xs disabled:cursor-not-allowed disabled:opacity-50"
                    aria-label={`Scroll ${title} fields left`}
                    disabled={!hasOverflow}
                    onClick={() => scrollBy(-320)}
                >
                    ←
                </button>
                <div
                    ref={railRef}
                    className="min-w-0 flex-1 overflow-x-scroll overscroll-x-contain"
                    role="region"
                    aria-label={`Scroll ${title} fields horizontally`}
                    tabIndex={0}
                    onScroll={(event) =>
                        syncScroll(event.currentTarget, rowsRef.current)
                    }
                >
                    <div
                        className="h-3"
                        style={{ width: Math.max(scrollWidth, 1) }}
                    />
                </div>
                <button
                    type="button"
                    className="rounded border px-2 py-0.5 text-xs disabled:cursor-not-allowed disabled:opacity-50"
                    aria-label={`Scroll ${title} fields right`}
                    disabled={!hasOverflow}
                    onClick={() => scrollBy(320)}
                >
                    →
                </button>
            </div>
            <div
                ref={rowsRef}
                className="space-y-2 overflow-x-auto overscroll-x-contain pb-3"
                role="region"
                tabIndex={0}
                aria-label={`${title} inputs. Scroll horizontally to reach every field.`}
                onScroll={(event) =>
                    syncScroll(event.currentTarget, railRef.current)
                }
            >
                <div ref={contentRef} className="w-max min-w-full space-y-2">
                    {children}
                </div>
            </div>
        </div>
    );
}
