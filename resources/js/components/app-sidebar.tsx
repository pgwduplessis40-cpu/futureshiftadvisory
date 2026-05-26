import { Link } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    BriefcaseBusiness,
    ClipboardList,
    FileText,
    FolderGit2,
    Inbox,
    LayoutGrid,
    PlugZap,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Clients',
        href: '/advisor/clients',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Entrepreneurs',
        href: '/advisor/entrepreneurs',
        icon: UsersRound,
    },
    {
        title: 'Knowledge',
        href: '/advisor/knowledge',
        icon: BookOpen,
    },
    {
        title: 'Templates',
        href: '/advisor/templates',
        icon: FileText,
    },
    {
        title: 'Prospects',
        href: '/advisor/prospects',
        icon: Inbox,
    },
    {
        title: 'Notifications',
        href: '/notifications',
        icon: Bell,
    },
    {
        title: 'API Health',
        href: '/admin/integration-health',
        icon: PlugZap,
    },
    {
        title: 'Questionnaires',
        href: '/admin/questionnaires',
        icon: ClipboardList,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
