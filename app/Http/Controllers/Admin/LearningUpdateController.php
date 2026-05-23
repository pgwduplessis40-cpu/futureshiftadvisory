<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Services\Learning\ApprovalFlow;
use App\Services\Learning\Rollback as RollbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class LearningUpdateController extends Controller
{
    public function __construct(private readonly ApprovalFlow $approvalFlow) {}

    public function index(): Response
    {
        return Inertia::render('admin/learning/Index', [
            'cards' => $this->approvalFlow->cards()->values(),
            'decisions' => $this->approvalFlow->decisions(),
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
