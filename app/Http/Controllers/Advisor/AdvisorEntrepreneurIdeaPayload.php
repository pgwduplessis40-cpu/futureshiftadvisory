<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Services\Entrepreneurs\FounderChangeRequestMessage;
use App\Services\Entrepreneurs\IdeaViabilityGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @phpstan-type IdeaValidationSummary array{id:string, revision_number:int, summary:string, problem:string|null, target_customer:string|null, solution:string|null, value_proposition:string|null, demand_signal:string|null, revenue_model:string|null, viability_alerts:list<mixed>, viability_gate:mixed, proposed_change_request:string, uncertainty:mixed, past_plan_pattern:list<mixed>, evaluated_at:string|null, ai_deferred:bool, advisor_gate_status:string, change_request_note:mixed, changes_requested_at:mixed, recalled_at:string|null, restored_from_revision_number:mixed, refresh_status:mixed, refresh_stale:bool, refresh_requested_at:mixed, refresh_started_at:mixed, refresh_completed_at:mixed, refresh_failed_at:mixed, refresh_failure:mixed, advisor_gate_passed_at:string|null, advisor_gate_note:string|null, gate_url:string, request_changes_url:string, refresh_url:string}
 * @phpstan-type Finding array{recommended_action?:mixed, title?:mixed, body?:mixed}
 * @phpstan-type FounderAction array{horizon:string, action:string}
 */
final class AdvisorEntrepreneurIdeaPayload
{
    public function __construct(
        private readonly FounderChangeRequestMessage $changeRequestMessages,
        private readonly IdeaViabilityGate $ideaViabilityGate,
    ) {}

    /** @return IdeaValidationSummary|null */
    public function summary(EntrepreneurProfile $profile): ?array
    {
        $validation = IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->orderByDesc('revision_number')
            ->orderByDesc('evaluated_at')
            ->first();

        if (! $validation instanceof IdeaValidation) {
            return null;
        }

        $evaluation = $validation->ai_evaluation ?? [];
        $aiDeferred = (bool) data_get($evaluation, 'metadata.degraded', false)
            || data_get($evaluation, 'model') === 'fake-ai-client';
        $gateStatus = $this->gateStatus($validation);
        $viabilityGate = $this->viabilityGate($validation, $gateStatus);
        $refreshStatus = data_get($evaluation, 'metadata.refresh_status');
        $refreshRequestedAt = data_get($evaluation, 'metadata.refresh_requested_at');
        $refreshStartedAt = data_get($evaluation, 'metadata.refresh_started_at');
        $refreshStale = $this->refreshStale($refreshStatus, $refreshStartedAt ?? $refreshRequestedAt);

        return [
            'id' => $validation->id,
            'revision_number' => $validation->revision_number,
            'summary' => (string) data_get($validation->ai_evaluation, 'summary', ''),
            'problem' => $validation->problem,
            'target_customer' => $validation->target_customer,
            'solution' => $validation->solution,
            'value_proposition' => $validation->value_proposition,
            'demand_signal' => $validation->demand_signal,
            'revenue_model' => $validation->revenue_model,
            'viability_alerts' => $validation->viability_alerts ?? [],
            'viability_gate' => $viabilityGate,
            'proposed_change_request' => $this->proposedChangeRequest($profile, $validation),
            'uncertainty' => data_get($evaluation, 'uncertainty'),
            'past_plan_pattern' => data_get($evaluation, 'past_plan_pattern', []),
            'evaluated_at' => $validation->evaluated_at?->toIso8601String(),
            'ai_deferred' => $aiDeferred,
            'advisor_gate_status' => $gateStatus,
            'change_request_note' => data_get($evaluation, 'metadata.change_request_note'),
            'changes_requested_at' => data_get($evaluation, 'metadata.changes_requested_at'),
            'recalled_at' => $validation->recalled_at?->toIso8601String(),
            'restored_from_revision_number' => data_get($evaluation, 'metadata.restored_from_revision_number'),
            'refresh_status' => $refreshStatus,
            'refresh_stale' => $refreshStale,
            'refresh_requested_at' => $refreshRequestedAt,
            'refresh_started_at' => $refreshStartedAt,
            'refresh_completed_at' => data_get($evaluation, 'metadata.refresh_completed_at'),
            'refresh_failed_at' => data_get($evaluation, 'metadata.refresh_failed_at'),
            'refresh_failure' => data_get($evaluation, 'metadata.refresh_failure'),
            'advisor_gate_passed_at' => $validation->advisor_gate_passed_at?->toIso8601String(),
            'advisor_gate_note' => $validation->advisor_gate_note,
            'gate_url' => route('advisor.entrepreneurs.idea-validations.gate', [$profile, $validation], absolute: false),
            'request_changes_url' => route('advisor.entrepreneurs.idea-validations.request-changes', [$profile, $validation], absolute: false),
            'refresh_url' => route('advisor.entrepreneurs.idea-validations.refresh', [$profile, $validation], absolute: false),
        ];
    }

    /**
     * @return array{status:string, label:string, summary:string, reasons:list<string>, approval_available:bool}
     */
    private function viabilityGate(IdeaValidation $validation, string $gateStatus): array
    {
        $gate = $this->ideaViabilityGate->assess($validation);

        if ($validation->advisor_gate_passed_at === null && $gateStatus === 'changes_requested') {
            return [
                ...$gate,
                'status' => IdeaViabilityGate::STATUS_AMBER,
                'label' => 'Amber - changes requested',
                'summary' => 'Advisor changes are still outstanding. The founder must update and resubmit the idea validation before it can be approved for the builder.',
                'reasons' => $gate['reasons'] !== []
                    ? $gate['reasons']
                    : ['Await founder resubmission before approving the business plan builder.'],
                'approval_available' => false,
            ];
        }

        return $gate;
    }

    private function proposedChangeRequest(EntrepreneurProfile $profile, IdeaValidation $validation): string
    {
        $evaluation = $validation->ai_evaluation ?? [];
        $findings = collect((array) data_get($evaluation, 'metadata.findings', []))
            ->filter(fn (mixed $finding): bool => is_array($finding))
            ->map(fn (array $finding): array => $this->founderActionForFinding($finding))
            ->filter(fn (array $action): bool => trim($action['action']) !== '')
            ->take(4)
            ->values();

        if ($findings->isEmpty()) {
            $findings = collect([
                ['horizon' => 'now', 'action' => 'Define the primary customer segment, the paid problem it faces, and why this offer is a better choice than the alternatives.'],
                ['horizon' => 'now', 'action' => 'Record at least one customer experiment with a clear hypothesis, evidence, result, and next step.'],
                ['horizon' => 'now', 'action' => 'Describe a repeatable offer, pricing, delivery capacity, and revenue model that is not dependent only on your personal time.'],
            ]);
        }

        $alerts = collect((array) $validation->viability_alerts)
            ->filter(fn (mixed $alert): bool => is_array($alert))
            ->map(fn (array $alert): string => trim((string) ($alert['message'] ?? '')))
            ->filter()
            ->map(fn (string $alert): array => [
                'horizon' => 'now',
                'action' => $this->completeFeedbackPoint($alert),
            ]);

        $shortTermActions = $findings
            ->merge($alerts)
            ->filter(fn (array $action): bool => $action['horizon'] === 'now')
            ->pluck('action')
            ->unique(fn (string $action): string => Str::lower($action))
            ->take(4)
            ->values();

        if ($shortTermActions->isEmpty()) {
            $shortTermActions = collect([
                'Define the immediate evidence needed to decide whether this idea should move into business-plan development.',
            ]);
        }

        $longTermActions = $findings
            ->filter(fn (array $action): bool => $action['horizon'] === 'long_term')
            ->pluck('action')
            ->unique(fn (string $action): string => Str::lower($action))
            ->take(3)
            ->values();

        if ($longTermActions->isEmpty()) {
            $longTermActions = collect([
                'Use the first validation cycle to decide which partnership, staffing, retention, and scale assumptions belong in the business-plan evidence.',
            ]);
        }

        return $this->changeRequestMessages->build($profile, [
            'Thank you for the work you have put into this idea validation.',
            'Your idea shows promise, but more evidence and a more repeatable commercial model are needed before it can move into business-plan development.',
            "Before resubmitting, please complete the short-term validation work:\n{$this->numberedFeedbackActions($shortTermActions)}",
            "Longer-term plan-builder evidence to prepare after the gate decision:\n{$this->numberedFeedbackActions($longTermActions)}",
            'Please update the idea validation with the short-term evidence and resubmit it for review. Keep the longer-term items for the plan-builder or scaling work if the gate is approved.',
        ]);
    }

    /**
     * @param  Finding  $finding
     * @return FounderAction
     */
    private function founderActionForFinding(array $finding): array
    {
        $recommendedAction = trim((string) ($finding['recommended_action'] ?? ''));
        if ($recommendedAction !== '') {
            $action = $this->completeFeedbackPoint($this->sanitiseReferenceSensitiveAction($recommendedAction));

            return [
                'horizon' => $this->feedbackHorizon($action, $this->findingContext($finding)),
                'action' => $action,
            ];
        }

        $title = trim((string) ($finding['title'] ?? ''));
        $body = trim((string) ($finding['body'] ?? ''));
        $context = Str::lower($title.' '.$body);

        if (Str::contains($context, ['revenue', 'pricing', 'price', 'time-constrained', 'capacity'])) {
            return [
                'horizon' => 'now',
                'action' => 'Build a sustainable revenue model: show how the offer can create income beyond your own billable days, including package pricing, delivery costs, monthly capacity, and recurring follow-on support.',
            ];
        }

        if (Str::contains($context, ['demand', 'market', 'customer evidence', 'willingness to pay'])) {
            return [
                'horizon' => 'now',
                'action' => 'Collect and document stronger demand evidence: choose a primary customer segment, test a paid offer, and record the hypothesis, evidence, result, and next step.',
            ];
        }

        if (Str::contains($context, ['value proposition', 'differentiat', 'positioning', 'communicat'])) {
            return [
                'horizon' => 'now',
                'action' => 'State one clear value proposition: name the customer, their pressing problem, the outcome they receive, and why this offer is more valuable than the alternatives.',
            ];
        }

        if (Str::contains($context, ['target customer', 'customer segment', 'customer'])) {
            return [
                'horizon' => 'now',
                'action' => 'Narrow the starting customer segment and explain the specific paid problem this offer will solve for them.',
            ];
        }

        if (Str::contains($context, ['solution', 'delivery', 'offer'])) {
            return [
                'horizon' => 'now',
                'action' => 'Describe a repeatable offer with clear outcomes, delivery steps, and what can be standardised as demand grows.',
            ];
        }

        $action = $this->completeFeedbackPoint(trim(implode(': ', array_filter([$title, $body]))));

        return [
            'horizon' => $this->feedbackHorizon($action, $context),
            'action' => $action,
        ];
    }

    /** @param Finding $finding */
    private function findingContext(array $finding): string
    {
        return Str::lower(implode(' ', [
            (string) ($finding['title'] ?? ''),
            (string) ($finding['body'] ?? ''),
            (string) ($finding['recommended_action'] ?? ''),
        ]));
    }

    private function feedbackHorizon(string $action, string $context): string
    {
        $haystack = Str::lower($action.' '.$context);

        if (Str::contains($haystack, [
            'before scaling',
            'full season',
            'seasonal',
            'partner agreement',
            'partnership agreement',
            'retention',
            'scaling',
            'volunteer',
            'written partnership',
        ])) {
            return 'long_term';
        }

        return 'now';
    }

    private function sanitiseReferenceSensitiveAction(string $action): string
    {
        if (! preg_match('/\bminimum wage\b|\$\d+(?:\.\d+)?\s*(?:\/\s*hr|per\s+hour|nzd_per_hour)/i', $action)) {
            return $action;
        }

        $action = preg_replace('/\s*\((?=[^)]*(?:minimum wage|\$\d+(?:\.\d+)?\s*(?:\/\s*hr|per\s+hour|nzd_per_hour)))[^)]*\)/i', '', $action) ?? $action;
        $action = preg_replace('/using\s+real\s+NZ\s+labou?r\s+rates/i', 'using current NZ wage reference data', $action) ?? $action;
        $action = preg_replace('/minimum wage\s+(?:is\s+|of\s+)?\$?\d+(?:\.\d+)?(?:\s*(?:\/\s*hr|per\s+hour|nzd_per_hour))?(?:\s+as\s+of\s+[A-Za-z]+\s+\d{4})?/i', 'current NZ minimum wage reference data', $action) ?? $action;
        $action = preg_replace('/\s+,/', ',', $action) ?? $action;

        return trim(preg_replace('/\s{2,}/', ' ', $action) ?? $action);
    }

    /** @param iterable<string> $actions */
    private function numberedFeedbackActions(iterable $actions): string
    {
        return collect($actions)
            ->values()
            ->map(fn (string $action, int $index): string => ($index + 1).'. '.$action)
            ->implode("\n");
    }

    private function completeFeedbackPoint(string $point): string
    {
        $point = trim($point);
        if (Str::length($point) <= 600) {
            return $point;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $point) ?: [];
        $limited = '';

        foreach ($sentences as $sentence) {
            $candidate = trim($limited.' '.trim($sentence));
            if (Str::length($candidate) > 600) {
                break;
            }

            $limited = $candidate;
        }

        if ($limited !== '') {
            return $limited;
        }

        $truncated = rtrim(Str::limit($point, 600, ''), " \t\n\r\0\x0B.,;:");

        return $truncated === '' ? $point : $truncated.'.';
    }

    private function gateStatus(IdeaValidation $validation): string
    {
        if ($validation->recalled_at !== null) {
            return 'recalled';
        }

        if ($validation->advisor_gate_passed_at !== null) {
            return 'approved';
        }

        $status = data_get($validation->ai_evaluation, 'metadata.advisor_gate_status');

        return is_string($status) && trim($status) !== '' ? $status : 'gate_needed';
    }

    private function refreshStale(mixed $status, mixed $timestamp): bool
    {
        if (! in_array($status, ['queued', 'running'], true) || ! is_string($timestamp) || trim($timestamp) === '') {
            return false;
        }

        $staleMinutes = max(1, (int) config('services.anthropic.refresh_stale_minutes', 2));

        return Carbon::parse($timestamp)->lessThan(now()->subMinutes($staleMinutes));
    }
}
