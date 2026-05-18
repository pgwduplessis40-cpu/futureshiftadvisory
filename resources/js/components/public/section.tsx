import type { ReactNode } from 'react';

type SectionProps = {
    children: ReactNode;
    className?: string;
    id?: string;
};

export function Section({ children, className, id }: SectionProps) {
    return (
        <section
            id={id}
            className={['mx-auto max-w-6xl px-6 lg:px-8', className].filter(Boolean).join(' ')}
        >
            {children}
        </section>
    );
}

export function SectionEyebrow({ children }: { children: ReactNode }) {
    return <p className="eyebrow">{children}</p>;
}

export function SectionTitle({
    children,
    as: As = 'h2',
    className,
}: {
    children: ReactNode;
    as?: 'h1' | 'h2' | 'h3';
    className?: string;
}) {
    return (
        <As
            className={[
                'font-display text-[var(--fs-admiralty)]',
                As === 'h1' ? 'text-4xl leading-[1.05] sm:text-5xl lg:text-6xl' : 'text-3xl leading-tight sm:text-4xl',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {children}
        </As>
    );
}

export function SectionLead({ children }: { children: ReactNode }) {
    return (
        <p className="mt-5 max-w-2xl text-lg leading-relaxed text-[var(--fs-graphite)]">
            {children}
        </p>
    );
}

export function GoldRule({ className }: { className?: string }) {
    return <hr className={['gold-rule', className].filter(Boolean).join(' ')} />;
}
