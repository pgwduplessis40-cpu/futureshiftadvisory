<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;

final class BusinessPlanIdentity
{
    /**
     * Resolve the organisation named in the plan without using the founder's name as a stand-in.
     *
     * @param  array<int, string>  $sourceBodies
     */
    public function businessName(EntrepreneurProfile $profile, ?BusinessPlan $plan = null, array $sourceBodies = []): ?string
    {
        $profile->loadMissing('client');

        $linkedClientName = trim((string) ($profile->client?->trading_name ?: $profile->client?->legal_name ?: ''));
        if ($this->usableName($linkedClientName)) {
            return $linkedClientName;
        }

        if ($plan instanceof BusinessPlan) {
            $plan->loadMissing('sections');
            $sourceBodies = [
                ...$sourceBodies,
                ...$plan->sections
                    ->map(fn (PlanSection $section): string => (string) $section->body)
                    ->all(),
            ];
        }

        $businessName = collect($sourceBodies)
            ->map(fn (mixed $body): ?string => $this->companyNameFromText((string) $body))
            ->filter()
            ->first();

        if (is_string($businessName) && $businessName !== '') {
            return $businessName;
        }

        $planTitle = trim((string) ($plan?->title ?? ''));

        return $this->usablePlanTitle($planTitle, $profile->name) ? $planTitle : null;
    }

    private function usableName(string $name): bool
    {
        return $name !== '' && ! str_starts_with(strtolower($name), 'invited client -');
    }

    private function usablePlanTitle(string $title, string $founderName): bool
    {
        $normalisedTitle = strtolower($title);
        if ($title === '' || str_contains($normalisedTitle, 'business plan') || str_contains($normalisedTitle, 'budget') || str_contains($normalisedTitle, 'runway')) {
            return false;
        }

        return strcasecmp($title, trim($founderName)) !== 0;
    }

    private function companyNameFromText(string $text): ?string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        if ($text === '') {
            return null;
        }

        $matched = preg_match(
            '/\b([A-Z][A-Za-z0-9&\'-]*(?:\s+(?:[A-Z][A-Za-z0-9&\'-]*|of|and|the)){0,7}\s+(?:Ltd|Limited|Incorporated|Inc|LLC|LLP|LP|Trust))\b/',
            $text,
            $matches,
        );

        return $matched === 1 ? trim((string) ($matches[1] ?? '')) : null;
    }
}
