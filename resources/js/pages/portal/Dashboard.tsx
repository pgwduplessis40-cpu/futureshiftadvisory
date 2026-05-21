import { Head, Link } from '@inertiajs/react';
import {
    Bell,
    ClipboardList,
    FileText,
    MessageSquare,
    TrendingUp,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ClientPayload = {
    id: string;
    legal_name: string;
    trading_name: string | null;
    engagement_type: string;
    engagement_type_label: string;
    data_quality: string;
    nzbn: string | null;
};

type Progress = {
    completed: number;
    total: number;
    percentage: number;
};

type Props = {
    client: ClientPayload;
    progress: Progress;
    currentStep: string;
    onboardingUrl: string;
    notificationSummary: {
        unread: number;
        urgent: number;
    };
    messagesUrl: string;
};

export default function PortalDashboard({
    client,
    progress,
    onboardingUrl,
    notificationSummary,
    messagesUrl,
}: Props) {
    return (
        <>
            <Head title="Client portal" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {client.trading_name || client.legal_name}
                        </h1>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span>{client.engagement_type_label}</span>
                            <span aria-hidden="true">/</span>
                            <span>NZBN {client.nzbn ?? '-'}</span>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={onboardingUrl}>
                            <ClipboardList
                                className="size-4"
                                aria-hidden="true"
                            />
                            Continue onboarding
                        </Link>
                    </Button>
                </div>

                <section
                    className="rounded-md border bg-background p-4"
                    aria-labelledby="onboarding-progress-heading"
                >
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2
                                id="onboarding-progress-heading"
                                className="text-sm font-medium"
                            >
                                Onboarding progress
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {progress.completed} of {progress.total} steps
                                complete
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {progress.percentage}%
                        </Badge>
                    </div>
                    <div
                        className="mt-4 h-2 rounded-full bg-muted"
                        role="progressbar"
                        aria-valuenow={progress.percentage}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label="Onboarding completion"
                    >
                        <div
                            className="h-2 rounded-full bg-[var(--fs-admiralty)]"
                            style={{ width: `${progress.percentage}%` }}
                        />
                    </div>
                </section>

                <div className="grid gap-4 md:grid-cols-3">
                    <StatusPanel
                        icon={TrendingUp}
                        label="Data quality"
                        value={client.data_quality}
                    />
                    <StatusPanel
                        icon={Bell}
                        label="Notifications"
                        value={`${notificationSummary.unread} unread`}
                    />
                    <StatusPanel
                        icon={FileText}
                        label="Referral status"
                        value="Not requested"
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section
                        className="space-y-4 rounded-md border bg-background p-4"
                        aria-labelledby="milestones-heading"
                    >
                        <div className="flex items-center gap-2">
                            <TrendingUp className="size-4" aria-hidden="true" />
                            <h2
                                id="milestones-heading"
                                className="text-sm font-medium"
                            >
                                Milestones
                            </h2>
                        </div>
                        <div className="text-sm text-muted-foreground">
                            Phase 2
                        </div>
                    </section>

                    <section
                        className="space-y-4 rounded-md border bg-background p-4"
                        aria-labelledby="messages-heading"
                    >
                        <div className="flex items-center gap-2">
                            <MessageSquare
                                className="size-4"
                                aria-hidden="true"
                            />
                            <h2
                                id="messages-heading"
                                className="text-sm font-medium"
                            >
                                Messages
                            </h2>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={messagesUrl}>Open messages</Link>
                        </Button>
                    </section>
                </div>
            </div>
        </>
    );
}

function StatusPanel({
    icon: Icon,
    label,
    value,
}: {
    icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
    label: string;
    value: string;
}) {
    return (
        <section className="rounded-md border bg-background p-4">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Icon className="size-4" aria-hidden={true} />
                {label}
            </div>
            <div className="mt-2 text-sm font-medium">{value}</div>
        </section>
    );
}
