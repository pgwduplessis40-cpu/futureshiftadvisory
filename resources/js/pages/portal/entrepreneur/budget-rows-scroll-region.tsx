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
    const contentRef = useRef<HTMLDivElement>(null);
    const [hasOverflow, setHasOverflow] = useState(false);

    useEffect(() => {
        if (!rowsRef.current || !contentRef.current) {
            return;
        }

        const rows = rowsRef.current;
        const content = contentRef.current;
        const updateDimensions = () => {
            setHasOverflow(content.scrollWidth > rows.clientWidth + 1);
        };

        updateDimensions();
        const observer = new ResizeObserver(updateDimensions);
        observer.observe(rows);
        observer.observe(content);

        return () => observer.disconnect();
    }, [children]);

    const scrollBy = (left: number) => {
        rowsRef.current?.scrollBy({ left, behavior: 'smooth' });
    };

    return (
        <div className="space-y-2">
            {hasOverflow ? (
                <div
                    className="sticky top-2 z-10 hidden items-center gap-2 rounded-md border bg-background/95 px-2 py-1.5 shadow-sm backdrop-blur md:flex"
                    aria-label={`${title} overflow controls`}
                >
                    <span className="min-w-0 flex-1 text-xs text-muted-foreground">
                        More fields are available. Drag the table bar below or
                        use these buttons.
                    </span>
                    <button
                        type="button"
                        className="rounded border px-2 py-0.5 text-xs"
                        aria-label={`Scroll ${title} fields left`}
                        onClick={() => scrollBy(-320)}
                    >
                        ←
                    </button>
                    <button
                        type="button"
                        className="rounded border px-2 py-0.5 text-xs"
                        aria-label={`Scroll ${title} fields right`}
                        onClick={() => scrollBy(320)}
                    >
                        →
                    </button>
                </div>
            ) : null}
            <div
                ref={rowsRef}
                className="space-y-2 overflow-x-auto overscroll-x-contain pb-3"
                role="region"
                tabIndex={0}
                aria-label={`${title} inputs. Scroll horizontally to reach every field.`}
            >
                <div ref={contentRef} className="min-w-full space-y-2">
                    {children}
                </div>
            </div>
        </div>
    );
}
