<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\IdeaValidation;

final class IdeaViabilityGate
{
    public const STATUS_RED = 'red';

    public const STATUS_AMBER = 'amber';

    public const STATUS_GREEN = 'green';

    /**
     * @return array{status: string, label: string, summary: string, reasons: array<int, string>, approval_available: bool}
     */
    public function assess(IdeaValidation $validation): array
    {
        $evaluation = is_array($validation->ai_evaluation) ? $validation->ai_evaluation : [];

        if ($validation->recalled_at !== null) {
            return $this->result(
                self::STATUS_AMBER,
                'Amber - recalled for revision',
                'The founder has recalled this idea validation. Wait for a resubmission before approving the business plan builder.',
                ['Await founder resubmission before approving the business plan builder.'],
            );
        }

        $advisorGateStatus = trim((string) data_get($evaluation, 'metadata.advisor_gate_status'));
        if ($validation->advisor_gate_passed_at === null && $advisorGateStatus === 'changes_requested') {
            return $this->result(
                self::STATUS_AMBER,
                'Amber - changes requested',
                'Advisor changes are still outstanding. The founder must update and resubmit the idea validation before it can be approved for the builder.',
                ['Await founder resubmission before approving the business plan builder.'],
            );
        }

        $redReasons = [];
        $amberReasons = [];

        $aiReviewReason = $this->aiReviewReason($evaluation);
        if ($aiReviewReason !== null) {
            $redReasons[] = $aiReviewReason;
        }

        foreach ($this->missingCoreFields($validation) as $reason) {
            $redReasons[] = $reason;
        }

        foreach ((array) $validation->viability_alerts as $alert) {
            $type = is_array($alert) ? (string) ($alert['type'] ?? '') : '';
            $isCoreWeakness = in_array(str_replace('_weakness', '', $type), [
                'problem',
                'target_customer',
                'solution',
                'value_proposition',
            ], true);

            if (is_array($alert) && (bool) ($alert['blocking'] ?? false) && ! $isCoreWeakness) {
                $redReasons[] = (string) ($alert['message'] ?? 'Resolve the blocking viability issue before approving the builder gate.');
            }
        }

        if (! $this->hasCompletedExperiment($evaluation) && ! $this->hasSufficientDemandEvidence([
            'demand_signal' => $validation->demand_signal,
        ])) {
            $amberReasons[] = 'Record a specific demand signal, such as customer interviews, a pilot, a paid commitment, or another test with a measurable result.';
        }

        if (! $this->hasCommercialRevenueModel((string) $validation->revenue_model)) {
            $amberReasons[] = 'Clarify who pays, what they pay for, and how the revenue will be collected.';
        }

        $uncertainty = strtolower(trim((string) data_get($evaluation, 'uncertainty')));
        if (! in_array($uncertainty, ['low', 'medium'], true)) {
            $amberReasons[] = 'Reduce the remaining uncertainty with more specific customer or commercial evidence.';
        }

        if ($redReasons !== []) {
            return $this->result(
                self::STATUS_RED,
                'Red - builder blocked',
                'The idea is not ready for the business plan builder. Resolve the blocking issues and rerun the AI review.',
                $redReasons,
            );
        }

        if ($amberReasons !== []) {
            return $this->result(
                self::STATUS_AMBER,
                'Amber - evidence needed',
                'The concept is plausible, but more evidence is needed before approving detailed business-plan work.',
                $amberReasons,
            );
        }

        return $this->result(
            self::STATUS_GREEN,
            'Green - ready for builder',
            'The minimum viability evidence is complete. An advisor may approve the business plan builder.',
            [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasSufficientDemandEvidence(array $payload): bool
    {
        $demandSignal = trim((string) ($payload['demand_signal'] ?? ''));

        if ($this->wordCount($demandSignal) < $this->minimumDemandSignalWords()) {
            return false;
        }

        return preg_match(
            '/\b(\d+|one|two|three|four|five|six|seven|eight|nine|ten|interview|survey|pilot|trial|paid|deposit|pre-?order|letter of intent|waitlist|signed)\b/i',
            $demandSignal,
        ) === 1;
    }

    public function hasCommercialRevenueModel(string $revenueModel): bool
    {
        if ($this->wordCount($revenueModel) < $this->minimumFieldWords()) {
            return false;
        }

        return preg_match(
            '/\b(subscription|fee|fees|price|pricing|paid|charge|sale|sales|commission|licen[cs]e|monthly|annual|per\s+(customer|unit|project|hour))\b/i',
            $revenueModel,
        ) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function missingCoreFields(IdeaValidation $validation): array
    {
        $minimumWords = $this->minimumFieldWords();
        $requirements = [
            'problem' => 'State a specific customer problem before approving the builder gate.',
            'target_customer' => 'Define a specific target customer segment before approving the builder gate.',
            'solution' => 'Describe the solution in enough detail to test it with customers.',
            'value_proposition' => 'Explain why the target customer would choose this over the alternatives.',
        ];
        $missing = [];

        foreach ($requirements as $field => $message) {
            if ($this->wordCount((string) $validation->{$field}) < $minimumWords) {
                $missing[] = $message;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function aiReviewReason(array $evaluation): ?string
    {
        $model = trim((string) data_get($evaluation, 'model'));
        $summary = trim((string) data_get($evaluation, 'summary'));
        $refreshStatus = trim((string) data_get($evaluation, 'metadata.refresh_status'));
        $degraded = (bool) data_get($evaluation, 'metadata.degraded', false)
            || str_contains(strtolower($model), 'fake-ai-client');

        if (in_array($refreshStatus, ['queued', 'running'], true)) {
            return 'Wait for the AI review to finish before deciding the builder gate.';
        }

        if ($degraded || $refreshStatus === 'failed' || $model === '' || $summary === '') {
            return 'A successful AI review is required before the builder gate can be approved.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function hasCompletedExperiment(array $evaluation): bool
    {
        return (int) data_get($evaluation, 'validation_evidence_loop.completed_experiment_count', 0) > 0;
    }

    private function minimumFieldWords(): int
    {
        return max(4, (int) config('entrepreneurs.idea_viability.minimum_field_words', 6));
    }

    private function minimumDemandSignalWords(): int
    {
        return max($this->minimumFieldWords(), (int) config('entrepreneurs.idea_viability.minimum_demand_signal_words', 8));
    }

    private function wordCount(string $value): int
    {
        return str_word_count(trim($value));
    }

    /**
     * @param  array<int, string>  $reasons
     * @return array{status: string, label: string, summary: string, reasons: array<int, string>, approval_available: bool}
     */
    private function result(string $status, string $label, string $summary, array $reasons): array
    {
        return [
            'status' => $status,
            'label' => $label,
            'summary' => $summary,
            'reasons' => array_values(array_unique($reasons)),
            'approval_available' => $status === self::STATUS_GREEN,
        ];
    }
}
