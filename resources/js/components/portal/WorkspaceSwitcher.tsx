import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type WorkspaceSwitcherPayload = {
    active_key: string;
    items: WorkspaceSwitcherItem[];
};

export type WorkspaceSwitcherItem = {
    key: string;
    service_type: string;
    label: string;
    description: string;
    href: string;
    primary: boolean;
    status_label: string;
    badge_count: number | null;
};

export function WorkspaceSwitcher({
    workspaces,
    className,
}: {
    workspaces?: WorkspaceSwitcherPayload | null;
    className?: string;
}) {
    if (!workspaces || workspaces.items.length <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Service workspaces"
            className={cn(
                'inline-flex max-w-full flex-wrap gap-1 rounded-md border bg-muted/25 p-1',
                className,
            )}
        >
            {workspaces.items.map((workspace) => {
                const active = workspace.key === workspaces.active_key;

                return (
                    <Link
                        key={workspace.key}
                        href={workspace.href}
                        className={cn(
                            'inline-flex min-h-9 items-center gap-2 rounded-sm px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            active &&
                                'bg-[var(--fs-admiralty)] text-white shadow-xs hover:text-white',
                        )}
                        aria-current={active ? 'page' : undefined}
                    >
                        <span>{workspace.label}</span>
                        {workspace.badge_count !== null ? (
                            <span
                                className={cn(
                                    'rounded-full border px-1.5 text-xs',
                                    active
                                        ? 'border-white/30 bg-white/15 text-white'
                                        : 'bg-background text-muted-foreground',
                                )}
                            >
                                {workspace.badge_count}
                            </span>
                        ) : null}
                    </Link>
                );
            })}
        </nav>
    );
}
