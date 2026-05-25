<?php

declare(strict_types=1);

namespace Tests\Unit\Privacy;

use App\Services\Privacy\CohortGuard;
use Tests\TestCase;

final class CohortGuardTest extends TestCase
{
    public function test_it_suppresses_below_minimum_cohort(): void
    {
        config()->set('privacy.min_cohort', 5);

        $payload = app(CohortGuard::class)->releaseAggregate(
            cohortSize: 4,
            aggregate: ['average_score' => 72, 'distribution' => ['strong' => 4]],
            suppressedMessage: 'Too few members.',
        );

        $this->assertTrue($payload['suppressed']);
        $this->assertSame(5, $payload['minimum_cohort']);
        $this->assertSame('Too few members.', $payload['message']);
        $this->assertArrayNotHasKey('distribution', $payload);
        $this->assertArrayNotHasKey('average_score', $payload);
    }

    public function test_it_releases_aggregate_only_payloads_without_identifiers_or_extremes(): void
    {
        config()->set('privacy.min_cohort', 5);

        $payload = app(CohortGuard::class)->releaseAggregate(
            cohortSize: 5,
            aggregate: [
                'average_score' => 72,
                'distribution' => ['strong' => 5],
                'values' => [70, 71, 72, 73, 74],
                'min' => 70,
                'max' => 74,
                'client_ids' => ['client-1'],
                'business_plan_ids' => ['plan-1'],
            ],
            metadata: ['industry' => 'retail'],
        );

        $this->assertFalse($payload['suppressed']);
        $this->assertSame(5, $payload['cohort_size']);
        $this->assertSame('retail', $payload['industry']);
        $this->assertSame(['strong' => 5], $payload['distribution']);
        $this->assertTrue($payload['privacy']['aggregate_only']);
        $this->assertArrayNotHasKey('values', $payload);
        $this->assertArrayNotHasKey('min', $payload);
        $this->assertArrayNotHasKey('max', $payload);
        $this->assertArrayNotHasKey('client_ids', $payload);
        $this->assertArrayNotHasKey('business_plan_ids', $payload);
    }
}
