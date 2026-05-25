<?php

declare(strict_types=1);

namespace App\Services\Privacy;

final class CohortGuard
{
    /**
     * @var array<int, string>
     */
    private const FORBIDDEN_KEYS = [
        'id',
        'ids',
        'value',
        'values',
        'min',
        'max',
        'minimum',
        'maximum',
        'client_ids',
        'plan_ids',
        'business_plan_ids',
        'entrepreneur_profile_ids',
    ];

    public function minCohort(): int
    {
        return max(2, (int) config('privacy.min_cohort', 5));
    }

    public function allows(int $cohortSize): bool
    {
        return $cohortSize >= $this->minCohort();
    }

    /**
     * @return array<string, mixed>
     */
    public function suppress(?string $message = null): array
    {
        return [
            'suppressed' => true,
            'minimum_cohort' => $this->minCohort(),
            'message' => $message ?? 'Cohort is below the privacy threshold.',
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function releaseAggregate(
        int $cohortSize,
        array $aggregate,
        ?string $suppressedMessage = null,
        array $metadata = [],
    ): array {
        if (! $this->allows($cohortSize)) {
            return $this->suppress($suppressedMessage);
        }

        return [
            'suppressed' => false,
            'minimum_cohort' => $this->minCohort(),
            'cohort_size' => $cohortSize,
            ...$this->sanitise($metadata),
            ...$this->sanitise($aggregate),
            'privacy' => $this->privacyMetadata(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function privacyMetadata(): array
    {
        return [
            'aggregate_only' => true,
            'min_max_suppressed' => true,
            'per_entity_values_suppressed' => true,
            'identifiers_suppressed' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitise(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalisedKey = strtolower((string) $key);

            if ($this->isForbiddenKey($normalisedKey)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitise($value) : $value;
        }

        return $clean;
    }

    private function isForbiddenKey(string $key): bool
    {
        return in_array($key, self::FORBIDDEN_KEYS, true)
            || str_ends_with($key, '_id')
            || str_ends_with($key, '_ids')
            || str_starts_with($key, 'per_')
            || str_contains($key, 'raw_value');
    }
}
