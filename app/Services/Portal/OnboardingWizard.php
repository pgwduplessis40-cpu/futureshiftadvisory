<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\EngagementType;
use App\Models\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

final class OnboardingWizard
{
    public const STEP_WELCOME = 'welcome';

    public const STEP_IDENTITY = 'identity';

    public const STEP_BUSINESS_SNAPSHOT = 'business-snapshot';

    public const STEP_GOALS = 'goals';

    public const STEP_QUESTIONNAIRE = 'questionnaire';

    public const STEP_DOCUMENTS = 'documents';

    public const STEP_REVIEW = 'review-submit';

    /**
     * @return array<int, array{number:int, slug:string, title:string, description:string}>
     */
    public function steps(): array
    {
        return [
            ['number' => 1, 'slug' => self::STEP_WELCOME, 'title' => 'Welcome', 'description' => 'Confirm the onboarding path.'],
            ['number' => 2, 'slug' => self::STEP_IDENTITY, 'title' => 'Identity verification', 'description' => 'Confirm your account details.'],
            ['number' => 3, 'slug' => self::STEP_BUSINESS_SNAPSHOT, 'title' => 'Business snapshot', 'description' => 'Review registry details.'],
            ['number' => 4, 'slug' => self::STEP_GOALS, 'title' => 'Goals', 'description' => 'Capture immediate priorities.'],
            ['number' => 5, 'slug' => self::STEP_QUESTIONNAIRE, 'title' => 'Questionnaire', 'description' => 'Match the engagement questionnaire.'],
            ['number' => 6, 'slug' => self::STEP_DOCUMENTS, 'title' => 'Documents', 'description' => 'Prepare supporting files.'],
            ['number' => 7, 'slug' => self::STEP_REVIEW, 'title' => 'Review and submit', 'description' => 'Confirm the onboarding summary.'],
        ];
    }

    /**
     * @return array{number:int, slug:string, title:string, description:string}
     */
    public function step(string $slug): array
    {
        $step = collect($this->steps())->firstWhere('slug', $slug);
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

        $currentStep = (int) ($state['current_step'] ?? 1);
        $currentStep = max(1, min($this->totalSteps(), $currentStep));

        $completedSteps = array_values(array_filter(
            (array) ($state['completed_steps'] ?? []),
            fn (mixed $slug): bool => is_string($slug) && $this->hasStep($slug),
        ));

        return [
            'current_step' => $currentStep,
            'completed_steps' => array_values(array_unique($completedSteps)),
            'steps' => is_array($state['steps'] ?? null) ? $state['steps'] : [],
            'submitted_at' => $state['submitted_at'] ?? null,
            'updated_at' => $state['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveStep(Client $client, string $slug, array $payload, ?Carbon $now = null): array
    {
        $step = $this->step($slug);
        $state = $this->state($client);
        $completed = array_values(array_unique([...$state['completed_steps'], $slug]));
        $nextStep = (int) $step['number'] >= $this->totalSteps()
            ? $this->totalSteps()
            : max((int) $state['current_step'], (int) $step['number'] + 1);

        $state['steps'][$slug] = $payload;
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
     * @return array<int, array<string, mixed>>
     */
    public function navigation(Client $client): array
    {
        $state = $this->state($client);

        return array_map(function (array $step) use ($state): array {
            $number = (int) $step['number'];
            $completed = in_array($step['slug'], $state['completed_steps'], true);
            $locked = $number > (int) $state['current_step'];

            return [
                ...$step,
                'href' => route('portal.onboarding.step', ['step' => $step['slug']]),
                'completed' => $completed,
                'locked' => $locked,
                'status' => $completed ? 'completed' : ($locked ? 'locked' : 'current'),
            ];
        }, $this->steps());
    }

    /**
     * @return array{completed:int, total:int, percentage:int}
     */
    public function progress(Client $client): array
    {
        $completed = count($this->state($client)['completed_steps']);
        $total = $this->totalSteps();

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
                'description' => 'WO-17 adds the live question engine. This step reserves the standard advisory questionnaire path.',
            ],
            EngagementType::DUE_DILIGENCE => [
                'set' => 'dd_specific',
                'title' => 'Due Diligence Questionnaire',
                'available' => false,
                'phase' => 'Phase 3',
                'description' => 'Due diligence questionnaire content is gated until the Phase 3 virtual data room work.',
            ],
            EngagementType::POST_ACQUISITION_ADVISORY => [
                'set' => 'post_acquisition_gap',
                'title' => 'Post-acquisition Gap Questionnaire',
                'available' => false,
                'phase' => 'Phase 3',
                'description' => 'Post-acquisition questionnaire content is gated until the Phase 3 advisory expansion.',
            ],
            EngagementType::ENTREPRENEUR_MODULE => [
                'set' => 'entrepreneur_readiness',
                'title' => 'Entrepreneur Readiness Questionnaire',
                'available' => false,
                'phase' => 'Phase 3',
                'description' => 'Entrepreneur readiness and idea validation questionnaires are part of the Phase 3 entrepreneur module.',
            ],
        };
    }

    public function currentStepSlug(Client $client): string
    {
        $currentStep = (int) $this->state($client)['current_step'];
        $step = Arr::first($this->steps(), fn (array $step): bool => (int) $step['number'] === $currentStep);

        return is_array($step) ? (string) $step['slug'] : self::STEP_WELCOME;
    }

    public function canAccess(Client $client, string $slug): bool
    {
        $step = $this->step($slug);

        return (int) $step['number'] <= (int) $this->state($client)['current_step'];
    }

    public function totalSteps(): int
    {
        return count($this->steps());
    }

    private function hasStep(string $slug): bool
    {
        return collect($this->steps())->contains(fn (array $step): bool => $step['slug'] === $slug);
    }
}
