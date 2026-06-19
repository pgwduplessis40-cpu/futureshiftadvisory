import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupAction,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types';

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    const closeMobileSidebar = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };

    const isActive = (item: NavItem) => {
        if (typeof item.isActive === 'boolean') {
            return item.isActive;
        }

        const path = toUrl(item.href);
        const exactOnly = [
            '/',
            '/dashboard',
            '/portal',
            '/portal/entrepreneur',
        ];

        return exactOnly.includes(path)
            ? isCurrentUrl(item.href)
            : isCurrentOrParentUrl(item.href);
    };

    return (
        <>
            {groups
                .filter((group) => group.items.length > 0)
                .map((group) => {
                    const menu = (
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isActive(item)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link
                                            href={item.href}
                                            prefetch
                                            onClick={closeMobileSidebar}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    );

                    if (!group.collapsible) {
                        return (
                            <SidebarGroup
                                key={group.title}
                                className="px-2 py-0"
                            >
                                <SidebarGroupLabel>
                                    {group.title}
                                </SidebarGroupLabel>
                                <SidebarGroupContent>
                                    {menu}
                                </SidebarGroupContent>
                            </SidebarGroup>
                        );
                    }

                    const hasActiveItem = group.items.some(isActive);

                    return (
                        <Collapsible
                            key={group.title}
                            defaultOpen={
                                hasActiveItem || group.defaultOpen === true
                            }
                            className="group/collapsible"
                        >
                            <SidebarGroup className="px-2 py-0">
                                <SidebarGroupLabel>
                                    {group.title}
                                </SidebarGroupLabel>
                                <CollapsibleTrigger asChild>
                                    <SidebarGroupAction
                                        aria-label={`Toggle ${group.title}`}
                                    >
                                        <ChevronDown
                                            className={cn(
                                                'transition-transform',
                                                'group-data-[state=open]/collapsible:rotate-180',
                                            )}
                                            aria-hidden="true"
                                        />
                                    </SidebarGroupAction>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarGroupContent>
                                        {menu}
                                    </SidebarGroupContent>
                                </CollapsibleContent>
                            </SidebarGroup>
                        </Collapsible>
                    );
                })}
        </>
    );
}
