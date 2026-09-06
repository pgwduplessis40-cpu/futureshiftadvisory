<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningRecommendation;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\User;
use App\Services\Learning\ApprovalFlow;
use App\Services\Learning\LayerCadenceRunner;
use App\Services\Learning\LearningMonitorDashboard;
use App\Services\Learning\LearningRecommendationWorkflow;
use App\Services\Learning\Rollback as RollbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class LearningUpdateController extends Controller
{
    public function __construct(
        private readonly ApprovalFlow $approvalFlow,
        private readonly LearningMonitorDashboard $monitor,
        private readonly LearningRecommendationWorkflow $recommendations,
    ) {}

    public function index(): Response
    {
        $activeRecommendations = $this->recommendations->activeRecommendations();
        $activeRecommendationLearningUpdateIds = $activeRecommendations
            ->pluck('learning_update_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return Inertia::render('admin/learning/Index', [
            'cards' => $this->approvalFlow->cards()
                ->reject(fn (array $card): bool => in_array($card['id'], $activeRecommendationLearningUpdateIds, true))
                ->values(),
            'decisions' => $this->approvalFlow->decisions(),
            'impact_reviews' => $this->approvalFlow->impactReviewCards()->values(),
            'recommendations' => $activeRecommendations
                ->map(fn (LearningRecommendation $recommendation): array => $this->recommendationPayload($recommendation))
                ->values(),
            'recommendation_defaults' => LearningUpdate::query()
                ->whereIn('status', [LearningUpdate::STATUS_DETECTED, LearningUpdate::STATUS_STAGED, LearningUpdate::STATUS_DEFERRED])
                ->whereNotIn('id', $activeRecommendationLearningUpdateIds)
                ->latest()
                ->get()
                ->mapWithKeys(fn (LearningUpdate $update): array => [(string) $update->getKey() => $this->recommendations->defaultsFor($update)])
                ->all(),
            'monitor' => $this->monitor->dashboard(),
            'rerun_url' => route('admin.learning-updates.rerun', absolute: false),
            'developer_brief_url' => route('admin.learning-recommendations.developer-brief', absolute: false),
            'recommendation_bulk_approve_url' => route('admin.learning-recommendations.approve-selected', absolute: false),
        ]);
    }

    public function decide(Request $request, LearningUpdate $learningUpdate): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in($this->approvalFlow->decisions())],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->approvalFlow->decide(
            update: $learningUpdate,
            decision: $validated['decision'],
            actor: $request->user(),
            effectiveDate: isset($validated['effective_date']) && $validated['effective_date'] !== null
                ? Carbon::parse($validated['effective_date'])
                : null,
            reason: $validated['reason'] ?? null,
        );

        return to_route('admin.learning-updates.index')->with('status', 'learning-update-decided');
    }

    public function rerun(Request $request, LayerCadenceRunner $runner): RedirectResponse
    {
        $validated = $request->validate([
            'layer_ids' => ['nullable', 'array'],
            'layer_ids.*' => ['integer', 'min:1'],
        ]);

        $runner->recordDueRuns(now(), $validated['layer_ids'] ?? []);

        return to_route('admin.learning-updates.index')->with('status', 'learning-layers-rerun');
    }

    public function draftRecommendation(Request $request, LearningUpdate $learningUpdate): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $validated = $request->validate($this->recommendationRules());

        $this->recommendations->draft($learningUpdate, $actor, $validated);

        return to_route('admin.learning-updates.index')->with('status', 'learning-recommendation-drafted');
    }

    public function approveRecommendation(Request $request, LearningRecommendation $learningRecommendation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->recommendations->approve($learningRecommendation, $actor);

        return to_route('admin.learning-updates.index')->with('status', 'learning-recommendation-approved');
    }

    public function approveSelectedRecommendations(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $validated = $request->validate([
            'recommendation_ids' => ['required', 'array', 'min:1', 'max:100'],
            'recommendation_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        $this->recommendations->approveMany($validated['recommendation_ids'], $actor);

        return to_route('admin.learning-updates.index')->with('status', 'learning-recommendations-approved');
    }

    public function updateRecommendationDelivery(Request $request, LearningRecommendation $learningRecommendation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(LearningRecommendation::deliveryStatuses())],
            'development_reference' => ['nullable', 'string', 'max:255'],
            'release_reference' => [
                Rule::requiredIf(fn (): bool => $request->input('status') === LearningRecommendation::STATUS_RELEASED),
                'nullable',
                'string',
                'max:255',
            ],
            'verification_notes' => [
                Rule::requiredIf(fn (): bool => $request->input('status') === LearningRecommendation::STATUS_VERIFIED),
                'nullable',
                'string',
                'max:4000',
            ],
            'regression_journeys' => ['nullable', 'array', 'max:20'],
            'regression_journeys.*' => ['string', 'max:255'],
        ]);

        $this->recommendations->updateDelivery($learningRecommendation, $actor, $validated);

        return to_route('admin.learning-updates.index')->with('status', 'learning-recommendation-delivery-updated');
    }

    public function developerBrief(): \Illuminate\Http\Response
    {
        $filename = 'future-shift-learning-recommendations-'.now()->toDateString().'.md';

        return response($this->recommendations->developerBrief(), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function reviewImpact(
        Request $request,
        LearningUpdateImplementation $learningUpdateImplementation,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'review_outcome' => ['required', 'string', 'max:4000'],
            'impact_outcome' => ['nullable', 'string', Rule::in(['improved', 'neutral', 'regressed', 'inconclusive'])],
            'affected_surface' => ['nullable', 'string', 'max:120'],
            'metric_name' => ['nullable', 'string', 'max:120'],
            'before_metric' => ['nullable', 'numeric'],
            'after_metric' => ['nullable', 'numeric'],
            'sample_size' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rollback_required' => ['nullable', 'boolean'],
        ]);

        $this->approvalFlow->recordImpactReview(
            $learningUpdateImplementation,
            $validated['review_outcome'],
            $user,
            [
                'impact_outcome' => $validated['impact_outcome'] ?? null,
                'affected_surface' => $validated['affected_surface'] ?? null,
                'metric_name' => $validated['metric_name'] ?? null,
                'before_metric' => $validated['before_metric'] ?? null,
                'after_metric' => $validated['after_metric'] ?? null,
                'sample_size' => $validated['sample_size'] ?? null,
                'rollback_required' => $validated['rollback_required'] ?? false,
            ],
        );

        return to_route('admin.learning-updates.index')->with('status', 'learning-impact-reviewed');
    }

    public function rollback(
        Request $request,
        LearningUpdateImplementation $learningUpdateImplementation,
        RollbackService $rollbacks,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:4000'],
        ]);

        $rollbacks->rollback(
            implementation: $learningUpdateImplementation,
            reason: $validated['reason'],
            actor: $request->user(),
        );

        return to_route('admin.learning-updates.index')->with('status', 'learning-update-rolled-back');
    }

    /** @return array<string, array<int, mixed>> */
    private function recommendationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'failure_shortfall' => ['required', 'string', 'max:4000'],
            'impact' => ['required', 'string', 'max:4000'],
            'impact_area' => ['required', 'string', 'max:255'],
            'recommendation' => ['required', 'string', 'max:4000'],
            'recommendation_impact' => ['required', 'string', 'max:4000'],
            'acceptance_criteria' => ['nullable', 'array', 'max:20'],
            'acceptance_criteria.*' => ['string', 'max:1000'],
            'regression_journeys' => ['nullable', 'array', 'max:20'],
            'regression_journeys.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array{id:string,learning_update_id:string,title:string,failure_shortfall:string,impact:string,impact_area:string,recommendation:string,recommendation_impact:string,acceptance_criteria:array<int,string>,regression_journeys:array<int,string>,status:string,approved_at:?string,development_reference:?string,release_reference:?string,released_at:?string,verified_at:?string,verification_notes:?string,review_due_at:?string,approve_url:string,delivery_url:string}
     */
    private function recommendationPayload(LearningRecommendation $recommendation): array
    {
        return [
            'id' => (string) $recommendation->getKey(),
            'learning_update_id' => (string) $recommendation->learning_update_id,
            'title' => $recommendation->title,
            'failure_shortfall' => $recommendation->failure_shortfall,
            'impact' => $recommendation->impact,
            'impact_area' => $recommendation->impact_area,
            'recommendation' => $recommendation->recommendation,
            'recommendation_impact' => $recommendation->recommendation_impact,
            'acceptance_criteria' => $recommendation->acceptance_criteria ?? [],
            'regression_journeys' => $recommendation->regression_journeys ?? [],
            'status' => $recommendation->status,
            'approved_at' => $recommendation->approved_at?->toIso8601String(),
            'development_reference' => $recommendation->development_reference,
            'release_reference' => $recommendation->release_reference,
            'released_at' => $recommendation->released_at?->toIso8601String(),
            'verified_at' => $recommendation->verified_at?->toIso8601String(),
            'verification_notes' => $recommendation->verification_notes,
            'review_due_at' => $recommendation->review_due_at?->toIso8601String(),
            'approve_url' => route('admin.learning-recommendations.approve', $recommendation, absolute: false),
            'delivery_url' => route('admin.learning-recommendations.delivery', $recommendation, absolute: false),
        ];
    }
}
