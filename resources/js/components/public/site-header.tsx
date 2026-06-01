import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { BrandMark } from '@/components/public/brand-mark';
import { login } from '@/routes';

type NavItem = { label: string; href: string; active: boolean };

export function SiteHeader() {
    const { url } = usePage();
    const [scrolled, setScrolled] = useState(false);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const isActive = (path: string) =>
        path === '/' ? url === '/' : url.startsWith(path);

    const nav: NavItem[] = [
        { label: 'Services', href: '/services', active: isActive('/services') },
        { label: 'About', href: '/about', active: isActive('/about') },
        { label: 'FAQ', href: '/faq', active: isActive('/faq') },
        { label: 'Contact', href: '/contact', active: isActive('/contact') },
    ];

    return (
        <header
            className={[
                'sticky top-0 z-40 w-full transition-all duration-200',
                scrolled
                    ? 'border-b border-[var(--fs-sand)] bg-[var(--fs-parchment)]/95 backdrop-blur'
                    : 'border-b border-transparent bg-[var(--fs-parchment)]',
            ].join(' ')}
        >
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-6 lg:px-8">
                <Link href="/" aria-label="Future Shift Advisory - home">
                    <BrandMark width={168} />
                </Link>

                <nav className="hidden items-center gap-8 md:flex">
                    {nav.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={[
                                'text-sm transition-colors',
                                item.active
                                    ? 'font-semibold text-[var(--fs-admiralty)]'
                                    : 'text-[var(--fs-graphite)] hover:text-[var(--fs-admiralty)]',
                            ].join(' ')}
                        >
                            {item.label}
                            {item.active && (
                                <span className="ml-2 inline-block h-[2px] w-4 bg-[var(--fs-warm-gold)] align-middle" />
                            )}
                        </Link>
                    ))}
                </nav>

                <div className="hidden items-center gap-3 md:flex">
                    <Link
                        href={login()}
                        className="text-xs font-medium tracking-wide text-[var(--fs-graphite)] uppercase hover:text-[var(--fs-admiralty)]"
                    >
                        Client Login
                    </Link>
                    <Link
                        href="/contact"
                        className="rounded-md bg-[var(--fs-admiralty)] px-4 py-2 text-sm font-medium text-[var(--fs-parchment)] shadow-sm transition-colors hover:bg-[var(--fs-commodore)]"
                    >
                        Book a discovery call
                    </Link>
                </div>

                <button
                    type="button"
                    aria-label={open ? 'Close menu' : 'Open menu'}
                    onClick={() => setOpen((v) => !v)}
                    className="-mr-2 rounded-md p-2 text-[var(--fs-admiralty)] md:hidden"
                >
                    {open ? (
                        <X className="h-5 w-5" />
                    ) : (
                        <Menu className="h-5 w-5" />
                    )}
                </button>
            </div>

            {open && (
                <div className="border-t border-[var(--fs-sand)] bg-[var(--fs-parchment)] md:hidden">
                    <div className="mx-auto flex max-w-6xl flex-col gap-1 px-6 py-4">
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={() => setOpen(false)}
                                className={[
                                    'rounded-md px-2 py-2 text-sm',
                                    item.active
                                        ? 'bg-[var(--fs-linen)] font-semibold text-[var(--fs-admiralty)]'
                                        : 'text-[var(--fs-graphite)] hover:bg-[var(--fs-linen)]',
                                ].join(' ')}
                            >
                                {item.label}
                            </Link>
                        ))}
                        <div className="mt-3 flex items-center justify-between gap-3 border-t border-[var(--fs-sand)] pt-3">
                            <Link
                                href={login()}
                                onClick={() => setOpen(false)}
                                className="text-xs font-medium tracking-wide text-[var(--fs-graphite)] uppercase"
                            >
                                Client Login
                            </Link>
                            <Link
                                href="/contact"
                                onClick={() => setOpen(false)}
                                className="rounded-md bg-[var(--fs-admiralty)] px-4 py-2 text-sm font-medium text-[var(--fs-parchment)]"
                            >
                                Book a discovery call
                            </Link>
                        </div>
                    </div>
                </div>
            )}
        </header>
    );
}
