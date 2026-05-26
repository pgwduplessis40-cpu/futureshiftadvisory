import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    BookOpen,
    BriefcaseBusiness,
    ClipboardList,
    FileText,
    FolderGit2,
    Inbox,
    LayoutGrid,
    MessageSquare,
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
import type { Auth, NavItem } from '@/types';

const dashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
};

const clientsNavItem: NavItem = {
    title: 'Clients',
    href: '/advisor/clients',
    icon: BriefcaseBusiness,
};

const entrepreneursNavItem: NavItem = {
    title: 'Entrepreneurs',
    href: '/advisor/entrepreneurs',
    icon: UsersRound,
};

const knowledgeNavItem: NavItem = {
    title: 'Knowledge',
    href: '/advisor/knowledge',
    icon: BookOpen,
};

const templatesNavItem: NavItem = {
    title: 'Templates',
    href: '/advisor/templates',
    icon: FileText,
};

const prospectsNavItem: NavItem = {
    title: 'Prospects',
    href: '/advisor/prospects',
    icon: Inbox,
};

const notificationsNavItem: NavItem = {
    title: 'Notifications',
    href: '/notifications',
    icon: Bell,
};

const messagesNavItem: NavItem = {
    title: 'Messages',
    href: '/portal/messages',
    icon: MessageSquare,
};

const apiHealthNavItem: NavItem = {
    title: 'API Health',
    href: '/admin/integration-health',
    icon: PlugZap,
};

const questionnairesNavItem: NavItem = {
    title: 'Questionnaires',
    href: '/admin/questionnaires',
    icon: ClipboardList,
};

const advisorNavItems: NavItem[] = [
    dashboardNavItem,
    clientsNavItem,
    entrepreneursNavItem,
    knowledgeNavItem,
    templatesNavItem,
    prospectsNavItem,
    notificationsNavItem,
    apiHealthNavItem,
];

const juniorAdvisorNavItems: NavItem[] = [
    dashboardNavItem,
    clientsNavItem,
    entrepreneursNavItem,
    knowledgeNavItem,
    templatesNavItem,
    prospectsNavItem,
    notificationsNavItem,
];

const mentorNavItems: NavItem[] = [
    dashboardNavItem,
    entrepreneursNavItem,
    knowledgeNavItem,
    templatesNavItem,
    notificationsNavItem,
];

const entrepreneurNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/portal/entrepreneur',
        icon: LayoutGrid,
    },
    messagesNavItem,
    notificationsNavItem,
];

const clientNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/portal',
        icon: LayoutGrid,
    },
    messagesNavItem,
    notificationsNavItem,
];

const defaultNavItems: NavItem[] = [dashboardNavItem, notificationsNavItem];

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

function mainNavItemsFor(userType?: string | null): NavItem[] {
    if (userType === 'entrepreneur') {
        return entrepreneurNavItems;
    }

    if (userType === 'client_primary' || userType === 'client_team') {
        return clientNavItems;
    }

    if (userType === 'super_admin') {
        return [...advisorNavItems, questionnairesNavItem];
    }

    if (userType === 'advisor') {
        return advisorNavItems;
    }

    if (userType === 'junior_advisor') {
        return juniorAdvisorNavItems;
    }

    if (userType === 'entrepreneur_mentor') {
        return mentorNavItems;
    }

    return defaultNavItems;
}

function homeHrefFor(userType?: string | null): NavItem['href'] {
    if (userType === 'entrepreneur') {
        return '/portal/entrepreneur';
    }

    if (userType === 'client_primary' || userType === 'client_team') {
        return '/portal';
    }

    return dashboard();
}

function canViewInternalFooter(userType?: string | null): boolean {
    return (
        userType === 'super_admin' ||
        userType === 'advisor' ||
        userType === 'junior_advisor' ||
        userType === 'entrepreneur_mentor'
    );
}

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const userType = auth.user.user_type;
    const mainNavItems = mainNavItemsFor(userType);
    const homeHref = homeHrefFor(userType);
    const visibleFooterItems = canViewInternalFooter(userType)
        ? footerNavItems
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
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
                {visibleFooterItems.length > 0 && (
                    <NavFooter items={visibleFooterItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
