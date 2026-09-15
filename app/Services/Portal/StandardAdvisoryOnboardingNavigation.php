<?php

declare(strict_types=1);

namespace App\Services\Portal;

final class StandardAdvisoryOnboardingNavigation
{
    public static function urlFor(string $momentumKey, string $fallback): string
    {
        $step = match ($momentumKey) {
            'goals' => OnboardingWizard::STEP_GOALS,
            'website' => OnboardingWizard::STEP_WEBSITE,
            'questionnaire' => OnboardingWizard::STEP_QUESTIONNAIRE,
            'evidence' => OnboardingWizard::STEP_DOCUMENTS,
            'onboarding' => OnboardingWizard::STEP_REVIEW,
            default => null,
        };

        return $step === null
            ? $fallback
            : route('portal.onboarding.step', ['step' => $step]);
    }
}
