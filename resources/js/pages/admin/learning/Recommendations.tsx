import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type RecommendationDefaults = {
    title: string;
    failure_shortfall: string;
    impact: string;
    impact_area: string;
    recommendation: string;
    recommendation_impact: string;
};

export type LearningRecommendation = RecommendationDefaults & {
    id: string;
    learning_update_id: string;
    acceptance_criteria: string[];
    regression_journeys: string[];
    delivery_owner: string | null;
    delivery_target: string | null;
    baseline_metrics: string[];
    rollback_plan: string | null;
    status: string;
    approved_at: string | null;
    development_reference: string | null;
    release_reference: string | null;
    released_at: string | null;
    verified_at: string | null;
    verification_notes: string | null;
    review_due_at: string | null;
    approve_url: string;
    delivery_url: string;
};

export function DraftRecommendationApprovalPanel({
    recommendations,
    approveSelectedUrl,
}: {
    recommendations: LearningRecommendation[];
    approveSelectedUrl: string;
}) {
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const availableIds = new Set(
        recommendations.map((recommendation) => recommendation.id),
    );
    const selectedRecommendationIds = selectedIds.filter((recommendationId) =>
        availableIds.has(recommendationId),
    );
    const selectedCount = selectedRecommendationIds.length;
    const allSelected =
        recommendations.length > 0 && selectedCount === recommendations.length;

    if (recommendations.length === 0) {
        return null;
    }

    function toggleRecommendation(recommendationId: string) {
        setSelectedIds((current) =>
            current.includes(recommendationId)
                ? current.filter((id) => id !== recommendationId)
                : [...current, recommendationId],
        );
    }

    function toggleAll() {
        setSelectedIds(
            allSelected
                ? []
                : recommendations.map((recommendation) => recommendation.id),
        );
    }

    function approveSelected() {
        if (selectedRecommendationIds.length === 0) {
            return;
        }

        router.post(approveSelectedUrl, {
            recommendation_ids: selectedRecommendationIds,
        });
    }

    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-medium">
                        Development recommendations awaiting approval
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        These recommendations are ready for a human decision.
                        Approval starts development tracking; it cannot change
                        the live product automatically.
                    </p>
                </div>
                <Badge variant="secondary">
                    {recommendations.length} awaiting approval
                </Badge>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-muted/30 p-3">
                <label className="flex items-center gap-2 text-sm font-medium">
                    <input
                        type="checkbox"
                        checked={allSelected}
                        onChange={toggleAll}
                        aria-label={`Select all ${recommendations.length} draft recommendations`}
                    />
                    Select all
                </label>
                <Button
                    type="button"
                    size="sm"
                    disabled={selectedCount === 0}
                    onClick={approveSelected}
                >
                    Approve {selectedCount} selected for development
                </Button>
            </div>
            <div className="grid gap-3">
                {recommendations.map((recommendation) => (
                    <DraftRecommendationApprovalItem
                        key={recommendation.id}
                        recommendation={recommendation}
                        selected={selectedIds.includes(recommendation.id)}
                        onSelectedChange={() =>
                            toggleRecommendation(recommendation.id)
                        }
                    />
                ))}
            </div>
        </section>
    );
}

export function RecommendationDeliveryPanel({
    recommendations,
}: {
    recommendations: LearningRecommendation[];
}) {
    if (recommendations.length === 0) {
        return null;
    }

    return (
        <section className="space-y-3 rounded-md border bg-background p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-medium">
                        Approved recommendations in delivery
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        An approved recommendation is only addressed after it
                        has a delivery owner, target, baseline, rollback plan,
                        development reference, release reference, and
                        verification evidence.
                    </p>
                </div>
                <Badge variant="secondary">{recommendations.length}</Badge>
            </div>
            <div className="grid gap-3">
                {recommendations.map((recommendation) => (
                    <RecommendationDeliveryItem
                        key={recommendation.id}
                        recommendation={recommendation}
                    />
                ))}
            </div>
        </section>
    );
}

function DraftRecommendationApprovalItem({
    recommendation,
    selected,
    onSelectedChange,
}: {
    recommendation: LearningRecommendation;
    selected: boolean;
    onSelectedChange: () => void;
}) {
    return (
        <article className="rounded-md border p-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <label className="flex min-w-0 flex-1 items-start gap-3">
                    <input
                        className="mt-1"
                        type="checkbox"
                        checked={selected}
                        onChange={onSelectedChange}
                        aria-label={`Select ${recommendation.title}`}
                    />
                    <div>
                        <h3 className="text-sm font-medium">
                            {recommendation.title}
                        </h3>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {recommendation.impact_area} · draft
                        </p>
                    </div>
                </label>
                <Badge variant="outline">draft</Badge>
            </div>
            <dl className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                <div>
                    <dt className="text-muted-foreground">
                        Failure / shortfall
                    </dt>
                    <dd>{recommendation.failure_shortfall}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Expected impact</dt>
                    <dd>{recommendation.recommendation_impact}</dd>
                </div>
            </dl>
            <p className="mt-3 text-sm">{recommendation.recommendation}</p>
            <p className="mt-2 text-xs text-muted-foreground">
                Regression journeys:{' '}
                {recommendation.regression_journeys.join(', ') ||
                    'must be recorded before release'}
            </p>
            <Button
                className="mt-3"
                type="button"
                size="sm"
                onClick={() => router.post(recommendation.approve_url)}
            >
                Approve for development
            </Button>
        </article>
    );
}

function RecommendationDeliveryItem({
    recommendation,
}: {
    recommendation: LearningRecommendation;
}) {
    const [status, setStatus] = useState(nextDeliveryStatus(recommendation));
    const [developmentReference, setDevelopmentReference] = useState(
        recommendation.development_reference ?? '',
    );
    const [releaseReference, setReleaseReference] = useState(
        recommendation.release_reference ?? '',
    );
    const [deliveryOwner, setDeliveryOwner] = useState(
        recommendation.delivery_owner ?? '',
    );
    const [deliveryTarget, setDeliveryTarget] = useState(
        recommendation.delivery_target ?? '',
    );
    const [baselineMetrics, setBaselineMetrics] = useState(
        recommendation.baseline_metrics.join('\n'),
    );
    const [rollbackPlan, setRollbackPlan] = useState(
        recommendation.rollback_plan ?? '',
    );
    const [verificationNotes, setVerificationNotes] = useState(
        recommendation.verification_notes ?? '',
    );

    function updateDelivery() {
        router.patch(recommendation.delivery_url, {
            status,
            development_reference: developmentReference || null,
            delivery_owner: deliveryOwner || null,
            delivery_target: deliveryTarget || null,
            baseline_metrics: lines(baselineMetrics),
            rollback_plan: rollbackPlan || null,
            release_reference: releaseReference || null,
            verification_notes: verificationNotes || null,
            regression_journeys: recommendation.regression_journeys,
        });
    }

    return (
        <article className="rounded-md border p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 className="text-sm font-medium">
                        {recommendation.title}
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {recommendation.impact_area} · {recommendation.status}
                    </p>
                </div>
                <Badge variant="outline">{recommendation.status}</Badge>
            </div>
            <dl className="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                <div>
                    <dt className="text-muted-foreground">
                        Failure / shortfall
                    </dt>
                    <dd>{recommendation.failure_shortfall}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Expected impact</dt>
                    <dd>{recommendation.recommendation_impact}</dd>
                </div>
            </dl>
            <p className="mt-3 text-sm">{recommendation.recommendation}</p>
            <p className="mt-2 text-xs text-muted-foreground">
                Regression journeys:{' '}
                {recommendation.regression_journeys.join(', ') ||
                    'must be recorded before release'}
            </p>
            {recommendation.status === 'draft' ? (
                <Button
                    className="mt-3"
                    type="button"
                    size="sm"
                    onClick={() => router.post(recommendation.approve_url)}
                >
                    Approve for development review
                </Button>
            ) : (
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        Delivery status
                        <select
                            className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            {deliveryStatusOptions(recommendation).map(
                                (option) => (
                                    <option key={option} value={option}>
                                        {formatLabel(option)}
                                    </option>
                                ),
                            )}
                        </select>
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        Development reference
                        <input
                            className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                            value={developmentReference}
                            placeholder="PR, issue, or commit"
                            onChange={(event) =>
                                setDevelopmentReference(event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        Delivery owner
                        <input
                            className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                            value={deliveryOwner}
                            placeholder="Named accountable owner"
                            onChange={(event) =>
                                setDeliveryOwner(event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground">
                        Delivery target
                        <input
                            className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                            value={deliveryTarget}
                            placeholder="Release, sprint, or target date"
                            onChange={(event) =>
                                setDeliveryTarget(event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground sm:col-span-2">
                        Baseline metrics (one per line)
                        <textarea
                            className="min-h-16 rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                            value={baselineMetrics}
                            placeholder="Current completion rate: 63%"
                            onChange={(event) =>
                                setBaselineMetrics(event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-xs text-muted-foreground sm:col-span-2">
                        Rollback plan
                        <textarea
                            className="min-h-16 rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                            value={rollbackPlan}
                            placeholder="How this change will be safely reverted if evidence regresses"
                            onChange={(event) =>
                                setRollbackPlan(event.target.value)
                            }
                        />
                    </label>
                    {status === 'released' ||
                    recommendation.status === 'released' ? (
                        <label className="grid gap-1 text-xs text-muted-foreground">
                            Release reference
                            <input
                                className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                                value={releaseReference}
                                placeholder="Deployment/version evidence"
                                onChange={(event) =>
                                    setReleaseReference(event.target.value)
                                }
                            />
                        </label>
                    ) : null}
                    {status === 'verified' ? (
                        <label className="grid gap-1 text-xs text-muted-foreground sm:col-span-2">
                            Verification evidence
                            <textarea
                                className="min-h-16 rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                                value={verificationNotes}
                                onChange={(event) =>
                                    setVerificationNotes(event.target.value)
                                }
                            />
                        </label>
                    ) : null}
                    <div>
                        <Button
                            type="button"
                            size="sm"
                            onClick={updateDelivery}
                        >
                            Update delivery
                        </Button>
                    </div>
                </div>
            )}
        </article>
    );
}

export function RecommendationDraft({
    learningUpdateId,
    defaults,
}: {
    learningUpdateId: string;
    defaults: RecommendationDefaults;
}) {
    const [title, setTitle] = useState(defaults.title);
    const [failureShortfall, setFailureShortfall] = useState(
        defaults.failure_shortfall,
    );
    const [impact, setImpact] = useState(defaults.impact);
    const [impactArea, setImpactArea] = useState(defaults.impact_area);
    const [recommendation, setRecommendation] = useState(
        defaults.recommendation,
    );
    const [recommendationImpact, setRecommendationImpact] = useState(
        defaults.recommendation_impact,
    );
    const [acceptanceCriteria, setAcceptanceCriteria] = useState('');
    const [regressionJourneys, setRegressionJourneys] = useState('');
    const [deliveryOwner, setDeliveryOwner] = useState('');
    const [deliveryTarget, setDeliveryTarget] = useState('');
    const [baselineMetrics, setBaselineMetrics] = useState('');
    const [rollbackPlan, setRollbackPlan] = useState('');

    function submit() {
        router.post(
            `/admin/learning-updates/${learningUpdateId}/recommendations`,
            {
                title,
                failure_shortfall: failureShortfall,
                impact,
                impact_area: impactArea,
                recommendation,
                recommendation_impact: recommendationImpact,
                acceptance_criteria: lines(acceptanceCriteria),
                regression_journeys: lines(regressionJourneys),
                delivery_owner: deliveryOwner || null,
                delivery_target: deliveryTarget || null,
                baseline_metrics: lines(baselineMetrics),
                rollback_plan: rollbackPlan || null,
            },
        );
    }

    return (
        <details className="rounded-md border p-3" open>
            <summary className="cursor-pointer text-sm font-medium">
                Create development recommendation
            </summary>
            <p className="mt-2 text-xs text-muted-foreground">
                Approval creates a traceable development item. It cannot change
                the live product automatically. A delivery owner, target,
                baseline, and rollback plan are required before development can
                start.
            </p>
            <div className="mt-3 grid gap-2">
                <InputField label="Title" value={title} onChange={setTitle} />
                <TextAreaField
                    label="Failure / shortfall"
                    value={failureShortfall}
                    onChange={setFailureShortfall}
                />
                <TextAreaField
                    label="Impact"
                    value={impact}
                    onChange={setImpact}
                />
                <InputField
                    label="Area of impact"
                    value={impactArea}
                    onChange={setImpactArea}
                />
                <TextAreaField
                    label="Recommendation"
                    value={recommendation}
                    onChange={setRecommendation}
                />
                <TextAreaField
                    label="Impact of recommendation"
                    value={recommendationImpact}
                    onChange={setRecommendationImpact}
                />
                <TextAreaField
                    label="Acceptance criteria (one per line)"
                    value={acceptanceCriteria}
                    onChange={setAcceptanceCriteria}
                />
                <TextAreaField
                    label="Regression journeys to verify (one per line)"
                    value={regressionJourneys}
                    onChange={setRegressionJourneys}
                />
                <InputField
                    label="Delivery owner"
                    value={deliveryOwner}
                    onChange={setDeliveryOwner}
                />
                <InputField
                    label="Delivery target"
                    value={deliveryTarget}
                    onChange={setDeliveryTarget}
                />
                <TextAreaField
                    label="Baseline metrics (one per line)"
                    value={baselineMetrics}
                    onChange={setBaselineMetrics}
                />
                <TextAreaField
                    label="Rollback plan"
                    value={rollbackPlan}
                    onChange={setRollbackPlan}
                />
                <Button type="button" size="sm" onClick={submit}>
                    Save recommendation for approval
                </Button>
            </div>
        </details>
    );
}

function InputField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="grid gap-1 text-xs text-muted-foreground">
            {label}
            <input
                className="h-9 rounded-md border bg-background px-3 text-sm text-foreground"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </label>
    );
}

function TextAreaField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="grid gap-1 text-xs text-muted-foreground">
            {label}
            <textarea
                className="min-h-16 rounded-md border bg-background px-3 py-2 text-sm text-foreground"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </label>
    );
}

function lines(value: string): string[] {
    return value
        .split('\n')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
}

function formatLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function nextDeliveryStatus(recommendation: LearningRecommendation): string {
    return deliveryStatusOptions(recommendation)[0] ?? recommendation.status;
}

function deliveryStatusOptions(
    recommendation: LearningRecommendation,
): string[] {
    switch (recommendation.status) {
        case 'approved':
        case 'blocked':
            return ['in_development'];
        case 'in_development':
            return ['released', 'blocked'];
        case 'released':
            return ['verified', 'rolled_back', 'blocked'];
        default:
            return [recommendation.status];
    }
}
