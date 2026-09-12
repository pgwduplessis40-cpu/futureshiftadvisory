import { Ban, PlusCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Metric } from './client-detail-presenters';
import type {
    StrategicPlanForm,
    StrategicPlanSummary,
} from './strategic-plan-types';

type EvidenceBindings = StrategicPlanForm['evidence_bindings'];

type Props = {
    plan: StrategicPlanSummary;
    deployed: boolean;
    sections: StrategicPlanForm['sections'];
    milestones: StrategicPlanForm['milestones'];
    evidenceBindings: EvidenceBindings;
    confirmCurrentSources: boolean;
    onEvidenceBindingsChange: (bindings: EvidenceBindings) => void;
    onConfirmCurrentSourcesChange: (confirmed: boolean) => void;
};

export function StrategicPlanEvidenceAlignment({
    plan,
    deployed,
    sections,
    milestones,
    evidenceBindings,
    confirmCurrentSources,
    onEvidenceBindingsChange,
    onConfirmCurrentSourcesChange,
}: Props) {
    const evidenceTargets = [
        ...sections.map((section) => ({
            key: `section:${section.key}`,
            label: section.title,
        })),
        ...milestones
            .filter((milestone) => milestone.id !== '')
            .map((milestone) => ({
                key: `milestone:${milestone.id}`,
                label: `Milestone: ${milestone.title}`,
            })),
    ];

    const updateBinding = (
        index: number,
        field: keyof EvidenceBindings[number],
        value: string,
    ) => {
        onEvidenceBindingsChange(
            evidenceBindings.map((binding, current) =>
                current === index ? { ...binding, [field]: value } : binding,
            ),
        );
    };

    const addBinding = () => {
        const source = plan.evidence_sources[0];
        const target = evidenceTargets[0];
        if (!source || !target) {
            return;
        }

        onEvidenceBindingsChange([
            ...evidenceBindings,
            {
                source_key: source.key,
                target_key: target.key,
                disposition: 'supports',
                rationale: '',
            },
        ]);
    };

    const updateDisposition = (
        index: number,
        disposition: 'supports' | 'out_of_scope',
    ) => {
        onEvidenceBindingsChange(
            evidenceBindings.map((binding, current) =>
                current === index
                    ? {
                          ...binding,
                          disposition,
                          target_key:
                              disposition === 'out_of_scope'
                                  ? 'out_of_scope'
                                  : binding.target_key === 'out_of_scope'
                                    ? (evidenceTargets[0]?.key ?? '')
                                    : binding.target_key,
                      }
                    : binding,
            ),
        );
    };

    return (
        <div className="space-y-3 rounded-md border bg-muted/20 p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-medium">Evidence alignment</h3>
                    <p className="text-xs text-muted-foreground">
                        Explicitly connect each material client, Business Plan,
                        Budget, or advisory source to this plan. Use out of
                        scope only with a recorded rationale.
                    </p>
                </div>
                {!deployed && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={
                            plan.evidence_sources.length === 0 ||
                            evidenceTargets.length === 0
                        }
                        onClick={addBinding}
                    >
                        <PlusCircle className="size-4" aria-hidden="true" />
                        Add evidence link
                    </Button>
                )}
            </div>

            {plan.evidence_summary && (
                <div className="grid gap-2 text-sm md:grid-cols-4">
                    <Metric
                        label="Links"
                        value={String(plan.evidence_summary.bindings)}
                    />
                    <Metric
                        label="Needs linking"
                        value={String(plan.evidence_summary.untraced_sources)}
                    />
                    <Metric
                        label="Changed sources"
                        value={String(
                            plan.evidence_summary.stale_source_signals,
                        )}
                    />
                    <Metric
                        label="Out of scope"
                        value={String(
                            plan.evidence_summary.intentional_exclusions,
                        )}
                    />
                </div>
            )}

            {evidenceBindings.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No evidence links have been confirmed yet.
                </p>
            ) : (
                <div className="space-y-3">
                    {evidenceBindings.map((binding, index) => (
                        <div
                            key={`${binding.source_key}-${binding.target_key}-${index}`}
                            className="grid gap-2 rounded-md border bg-background p-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_150px_auto]"
                        >
                            <select
                                value={binding.source_key}
                                disabled={deployed}
                                onChange={(event) =>
                                    updateBinding(
                                        index,
                                        'source_key',
                                        event.target.value,
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                            >
                                {plan.evidence_sources.map((source) => (
                                    <option key={source.key} value={source.key}>
                                        {source.label} ({source.materiality})
                                    </option>
                                ))}
                            </select>
                            <select
                                value={binding.target_key}
                                disabled={deployed}
                                onChange={(event) =>
                                    updateBinding(
                                        index,
                                        'target_key',
                                        event.target.value,
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                            >
                                {binding.disposition === 'out_of_scope' && (
                                    <option value="out_of_scope">
                                        No plan item: out of scope
                                    </option>
                                )}
                                {evidenceTargets.map((target) => (
                                    <option key={target.key} value={target.key}>
                                        {target.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={binding.disposition}
                                disabled={deployed}
                                onChange={(event) =>
                                    updateDisposition(
                                        index,
                                        event.target.value as
                                            'supports' | 'out_of_scope',
                                    )
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70"
                            >
                                <option value="supports">Supports plan</option>
                                <option value="out_of_scope">
                                    Intentionally out of scope
                                </option>
                            </select>
                            {!deployed && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    onClick={() =>
                                        onEvidenceBindingsChange(
                                            evidenceBindings.filter(
                                                (_binding, current) =>
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
                                        Remove evidence link
                                    </span>
                                </Button>
                            )}
                            <textarea
                                value={binding.rationale}
                                disabled={deployed}
                                onChange={(event) =>
                                    updateBinding(
                                        index,
                                        'rationale',
                                        event.target.value,
                                    )
                                }
                                rows={2}
                                placeholder="Why this source supports the selected item, or why it is intentionally out of scope"
                                className="min-h-18 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-70 lg:col-span-4"
                            />
                        </div>
                    ))}
                </div>
            )}

            {!deployed && (
                <label className="flex items-start gap-2 text-sm text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={confirmCurrentSources}
                        onChange={(event) =>
                            onConfirmCurrentSourcesChange(event.target.checked)
                        }
                        className="mt-1 size-4 rounded border-input"
                    />
                    <span>
                        I reviewed the current source evidence. Refresh the
                        plan’s source snapshot when this draft is saved.
                    </span>
                </label>
            )}
        </div>
    );
}
