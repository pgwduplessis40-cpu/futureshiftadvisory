import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    FileSpreadsheet,
    MessageSquare,
    Target,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { ClientCoBrowse } from '@/components/co-browse/ClientCoBrowse';
import { WorkspaceSwitcher } from '@/components/portal/WorkspaceSwitcher';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PortalDashboardProps } from '../Dashboard';

type StandardAdvisoryDashboardProps = Pick<
    PortalDashboardProps,
    | 'client'
    | 'coBrowse'
    | 'progress'
    | 'onboardingUrl'
    | 'workspaces'
    | 'standardAdvisory'
    | 'serviceActivations'
    | 'proposals'
    | 'reports'
    | 'strategicPlan'
    | 'messageSummary'
    | 'messagesUrl'
> & {
    welcomeBanner: ReactNode;
};

export function StandardAdvisoryDashboard({
    client,
    coBrowse,
    progress,
    onboardingUrl,
    workspaces,
    standardAdvisory,
    serviceActivations,
    proposals,
    reports,
    strategicPlan,
    messageSummary,
    messagesUrl,
    welcomeBanner,
}: StandardAdvisoryDashboardProps) {
    const onboardingOpen = progress.percentage < 100;
    const planBudgetOption = serviceActivations.options.find(
        (option) => option.service_type === 'dd_plan_budget',
    );
    const planBudgetActivation = serviceActivations.items.find(
        (item) =>
            item.service_type === 'dd_plan_budget' &&
            !['cancelled', 'closed', 'rejected'].includes(item.status),
    );
    const releasedReport = standardAdvisory?.client_report?.view_url
        ? standardAdvisory.client_report
        : (reports.find((report) => report.type === 'client') ?? null);
    const proposal = proposals.find((item) => item.signed_at === null) ?? null;
    const primaryAction = onboardingOpen
        ? {
              eyebrow: 'Your next step',
              title: 'Complete your advisory brief',
              description:
                  'Answer the next short step so FSA can understand your business priorities and prepare the right advisory review.',
              href: onboardingUrl,
              label: 'Continue onboarding',
          }
        : proposal
          ? {
                eyebrow: 'Your next step',
                title: 'Review your advisory proposal',
                description:
                    'FSA has prepared the proposed scope and next steps for your review.',
                href: proposal.signoff_url,
                label: 'Review proposal',
            }
          : releasedReport?.view_url
            ? {
                  eyebrow: 'Your advisory update',
                  title: releasedReport.title,
                  description:
                      'Your advisor has released an update for you to review before the next agreed step.',
                  href: releasedReport.view_url,
                  label: 'View update',
              }
            : {
                  eyebrow: 'With FSA',
                  title: 'FSA is reviewing your advisory brief',
                  description:
                      standardAdvisory?.next_action ??
                      'We will confirm the next advisory step once your submitted information has been reviewed.',
                  href: messagesUrl,
                  label: 'Message FSA',
              };
    const stages = [
        'Get started',
        'Your goals',
        'Business details',
        'Advisory questionnaire',
        'Supporting evidence',
        'Review and submit',
    ];

    return (
        <>
            <Head title="Standard Advisory" />
            <ClientCoBrowse config={coBrowse} />
            <main
                className="flex-1 space-y-6"
                data-co-browse-target="client.dashboard.workspace"
            >
                <div>
                    <h1 className="text-xl font-semibold">
                        {client.trading_name || client.legal_name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Standard Advisory
                    </p>
                </div>
                <WorkspaceSwitcher workspaces={workspaces} />
                {welcomeBanner}
                <section className="rounded-md border bg-background p-5 shadow-xs">
                    <Badge variant="secondary">{primaryAction.eyebrow}</Badge>
                    <h2 className="mt-3 text-lg font-semibold">
                        {primaryAction.title}
                    </h2>
                    <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                        {primaryAction.description}
                    </p>
                    {onboardingOpen ? (
                        <div className="mt-4 max-w-md">
                            <div className="flex items-center justify-between text-sm">
                                <span>Advisory brief progress</span>
                                <span className="text-muted-foreground">
                                    {progress.completed} of {progress.total}
                                </span>
                            </div>
                            <div
                                className="mt-2 h-2 rounded-full bg-muted"
                                role="progressbar"
                                aria-valuenow={progress.percentage}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label="Standard Advisory onboarding completion"
                            >
                                <div
                                    className="h-2 rounded-full bg-[var(--fs-admiralty)]"
                                    style={{ width: `${progress.percentage}%` }}
                                />
                            </div>
                        </div>
                    ) : null}
                    <div className="mt-5 flex flex-wrap gap-3">
                        <Button asChild>
                            <Link href={primaryAction.href}>
                                {primaryAction.label}
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={messagesUrl}>
                                <MessageSquare
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Messages
                                {messageSummary.unread_count > 0
                                    ? ` (${messageSummary.unread_count})`
                                    : ''}
                            </Link>
                        </Button>
                    </div>
                </section>
                {onboardingOpen ? (
                    <section className="rounded-md border bg-background p-4">
                        <h2 className="text-sm font-medium">
                            Your advisory path
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            One short step at a time. Only the next step needs
                            your attention now.
                        </p>
                        <ol className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            {stages.map((stage, index) => {
                                const complete = index < progress.completed;
                                const active =
                                    !complete && index === progress.completed;

                                return (
                                    <li
                                        key={stage}
                                        className={cn(
                                            'flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
                                            complete
                                                ? 'border-emerald-200 bg-emerald-50/60'
                                                : active
                                                  ? 'border-primary bg-primary/5'
                                                  : 'bg-muted/20 text-muted-foreground',
                                        )}
                                    >
                                        {complete ? (
                                            <CheckCircle2
                                                className="size-4 text-emerald-700"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <span className="flex size-4 items-center justify-center rounded-full border text-[10px]">
                                                {index + 1}
                                            </span>
                                        )}
                                        <span>{stage}</span>
                                        {active ? (
                                            <Badge
                                                variant="secondary"
                                                className="ml-auto"
                                            >
                                                Next
                                            </Badge>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ol>
                    </section>
                ) : null}
                {planBudgetOption || planBudgetActivation ? (
                    <section className="flex flex-col gap-4 rounded-md border bg-background p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <FileSpreadsheet
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                <h2 className="text-sm font-medium">
                                    Business Plan & Budget
                                </h2>
                                <Badge variant="secondary">
                                    {planBudgetActivation?.status_label ??
                                        'Optional service'}
                                </Badge>
                            </div>
                            <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                                {planBudgetActivation
                                    ? planBudgetActivation.workspace_url
                                        ? 'Your approved Business Plan & Budget workspace is ready.'
                                        : 'Your request is with FSA. We will confirm the package, scope, and fee before any charge or access.'
                                    : 'Need a formal business plan, budget, or funding view? Request this optional service without interrupting your advisory onboarding.'}
                            </p>
                        </div>
                        {planBudgetActivation?.workspace_url ||
                        planBudgetActivation?.url ||
                        planBudgetOption?.start_url ? (
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={
                                        planBudgetActivation?.workspace_url ??
                                        planBudgetActivation?.url ??
                                        planBudgetOption?.start_url ??
                                        '#'
                                    }
                                >
                                    {planBudgetActivation
                                        ? planBudgetActivation.workspace_url
                                            ? 'Open workspace'
                                            : 'Review request'
                                        : 'Request access'}
                                </Link>
                            </Button>
                        ) : null}
                    </section>
                ) : null}
                {strategicPlan ? (
                    <section className="rounded-md border bg-background p-4">
                        <div className="flex items-center gap-2">
                            <Target
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <h2 className="text-sm font-medium">
                                Implementation plan
                            </h2>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Your {strategicPlan.duration_label.toLowerCase()}{' '}
                            implementation plan turns the agreed advisory work
                            into 90, 180, and 270-day priorities.
                        </p>
                        <div className="mt-4 grid gap-3 sm:grid-cols-3">
                            <AdvisoryFact
                                label="Plan progress"
                                value={`${strategicPlan.progress_percent}%`}
                                detail="Agreed milestones"
                            />
                            <AdvisoryFact
                                label="Completed"
                                value={`${strategicPlan.completed_milestones}/${strategicPlan.total_milestones}`}
                                detail="Implementation milestones"
                            />
                            <AdvisoryFact
                                label="Current horizon"
                                value={strategicPlan.duration_label}
                                detail={strategicPlan.complexity_label}
                            />
                        </div>
                    </section>
                ) : null}
            </main>
        </>
    );
}

function AdvisoryFact({
    label,
    value,
    detail,
}: {
    label: string;
    value: ReactNode;
    detail?: string;
}) {
    return (
        <article className="rounded-md border p-3">
            <div className="text-xs font-medium tracking-normal text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-2 text-sm font-medium">{value}</div>
            {detail ? (
                <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                    {detail}
                </p>
            ) : null}
        </article>
    );
}
