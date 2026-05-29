<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\User;
use App\Services\Learning\ApprovalFlow;
use App\Services\Learning\LayerCadenceRunner;
use App\Services\Learning\LearningMonitorDashboard;
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
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/learning/Index', [
            'cards' => $this->approvalFlow->cards()->values(),
            'decisions' => $this->approvalFlow->decisions(),
            'impact_reviews' => $this->approvalFlow->impactReviewCards()->values(),
            'monitor' => $this->monitor->dashboard(),
            'rerun_url' => route('admin.learning-updates.rerun', absolute: false),
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

    public function reviewImpact(
        Request $request,
        LearningUpdateImplementation $learningUpdateImplementation,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'review_outcome' => ['required', 'string', 'max:4000'],
        ]);

        $this->approvalFlow->recordImpactReview(
            $learningUpdateImplementation,
            $validated['review_outcome'],
            $user,
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
}
