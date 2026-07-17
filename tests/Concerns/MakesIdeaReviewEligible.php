<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\IdeaValidation;
use App\Services\Entrepreneurs\IdeaViabilityGate;

trait MakesIdeaReviewEligible
{
    protected function completedIdeaReview(IdeaValidation $validation, bool $withMinimumEvidence = true): IdeaValidation
    {
        $evaluation = is_array($validation->ai_evaluation) ? $validation->ai_evaluation : [];
        $metadata = is_array(data_get($evaluation, 'metadata')) ? data_get($evaluation, 'metadata') : [];

        unset(
            $metadata['degraded'],
            $metadata['refresh_failure'],
            $metadata['refresh_failed_at'],
            $metadata['unavailable_reason'],
        );

        $metadata['refresh_status'] = 'completed';
        data_set($evaluation, 'metadata', $metadata);
        data_set($evaluation, 'model', 'test-completed-idea-review');
        data_set($evaluation, 'summary', 'A completed test review confirms the submitted evidence is ready for builder-gate assessment.');
        data_set($evaluation, 'uncertainty', 'low');

        $attributes = ['ai_evaluation' => $evaluation];

        if ($withMinimumEvidence) {
            foreach ([
                'problem' => 'Regional service owners lose time coordinating essential operational work across disconnected tools.',
                'target_customer' => 'Owner-managed regional service businesses with small teams and recurring customer work.',
                'solution' => 'A guided workflow that coordinates operations, customer follow-up, and team priorities in one place.',
                'value_proposition' => 'Owners save administrative time and deliver more reliable service without replacing their existing tools.',
            ] as $field => $fallback) {
                if (str_word_count((string) $validation->{$field}) < 6) {
                    $attributes[$field] = $fallback;
                }
            }

            $gate = app(IdeaViabilityGate::class);
            if (! $gate->hasSufficientDemandEvidence(['demand_signal' => $validation->demand_signal])) {
                $attributes['demand_signal'] = 'Eight customer interviews and two paid pilots confirmed recurring demand for the workflow.';
            }

            if (! $gate->hasCommercialRevenueModel((string) $validation->revenue_model)) {
                $attributes['revenue_model'] = 'Monthly subscription pricing plus a setup fee charged per customer business.';
            }

            $attributes['viability_alerts'] = [];
        }

        $validation->forceFill($attributes)->save();

        return $validation->refresh();
    }
}
