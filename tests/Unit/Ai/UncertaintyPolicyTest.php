<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Integrity\UncertaintyPolicy;
use Tests\TestCase;

final class UncertaintyPolicyTest extends TestCase
{
    public function test_insufficient_data_quality_forces_high_uncertainty(): void
    {
        $this->assertSame(
            Uncertainty::High,
            app(UncertaintyPolicy::class)->derive('insufficient', 0.95),
        );
    }

    public function test_high_quality_and_high_confidence_can_have_no_uncertainty(): void
    {
        $this->assertSame(
            Uncertainty::None,
            app(UncertaintyPolicy::class)->derive('high', 0.95),
        );
    }

    public function test_medium_confidence_returns_medium_uncertainty(): void
    {
        $this->assertSame(
            Uncertainty::Medium,
            app(UncertaintyPolicy::class)->derive('high', 0.55),
        );
    }
}
