import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpenCheck,
    BriefcaseBusiness,
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    HeartPulse,
    LayoutDashboard,
    Lightbulb,
    LogOut,
    Menu,
    MessageSquare,
    Settings,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentType, ReactNode } from 'react';
import { NotificationBell } from '@/components/notifications/NotificationBell';
import { BrandMark } from '@/components/public/brand-mark';
import { Button } from '@/components/ui/button';
import { clearPortalOfflineQueue } from '@/lib/portal-offline';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import type { Auth } from '@/types';

type NavItem = {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
};

type NavSection = {
    label: string;
    items: NavItem[];
};

type PortalServiceType = 'due_diligence' | 'entrepreneur';

type PortalServiceOption = {
    service_type: PortalServiceType;
    label: string;
    description: string;
    available: boolean;
    start_url: string;
};

type PortalServiceItem = {
    id: string;
    service_type: PortalServiceType;
    client_label: string;
    status: string;
    url: string;
    workspace_url: string | null;
};

type PortalServices = {
    options: PortalServiceOption[];
    items: PortalServiceItem[];
};

export default function PortalLayout({ children }: { children: ReactNode }) {
    const page = usePage<{
        auth: Auth;
        portalServices?: PortalServices | null;
    }>();
    const { auth, portalServices } = page.props;
    const { url } = page;
    const [open, setOpen] = useState(false);
    const userType = auth.user.user_type;
    const isEntrepreneur = userType === 'entrepreneur';
    const dashboardHref = isEntrepreneur ? '/portal/entrepreneur' : '/portal';
    const navSections: NavSection[] = isEntrepreneur
        ? [
              {
                  label: 'Platform',
                  items: [
                      {
                          label: 'Dashboard',
                          href: dashboardHref,
                          icon: LayoutDashboard,
                      },
                      {
                          label: 'Business Plan',
                          href: '/portal/entrepreneur/plan',
                          icon: BookOpenCheck,
                      },
                      {
                          label: 'Inspiration',
                          href: '/portal/inspiration-board',
                          icon: Sparkles,
                      },
                      {
                          label: 'Feedback',
                          href: '/portal/entrepreneur/surveys',
                          icon: ClipboardCheck,
                      },
                  ],
              },
              {
                  label: 'Comms',
                  items: [
                      {
                          label: 'Messages',
                          href: '/portal/messages',
                          icon: MessageSquare,
                      },
                      {
                          label: 'Notifications',
                          href: '/notifications',
                          icon: Bell,
                      },
                  ],
              },
              {
                  label: 'Calendar',
                  items: [
                      {
                          label: 'Calendar',
                          href: '/portal/calendar',
                          icon: CalendarDays,
                      },
                  ],
              },
          ]
        : [
              {
                  label: 'Platform',
                  items: [
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
                          label: 'Feedback',
                          href: '/portal/surveys',
                          icon: ClipboardCheck,
                      },
                      {
                          label: 'Inspiration',
                          href: '/portal/inspiration-board',
                          icon: Sparkles,
                      },
                  ],
              },
              {
                  label: 'Services',
                  items: portalServiceNavItems(portalServices),
              },
              {
                  label: 'Comms',
                  items: [
                      {
                          label: 'Messages',
                          href: '/portal/messages',
                          icon: MessageSquare,
                      },
                      {
                          label: 'Notifications',
                          href: '/notifications',
                          icon: Bell,
                      },
                  ],
              },
              {
                  label: 'Calendar',
                  items: [
                      {
                          label: 'Calendar',
                          href: '/portal/calendar',
                          icon: CalendarDays,
                      },
                  ],
              },
          ];

    const isActive = (href: string) => {
        if (href === dashboardHref) {
            return url === href;
        }

        return url.startsWith(href);
    };

    const closeMobile = () => setOpen(false);
    const handleLogout = () => {
        closeMobile();
        void clearPortalOfflineQueue();
        router.flushAll();
    };

    return (
        <div className="public min-h-screen bg-[var(--fs-parchment)] text-[var(--fs-graphite)] md:flex">
            <a
                href="#portal-main"
                className="sr-only rounded-md bg-background px-3 py-2 text-sm font-medium focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50"
            >
                Skip to portal content
            </a>

            <aside className="hidden w-64 shrink-0 bg-[var(--fs-parchment)] md:sticky md:top-0 md:flex md:h-screen md:flex-col">
                <div className="px-5 py-5">
                    <Link href={dashboardHref} aria-label="Portal dashboard">
                        <BrandMark width={168} />
                    </Link>
                </div>

                <nav
                    className="space-y-5 px-3 py-4"
                    aria-label="Portal navigation"
                >
                    {navSections.map((section) => (
                        <PortalNavSection
                            key={section.label}
                            section={section}
                            isActive={isActive}
                        />
                    ))}
                </nav>

                <div className="mt-auto p-3">
                    <div className="mb-3 flex items-center justify-between gap-3 px-2">
                        <div className="min-w-0">
                            <div className="truncate text-sm font-medium">
                                {auth.user.name}
                            </div>
                            <div className="truncate text-xs text-muted-foreground">
                                {auth.user.email}
                            </div>
                        </div>
                        <NotificationBell />
                    </div>

                    <div className="grid gap-1">
                        <PortalNavLink
                            item={{
                                label: 'Settings',
                                href: '/settings/profile',
                                icon: Settings,
                            }}
                            active={isActive('/settings')}
                        />
                        <Link
                            href={logout()}
                            as="button"
                            onClick={handleLogout}
                            className="inline-flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-[var(--fs-graphite)] transition-colors outline-none hover:bg-[var(--fs-linen)] hover:text-[var(--fs-admiralty)] focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            data-test="portal-logout-button"
                        >
                            <LogOut className="size-4" aria-hidden="true" />
                            Log out
                        </Link>
                    </div>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-40 border-b border-[var(--fs-sand)] bg-[var(--fs-parchment)]/95 backdrop-blur md:hidden">
                    <div className="flex h-16 items-center justify-between px-4">
                        <Link
                            href={dashboardHref}
                            aria-label="Portal dashboard"
                        >
                            <BrandMark width={148} />
                        </Link>

                        <div className="flex items-center gap-2">
                            <NotificationBell />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={open ? 'Close menu' : 'Open menu'}
                                aria-expanded={open}
                                aria-controls="portal-mobile-nav"
                                onClick={() => setOpen((value) => !value)}
                            >
                                {open ? (
                                    <X className="size-4" aria-hidden="true" />
                                ) : (
                                    <Menu
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                            </Button>
                        </div>
                    </div>

                    {open && (
                        <nav
                            id="portal-mobile-nav"
                            className="space-y-4 border-t border-[var(--fs-sand)] px-4 py-3"
                            aria-label="Portal mobile navigation"
                        >
                            {navSections.map((section) => (
                                <PortalNavSection
                                    key={section.label}
                                    section={section}
                                    isActive={isActive}
                                    onClick={closeMobile}
                                />
                            ))}
                            <PortalNavLink
                                item={{
                                    label: 'Settings',
                                    href: '/settings/profile',
                                    icon: Settings,
                                }}
                                active={isActive('/settings')}
                                onClick={closeMobile}
                            />
                            <Link
                                href={logout()}
                                as="button"
                                onClick={handleLogout}
                                className="inline-flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-[var(--fs-graphite)] transition-colors outline-none hover:bg-[var(--fs-linen)] hover:text-[var(--fs-admiralty)] focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                data-test="portal-mobile-logout-button"
                            >
                                <LogOut className="size-4" aria-hidden="true" />
                                Log out
                            </Link>
                        </nav>
                    )}
                </header>

                <main
                    id="portal-main"
                    className="w-full px-4 py-6 sm:px-6 lg:px-8"
                >
                    <div className="mx-auto max-w-6xl">{children}</div>
                </main>
            </div>
        </div>
    );
}

function portalServiceNavItems(
    portalServices?: PortalServices | null,
): NavItem[] {
    const fallbackOptions: PortalServiceOption[] = [
        {
            service_type: 'due_diligence',
            label: 'Explore buying a business',
            description:
                'Open a DD workspace when you are considering a purchase or investment.',
            available: true,
            start_url: '/portal/service-activations/new/due_diligence',
        },
        {
            service_type: 'entrepreneur',
            label: 'Test new Business Idea',
            description:
                'Open idea validation, business-plan, and budget support inside this portal.',
            available: true,
            start_url: '/portal/service-activations/new/entrepreneur',
        },
    ];
    const closedStatuses = new Set(['cancelled', 'closed', 'rejected']);
    const options =
        portalServices?.options && portalServices.options.length > 0
            ? portalServices.options
            : fallbackOptions;

    return options.map((option) => {
        const current = portalServices?.items.find(
            (item) =>
                item.service_type === option.service_type &&
                !closedStatuses.has(item.status),
        );

        return {
            label: option.label,
            href: current?.workspace_url ?? current?.url ?? option.start_url,
            icon:
                option.service_type === 'due_diligence'
                    ? BriefcaseBusiness
                    : Lightbulb,
        };
    });
}

function PortalNavSection({
    section,
    isActive,
    onClick,
}: {
    section: NavSection;
    isActive: (href: string) => boolean;
    onClick?: () => void;
}) {
    return (
        <section aria-label={section.label}>
            <div className="px-3 text-xs font-medium text-muted-foreground">
                {section.label}
            </div>
            <div className="mt-2 grid gap-1">
                {section.items.map((item) => (
                    <PortalNavLink
                        key={item.href}
                        item={item}
                        active={isActive(item.href)}
                        onClick={onClick}
                    />
                ))}
            </div>
        </section>
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
                'inline-flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
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
