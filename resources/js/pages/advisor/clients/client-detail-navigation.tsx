import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { ComponentType, MouseEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { ClientDetailTab } from './client-detail-types';
export function ClientDetailSection({
    title,
    description,
    collapsible = false,
    defaultOpen = false,
    children,
}: {
    title: string;
    description: string;
    collapsible?: boolean;
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    const heading = (
        <>
            <h2 className="text-base font-semibold">{title}</h2>
            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                {description}
            </p>
        </>
    );

    if (collapsible) {
        return (
            <section>
                <Collapsible defaultOpen={defaultOpen}>
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="group flex w-full items-start justify-between gap-3 rounded-sm text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <span className="min-w-0">{heading}</span>
                            <ChevronDown
                                className="mt-1 size-5 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
                                aria-hidden="true"
                            />
                        </button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="pt-3">
                        <div className="space-y-4">{children}</div>
                    </CollapsibleContent>
                </Collapsible>
            </section>
        );
    }

    return (
        <section className="space-y-3">
            <div>{heading}</div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

export function ClientDetailTabList({
    activeTab,
    onChange,
}: {
    activeTab: ClientDetailTab;
    onChange: (tab: ClientDetailTab) => void;
}) {
    return (
        <div
            className="inline-flex max-w-full rounded-md border bg-muted/30 p-1"
            role="tablist"
            aria-label="Client detail sections"
        >
            <ClientDetailTabButton
                active={activeTab === 'actions'}
                onClick={() => onChange('actions')}
            >
                Actions
            </ClientDetailTabButton>
            <ClientDetailTabButton
                active={activeTab === 'information'}
                onClick={() => onChange('information')}
            >
                Information
            </ClientDetailTabButton>
        </div>
    );
}

export function ClientDetailTabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            className={cn(
                'shrink-0 rounded-sm px-3 py-1.5 text-sm font-medium text-foreground transition-shadow hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                active && 'bg-background text-foreground shadow-xs',
            )}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

export function AnalysisFindingFilterButton({
    active,
    count,
    onClick,
    children,
}: {
    active: boolean;
    count: number;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            className={cn(
                'inline-flex h-7 items-center gap-1.5 rounded-sm px-2 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                active && 'bg-background text-foreground shadow-xs',
            )}
            onClick={onClick}
        >
            <span>{children}</span>
            <span className="grid size-4 place-items-center rounded-full bg-muted text-[10px] tabular-nums">
                {count}
            </span>
        </button>
    );
}

export function ActionTile({
    icon: Icon,
    title,
    value,
    explanation,
    href,
    actionLabel,
    onAction,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    title: string;
    value: ReactNode;
    explanation: string;
    href: string;
    actionLabel: string;
    onAction?: (event: MouseEvent<Element>) => void;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <section className="rounded-md border bg-background p-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Icon className="size-4" aria-hidden={true} />
                        {title}
                    </div>
                    <div className="mt-2 text-sm font-medium">{value}</div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="mt-3 px-0"
                    >
                        <Link href={href} onClick={onAction}>
                            {actionLabel}
                        </Link>
                    </Button>
                </section>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}
