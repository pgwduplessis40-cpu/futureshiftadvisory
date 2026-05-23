<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IdeaValidationService
{
    public function __construct(
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{problem:string,target_customer:string,solution:string,value_proposition:string,demand_signal:string,revenue_model:string}  $payload
     */
    public function evaluate(EntrepreneurProfile $profile, array $payload, User $actor): IdeaValidation
    {
        return DB::transaction(function () use ($profile, $payload, $actor): IdeaValidation {
            $pastPattern = $this->pastPlanPattern($profile, $payload);
            $prompt = new PromptEnvelope(
                id: 'entrepreneur.idea_validation',
                version: '2026-05-23',
                task: 'Evaluate entrepreneur concept viability against prior plan patterns without overstating certainty.',
                body: 'Assess problem, customer, solution, value proposition, demand, and revenue model. Return practical risks and cite past plan pattern context.',
                input: [
                    'profile_id' => $profile->getKey(),
                    'concept_summary' => $profile->concept_summary,
                    'idea' => $payload,
                    'past_plan_pattern' => $pastPattern,
                ],
                dataQualitySummary: [
                    'level' => 'entrepreneur_supplied',
                    'message' => 'Idea validation is based on founder-supplied information and aggregate prior-plan patterns.',
                ],
                sourceReferences: [
                    $pastPattern['source_reference'],
                ],
            );
            $response = $this->ai->analyse($prompt);
            $alerts = $this->viabilityAlerts($payload);

            $validation = IdeaValidation::query()->create([
                'entrepreneur_profile_id' => $profile->getKey(),
                'problem' => $payload['problem'],
                'target_customer' => $payload['target_customer'],
                'solution' => $payload['solution'],
                'value_proposition' => $payload['value_proposition'],
                'demand_signal' => $payload['demand_signal'],
                'revenue_model' => $payload['revenue_model'],
                'ai_evaluation' => [
                    'summary' => $response->text,
                    'model' => $response->model,
                    'prompt_id' => $prompt->id,
                    'prompt_hash' => $response->promptHash,
                    'uncertainty' => $response->uncertainty->value,
                    'attributions' => [
                        ...$response->attributions,
                        [
                            'claim' => 'Compared against aggregate past plan pattern context.',
                            'source_reference' => $pastPattern['source_reference'],
                        ],
                    ],
                    'past_plan_pattern' => $pastPattern,
                ],
                'viability_alerts' => $alerts,
                'evaluated_at' => now(),
                'evaluated_by_user_id' => $actor->getKey(),
            ]);

            $profile->forceFill([
                'stage' => EntrepreneurStage::IDEA_VALIDATION,
            ])->save();

            $this->audit->record('entrepreneur.idea_validated', subject: $validation, actor: $actor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'alert_count' => count($alerts),
                'plan_builder_unlocked' => false,
            ]);

            return $validation->refresh();
        });
    }

    public function passAdvisorGate(IdeaValidation $validation, User $advisor, string $note): IdeaValidation
    {
        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'advisor_gate_note' => 'An advisor note is required before the plan builder can open.',
            ]);
        }

        return DB::transaction(function () use ($validation, $advisor, $note): IdeaValidation {
            $validation->forceFill([
                'advisor_gate_passed_at' => now(),
                'advisor_gate_passed_by_user_id' => $advisor->getKey(),
                'advisor_gate_note' => $note,
            ])->save();

            $validation->entrepreneurProfile?->forceFill([
                'stage' => EntrepreneurStage::BUILDING_PHASE_1,
            ])->save();

            $this->audit->record('entrepreneur.idea_gate_passed', subject: $validation, actor: $advisor, after: [
                'entrepreneur_profile_id' => $validation->entrepreneur_profile_id,
                'advisor_gate_passed_at' => $validation->advisor_gate_passed_at?->toIso8601String(),
            ]);

            return $validation->refresh();
        });
    }

    public function planBuilderUnlocked(EntrepreneurProfile $profile): bool
    {
        return IdeaValidation::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->whereNotNull('advisor_gate_passed_at')
            ->exists();
    }

    /**
     * @param  array<string, string>  $payload
     * @return array{source_reference:string, cohort:int, industry:string, note:string}
     */
    private function pastPlanPattern(EntrepreneurProfile $profile, array $payload): array
    {
        $industry = $this->industryFromPayload($payload);
        $cohort = BusinessPlan::query()
            ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
            ->where('status', BusinessPlan::STATUS_FOUNDING)
            ->whereJsonContains('founding_advisory_payload->industry', $industry)
            ->count();

        return [
            'source_reference' => 'past_plan_patterns:'.$industry,
            'cohort' => $cohort,
            'industry' => $industry,
            'note' => $cohort > 0
                ? "Compared against {$cohort} prior finalised entrepreneur plan(s) in {$industry}."
                : "No prior finalised entrepreneur plans are available for {$industry}; use founder evidence cautiously.",
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function industryFromPayload(array $payload): string
    {
        $text = strtolower(implode(' ', $payload));

        return str_contains($text, 'retail')
            ? 'retail'
            : (str_contains($text, 'hospitality') ? 'hospitality' : 'general');
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<int, array{severity:string,type:string,message:string,blocking:bool}>
     */
    private function viabilityAlerts(array $payload): array
    {
        $alerts = [];

        foreach ([
            'problem' => 'Clarify the customer problem before investing in solution detail.',
            'target_customer' => 'Define a narrower target customer segment.',
            'solution' => 'Describe the concrete solution more specifically.',
            'value_proposition' => 'Explain why the customer would choose this over alternatives.',
            'demand_signal' => 'Add stronger demand evidence before launch.',
            'revenue_model' => 'Explain how revenue will be earned and collected.',
        ] as $field => $message) {
            if (str_word_count(trim((string) ($payload[$field] ?? ''))) < 4) {
                $alerts[] = [
                    'severity' => 'informational',
                    'type' => $field.'_weakness',
                    'message' => $message,
                    'blocking' => false,
                ];
            }
        }

        if (str_contains(strtolower($payload['demand_signal'] ?? ''), 'none')) {
            $alerts[] = [
                'severity' => 'informational',
                'type' => 'demand_not_evidenced',
                'message' => 'Demand is not yet evidenced; validate before committing material spend.',
                'blocking' => false,
            ];
        }

        return $alerts;
    }
}
