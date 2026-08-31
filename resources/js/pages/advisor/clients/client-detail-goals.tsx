import { router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ListChecks,
    PlusCircle,
    RotateCcw,
    Target,
    Upload,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import FileDropzone from '@/components/file-dropzone';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Metric,
    formatCurrency,
    formatDate,
    formatLabel,
    formatPercent,
    goalStatusVariant,
    milestoneStatusVariant,
    nullableNumber,
    proofStatusVariant,
} from './client-detail-presenters';
import type {
    ClientDetail,
    GoalForm,
    GoalSummary,
    MilestoneActionForm,
    MilestoneForm,
    MilestoneSummary,
    ProofForm,
} from './client-detail-types';
export function GoalsPanel({ client }: { client: ClientDetail }) {
    const form = useForm<GoalForm>({
        title: '',
        description: '',
        annual_benefit: null,
        duration_years: 1,
        pv_target: null,
        target_date: '',
        target_growth_percent: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(client.goal_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <section id="section-goals" className="space-y-4 rounded-md border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Target className="size-4" aria-hidden="true" />
                    <h2 className="text-sm font-medium">Goals</h2>
                    <Badge variant="outline">
                        {client.goals.active_goals} active
                    </Badge>
                </div>
                <div className="text-sm font-medium">
                    {formatCurrency(client.goals.pv_realised_total)} realised
                </div>
            </div>

            <form onSubmit={submit} className="grid gap-4 lg:grid-cols-6">
                <div className="grid gap-2 lg:col-span-2">
                    <Label htmlFor="goal_title">Goal</Label>
                    <input
                        id="goal_title"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.title} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="goal_pv_target">Target PV</Label>
                    <input
                        id="goal_pv_target"
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.data.pv_target ?? ''}
                        onChange={(event) =>
                            form.setData(
                                'pv_target',
                                nullableNumber(event.target.value),
                            )
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.pv_target} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="goal_benefit">Annual benefit</Label>
                    <input
                        id="goal_benefit"
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.data.annual_benefit ?? ''}
                        onChange={(event) =>
                            form.setData(
                                'annual_benefit',
                                nullableNumber(event.target.value),
                            )
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.annual_benefit} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="goal_duration">Benefit years</Label>
                    <input
                        id="goal_duration"
                        type="number"
                        min={1}
                        max={10}
                        value={form.data.duration_years}
                        onChange={(event) =>
                            form.setData(
                                'duration_years',
                                Number(event.target.value),
                            )
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.duration_years} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="goal_target_date">Target date</Label>
                    <input
                        id="goal_target_date"
                        type="date"
                        value={form.data.target_date}
                        onChange={(event) =>
                            form.setData('target_date', event.target.value)
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.target_date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="goal_growth_percent">Growth %</Label>
                    <input
                        id="goal_growth_percent"
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.data.target_growth_percent ?? ''}
                        onChange={(event) =>
                            form.setData(
                                'target_growth_percent',
                                nullableNumber(event.target.value),
                            )
                        }
                        className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.target_growth_percent} />
                </div>
                <div className="flex items-end">
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="w-full"
                    >
                        <PlusCircle className="size-4" aria-hidden="true" />
                        Add goal
                    </Button>
                </div>
                <div className="grid gap-2 lg:col-span-6">
                    <Label htmlFor="goal_description">Description</Label>
                    <textarea
                        id="goal_description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        rows={2}
                        className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError message={form.errors.description} />
                </div>
            </form>

            {client.goals.goals.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No goals recorded yet.
                </p>
            ) : (
                <div className="space-y-4">
                    {client.goals.goals.map((goal) => (
                        <GoalRow key={goal.id} goal={goal} />
                    ))}
                </div>
            )}
        </section>
    );
}

export function GoalRow({ goal }: { goal: GoalSummary }) {
    const remeasureGoal = () => {
        if (!goal.remeasure_url) {
            return;
        }

        router.post(
            goal.remeasure_url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Goal PV re-measured.'),
                onError: () => toast.error('Goal PV could not be re-measured.'),
            },
        );
    };
    const markAchieved = () => {
        if (!goal.achieve_url) {
            return;
        }

        router.patch(
            goal.achieve_url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Goal marked achieved.'),
                onError: () =>
                    toast.error('Goal could not be marked achieved.'),
            },
        );
    };

    return (
        <article className="space-y-4 rounded-md border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-medium">{goal.title}</h3>
                        <Badge variant={goalStatusVariant(goal.status)}>
                            {formatLabel(goal.status)}
                        </Badge>
                    </div>
                    {goal.description && (
                        <p className="text-sm text-muted-foreground">
                            {goal.description}
                        </p>
                    )}
                </div>
                <div className="text-right">
                    <div className="text-sm font-medium">
                        {formatCurrency(goal.pv_target)}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Target{' '}
                        {goal.target_date
                            ? formatDate(goal.target_date)
                            : 'date not set'}
                    </div>
                </div>
            </div>

            <div className="grid gap-2 rounded-md border bg-muted/20 p-3 text-sm sm:grid-cols-2 xl:grid-cols-5">
                <Metric
                    label="Baseline PV"
                    value={
                        goal.measurement.baseline_pv === null
                            ? '-'
                            : formatCurrency(goal.measurement.baseline_pv)
                    }
                    hint={formatDate(goal.measurement.baseline_as_at)}
                />
                <Metric
                    label="Current PV"
                    value={
                        goal.measurement.current_pv === null
                            ? '-'
                            : formatCurrency(goal.measurement.current_pv)
                    }
                    hint={formatDate(goal.measurement.current_as_at)}
                />
                <Metric
                    label="Movement"
                    value={
                        goal.measurement.pv_movement === null
                            ? '-'
                            : formatCurrency(goal.measurement.pv_movement)
                    }
                    hint={`${formatCurrency(goal.measurement.realised_pv)} verified`}
                />
                <Metric
                    label="To target"
                    value={
                        goal.measurement.target_gap === null
                            ? '-'
                            : formatCurrency(goal.measurement.target_gap)
                    }
                    hint={
                        goal.measurement.progress_percent === null
                            ? 'progress pending'
                            : `${formatPercent(goal.measurement.progress_percent)} progress`
                    }
                />
                <Metric
                    label="Evidence bridge"
                    value={
                        goal.measurement.realised_explains_percent === null
                            ? '-'
                            : formatPercent(
                                  goal.measurement.realised_explains_percent,
                              )
                    }
                    hint="of PV movement explained"
                />
            </div>

            {goal.milestone_store_url && (
                <div className="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant={
                            goal.measurement.due_for_remeasurement
                                ? 'default'
                                : 'outline'
                        }
                        disabled={!goal.remeasure_url}
                        onClick={remeasureGoal}
                    >
                        <RotateCcw className="size-4" aria-hidden="true" />
                        Re-measure PV
                    </Button>
                    {goal.status !== 'achieved' && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!goal.achieve_url}
                            onClick={markAchieved}
                        >
                            <CheckCircle2
                                className="size-4"
                                aria-hidden="true"
                            />
                            Mark achieved
                        </Button>
                    )}
                </div>
            )}

            {goal.milestone_store_url && <MilestoneFormPanel goal={goal} />}

            {goal.milestones.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No milestones yet.
                </p>
            ) : (
                <div className="divide-y rounded-md border">
                    {goal.milestones.map((milestone) => (
                        <MilestoneRow
                            key={milestone.id}
                            milestone={milestone}
                        />
                    ))}
                </div>
            )}
        </article>
    );
}

export function MilestoneFormPanel({ goal }: { goal: GoalSummary }) {
    const form = useForm<MilestoneForm>({
        title: '',
        recommendation_ref: '',
        annual_impact: null,
        duration_years: 1,
        pv_of_impact: null,
        due_date: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!goal.milestone_store_url) {
            return;
        }

        form.post(goal.milestone_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 lg:grid-cols-6">
            <div className="grid gap-2 lg:col-span-2">
                <Label htmlFor={`milestone_title_${goal.id}`}>Milestone</Label>
                <input
                    id={`milestone_title_${goal.id}`}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`milestone_impact_${goal.id}`}>Impact</Label>
                <input
                    id={`milestone_impact_${goal.id}`}
                    type="number"
                    min={0}
                    step="0.01"
                    value={form.data.annual_impact ?? ''}
                    onChange={(event) =>
                        form.setData(
                            'annual_impact',
                            nullableNumber(event.target.value),
                        )
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.annual_impact} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`milestone_due_${goal.id}`}>Due</Label>
                <input
                    id={`milestone_due_${goal.id}`}
                    type="date"
                    value={form.data.due_date}
                    onChange={(event) =>
                        form.setData('due_date', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.due_date} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`milestone_ref_${goal.id}`}>Reference</Label>
                <input
                    id={`milestone_ref_${goal.id}`}
                    value={form.data.recommendation_ref}
                    onChange={(event) =>
                        form.setData('recommendation_ref', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.recommendation_ref} />
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                    className="w-full"
                >
                    <ListChecks className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
        </form>
    );
}

export function MilestoneRow({ milestone }: { milestone: MilestoneSummary }) {
    return (
        <article className="space-y-4 p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-medium">
                            {milestone.title}
                        </h4>
                        <Badge
                            variant={milestoneStatusVariant(milestone.status)}
                        >
                            {formatLabel(milestone.status)}
                        </Badge>
                        {milestone.proof_status && (
                            <Badge
                                variant={proofStatusVariant(
                                    milestone.proof_status,
                                )}
                            >
                                {formatLabel(milestone.proof_status)}
                            </Badge>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                        <span>{formatCurrency(milestone.pv_of_impact)}</span>
                        <span>{formatDate(milestone.due_date)}</span>
                        <span>{milestone.actions_count} actions</span>
                        {milestone.recommendation_ref && (
                            <span>{milestone.recommendation_ref}</span>
                        )}
                    </div>
                </div>
                <div className="text-xs text-muted-foreground">
                    {milestone.completed_at
                        ? `Completed ${formatDate(milestone.completed_at)}`
                        : 'Open'}
                </div>
            </div>

            <div className="grid gap-3 lg:grid-cols-2">
                {milestone.action_store_url && (
                    <MilestoneActionFormPanel milestone={milestone} />
                )}
                {milestone.proof_store_url && (
                    <ProofUploadFormPanel milestone={milestone} />
                )}
            </div>
        </article>
    );
}

export function MilestoneActionFormPanel({
    milestone,
}: {
    milestone: MilestoneSummary;
}) {
    const form = useForm<MilestoneActionForm>({
        title: '',
        due_date: '',
        priority: 'normal',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!milestone.action_store_url) {
            return;
        }

        form.post(milestone.action_store_url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
            <div className="grid gap-2 sm:col-span-3">
                <Label htmlFor={`action_title_${milestone.id}`}>Action</Label>
                <input
                    id={`action_title_${milestone.id}`}
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`action_due_${milestone.id}`}>Due</Label>
                <input
                    id={`action_due_${milestone.id}`}
                    type="date"
                    value={form.data.due_date}
                    onChange={(event) =>
                        form.setData('due_date', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.due_date} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`action_priority_${milestone.id}`}>
                    Priority
                </Label>
                <select
                    id={`action_priority_${milestone.id}`}
                    value={form.data.priority}
                    onChange={(event) =>
                        form.setData('priority', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
                <InputError message={form.errors.priority} />
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                    className="w-full"
                >
                    <PlusCircle className="size-4" aria-hidden="true" />
                    Add
                </Button>
            </div>
        </form>
    );
}

export function ProofUploadFormPanel({
    milestone,
}: {
    milestone: MilestoneSummary;
}) {
    const form = useForm<ProofForm>({
        proof: null,
        claim: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!milestone.proof_store_url) {
            return;
        }

        form.post(milestone.proof_store_url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
            <div className="grid gap-2 sm:col-span-3">
                <FileDropzone
                    id={`proof_file_${milestone.id}`}
                    files={form.data.proof ? [form.data.proof] : []}
                    label="Proof"
                    description="Drag proof here or browse"
                    onFilesChange={(files) =>
                        form.setData('proof', files[0] ?? null)
                    }
                />
                <InputError message={form.errors.proof} />
            </div>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`proof_claim_${milestone.id}`}>Claim</Label>
                <input
                    id={`proof_claim_${milestone.id}`}
                    value={form.data.claim}
                    onChange={(event) =>
                        form.setData('claim', event.target.value)
                    }
                    className="h-10 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={form.errors.claim} />
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                    className="w-full"
                >
                    <Upload className="size-4" aria-hidden="true" />
                    Upload
                </Button>
            </div>
        </form>
    );
}
