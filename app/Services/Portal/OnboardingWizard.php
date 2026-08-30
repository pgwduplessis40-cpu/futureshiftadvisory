<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\NpoEngagement;
use App\Services\Dd\ClientCapability;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

final class OnboardingWizard
{
    private const JOURNEY_VERSION = 3;

    public function __construct(private readonly ClientCapability $clientCapability) {}

    public const STEP_WELCOME = 'welcome';

    /** @deprecated Identity verification is handled by account security controls. */
    public const STEP_IDENTITY = 'identity';

    /** @deprecated Registry data is maintained in the advisor workspace. */
    public const STEP_BUSINESS_SNAPSHOT = 'business-snapshot';

    public const STEP_GOALS = 'goals';

    public const STEP_WEBSITE = 'website';

    public const STEP_DD_SUPPORT = 'dd-support-level';

    public const STEP_QUESTIONNAIRE = 'questionnaire';

    public const STEP_DOCUMENTS = 'documents';

    public const STEP_REVIEW = 'review-submit';

    /**
     * @return array<int, array{number:int, slug:string, title:string, description:string}>
     */
    public function steps(Client $client): array
    {
        $steps = [
            ['slug' => self::STEP_WELCOME, 'title' => 'Welcome', 'description' => 'Confirm the onboarding path.'],
        ];

        if ($this->isDueDiligenceClient($client)) {
            $steps = [
                ...$steps,
                ['slug' => self::STEP_DD_SUPPORT, 'title' => 'DD support level', 'description' => 'Choose the due diligence support path.'],
                ['slug' => self::STEP_QUESTIONNAIRE, 'title' => 'Questionnaire', 'description' => 'Answer the due diligence questions.'],
                ['slug' => self::STEP_DOCUMENTS, 'title' => 'Documents', 'description' => 'Prepare acquisition evidence.'],
                ['slug' => self::STEP_REVIEW, 'title' => 'Review and submit', 'description' => 'Confirm the due diligence onboarding summary.'],
            ];
        } else {
            $steps = [
                ...$steps,
                ['slug' => self::STEP_GOALS, 'title' => 'Goals', 'description' => 'Capture immediate priorities.'],
                ['slug' => self::STEP_WEBSITE, 'title' => 'Website', 'description' => 'Share your public website.'],
                ['slug' => self::STEP_QUESTIONNAIRE, 'title' => 'Questionnaire', 'description' => 'Match the engagement questionnaire.'],
                ['slug' => self::STEP_DOCUMENTS, 'title' => 'Documents', 'description' => 'Prepare supporting files.'],
                ['slug' => self::STEP_REVIEW, 'title' => 'Review and submit', 'description' => 'Confirm the onboarding summary.'],
            ];
        }

        return array_map(
            fn (array $step, int $index): array => ['number' => $index + 1, ...$step],
            $steps,
            array_keys($steps),
        );
    }

    /**
     * @return array{number:int, slug:string, title:string, description:string}
     */
    public function step(Client $client, string $slug): array
    {
        $step = collect($this->steps($client))->firstWhere('slug', $slug);
        abort_unless(is_array($step), 404);

        return $step;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(Client $client): array
    {
        $state = is_array($client->onboarding_wizard_state)
            ? $client->onboarding_wizard_state
            : [];

        $journeyVersion = (int) ($state['journey_version'] ?? 1);
        $completedSteps = array_values(array_filter(
            (array) ($state['completed_steps'] ?? []),
            fn (mixed $slug): bool => is_string($slug) && $this->hasStep($client, $slug),
        ));

        if ($this->isDueDiligenceClient($client) && $this->dueDiligenceSupport($client)['confirmed']) {
            $completedSteps[] = self::STEP_DD_SUPPORT;
        }

        $completedSteps = array_values(array_unique($completedSteps));
        $currentStep = match (true) {
            $journeyVersion < 2 => $this->currentStepForLegacyJourney($state),
            $journeyVersion < self::JOURNEY_VERSION && $this->isDueDiligenceClient($client) => $this->nextIncompleteStepNumber($client, $completedSteps, 1),
            default => (int) ($state['current_step'] ?? 1),
        };
        $currentStep = max(1, min($this->totalSteps($client), $currentStep));

        return [
            'journey_version' => self::JOURNEY_VERSION,
            'current_step' => $currentStep,
            'completed_steps' => array_values(array_unique($completedSteps)),
            'steps' => is_array($state['steps'] ?? null) ? $state['steps'] : [],
            'drafts' => is_array($state['drafts'] ?? null) ? $state['drafts'] : [],
            'submitted_at' => $state['submitted_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveStep(Client $client, string $slug, array $payload, ?Carbon $now = null): array
    {
        $step = $this->step($client, $slug);
        $state = $this->state($client);
        $completed = array_values(array_unique([...$state['completed_steps'], $slug]));
        $nextStep = max(
            (int) $state['current_step'],
            $this->nextIncompleteStepNumber($client, $completed, (int) $step['number'] + 1),
        );

        $state['steps'][$slug] = $payload;
        unset($state['drafts'][$slug]);
        $state['journey_version'] = self::JOURNEY_VERSION;
        $state['completed_steps'] = $completed;
        $state['current_step'] = $nextStep;
        $state['updated_at'] = ($now ?? now())->toIso8601String();

        if ($slug === self::STEP_REVIEW) {
            $state['submitted_at'] = ($now ?? now())->toIso8601String();
        }

        $client->forceFill([
            'onboarding_wizard_state' => $state,
        ])->save();

        return $state;
    }

    /**
     * Save an incomplete step without making it available to the next onboarding stage.
     *
     * @return array<string, mixed>
     */
    public function saveDraft(Client $client, string $slug, array $payload, ?Carbon $now = null): array
    {
        $this->step($client, $slug);

        $state = $this->state($client);
        $savedAt = ($now ?? now())->toIso8601String();
        $state['drafts'][$slug] = [
            'payload' => $payload,
            'saved_at' => $savedAt,
        ];
        $state['journey_version'] = self::JOURNEY_VERSION;
        $state['updated_at'] = $savedAt;

        $client->forceFill([
            'onboarding_wizard_state' => $state,
        ])->save();

        return $state;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function navigation(Client $client): array
    {
        $state = $this->state($client);

        return array_map(function (array $step) use ($state): array {
            $number = (int) $step['number'];
            $completed = in_array($step['slug'], $state['completed_steps'], true);
            $locked = ! $completed && $number > (int) $state['current_step'];

            return [
                ...$step,
                'href' => route('portal.onboarding.step', ['step' => $step['slug']]),
                'completed' => $completed,
                'locked' => $locked,
                'status' => $completed ? 'completed' : ($locked ? 'locked' : 'current'),
            ];
        }, $this->steps($client));
    }

    /**
     * @return array{completed:int, total:int, percentage:int}
     */
    public function progress(Client $client): array
    {
        $completed = count($this->state($client)['completed_steps']);
        $total = $this->totalSteps($client);

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => (int) round(($completed / $total) * 100),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionnaire(Client $client): array
    {
        $type = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);

        return match ($type) {
            EngagementType::STANDARD_ADVISORY => [
                'set' => 'standard_advisory',
                'title' => 'Standard Advisory Questionnaire',
                'available' => true,
                'phase' => 'Phase 1',
                'description' => 'Complete the Standard Advisory questionnaire for the current engagement.',
            ],
            EngagementType::DUE_DILIGENCE => [
                'set' => 'dd_specific',
                'title' => 'Due Diligence Questionnaire',
                'available' => $this->dueDiligenceEngagement($client) instanceof DdEngagement,
                'phase' => 'Phase 3',
                'description' => 'Complete the due diligence questionnaire so the advisor can assess the acquisition target and generate DD advice.',
            ],
            EngagementType::POST_ACQUISITION_ADVISORY => [
                'set' => 'post_acquisition_gap',
                'title' => 'Post-acquisition Gap Questionnaire',
                'available' => true,
                'phase' => 'Phase 3',
                'description' => 'Review DD-prefilled answers and complete the remaining post-close gaps before advisory work begins.',
            ],
            EngagementType::ENTREPRENEUR_MODULE => [
                'set' => 'entrepreneur_readiness',
                'title' => 'Entrepreneur Readiness Questionnaire',
                'available' => false,
                'phase' => 'Phase 3',
                'description' => 'Entrepreneur readiness and idea validation questionnaires are part of the Phase 3 entrepreneur module.',
            ],
            EngagementType::NPO => $this->npoQuestionnaire($client),
        };
    }

    /**
     * @return array{available:bool, confirmed:bool, label:string, dd_experience:?string, business_ownership_experience:?string, financial_confidence:?string, preferred_guidance:?string}
     */
    public function dueDiligenceSupport(Client $client): array
    {
        $engagement = $this->dueDiligenceEngagement($client);
        $capability = (array) data_get($engagement?->target_details ?? [], 'client_capability', []);
        $confirmed = ! $this->clientCapability->needsConfirmation($capability);
        $mode = $capability['mode'] ?? 'guided';

        return [
            'available' => $engagement instanceof DdEngagement,
            'confirmed' => $engagement instanceof DdEngagement && $confirmed,
            'label' => $mode === 'experienced' ? 'Experienced DD support' : 'Guided DD support',
            'dd_experience' => is_string($capability['dd_experience'] ?? null) ? $capability['dd_experience'] : null,
            'business_ownership_experience' => is_string($capability['business_ownership_experience'] ?? null) ? $capability['business_ownership_experience'] : null,
            'financial_confidence' => is_string($capability['financial_confidence'] ?? null) ? $capability['financial_confidence'] : null,
            'preferred_guidance' => is_string($capability['preferred_guidance'] ?? null) ? $capability['preferred_guidance'] : null,
        ];
    }

    /**
     * @param  array<string, string>  $intake
     */
    public function saveDueDiligenceSupport(Client $client, array $intake): void
    {
        $engagement = $this->dueDiligenceEngagement($client);
        abort_unless($engagement instanceof DdEngagement, 409, 'FSA needs to set up the due diligence engagement before you can choose a support level.');

        $targetDetails = $engagement->target_details ?? [];
        $targetDetails['client_capability'] = $this->clientCapability->fromIntake(
            $intake,
            'dd_onboarding',
        );

        $engagement->forceFill(['target_details' => $targetDetails])->save();
    }

    public function currentStepSlug(Client $client): string
    {
        $currentStep = (int) $this->state($client)['current_step'];
        $step = Arr::first($this->steps($client), fn (array $step): bool => (int) $step['number'] === $currentStep);

        return is_array($step) ? (string) $step['slug'] : self::STEP_WELCOME;
    }

    public function canAccess(Client $client, string $slug): bool
    {
        $step = $this->step($client, $slug);
        $state = $this->state($client);

        return in_array($slug, $state['completed_steps'], true)
            || (int) $step['number'] <= (int) $state['current_step'];
    }

    public function totalSteps(Client $client): int
    {
        return count($this->steps($client));
    }

    private function hasStep(Client $client, string $slug): bool
    {
        return collect($this->steps($client))->contains(fn (array $step): bool => $step['slug'] === $slug);
    }

    /**
     * Existing clients are redirected around the retired internal-only steps.
     *
     * @param  array<string, mixed>  $state
     */
    private function currentStepForLegacyJourney(array $state): int
    {
        $currentStep = (int) ($state['current_step'] ?? 1);

        return match (true) {
            $currentStep <= 1 => 1,
            $currentStep <= 4 => 2,
            default => 3,
        };
    }

    /**
     * @param  array<int, string>  $completedSteps
     */
    private function nextIncompleteStepNumber(Client $client, array $completedSteps, int $startingAt): int
    {
        $next = collect($this->steps($client))
            ->first(fn (array $candidate): bool => (int) $candidate['number'] >= $startingAt
                && ! in_array($candidate['slug'], $completedSteps, true));

        return is_array($next) ? (int) $next['number'] : $this->totalSteps($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function npoQuestionnaire(Client $client): array
    {
        if ($this->fullNpoEngagement($client) instanceof NpoEngagement) {
            return [
                'set' => QuestionnaireSet::STANDARD_NPO->value,
                'title' => 'Standard NPO Health Questionnaire',
                'available' => true,
                'phase' => 'Phase 5b',
                'description' => 'Complete the full NPO health questionnaire for the current engagement.',
            ];
        }

        return [
            'set' => QuestionnaireSet::GOVERNANCE_REVIEW->value,
            'title' => 'Governance Review Questionnaire',
            'available' => true,
            'phase' => 'Phase 5a',
            'description' => 'Complete the Governance Review questionnaire for the current NPO engagement.',
        ];
    }

    private function fullNpoEngagement(Client $client): ?NpoEngagement
    {
        return NpoEngagement::query()
            ->where('client_id', $client->getKey())
            ->whereIn('sub_type', [
                NpoEngagementSubType::StandardNpo->value,
                NpoEngagementSubType::SocialEnterprise->value,
            ])
            ->latest()
            ->first();
    }

    private function dueDiligenceEngagement(Client $client): ?DdEngagement
    {
        return DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
    }

    private function isDueDiligenceClient(Client $client): bool
    {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::tryFrom((string) $client->engagement_type);

        return $engagementType === EngagementType::DUE_DILIGENCE;
    }
}
