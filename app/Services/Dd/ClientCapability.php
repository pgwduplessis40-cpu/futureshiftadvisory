<?php

declare(strict_types=1);

namespace App\Services\Dd;

final class ClientCapability
{
    /**
     * @param  array<string, mixed>  $intake
     * @return array<string, mixed>
     */
    public function fromIntake(array $intake, string $capturedFrom): array
    {
        $ddExperience = (string) ($intake['dd_experience'] ?? 'first_time');
        $ownershipExperience = (string) ($intake['business_ownership_experience'] ?? 'none');
        $financialConfidence = (string) ($intake['financial_confidence'] ?? 'low');
        $preferredGuidance = (string) ($intake['preferred_guidance'] ?? 'guided');

        $experiencedSignals = [
            $ddExperience === 'completed_before',
            $ownershipExperience === 'bought_or_sold_business',
            in_array($ownershipExperience, ['managed_business', 'owned_business'], true)
                && $financialConfidence === 'high',
            $preferredGuidance === 'fast_track',
        ];
        $guidedSignals = [
            $ddExperience === 'first_time',
            $ownershipExperience === 'none',
            $financialConfidence === 'low',
            $preferredGuidance === 'guided',
        ];

        $mode = in_array(true, $experiencedSignals, true)
            && ! in_array(true, $guidedSignals, true)
            ? 'experienced'
            : 'guided';

        return [
            'mode' => $mode,
            'support_level' => $mode === 'experienced' ? 'fast_track' : 'guided',
            'label' => $mode === 'experienced' ? 'Experienced DD support' : 'Guided DD support',
            'dd_experience' => $ddExperience,
            'business_ownership_experience' => $ownershipExperience,
            'financial_confidence' => $financialConfidence,
            'preferred_guidance' => $preferredGuidance,
            'captured_from' => $capturedFrom,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $capability
     */
    public function needsConfirmation(array $capability): bool
    {
        if (! in_array(($capability['mode'] ?? null), ['guided', 'experienced'], true)) {
            return true;
        }

        foreach (['dd_experience', 'business_ownership_experience', 'financial_confidence', 'preferred_guidance'] as $key) {
            if (! isset($capability[$key]) || ! is_string($capability[$key]) || trim($capability[$key]) === '') {
                return true;
            }
        }

        return false;
    }
}
