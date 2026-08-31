import { Link, router, useForm } from '@inertiajs/react';
import {
    Download,
    FileText,
    ListChecks,
    MessageSquare,
    RotateCcw,
    Send,
    ShieldAlert,
    TrendingUp,
    Undo2,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ConsentSelect } from './client-detail-knowledge';
import {
    Metric,
    formatCurrency,
    formatDate,
    formatLabel,
    formatMetric,
    proposalStatusVariant,
} from './client-detail-presenters';
import type {
    ClientDetail,
    ProposalForm,
    ProposalSummary,
} from './client-detail-types';
import type { StrategicBudgetSummary } from './service-workspaces';
export function AdvisoryServiceAccessPanel({
    client,
    budget,
}: {
    client: ClientDetail;
    budget: StrategicBudgetSummary;
}) {
    const ddReportReviewed = client.due_diligence?.assessment_ready ?? false;
    const planBudgetApproved = [
        'advisor_approved',
        'used_in_proposal',
        'accepted_proposal_snapshot',
    ].includes(budget.status);
    const blockers = [
        !ddReportReviewed ? 'Review and release the DD report.' : null,
        !planBudgetApproved
            ? 'Approve the Business Plan & Budget assessment.'
            : null,
    ].filter((item): item is string => item !== null);
    const ready = blockers.length === 0;

    return (
        <section
            id="section-advisory-service-access"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <TrendingUp className="size-4" aria-hidden="true" />
                        <h2 className="text-sm font-medium">
                            Advisory service access
                        </h2>
                        <Badge variant={ready ? 'secondary' : 'outline'}>
                            {ready ? 'Ready to scope' : 'After approvals'}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Strategic planning is not part of the DD workspace. Once
                        DD and the Business Plan &amp; Budget are approved, use
                        this handoff to confirm whether the client wants an
                        advisory service proposal.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link href={`/advisor/clients/${client.id}/messages`}>
                            <MessageSquare
                                className="size-4"
                                aria-hidden="true"
                            />
                            Message client
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <a href="#section-proposals">
                            <FileText className="size-4" aria-hidden="true" />
                            Review proposals
                        </a>
                    </Button>
                </div>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                <Metric
                    label="Due Diligence"
                    value={ddReportReviewed ? 'Assessed' : 'Report review due'}
                />
                <Metric
                    label="Business Plan & Budget"
                    value={
                        planBudgetApproved
                            ? 'Assessment approved'
                            : budget.status_label
                    }
                />
            </div>

            {ready ? (
                <div className="rounded-md border bg-emerald-50 p-3 text-sm text-emerald-950">
                    DD and BP&amp;B are ready. Confirm the advisory scope,
                    service fee, and proposal with the client before opening the
                    advisory service path.
                </div>
            ) : (
                <div className="space-y-2 rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                    <p>Finish these items before requesting advisory access:</p>
                    <ul className="grid gap-1">
                        {blockers.map((blocker) => (
                            <li key={blocker} className="flex gap-2">
                                <span className="mt-2 size-1.5 shrink-0 rounded-full bg-foreground/60" />
                                <span>{blocker}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}

export function ProposalsPanel({ client }: { client: ClientDetail }) {
    const form = useForm<ProposalForm>({
        fee_calculation_id: client.fee_calculations[0]?.id ?? '',
        scope_summary: client.fee_calculations[0]?.proposal_scope_summary ?? '',
        insurance_consent: 'undecided',
        coach_consent: 'undecided',
        budget_override_category: '',
        budget_override_notes: '',
    });

    const submit = () => {
        form.post(client.proposal_store_url, {
            preserveScroll: true,
            onSuccess: () =>
                form.setData({
                    fee_calculation_id: '',
                    scope_summary: '',
                    insurance_consent: 'undecided',
                    coach_consent: 'undecided',
                    budget_override_category: '',
                    budget_override_notes: '',
                }),
        });
    };
    const selectedCalculation = client.fee_calculations.find(
        (calculation) => calculation.id === form.data.fee_calculation_id,
    );
    const requiresReferralConsents =
        selectedCalculation?.method !== 'integration';

    const release = (proposal: ProposalSummary) => {
        router.patch(
            proposal.release_url,
            { expiry_days: client.proposal_expiry_days },
            { preserveScroll: true },
        );
    };

    const recall = (proposal: ProposalSummary) => {
        router.patch(proposal.recall_url, {}, { preserveScroll: true });
    };

    const renew = (proposal: ProposalSummary) => {
        router.patch(proposal.renew_url, {}, { preserveScroll: true });
    };

    const generateStrategicPlan = (proposal: ProposalSummary) => {
        if (!proposal.strategic_plan_generate_url) {
            return;
        }

        router.post(
            proposal.strategic_plan_generate_url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <section
            id="section-proposals"
            className="space-y-4 rounded-md border p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FileText className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Proposals</h2>
                </div>
                <Badge variant="outline">{client.proposals.length}</Badge>
            </div>

            {client.fee_calculations.length > 0 ? (
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(220px,0.45fr)]">
                    <div className="grid gap-2">
                        <Label htmlFor="proposal_scope">Scope</Label>
                        <textarea
                            id="proposal_scope"
                            value={form.data.scope_summary}
                            onChange={(event) =>
                                form.setData(
                                    'scope_summary',
                                    event.target.value,
                                )
                            }
                            rows={3}
                            className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                        <InputError message={form.errors.scope_summary} />
                    </div>

                    <div className="grid gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor="proposal_fee">Fee ex GST</Label>
                            <select
                                id="proposal_fee"
                                value={form.data.fee_calculation_id}
                                onChange={(event) => {
                                    const feeCalculationId = event.target.value;
                                    const calculation =
                                        client.fee_calculations.find(
                                            (item) =>
                                                item.id === feeCalculationId,
                                        );

                                    form.setData((data) => ({
                                        ...data,
                                        fee_calculation_id: feeCalculationId,
                                        scope_summary:
                                            calculation?.proposal_scope_summary ??
                                            '',
                                    }));
                                }}
                                className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {client.fee_calculations.map((calculation) => (
                                    <option
                                        key={calculation.id}
                                        value={calculation.id}
                                    >
                                        {formatLabel(calculation.method)} -{' '}
                                        {formatCurrency(
                                            calculation.suggested_mid,
                                        )}{' '}
                                        ex GST
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.fee_calculation_id}
                            />
                        </div>

                        {selectedCalculation ? (
                            <div className="rounded-md border bg-muted/30 p-3">
                                <div className="text-xs text-muted-foreground">
                                    Strategic plan
                                </div>
                                <div className="mt-1 text-sm font-medium">
                                    {
                                        selectedCalculation.strategic_plan_duration_label
                                    }{' '}
                                    /{' '}
                                    {
                                        selectedCalculation.strategic_plan_complexity_label
                                    }
                                </div>
                            </div>
                        ) : null}

                        {requiresReferralConsents ? (
                            <div className="grid grid-cols-2 gap-3">
                                <ConsentSelect
                                    id="insurance_consent"
                                    label="Insurance"
                                    value={form.data.insurance_consent}
                                    error={form.errors.insurance_consent}
                                    onChange={(value) =>
                                        form.setData('insurance_consent', value)
                                    }
                                />
                                <ConsentSelect
                                    id="coach_consent"
                                    label="Coach"
                                    value={form.data.coach_consent}
                                    error={form.errors.coach_consent}
                                    onChange={(value) =>
                                        form.setData('coach_consent', value)
                                    }
                                />
                            </div>
                        ) : null}

                        <Button
                            type="button"
                            disabled={
                                form.processing ||
                                form.data.fee_calculation_id === ''
                            }
                            onClick={submit}
                        >
                            <FileText className="size-4" aria-hidden="true" />
                            Generate
                        </Button>
                    </div>

                    {!client.proposal_budget_guard.approved && (
                        <div className="space-y-3 rounded-md border bg-muted/30 p-3 lg:col-span-2">
                            <div className="flex flex-wrap items-start gap-2">
                                <ShieldAlert
                                    className="mt-0.5 size-4"
                                    aria-hidden="true"
                                />
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Budget readiness acknowledgement
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {client.proposal_budget_guard.warning ??
                                            'The Business Plan & Budget has not been advisor approved. This can affect package selection, fee level, payment terms, affordability checks, and proposal confidence.'}
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-3 md:grid-cols-[minmax(180px,0.35fr)_minmax(0,1fr)]">
                                <div className="grid gap-2">
                                    <Label htmlFor="budget_override_category">
                                        Reason category
                                    </Label>
                                    <select
                                        id="budget_override_category"
                                        value={
                                            form.data.budget_override_category
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'budget_override_category',
                                                event.target.value,
                                            )
                                        }
                                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="">Select reason</option>
                                        <option value="client_urgency">
                                            Client urgency
                                        </option>
                                        <option value="limited_financials">
                                            Limited financials
                                        </option>
                                        <option value="preliminary_budget">
                                            Preliminary budget sufficient
                                        </option>
                                        <option value="advisor_judgement">
                                            Advisor judgement
                                        </option>
                                        <option value="other">Other</option>
                                    </select>
                                    <InputError
                                        message={
                                            form.errors.budget_override_category
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="budget_override_notes">
                                        Advisor notes
                                    </Label>
                                    <textarea
                                        id="budget_override_notes"
                                        value={form.data.budget_override_notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'budget_override_notes',
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                        className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                    <InputError
                                        message={
                                            form.errors.budget_override_notes
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    No fee calculations are awaiting a proposal.
                </p>
            )}

            {client.proposals.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No proposals yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {client.proposals.map((proposal) => (
                        <article
                            key={proposal.id}
                            className="space-y-3 rounded-md border p-3"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-medium">
                                            Proposal v{proposal.version}
                                        </h3>
                                        <Badge
                                            variant={proposalStatusVariant(
                                                proposal.status,
                                            )}
                                        >
                                            {proposal.status_label}
                                        </Badge>
                                        <Badge variant="outline">
                                            {proposal.fee_method_label}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {
                                                proposal.strategic_plan_duration_label
                                            }
                                        </Badge>
                                        {proposal.days_to_expiry !== null && (
                                            <Badge variant="outline">
                                                {proposal.days_to_expiry}d
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatCurrency(
                                            proposal.suggested_mid ?? 0,
                                        )}{' '}
                                        mid fee ex GST /{' '}
                                        {
                                            proposal.strategic_plan_complexity_label
                                        }
                                    </div>
                                    <p className="max-w-3xl text-sm leading-5 text-muted-foreground">
                                        {proposal.brief}
                                    </p>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    {proposal.view_url && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={proposal.view_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Download
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                                View
                                            </a>
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!proposal.can_release}
                                        onClick={() => release(proposal)}
                                    >
                                        <Send
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Release to client
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!proposal.can_recall}
                                        onClick={() => recall(proposal)}
                                    >
                                        <Undo2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Recall
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!proposal.can_renew}
                                        onClick={() => renew(proposal)}
                                    >
                                        <RotateCcw
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Renew
                                    </Button>
                                    {proposal.strategic_plan_generate_url && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() =>
                                                generateStrategicPlan(proposal)
                                            }
                                        >
                                            <ListChecks
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                            Generate strategic plan
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <dl className="grid gap-2 text-sm md:grid-cols-4">
                                <Metric
                                    label="Released"
                                    value={formatDate(proposal.released_at)}
                                />
                                <Metric
                                    label="Expires"
                                    value={formatDate(proposal.expires_at)}
                                />
                                <Metric
                                    label="ROI"
                                    value={formatMetric(proposal.roi_ratio)}
                                />
                                <Metric
                                    label="Plan duration"
                                    value={
                                        proposal.strategic_plan_duration_label
                                    }
                                />
                            </dl>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}
