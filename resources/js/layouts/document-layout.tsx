import type { ReactNode } from 'react';

export default function DocumentLayout({ children }: { children: ReactNode }) {
    return <div className="min-h-screen bg-stone-100">{children}</div>;
}
