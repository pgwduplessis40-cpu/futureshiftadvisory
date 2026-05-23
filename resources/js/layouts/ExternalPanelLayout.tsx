import { Link } from '@inertiajs/react';

export default function ExternalPanelLayout({
    panelType,
    children,
}: {
    panelType: 'broker' | 'coach';
    children: React.ReactNode;
}) {
    const label = panelType === 'broker' ? 'Broker panel' : 'Coach panel';

    return (
        <div className="min-h-screen bg-slate-50 text-slate-950">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                    <div>
                        <p className="text-sm font-medium text-emerald-700">
                            Future Shift Advisory
                        </p>
                        <h1 className="text-lg font-semibold">{label}</h1>
                    </div>
                    <nav className="flex items-center gap-4 text-sm">
                        <Link
                            href="/panel"
                            className="text-slate-700 hover:text-slate-950"
                        >
                            Referrals
                        </Link>
                        <Link
                            href="/panel/agreement"
                            className="text-slate-700 hover:text-slate-950"
                        >
                            Agreement
                        </Link>
                    </nav>
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-4 py-6">{children}</main>
        </div>
    );
}
