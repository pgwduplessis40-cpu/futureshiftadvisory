import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    HeartPulse,
    LayoutDashboard,
    Menu,
    MessageSquare,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { NotificationBell } from '@/components/notifications/NotificationBell';
import { BrandMark } from '@/components/public/brand-mark';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

type NavItem = {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
};

export default function PortalLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { url } = usePage();
    const [open, setOpen] = useState(false);
    const userType = auth.user.user_type;
    const isEntrepreneur = userType === 'entrepreneur';
    const dashboardHref = isEntrepreneur ? '/portal/entrepreneur' : '/portal';
    const navItems: NavItem[] = isEntrepreneur
        ? [
              {
                  label: 'Portal',
                  href: dashboardHref,
                  icon: LayoutDashboard,
              },
          ]
        : [
              {
                  label: 'Dashboard',
                  href: dashboardHref,
                  icon: LayoutDashboard,
              },
              {
                  label: 'Onboarding',
                  href: '/portal/onboarding',
                  icon: ClipboardList,
              },
              {
                  label: 'Wellbeing',
                  href: '/portal/wellbeing',
                  icon: HeartPulse,
              },
              {
                  label: 'Messages',
                  href: '/portal/messages',
                  icon: MessageSquare,
              },
          ];

    const isActive = (href: string) =>
        href === '/portal' ? url === href : url.startsWith(href);

    return (
        <div className="min-h-screen bg-[var(--fs-parchment)] text-[var(--fs-graphite)]">
            <a
                href="#portal-main"
                className="sr-only rounded-md bg-background px-3 py-2 text-sm font-medium focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50"
            >
                Skip to portal content
            </a>

            <header className="sticky top-0 z-40 border-b border-[var(--fs-sand)] bg-[var(--fs-parchment)]/95 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <Link href={dashboardHref} aria-label="Portal dashboard">
                        <BrandMark width={168} />
                    </Link>

                    <nav
                        className="hidden items-center gap-1 md:flex"
                        aria-label="Portal navigation"
                    >
                        {navItems.map((item) => (
                            <PortalNavLink
                                key={item.href}
                                item={item}
                                active={isActive(item.href)}
                            />
                        ))}
                    </nav>

                    <div className="flex items-center gap-2">
                        <NotificationBell />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="md:hidden"
                            aria-label={open ? 'Close menu' : 'Open menu'}
                            aria-expanded={open}
                            aria-controls="portal-mobile-nav"
                            onClick={() => setOpen((value) => !value)}
                        >
                            {open ? (
                                <X className="size-4" aria-hidden="true" />
                            ) : (
                                <Menu className="size-4" aria-hidden="true" />
                            )}
                        </Button>
                    </div>
                </div>

                {open && (
                    <nav
                        id="portal-mobile-nav"
                        className="border-t border-[var(--fs-sand)] px-4 py-3 md:hidden"
                        aria-label="Portal mobile navigation"
                    >
                        <div className="mx-auto grid max-w-6xl gap-2">
                            {navItems.map((item) => (
                                <PortalNavLink
                                    key={item.href}
                                    item={item}
                                    active={isActive(item.href)}
                                    onClick={() => setOpen(false)}
                                />
                            ))}
                        </div>
                    </nav>
                )}
            </header>

            <main
                id="portal-main"
                className="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8"
            >
                {children}
            </main>
        </div>
    );
}

function PortalNavLink({
    item,
    active,
    onClick,
}: {
    item: NavItem;
    active: boolean;
    onClick?: () => void;
}) {
    const Icon = item.icon;

    return (
        <Link
            href={item.href}
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                active
                    ? 'bg-[var(--fs-admiralty)] text-[var(--fs-parchment)]'
                    : 'text-[var(--fs-graphite)] hover:bg-[var(--fs-linen)] hover:text-[var(--fs-admiralty)]',
            )}
        >
            <Icon className="size-4" aria-hidden={true} />
            {item.label}
        </Link>
    );
}
