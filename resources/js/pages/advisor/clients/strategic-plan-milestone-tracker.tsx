import { router } from '@inertiajs/react';
import { Ban, FileCheck2, PlusCircle } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Metric, formatDate } from './client-detail-presenters';
import type { StrategicPlanForm } from './strategic-plan-types';

type Milestones = StrategicPlanForm['milestones'];
type Milestone = Milestones[number];

type Props = {
    deployed: boolean;
    milestones: Milestones;
    onMilestonesChange: (milestones: Milestones) => void;
};

export function StrategicPlanMilestoneTracker({
    deployed,
    milestones,
    onMilestonesChange,
}: Props) {
    const updateMilestone = (
        index: number,
        field: keyof Milestone,
        value: string | number,
    ) => {
        onMilestonesChange(
            milestones.map((milestone, current) =>
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
        onMilestonesChange([
            ...milestones,
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
                metric_label: '',
                measurement_unit: '',
                target_direction: 'increase',
                baseline_value: '',
                target_value: '',
                actual_value: '',
                measurement_updated_at: null,
            },
        ]);
    };

    const saveOutcome = (milestone: Milestone) => {
        if (!milestone.outcome_update_url) {
            return;
        }

        router.patch(
            milestone.outcome_update_url,
            {
                metric_label: milestone.metric_label,
                measurement_unit: milestone.measurement_unit,
                target_direction: milestone.target_direction,
                baseline_value: milestone.baseline_value,
                target_value: milestone.target_value,
                actual_value: milestone.actual_value,
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Outcome measurement saved.'),
            },
        );
    };

    return (
        <div className="space-y-3 rounded-md border p-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-sm font-medium">Milestone tracker</h3>
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
                {milestones.map((milestone, index) => (
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
                                    onClick={() =>
                                        onMilestonesChange(
                                            milestones.filter(
                                                (_milestone, current) =>
                                                    current !== index,
                                            ),
                                        )
                                    }
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
                        <div className="grid gap-2 rounded-md border bg-background p-3 md:grid-cols-3">
                            <div className="grid gap-1 md:col-span-2">
                                <Label
                                    htmlFor={`strategic_milestone_metric_${index}`}
                                >
                                    Outcome measure
                                </Label>
                                <Input
                                    id={`strategic_milestone_metric_${index}`}
                                    value={milestone.metric_label}
                                    disabled={
                                        deployed &&
                                        !milestone.outcome_update_url
                                    }
                                    placeholder="e.g. Active retainers"
                                    onChange={(event) =>
                                        updateMilestone(
                                            index,
                                            'metric_label',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`strategic_milestone_unit_${index}`}
                                >
                                    Unit
                                </Label>
                                <Input
                                    id={`strategic_milestone_unit_${index}`}
                                    value={milestone.measurement_unit}
                                    disabled={
                                        deployed &&
                                        !milestone.outcome_update_url
                                    }
                                    placeholder="customers"
                                    onChange={(event) =>
                                        updateMilestone(
                                            index,
                                            'measurement_unit',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor={`strategic_milestone_direction_${index}`}
                                >
                                    Target direction
                                </Label>
                                <select
                                    id={`strategic_milestone_direction_${index}`}
                                    value={milestone.target_direction}
                                    disabled={
                                        deployed &&
                                        !milestone.outcome_update_url
                                    }
                                    onChange={(event) =>
                                        updateMilestone(
                                            index,
                                            'target_direction',
                                            event.target.value,
                                        )
                                    }
                                    className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                                >
                                    <option value="increase">Increase</option>
                                    <option value="decrease">Decrease</option>
                                </select>
                            </div>
                            <MeasurementInput
                                id={`strategic_milestone_baseline_${index}`}
                                label="Baseline"
                                value={milestone.baseline_value}
                                disabled={
                                    deployed && !milestone.outcome_update_url
                                }
                                onChange={(value) =>
                                    updateMilestone(
                                        index,
                                        'baseline_value',
                                        value,
                                    )
                                }
                            />
                            <MeasurementInput
                                id={`strategic_milestone_target_${index}`}
                                label="Target"
                                value={milestone.target_value}
                                disabled={
                                    deployed && !milestone.outcome_update_url
                                }
                                onChange={(value) =>
                                    updateMilestone(
                                        index,
                                        'target_value',
                                        value,
                                    )
                                }
                            />
                            <MeasurementInput
                                id={`strategic_milestone_actual_${index}`}
                                label="Actual"
                                value={milestone.actual_value}
                                disabled={
                                    deployed && !milestone.outcome_update_url
                                }
                                onChange={(value) =>
                                    updateMilestone(
                                        index,
                                        'actual_value',
                                        value,
                                    )
                                }
                            />
                            {deployed && milestone.outcome_update_url && (
                                <div className="flex items-end md:col-span-3">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => saveOutcome(milestone)}
                                    >
                                        <FileCheck2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Save outcome measurement
                                    </Button>
                                </div>
                            )}
                        </div>
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
    );
}

function MeasurementInput({
    id,
    label,
    value,
    disabled,
    onChange,
}: {
    id: string;
    label: string;
    value: number | '';
    disabled: boolean;
    onChange: (value: number | '') => void;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                value={value}
                disabled={disabled}
                onChange={(event) =>
                    onChange(
                        event.target.value === ''
                            ? ''
                            : Number(event.target.value),
                    )
                }
            />
        </div>
    );
}
