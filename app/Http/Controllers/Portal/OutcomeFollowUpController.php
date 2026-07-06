<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\OutcomeFollowUp;
use App\Models\Proposal;
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
            'focus_area_outcomes' => ['sometimes', 'array', 'max:25'],
            'focus_area_outcomes.*.proposal_id' => ['nullable', 'uuid'],
            'focus_area_outcomes.*.analysis_finding_id' => ['nullable', 'uuid'],
            'focus_area_outcomes.*.module' => ['nullable', 'string', 'max:80'],
            'focus_area_outcomes.*.title' => ['nullable', 'string', 'max:180'],
            'focus_area_outcomes.*.status' => ['required', 'string', Rule::in([
                'implemented',
                'partially_implemented',
                'not_started',
                'not_applicable',
            ])],
            'focus_area_outcomes.*.implemented' => ['nullable', 'boolean'],
            'focus_area_outcomes.*.notes' => ['nullable', 'string', 'max:500'],
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
            'focus_area_outcomes' => $this->focusAreaOutcomes($followUp),
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function focusAreaOutcomes(OutcomeFollowUp $followUp): array
    {
        $stored = $this->normaliseFocusAreaOutcomes(
            data_get($followUp->response_payload, 'focus_area_outcomes', []),
        );

        if ($stored !== []) {
            return $stored;
        }

        if ($followUp->client_id === null) {
            return [];
        }

        $proposal = Proposal::query()
            ->where('client_id', $followUp->client_id)
            ->where('status', ProposalStatus::Signed->value)
            ->latest('signed_at')
            ->latest()
            ->first();

        if (! $proposal instanceof Proposal) {
            return [];
        }

        return collect((array) data_get($proposal->scope, 'focus_areas', []))
            ->filter(fn (mixed $area): bool => is_array($area))
            ->map(fn (array $area): array => [
                'proposal_id' => (string) $proposal->getKey(),
                'analysis_finding_id' => $this->nullableString($area['analysis_finding_id'] ?? null),
                'module' => $this->nullableString($area['module'] ?? null),
                'title' => trim((string) ($area['title'] ?? 'Advisory focus area')),
                'status' => 'not_started',
                'implemented' => false,
                'notes' => '',
            ])
            ->filter(
                fn (array $area): bool => $area['title'] !== ''
                    || $area['analysis_finding_id'] !== null
                    || $area['module'] !== null
            )
            ->take(25)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normaliseFocusAreaOutcomes(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $area): bool => is_array($area))
            ->map(fn (array $area): array => [
                'proposal_id' => $this->nullableString($area['proposal_id'] ?? null),
                'analysis_finding_id' => $this->nullableString($area['analysis_finding_id'] ?? null),
                'module' => $this->nullableString($area['module'] ?? null),
                'title' => trim((string) ($area['title'] ?? 'Advisory focus area')),
                'status' => (string) ($area['status'] ?? 'not_started'),
                'implemented' => (bool) ($area['implemented'] ?? false),
                'notes' => trim((string) ($area['notes'] ?? '')),
            ])
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
