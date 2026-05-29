import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Bell,
    KeyRound,
    LayoutGrid,
    MessageSquare,
    Settings,
    UserRound,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { dashboard } from '@/routes';

type DashboardAction = {
    title: string;
    description: string;
    href: string;
    action: string;
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    explanation: string;
    priority: 'high' | 'information';
};

const actions: DashboardAction[] = [
    {
        title: 'Notifications',
        description: 'Review account alerts and updates.',
        href: '/notifications',
        action: 'Open',
        icon: Bell,
        explanation:
            'Notifications collect account, document, security, and advisory alerts available to your role.',
        priority: 'high',
    },
    {
        title: 'Security',
        description: 'Maintain password and two-factor authentication.',
        href: '/settings/security',
        action: 'Review',
        icon: KeyRound,
        explanation:
            'Security settings show password controls and two-factor status for your account.',
        priority: 'high',
    },
    {
        title: 'Profile',
        description: 'Update your name, email, and deactivation request.',
        href: '/settings/profile',
        action: 'Manage',
        icon: UserRound,
        explanation:
            'Profile settings let you update identity details and request account deactivation without deleting records.',
        priority: 'information',
    },
    {
        title: 'Preferences',
        description: 'Adjust appearance and communication preferences.',
        href: '/settings/appearance',
        action: 'Configure',
        icon: Settings,
        explanation:
            'Preferences keep app appearance and account defaults aligned with how you work.',
        priority: 'information',
    },
];

export default function Dashboard() {
    const priorityActions = actions.filter(
        (action) => action.priority === 'high',
    );
    const informationActions = actions.filter(
        (action) => action.priority === 'information',
    );

    return (
        <>
            <Head title="Dashboard" />
            <main className="flex-1 space-y-6 p-6">
                <header className="space-y-2">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <LayoutGrid className="size-4" aria-hidden="true" />
                        Account workspace
                    </div>
                    <h1 className="text-2xl font-semibold tracking-normal">
                        Dashboard
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Start with account actions that can block access, then
                        use the lower panels for profile and preference context.
                    </p>
                </header>

                <DashboardSection
                    title="Priority actions"
                    description="Review account alerts and security settings first."
                >
                    <section className="grid gap-4 md:grid-cols-2">
                        {priorityActions.map((action) => (
                            <ActionCard key={action.href} action={action} />
                        ))}
                    </section>
                </DashboardSection>

                <DashboardSection
                    title="Account information"
                    description="Use these panels for lower-urgency account maintenance."
                >
                    <section className="grid gap-4 md:grid-cols-2">
                        {informationActions.map((action) => (
                            <ActionCard key={action.href} action={action} />
                        ))}
                    </section>

                    <Card className="rounded-md">
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle>Need a portal?</CardTitle>
                                    <CardDescription>
                                        Your role does not currently expose a
                                        dedicated operational dashboard.
                                    </CardDescription>
                                </div>
                                <MessageSquare
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            <Button asChild variant="outline" size="sm">
                                <Link href="/notifications">
                                    Check notifications
                                    <ArrowUpRight aria-hidden="true" />
                                </Link>
                            </Button>
                            <Button asChild size="sm">
                                <Link href="/settings/profile">
                                    Open profile
                                    <ArrowUpRight aria-hidden="true" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </DashboardSection>
            </main>
        </>
    );
}

function DashboardSection({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

function ActionCard({ action }: { action: DashboardAction }) {
    const Icon = action.icon;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Card className="rounded-md">
                    <CardHeader className="gap-3">
                        <div className="flex items-center justify-between gap-3">
                            <CardDescription>{action.title}</CardDescription>
                            <Icon
                                className="size-4 text-muted-foreground"
                                aria-hidden={true}
                            />
                        </div>
                        <CardTitle className="text-base">
                            {action.description}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={action.href}>
                                {action.action}
                                <ArrowUpRight aria-hidden="true" />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </TooltipTrigger>
            <TooltipContent side="bottom" className="max-w-xs">
                {action.explanation}
            </TooltipContent>
        </Tooltip>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
