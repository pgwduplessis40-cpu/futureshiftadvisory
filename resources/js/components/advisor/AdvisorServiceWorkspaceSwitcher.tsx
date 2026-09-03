import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export type AdvisorServiceWorkspacePayload = {
    active_key: string;
    items: AdvisorServiceWorkspaceItem[];
};

export type AdvisorServiceWorkspaceItem = {
    key: string;
    label: string;
    href: string;
    active: boolean;
};

export function AdvisorServiceWorkspaceSwitcher({
    workspaces,
    activeKey = workspaces?.active_key,
}: {
    workspaces?: AdvisorServiceWorkspacePayload | null;
    activeKey?: string;
}) {
    if (!workspaces || workspaces.items.length <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Client service workspaces"
            className="overflow-x-auto rounded-md border bg-muted/30 p-1"
        >
            <div className="flex min-w-max gap-1">
                {workspaces.items.map((workspace) => (
                    <Link
                        key={workspace.key}
                        href={workspace.href}
                        aria-current={
                            workspace.key === activeKey ? 'page' : undefined
                        }
                        className={cn(
                            'inline-flex min-h-10 items-center rounded-sm px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            workspace.key === activeKey &&
                                'bg-[var(--fs-admiralty)] text-white shadow-xs hover:text-white',
                        )}
                    >
                        {workspace.label}
                    </Link>
                ))}
            </div>
        </nav>
    );
}
