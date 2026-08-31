import { Link, router, useForm } from '@inertiajs/react';
import {
    Ban,
    CreditCard,
    FileCheck2,
    FileText,
    ListChecks,
    Mail,
    PlusCircle,
    PlugZap,
    RotateCcw,
    Send,
    ShieldAlert,
    Unplug,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Metric,
    formatDate,
    formatLabel,
    formatMetric,
    formatMoney,
    paymentStatusVariant,
} from './client-detail-presenters';
import type {
    ClientDetail,
    PaymentSummary,
    StrategicPlanForm,
    StrategicPlanSection,
    StrategicPlanSummary,
} from './client-detail-types';
import type { StrategicPlanDeploymentGuard } from './service-workspaces';
export function AccountingConnectionsPanel({
    client,
}: {
    client: ClientDetail;
}) {
    const pullSnapshot = (url: string) => {
        router.post(url, {}, { preserveScroll: true });
    };

    const revokeConnection = (url: string) => {
        router.patch(url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-accounting"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <PlugZap className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">
                        Accounting connections
                    </h2>
                </div>
                <Badge variant="outline">
                    {
                        client.accounting.connections.filter(
                            (connection) => connection.connected,
                        ).length
                    }
                </Badge>
            </div>

            <div className="flex flex-wrap gap-2">
                {client.accounting.providers.map((provider) => (
                    <Button
                        key={provider.provider}
                        asChild
                        size="sm"
                        variant={provider.connected ? 'outline' : 'default'}
                    >
                        <Link href={provider.connect_url}>
                            <PlugZap className="size-4" aria-hidden="true" />
                            {provider.connected ? 'Reconnect' : 'Connect'}{' '}
                            {provider.label}
                        </Link>
                    </Button>
                ))}
            </div>

            {client.accounting.connections.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No accounting connections yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.accounting.connections.map((connection) => {
                        const noReportData =
                            connection.latest_snapshot?.source_badge ===
                            'live_no_data';

                        return (
                            <article
                                key={connection.id}
                                className="space-y-3 rounded-md border p-3"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="text-sm font-medium">
                                                {connection.provider_label}
                                            </h3>
                                            <Badge
                                                variant={
                                                    connection.connected
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {formatLabel(connection.status)}
                                            </Badge>
                                            {connection.latest_snapshot && (
                                                <Badge variant="outline">
                                                    {formatLabel(
                                                        connection
                                                            .latest_snapshot
                                                            .source_badge,
                                                    )}
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {connection.external_tenant_id ??
                                                '-'}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={!connection.connected}
                                            onClick={() =>
                                                pullSnapshot(
                                                    connection.pull_url,
                                                )
                                            }
                                        >
                                            <RotateCcw
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Pull
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={!connection.connected}
                                            onClick={() =>
                                                revokeConnection(
                                                    connection.revoke_url,
                                                )
                                            }
                                        >
                                            <Unplug
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Revoke
                                        </Button>
                                    </div>
                                </div>

                                <dl className="grid gap-2 text-sm md:grid-cols-3">
                                    <Metric
                                        label="Connected"
                                        value={formatDate(
                                            connection.connected_at,
                                        )}
                                    />
                                    <Metric
                                        label="Last pull"
                                        value={formatDate(
                                            connection.last_snapshot_at,
                                        )}
                                    />
                                    <Metric
                                        label="Period"
                                        value={
                                            connection.latest_snapshot
                                                ?.period_end ?? '-'
                                        }
                                    />
                                </dl>

                                {noReportData ? (
                                    <div className="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                        <ShieldAlert
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <p>
                                            Xero authorised successfully, but no
                                            report activity was found in the
                                            connected organisation. Add or
                                            publish accounting activity in Xero,
                                            or connect an organisation with
                                            trading data, then pull again.
                                        </p>
                                    </div>
                                ) : (
                                    connection.latest_snapshot && (
                                        <div className="flex flex-wrap gap-2">
                                            {Object.entries(
                                                connection.latest_snapshot
                                                    .metrics,
                                            )
                                                .slice(0, 4)
                                                .map(([metric, value]) => (
                                                    <Badge
                                                        key={metric}
                                                        variant="secondary"
                                                    >
                                                        {formatLabel(metric)}:{' '}
                                                        {formatMetric(value)}
                                                    </Badge>
                                                ))}
                                        </div>
                                    )
                                )}
                            </article>
                        );
                    })}
                </div>
            )}
        </section>
    );
}

export function PaymentsPanel({ client }: { client: ClientDetail }) {
    const retryPayment = (payment: PaymentSummary) => {
        if (!payment.manual_retry_available) {
            return;
        }

        router.post(payment.retry_url, {}, { preserveScroll: true });
    };

    return (
        <section
            id="section-payments"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <CreditCard className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Payment exceptions</h2>
                </div>
                <Badge
                    variant={
                        client.payments.length > 0 ? 'secondary' : 'outline'
                    }
                >
                    {client.payments.length} open
                </Badge>
            </div>

            {client.payments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No failed or retrying payments. Successful payments are
                    hidden here so this stays focused on advisor action.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.payments.map((payment) => (
                        <article
                            key={payment.id}
                            id={payment.id}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-medium">
                                            {formatMoney(
                                                payment.amount,
                                                payment.currency,
                                            )}
                                        </h3>
                                        <Badge
                                            variant={paymentStatusVariant(
                                                payment.status,
                                            )}
                                        >
                                            {formatLabel(payment.status)}
                                        </Badge>
                                        <Badge variant="outline">
                                            Attempt {payment.attempt}
                                        </Badge>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Processed{' '}
                                        {formatDate(payment.processed_at)}
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={payment.contact_url}>
                                            <Mail
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Contact
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            !payment.manual_retry_available
                                        }
                                        onClick={() => retryPayment(payment)}
                                    >
                                        <RotateCcw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Retry
                                    </Button>
                                </div>
                            </div>

                            <dl className="grid gap-2 text-sm md:grid-cols-3">
                                <Metric
                                    label="Amount"
                                    value={formatMoney(
                                        payment.amount,
                                        payment.currency,
                                    )}
                                />
                                <Metric
                                    label="Next retry"
                                    value={formatDate(
                                        payment.automatic_next_retry_at,
                                    )}
                                />
                                <Metric
                                    label="Retry"
                                    value={
                                        payment.manual_retry_available
                                            ? 'available'
                                            : 'unavailable'
                                    }
                                />
                            </dl>

                            {payment.failed_reason && (
                                <div className="rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
                                    {payment.failed_reason}
                                </div>
                            )}
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

export function StrategicPlanPanel({
    plan,
    deploymentGuard,
}: {
    plan: StrategicPlanSummary | null;
    deploymentGuard: StrategicPlanDeploymentGuard;
}) {
    const [deploying, setDeploying] = useState(false);

    if (!plan) {
        return null;
    }

    return (
        <StrategicPlanEditor
            plan={plan}
            deploymentGuard={deploymentGuard}
            deploying={deploying}
            setDeploying={setDeploying}
        />
    );
}

export function StrategicPlanEditor({
    plan,
    deploymentGuard,
    deploying,
    setDeploying,
}: {
    plan: StrategicPlanSummary;
    deploymentGuard: StrategicPlanDeploymentGuard;
    deploying: boolean;
    setDeploying: (deploying: boolean) => void;
}) {
    const deployed = plan.status === 'deployed';
    const canDeploy = !deployed && deploymentGuard.allowed;
    const form = useForm<StrategicPlanForm>({
        summary: plan.summary ?? '',
        sections: plan.sections,
        milestones: plan.milestones.map((milestone) => ({
            ...milestone,
            description: milestone.description ?? '',
            advisor_notes: milestone.advisor_notes ?? '',
        })),
    });

    const save = () => {
        form.patch(plan.update_url, {
            preserveScroll: true,
            onSuccess: () => toast.success('Strategic plan saved.'),
        });
    };

    const deploy = () => {
        if (!canDeploy) {
            return;
        }

        router.patch(
            plan.deploy_url,
            {},
            {
                preserveScroll: true,
                onStart: () => setDeploying(true),
                onFinish: () => setDeploying(false),
                onSuccess: () => toast.success('Strategic plan deployed.'),
            },
        );
    };

    const updateSection = (
        index: number,
        field: keyof StrategicPlanSection,
        value: string,
    ) => {
        form.setData(
            'sections',
            form.data.sections.map((section, current) =>
                current === index ? { ...section, [field]: value } : section,
            ),
        );
    };

    const updateMilestone = (
        index: number,
        field: keyof StrategicPlanForm['milestones'][number],
        value: string | number,
    ) => {
        form.setData(
            'milestones',
            form.data.milestones.map((milestone, current) =>
                current === index
                    ? {
                          ...milestone,
                          [field]: value,
                      }
                    : milestone,
            ),
        );
    };

    const addMilestone = () => {
        form.setData('milestones', [
            ...form.data.milestones,
            {
                id: '',
                title: '',
                description: '',
                owner: 'joint',
                owner_label: 'Joint',
                due_offset_days: 30,
                due_date: null,
                status: 'pending',
                status_label: 'Pending',
                progress_percent: 0,
                evidence_notes: '',
                advisor_notes: '',
            },
        ]);
    };

    const removeMilestone = (index: number) => {
        form.setData(
            'milestones',
            form.data.milestones.filter(
                (_milestone, current) => current !== index,
            ),
        );
    };

    return (
        <section
            id="section-strategic-plan"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <ListChecks className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">Strategic Plan</h2>
                        <Badge variant={deployed ? 'secondary' : 'outline'}>
                            {plan.status_label}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Generated after proposal acceptance. Review with the
                        client and deploy milestones only after DD and Business
                        Plan &amp; Budget assessment are complete.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm" variant="outline">
                        <a href={plan.pdf_url} target="_blank" rel="noreferrer">
                            <FileText className="size-4" aria-hidden="true" />
                            View PDF
                        </a>
                    </Button>
                    {!deployed && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={form.processing}
                            onClick={save}
                        >
                            <FileCheck2 className="size-4" aria-hidden="true" />
                            Save draft
                        </Button>
                    )}
                    {(deployed || deploymentGuard.allowed) && (
                        <Button
                            type="button"
                            size="sm"
                            disabled={!canDeploy || deploying}
                            onClick={deploy}
                        >
                            <Send className="size-4" aria-hidden="true" />
                            {deployed
                                ? 'Deployed'
                                : deploying
                                  ? 'Deploying'
                                  : 'Deploy strategic plan'}
                        </Button>
                    )}
                </div>
            </div>

            {!deployed && !deploymentGuard.allowed && (
                <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    {deploymentGuard.message ??
                        'Strategic plan deployment is locked until the DD and Business Plan & Budget assessments are complete.'}
                </div>
            )}

            <div className="grid gap-3 md:grid-cols-5">
                <Metric label="Progress" value={`${plan.progress_percent}%`} />
                <Metric
                    label="Milestones"
                    value={`${plan.completed_milestones}/${plan.total_milestones}`}
                />
                <Metric
                    label="Duration"
                    value={plan.duration_label}
                    hint={plan.complexity_label}
                />
                <Metric
                    label="Generated"
                    value={formatDate(plan.generated_at)}
                />
                <Metric label="Deployed" value={formatDate(plan.deployed_at)} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="strategic_plan_summary">Summary</Label>
                <textarea
                    id="strategic_plan_summary"
                    value={form.data.summary}
                    disabled={deployed}
                    onChange={(event) =>
                        form.setData('summary', event.target.value)
                    }
                    rows={4}
                    className="min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                />
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                {form.data.sections.map((section, index) => (
                    <div
                        key={section.key}
                        className="space-y-2 rounded-md border p-3"
                    >
                        <Label
                            htmlFor={`strategic_plan_section_${section.key}`}
                        >
                            {section.title}
                        </Label>
                        <textarea
                            id={`strategic_plan_section_${section.key}`}
                            value={section.body}
                            disabled={deployed}
                            onChange={(event) =>
                                updateSection(index, 'body', event.target.value)
                            }
                            rows={5}
                            className="min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                        />
                    </div>
                ))}
            </div>

            <div className="space-y-3 rounded-md border p-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-medium">
                            Milestone tracker
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Due dates are set from the agreed start date.
                        </p>
                    </div>
                    {!deployed && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={addMilestone}
                        >
                            <PlusCircle className="size-4" aria-hidden="true" />
                            Add milestone
                        </Button>
                    )}
                </div>

                <div className="space-y-3">
                    {form.data.milestones.map((milestone, index) => (
                        <div
                            key={`${milestone.id}-${index}`}
                            className="grid gap-3 rounded-md bg-muted/30 p-3"
                        >
                            <div className="grid gap-2 lg:grid-cols-[minmax(0,1fr)_150px_150px_auto]">
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`strategic_milestone_title_${index}`}
                                    >
                                        Title
                                    </Label>
                                    <Input
                                        id={`strategic_milestone_title_${index}`}
                                        value={milestone.title}
                                        disabled={deployed}
                                        onChange={(event) =>
                                            updateMilestone(
                                                index,
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`strategic_milestone_owner_${index}`}
                                    >
                                        Owner
                                    </Label>
                                    <select
                                        id={`strategic_milestone_owner_${index}`}
                                        value={milestone.owner}
                                        disabled={deployed}
                                        onChange={(event) =>
                                            updateMilestone(
                                                index,
                                                'owner',
                                                event.target.value,
                                            )
                                        }
                                        className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                                    >
                                        <option value="client">Client</option>
                                        <option value="advisor">Advisor</option>
                                        <option value="joint">Joint</option>
                                    </select>
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`strategic_milestone_due_${index}`}
                                    >
                                        Due after
                                    </Label>
                                    <Input
                                        id={`strategic_milestone_due_${index}`}
                                        type="number"
                                        min={1}
                                        max={365}
                                        value={milestone.due_offset_days}
                                        disabled={deployed}
                                        onChange={(event) =>
                                            updateMilestone(
                                                index,
                                                'due_offset_days',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </div>
                                {!deployed && (
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        className="self-end"
                                        onClick={() => removeMilestone(index)}
                                    >
                                        <Ban
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        <span className="sr-only">
                                            Remove milestone
                                        </span>
                                    </Button>
                                )}
                            </div>
                            <textarea
                                value={milestone.description}
                                disabled={deployed}
                                onChange={(event) =>
                                    updateMilestone(
                                        index,
                                        'description',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                placeholder="Milestone description"
                                className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                            />
                            {deployed && (
                                <div className="grid gap-2 text-sm md:grid-cols-4">
                                    <Metric
                                        label="Status"
                                        value={milestone.status_label}
                                    />
                                    <Metric
                                        label="Progress"
                                        value={`${milestone.progress_percent}%`}
                                    />
                                    <Metric
                                        label="Due"
                                        value={formatDate(milestone.due_date)}
                                    />
                                    <Metric
                                        label="Owner"
                                        value={milestone.owner_label}
                                    />
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
