<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\OutcomeFollowUp;
use App\Models\User;
use App\Services\Outcomes\OutcomeFollowUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class OutcomeFollowUpController extends Controller
{
    public function show(Request $request, OutcomeFollowUp $outcomeFollowUp): Response
    {
        $user = $this->assertCanAccess($request, $outcomeFollowUp);

        $outcomeFollowUp->load([
            'client',
            'entrepreneurProfile',
            'planAssessment.businessPlan',
            'ddEngagement',
        ]);

        return Inertia::render('portal/outcomes/Show', [
            'followUp' => $this->followUpPayload($outcomeFollowUp),
            'storeUrl' => route('portal.outcome-follow-ups.submit', $outcomeFollowUp, absolute: false),
            'dashboardUrl' => $this->dashboardUrl($outcomeFollowUp, $user),
        ]);
    }

    public function submit(Request $request, OutcomeFollowUp $outcomeFollowUp, OutcomeFollowUpService $outcomes): RedirectResponse
    {
        $user = $this->assertCanAccess($request, $outcomeFollowUp);
        $validated = $request->validate($this->rules($outcomeFollowUp));

        $outcomes->submit($outcomeFollowUp, $user, $validated);

        return redirect($this->dashboardUrl($outcomeFollowUp, $user))
            ->with('status', 'outcome-follow-up-submitted');
    }

    private function assertCanAccess(Request $request, OutcomeFollowUp $followUp): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (
            $followUp->client_id !== null
            && in_array((string) $followUp->client_id, $user->accessibleClientIds(), true)
        ) {
            return $user;
        }

        $followUp->loadMissing('entrepreneurProfile');
        if (
            $followUp->entrepreneurProfile !== null
            && (string) $followUp->entrepreneurProfile->user_id === (string) $user->getKey()
        ) {
            return $user;
        }

        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(OutcomeFollowUp $followUp): array
    {
        $statusOptions = $followUp->subject_type === OutcomeFollowUp::SUBJECT_DUE_DILIGENCE
            ? ['completed_acquisition', 'renegotiated', 'still_assessing', 'abandoned']
            : ['growth', 'stable', 'stalled', 'failed'];

        return [
            'status' => ['required', 'string', Rule::in($statusOptions)],
            'still_trading' => ['required', 'boolean'],
            'revenue_direction' => ['required', 'string', Rule::in(['up', 'flat', 'down', 'not_started', 'not_available'])],
            'revenue_growth_percent' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'recorded_price' => ['nullable', 'numeric', 'min:0'],
            'implemented_recommendations' => ['required', 'integer', 'min:0', 'max:1000'],
            'total_recommendations' => ['required', 'integer', 'min:0', 'max:1000', 'gte:implemented_recommendations'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function followUpPayload(OutcomeFollowUp $followUp): array
    {
        $isDd = $followUp->subject_type === OutcomeFollowUp::SUBJECT_DUE_DILIGENCE;
        $subjectName = $isDd
            ? ($followUp->ddEngagement?->target_name ?? $followUp->client?->trading_name ?? $followUp->client?->legal_name)
            : ($followUp->entrepreneurProfile?->name ?? $followUp->planAssessment?->businessPlan?->title);

        return [
            'id' => $followUp->id,
            'subject_type' => $followUp->subject_type,
            'subject_label' => $isDd ? 'Explore buying a business' : 'Test new Business Idea',
            'subject_name' => $subjectName ?? 'Outcome follow-up',
            'cadence_month' => $followUp->cadence_month,
            'status' => $followUp->status,
            'is_open' => $followUp->status === OutcomeFollowUp::STATUS_PENDING,
            'due_at' => $followUp->due_at?->toIso8601String(),
            'completed_at' => $followUp->completed_at?->toIso8601String(),
            'engagement_completed_at' => $followUp->engagement_completed_at?->toIso8601String(),
            'response' => $followUp->response_payload ?? [],
            'status_options' => $isDd
                ? [
                    ['value' => 'completed_acquisition', 'label' => 'Completed acquisition'],
                    ['value' => 'renegotiated', 'label' => 'Renegotiated or changed terms'],
                    ['value' => 'still_assessing', 'label' => 'Still assessing'],
                    ['value' => 'abandoned', 'label' => 'Did not proceed'],
                ]
                : [
                    ['value' => 'growth', 'label' => 'Trading and growing'],
                    ['value' => 'stable', 'label' => 'Trading and stable'],
                    ['value' => 'stalled', 'label' => 'Stalled or not launched'],
                    ['value' => 'failed', 'label' => 'Closed or discontinued'],
                ],
        ];
    }

    private function dashboardUrl(OutcomeFollowUp $followUp, User $user): string
    {
        if (
            $followUp->subject_type === OutcomeFollowUp::SUBJECT_ENTREPRENEUR
            && (
                $user->user_type === User::TYPE_ENTREPRENEUR
                || $followUp->service_activation_id !== null
            )
        ) {
            return route('portal.entrepreneur.dashboard', absolute: false);
        }

        return route('portal.dashboard', absolute: false);
    }
}
