import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    Bell,
    BookOpen,
    BriefcaseBusiness,
    CalendarDays,
    ClipboardList,
    ClipboardCheck,
    Database,
    FileSpreadsheet,
    FileText,
    FolderGit2,
    Handshake,
    HeartHandshake,
    HeartPulse,
    History,
    Inbox,
    KeyRound,
    LayoutGrid,
    Lightbulb,
    MessageSquare,
    PlugZap,
    Scale,
    Settings2,
    ShieldCheck,
    Sparkles,
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
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { Auth, NavGroup, NavItem } from '@/types';

const dashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
};

const advisoryClientsNavItem: NavItem = {
    title: 'Advisory',
    href: '/advisor/clients?engagement_type=standard_advisory',
    icon: BriefcaseBusiness,
};

const advisorCalendarNavItem: NavItem = {
    title: 'Calendar',
    href: '/advisor/calendar',
    icon: CalendarDays,
};

const activityCalendarNavItem: NavItem = {
    title: 'Calendar',
    href: '/calendar',
    icon: CalendarDays,
};

const portalCalendarNavItem: NavItem = {
    title: 'Calendar',
    href: '/portal/calendar',
    icon: CalendarDays,
};

const dueDiligenceNavItem: NavItem = {
    title: 'Due Diligence',
    href: '/advisor/clients?engagement_type=due_diligence',
    icon: Scale,
};

const npoNavItem: NavItem = {
    title: 'NPOs',
    href: '/advisor/clients?engagement_type=npo',
    icon: HeartHandshake,
};

const entrepreneursNavItem: NavItem = {
    title: 'Entrepreneurs',
    href: '/advisor/entrepreneurs',
    icon: UsersRound,
};

const brokersNavItem: NavItem = {
    title: 'Brokers',
    href: '/advisor/partners/brokers',
    icon: Handshake,
};

const coachesNavItem: NavItem = {
    title: 'Coaches',
    href: '/advisor/partners/coaches',
    icon: HeartPulse,
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

const advisorMessagesNavItem: NavItem = {
    title: 'Messages',
    href: '/advisor/messages',
    icon: MessageSquare,
};

const acquisitionPlanNavItem: NavItem = {
    title: 'Prepare Due Diligence',
    href: '/portal/acquisition-plan',
    icon: Scale,
};

const entrepreneurBuyingBusinessNavItem: NavItem = {
    title: 'Explore buying a business',
    href: '/portal/service-activations/new/due_diligence',
    icon: BriefcaseBusiness,
};

const strategicPlanBudgetNavItem: NavItem = {
    title: 'Business Plan & Budget',
    href: '/portal/business-plan-budget',
    icon: FileSpreadsheet,
};

const apiHealthNavItem: NavItem = {
    title: 'API Health',
    href: '/admin/integration-health',
    icon: PlugZap,
};

const integrationCredentialsNavItem: NavItem = {
    title: 'Credentials',
    href: '/admin/integration-credentials',
    icon: KeyRound,
};

const projectSettingsNavItem: NavItem = {
    title: 'Project Settings',
    href: '/admin/project-settings',
    icon: Settings2,
};

const referenceDataNavItem: NavItem = {
    title: 'Reference Data',
    href: '/admin/reference-data',
    icon: Database,
};

const serviceRatesNavItem: NavItem = {
    title: 'Service Rates',
    href: '/admin/service-rates',
    icon: BadgeDollarSign,
};

const staffNavItem: NavItem = {
    title: 'Staff',
    href: '/admin/staff',
    icon: UsersRound,
};

const principlesRolesNavItem: NavItem = {
    title: 'Principles & Roles',
    href: '/admin/principles-roles',
    icon: ShieldCheck,
};

const ratingFrameworkNavItem: NavItem = {
    title: 'Rating Framework',
    href: '/admin/rating-frameworks',
    icon: ClipboardCheck,
};

const termsNavItem: NavItem = {
    title: "T&C's",
    href: '/admin/terms',
    icon: FileText,
};

const partnerAgreementNavItem: NavItem = {
    title: 'Partner Agreement',
    href: '/admin/partner-agreement',
    icon: FileText,
};

const auditTrailNavItem: NavItem = {
    title: 'Audit Trail',
    href: '/admin/audit-trail',
    icon: History,
};

const questionnairesNavItem: NavItem = {
    title: 'Questionnaires',
    href: '/admin/questionnaires',
    icon: ClipboardList,
};

const surveysNavItem: NavItem = {
    title: 'Surveys',
    href: '/admin/surveys',
    icon: ClipboardCheck,
};

const portalSurveysNavItem: NavItem = {
    title: 'Feedback',
    href: '/portal/surveys',
    icon: ClipboardCheck,
};

const entrepreneurSurveysNavItem: NavItem = {
    title: 'Feedback',
    href: '/portal/entrepreneur/surveys',
    icon: ClipboardCheck,
};

const entrepreneurBusinessPlanNavItem: NavItem = {
    title: 'Business Plan',
    href: '/portal/entrepreneur/plan',
    icon: BookOpen,
};

const welcomeMessageNavItem: NavItem = {
    title: 'Welcome Message',
    href: '/admin/welcome-message',
    icon: HeartHandshake,
};

const inspirationBoardNavItem: NavItem = {
    title: 'Inspiration',
    href: '/admin/inspiration-board',
    icon: Sparkles,
};

const portalInspirationNavItem: NavItem = {
    title: 'Inspiration',
    href: '/portal/inspiration-board',
    icon: Sparkles,
};

const entrepreneurDashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: '/portal/entrepreneur',
    icon: LayoutGrid,
};

const portalDashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: '/portal',
    icon: LayoutGrid,
};

const npoBoardDashboardNavItem: NavItem = {
    title: 'Dashboard',
    href: '/portal/npo-board',
    icon: LayoutGrid,
};

const portalOnboardingNavItem: NavItem = {
    title: 'Onboarding',
    href: '/portal/onboarding',
    icon: ClipboardList,
};

const portalWellbeingNavItem: NavItem = {
    title: 'Wellbeing',
    href: '/portal/wellbeing',
    icon: HeartPulse,
};

const defaultNavItems: NavItem[] = [
    dashboardNavItem,
    activityCalendarNavItem,
    notificationsNavItem,
];

const advisorClientNavItems: NavItem[] = [
    advisoryClientsNavItem,
    dueDiligenceNavItem,
    npoNavItem,
    entrepreneursNavItem,
];

const advisorPartnerNavItems: NavItem[] = [brokersNavItem, coachesNavItem];

const advisorCommunicationNavItems: NavItem[] = [
    advisorMessagesNavItem,
    notificationsNavItem,
];

const advisorAdministrationNavItems: NavItem[] = [
    knowledgeNavItem,
    templatesNavItem,
];

const superAdminCommunicationNavItems: NavItem[] = [
    advisorMessagesNavItem,
    notificationsNavItem,
    inspirationBoardNavItem,
    welcomeMessageNavItem,
];

const superAdminAdministrationNavItems: NavItem[] = [
    knowledgeNavItem,
    templatesNavItem,
    apiHealthNavItem,
    integrationCredentialsNavItem,
    projectSettingsNavItem,
    staffNavItem,
    principlesRolesNavItem,
    serviceRatesNavItem,
    ratingFrameworkNavItem,
    termsNavItem,
    partnerAgreementNavItem,
    auditTrailNavItem,
    referenceDataNavItem,
    questionnairesNavItem,
    surveysNavItem,
];

type PortalClient = {
    engagement_type?: string | null;
    onboarding_complete?: boolean;
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

type AdvisorPageClient = {
    engagement_type?: string | null;
};

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

function navGroup(
    title: string,
    items: NavItem[],
    options: Pick<NavGroup, 'collapsible' | 'defaultOpen'> = {},
): NavGroup {
    return { title, items, ...options };
}

function internalNavGroups({
    platformItems,
    clientItems,
    communicationItems,
    calendarItem,
    partnerItems = [],
    administrationItems,
}: {
    platformItems: NavItem[];
    clientItems: NavItem[];
    communicationItems: NavItem[];
    calendarItem: NavItem;
    partnerItems?: NavItem[];
    administrationItems: NavItem[];
}): NavGroup[] {
    return [
        platformItems.length > 0 ? navGroup('Platform', platformItems) : null,
        clientItems.length > 0 ? navGroup('Clients', clientItems) : null,
        communicationItems.length > 0
            ? navGroup('Comms', communicationItems)
            : null,
        navGroup('Calendar', [calendarItem]),
        partnerItems.length > 0 ? navGroup('Partners', partnerItems) : null,
        administrationItems.length > 0
            ? navGroup('Administration', administrationItems, {
                  collapsible: true,
                  defaultOpen: false,
              })
            : null,
    ].filter((group): group is NavGroup => group !== null);
}

function portalNavGroups({
    platformItems,
    serviceItems = [],
    communicationItems = [messagesNavItem, notificationsNavItem],
}: {
    platformItems: NavItem[];
    serviceItems?: NavItem[];
    communicationItems?: NavItem[];
}): NavGroup[] {
    return [
        navGroup('Platform', platformItems),
        serviceItems.length > 0 ? navGroup('Services', serviceItems) : null,
        navGroup('Comms', communicationItems),
        navGroup('Calendar', [portalCalendarNavItem]),
    ].filter((group): group is NavGroup => group !== null);
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
            title: option.label,
            href: current?.workspace_url ?? current?.url ?? option.start_url,
            icon:
                option.service_type === 'due_diligence'
                    ? BriefcaseBusiness
                    : Lightbulb,
        };
    });
}

function navGroupsFor(
    userType?: string | null,
    portalClient?: PortalClient | null,
    portalServices?: PortalServices | null,
): NavGroup[] {
    if (userType === 'entrepreneur') {
        return portalNavGroups({
            platformItems: [
                entrepreneurDashboardNavItem,
                entrepreneurBusinessPlanNavItem,
                portalInspirationNavItem,
                entrepreneurSurveysNavItem,
            ],
            serviceItems: [entrepreneurBuyingBusinessNavItem],
        });
    }

    if (userType === 'client_primary' || userType === 'client_team') {
        const serviceItems = portalServiceNavItems(portalServices);
        const onboardingComplete = portalClient?.onboarding_complete === true;
        const onboardingNavItem: NavItem = {
            ...portalOnboardingNavItem,
            title: onboardingComplete ? 'Onboarded' : 'Onboarding',
        };
        const planBudgetNavItem: NavItem = {
            ...strategicPlanBudgetNavItem,
            title:
                portalClient?.engagement_type === 'npo'
                    ? 'Operating Plan & Budget'
                    : 'Business Plan & Budget',
        };
        const activePathItems =
            portalClient?.engagement_type === 'due_diligence'
                ? [acquisitionPlanNavItem, planBudgetNavItem]
                : [planBudgetNavItem];
        const supportingItems = [
            portalWellbeingNavItem,
            portalSurveysNavItem,
            portalInspirationNavItem,
        ];
        const platformItems = onboardingComplete
            ? [
                  portalDashboardNavItem,
                  ...activePathItems,
                  ...supportingItems,
                  onboardingNavItem,
              ]
            : [
                  portalDashboardNavItem,
                  onboardingNavItem,
                  ...activePathItems,
                  ...supportingItems,
              ];

        if (portalClient?.engagement_type === 'due_diligence') {
            return portalNavGroups({
                platformItems,
                serviceItems,
            });
        }

        return portalNavGroups({ platformItems, serviceItems });
    }

    if (userType === 'npo_board_member') {
        return portalNavGroups({
            platformItems: [npoBoardDashboardNavItem],
        });
    }

    if (userType === 'super_admin') {
        return internalNavGroups({
            platformItems: [dashboardNavItem, prospectsNavItem],
            clientItems: advisorClientNavItems,
            communicationItems: superAdminCommunicationNavItems,
            calendarItem: advisorCalendarNavItem,
            partnerItems: advisorPartnerNavItems,
            administrationItems: superAdminAdministrationNavItems,
        });
    }

    if (userType === 'advisor') {
        return internalNavGroups({
            platformItems: [dashboardNavItem, prospectsNavItem],
            clientItems: advisorClientNavItems,
            communicationItems: advisorCommunicationNavItems,
            calendarItem: advisorCalendarNavItem,
            partnerItems: advisorPartnerNavItems,
            administrationItems: advisorAdministrationNavItems,
        });
    }

    if (userType === 'junior_advisor') {
        return internalNavGroups({
            platformItems: [dashboardNavItem, prospectsNavItem],
            clientItems: advisorClientNavItems,
            communicationItems: advisorCommunicationNavItems,
            calendarItem: advisorCalendarNavItem,
            administrationItems: [knowledgeNavItem, templatesNavItem],
        });
    }

    if (userType === 'entrepreneur_mentor') {
        return internalNavGroups({
            platformItems: [dashboardNavItem],
            clientItems: [entrepreneursNavItem],
            communicationItems: advisorCommunicationNavItems,
            calendarItem: activityCalendarNavItem,
            administrationItems: [knowledgeNavItem, templatesNavItem],
        });
    }

    if (userType === 'broker') {
        return internalNavGroups({
            platformItems: [dashboardNavItem],
            clientItems: [],
            communicationItems: [notificationsNavItem],
            calendarItem: activityCalendarNavItem,
            administrationItems: [],
        });
    }

    if (userType === 'coach') {
        return internalNavGroups({
            platformItems: [dashboardNavItem],
            clientItems: [],
            communicationItems: [notificationsNavItem],
            calendarItem: activityCalendarNavItem,
            administrationItems: [],
        });
    }

    return [navGroup('Platform', defaultNavItems)];
}

function homeHrefFor(userType?: string | null): NavItem['href'] {
    if (userType === 'entrepreneur') {
        return '/portal/entrepreneur';
    }

    if (userType === 'client_primary' || userType === 'client_team') {
        return '/portal';
    }

    if (userType === 'npo_board_member') {
        return '/portal/npo-board';
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

function navGroupsWithClientFilterState(
    groups: NavGroup[],
    currentPath: string,
    engagementType: string | null,
): NavGroup[] {
    const withState = (item: NavItem): NavItem => {
        if (item.href === advisoryClientsNavItem.href) {
            return {
                ...item,
                isActive:
                    currentPath.startsWith('/advisor/clients') &&
                    engagementType === 'standard_advisory',
            };
        }

        if (item.href === dueDiligenceNavItem.href) {
            return {
                ...item,
                isActive:
                    currentPath === '/advisor/clients' &&
                    engagementType === 'due_diligence',
            };
        }

        if (item.href === npoNavItem.href) {
            return {
                ...item,
                isActive:
                    currentPath === '/advisor/clients' &&
                    engagementType === 'npo',
            };
        }

        return item;
    };

    return groups.map((group) => ({
        ...group,
        items: group.items.map(withState),
    }));
}

export function AppSidebar() {
    const page = usePage<{
        auth: Auth;
        portalClient?: PortalClient | null;
        portalServices?: PortalServices | null;
        client?: AdvisorPageClient | null;
    }>();
    const { isMobile, setOpenMobile } = useSidebar();
    const { auth } = page.props;
    const userType = auth.user.user_type;
    const currentUrl = new URL(
        page.url,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    );
    const engagementType = currentUrl.pathname.startsWith('/advisor/clients')
        ? (currentUrl.searchParams.get('engagement_type') ??
          page.props.client?.engagement_type ??
          null)
        : null;
    const mainNavGroups = navGroupsWithClientFilterState(
        navGroupsFor(
            userType,
            page.props.portalClient,
            page.props.portalServices,
        ),
        currentUrl.pathname,
        engagementType,
    );
    const homeHref = homeHrefFor(userType);
    const visibleFooterItems = canViewInternalFooter(userType)
        ? footerNavItems
        : [];
    const closeMobileSidebar = () => {
        if (isMobile) {
            setOpenMobile(false);
        }
    };

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            className="h-16 px-3 group-data-[collapsible=icon]:size-10! group-data-[collapsible=icon]:p-1!"
                            asChild
                        >
                            <Link
                                href={homeHref}
                                prefetch
                                onClick={closeMobileSidebar}
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={mainNavGroups} />
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
