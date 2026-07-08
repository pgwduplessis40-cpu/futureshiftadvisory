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
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final class IdeaValidationService implements ProvidesMethodology
{
    public static function methodologyIds(): array
    {
        return ['entrepreneur.idea_validation'];
    }

    public function __construct(
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
        private readonly EntrepreneurMilestones $milestones,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function evaluate(EntrepreneurProfile $profile, array $payload, User $actor): IdeaValidation
    {
        return DB::transaction(function () use ($profile, $payload, $actor): IdeaValidation {
            $evaluation = $this->evaluatePayload($profile, $payload);

            $validation = IdeaValidation::query()->create([
                'entrepreneur_profile_id' => $profile->getKey(),
                'problem' => (string) $payload['problem'],
                'target_customer' => (string) $payload['target_customer'],
                'solution' => (string) $payload['solution'],
                'value_proposition' => (string) $payload['value_proposition'],
                'demand_signal' => (string) $payload['demand_signal'],
                'revenue_model' => (string) $payload['revenue_model'],
                'ai_evaluation' => $evaluation['ai_evaluation'],
                'viability_alerts' => $evaluation['viability_alerts'],
                'evaluated_at' => now(),
                'evaluated_by_user_id' => $actor->getKey(),
            ]);

            $profile->forceFill([
                'stage' => EntrepreneurStage::IDEA_VALIDATION,
            ])->save();

            $this->audit->record('entrepreneur.idea_validated', subject: $validation, actor: $actor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'alert_count' => count($evaluation['viability_alerts']),
                'plan_builder_unlocked' => false,
            ]);

            return $validation->refresh();
        });
    }

    public function refreshEvaluation(IdeaValidation $validation, User $actor): IdeaValidation
    {
        return DB::transaction(function () use ($validation, $actor): IdeaValidation {
            $validation->loadMissing('entrepreneurProfile');
            $profile = $validation->entrepreneurProfile;

            if (! $profile instanceof EntrepreneurProfile) {
                throw new InvalidArgumentException('Idea validation must belong to an entrepreneur profile before it can be refreshed.');
            }

            $payload = [
                'problem' => (string) $validation->problem,
                'target_customer' => (string) $validation->target_customer,
                'solution' => (string) $validation->solution,
                'value_proposition' => (string) $validation->value_proposition,
                'demand_signal' => (string) $validation->demand_signal,
                'revenue_model' => (string) $validation->revenue_model,
            ];
            $experiments = data_get($validation->ai_evaluation, 'validation_evidence_loop.experiments', []);
            if (is_array($experiments) && $experiments !== []) {
                $payload['validation_experiments'] = $experiments;
            }

            $evaluation = $this->evaluatePayload($profile, $payload);
            $aiEvaluation = $evaluation['ai_evaluation'];
            data_set($aiEvaluation, 'metadata.refresh_status', 'completed');
            data_set($aiEvaluation, 'metadata.refresh_completed_at', now()->toIso8601String());

            $validation->forceFill([
                'ai_evaluation' => $aiEvaluation,
                'viability_alerts' => $evaluation['viability_alerts'],
                'evaluated_at' => now(),
                'evaluated_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record('entrepreneur.idea_validation_refreshed', subject: $validation, actor: $actor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'alert_count' => count($evaluation['viability_alerts']),
            ]);

            return $validation->refresh();
        });
    }

    public function markRefreshQueued(IdeaValidation $validation, User $actor): IdeaValidation
    {
        $evaluation = $validation->ai_evaluation ?? [];
        data_set($evaluation, 'metadata.refresh_status', 'queued');
        data_set($evaluation, 'metadata.refresh_requested_at', now()->toIso8601String());
        data_set($evaluation, 'metadata.refresh_requested_by_user_id', $actor->getKey());

        $validation->forceFill([
            'ai_evaluation' => $evaluation,
        ])->save();

        $this->audit->record('entrepreneur.idea_validation_refresh_queued', subject: $validation, actor: $actor, after: [
            'entrepreneur_profile_id' => $validation->entrepreneur_profile_id,
        ]);

        return $validation->refresh();
    }

    public function markRefreshRunning(IdeaValidation $validation, User $actor): IdeaValidation
    {
        $evaluation = $validation->ai_evaluation ?? [];
        data_set($evaluation, 'metadata.refresh_status', 'running');
        data_set($evaluation, 'metadata.refresh_started_at', now()->toIso8601String());
        data_set($evaluation, 'metadata.refresh_started_by_user_id', $actor->getKey());

        $validation->forceFill([
            'ai_evaluation' => $evaluation,
        ])->save();

        $this->audit->record('entrepreneur.idea_validation_refresh_started', subject: $validation, actor: $actor, after: [
            'entrepreneur_profile_id' => $validation->entrepreneur_profile_id,
        ]);

        return $validation->refresh();
    }

    public function markRefreshFailed(IdeaValidation $validation, User $actor, Throwable $exception): IdeaValidation
    {
        $evaluation = $validation->ai_evaluation ?? [];
        data_set($evaluation, 'metadata.refresh_status', 'failed');
        data_set($evaluation, 'metadata.refresh_failed_at', now()->toIso8601String());
        data_set($evaluation, 'metadata.refresh_failure', Str::limit($exception->getMessage(), 300));

        $validation->forceFill([
            'ai_evaluation' => $evaluation,
        ])->save();

        $this->audit->record('entrepreneur.idea_validation_refresh_failed', subject: $validation, actor: $actor, after: [
            'entrepreneur_profile_id' => $validation->entrepreneur_profile_id,
            'exception' => $exception::class,
        ]);

        return $validation->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ai_evaluation:array<string, mixed>, viability_alerts:array<int, array{severity:string,type:string,message:string,blocking:bool}>}
     */
    private function evaluatePayload(EntrepreneurProfile $profile, array $payload): array
    {
        $pastPattern = $this->pastPlanPattern($profile, $payload);
        $evidenceLoop = $this->validationEvidenceLoop($payload);
        $prompt = new PromptEnvelope(
            id: EntrepreneurPromptRegistry::IDEA_VALIDATION,
            version: '2026-05-23',
            task: 'Evaluate entrepreneur concept viability against prior plan patterns without overstating certainty.',
            body: 'Assess problem, customer, solution, value proposition, demand, and revenue model. Return practical risks and cite past plan pattern context.',
            input: [
                'profile_id' => $profile->getKey(),
                'concept_summary' => $profile->concept_summary,
                'idea' => $payload,
                'past_plan_pattern' => $pastPattern,
                'validation_evidence_loop' => $evidenceLoop,
            ],
            dataQualitySummary: [
                'level' => $evidenceLoop['status'] === 'experiments_recorded' ? 'founder_supplied_with_experiment_evidence' : 'entrepreneur_supplied',
                'message' => 'Idea validation is based on founder-supplied information, recorded experiments where available, and aggregate prior-plan patterns.',
            ],
            sourceReferences: [
                $pastPattern['source_reference'],
                $evidenceLoop['source_reference'],
            ],
        );
        $response = $this->ai->analyse($prompt);
        $alerts = $this->viabilityAlerts($payload, $evidenceLoop);

        return [
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
                    [
                        'claim' => 'Idea validation recorded the founder experiment and evidence loop status.',
                        'source_reference' => $evidenceLoop['source_reference'],
                    ],
                ],
                'past_plan_pattern' => $pastPattern,
                'validation_evidence_loop' => $evidenceLoop,
                'metadata' => $response->metadata,
            ],
            'viability_alerts' => $alerts,
        ];
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
            $this->milestones->awardIdeaValidated($validation->refresh()->load('entrepreneurProfile'));

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
     * @param  array<string, mixed>  $payload
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
     * @param  array<string, mixed>  $payload
     */
    private function industryFromPayload(array $payload): string
    {
        $text = strtolower(collect($payload)
            ->filter(fn (mixed $value): bool => is_scalar($value))
            ->map(fn (mixed $value): string => (string) $value)
            ->implode(' '));

        $industries = [
            'retail' => ['retail', 'store', 'shop', 'ecommerce', 'stockout', 'inventory'],
            'hospitality' => ['hospitality', 'restaurant', 'cafe', 'hotel', 'venue', 'food service'],
            'software' => ['software', 'saas', 'platform', 'app', 'subscription', 'workflow'],
            'trades' => ['trade', 'builder', 'electrician', 'plumber', 'hvac', 'maintenance'],
            'health' => ['health', 'clinic', 'wellbeing', 'patient', 'therapy'],
            'education' => ['education', 'training', 'course', 'learning', 'student'],
            'manufacturing' => ['manufacturing', 'factory', 'production', 'assembly'],
            'professional_services' => ['consulting', 'advisory', 'accounting', 'legal', 'agency'],
            'construction_property' => ['construction', 'property', 'developer', 'real estate'],
        ];

        foreach ($industries as $industry => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $industry;
                }
            }
        }

        return 'general';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{severity:string,type:string,message:string,blocking:bool}>
     */
    private function viabilityAlerts(array $payload, array $evidenceLoop): array
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

        if (str_contains(strtolower((string) ($payload['demand_signal'] ?? '')), 'none')) {
            $alerts[] = [
                'severity' => 'informational',
                'type' => 'demand_not_evidenced',
                'message' => 'Demand is not yet evidenced; validate before committing material spend.',
                'blocking' => false,
            ];
        }

        if (($evidenceLoop['status'] ?? null) !== 'experiments_recorded') {
            $alerts[] = [
                'severity' => 'informational',
                'type' => 'experiment_loop_missing',
                'message' => 'Record at least one customer experiment with hypothesis, evidence, result, and next step before committing material spend.',
                'blocking' => false,
            ];
        }

        return $alerts;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validationEvidenceLoop(array $payload): array
    {
        $experiments = collect((array) ($payload['experiments'] ?? $payload['validation_experiments'] ?? []))
            ->filter(fn (mixed $experiment): bool => is_array($experiment))
            ->map(fn (array $experiment): array => [
                'name' => trim((string) ($experiment['name'] ?? $experiment['label'] ?? 'Customer validation experiment')),
                'hypothesis' => trim((string) ($experiment['hypothesis'] ?? '')),
                'evidence' => trim((string) ($experiment['evidence'] ?? $experiment['observed_signal'] ?? '')),
                'result' => trim((string) ($experiment['result'] ?? $experiment['outcome'] ?? '')),
                'next_step' => trim((string) ($experiment['next_step'] ?? $experiment['next'] ?? '')),
                'status' => in_array($experiment['status'] ?? '', ['planned', 'running', 'completed', 'invalidated'], true)
                    ? (string) $experiment['status']
                    : 'planned',
            ])
            ->values()
            ->all();

        if ($experiments === []) {
            return [
                'status' => 'missing_experiments',
                'source_reference' => 'idea_validation:experiments:not_captured',
                'experiments' => [],
                'next_recommended_experiment' => 'Run a small customer discovery or paid-pilot test, record the hypothesis, evidence, result, and next decision.',
            ];
        }

        $completed = collect($experiments)->where('status', 'completed')->count();

        return [
            'status' => 'experiments_recorded',
            'source_reference' => 'idea_validation:experiments:founder_supplied',
            'experiment_count' => count($experiments),
            'completed_experiment_count' => $completed,
            'experiments' => $experiments,
            'next_recommended_experiment' => $completed > 0
                ? 'Use the strongest completed experiment to define the next commercial validation step.'
                : 'Complete at least one planned experiment before treating demand as evidenced.',
        ];
    }
}
