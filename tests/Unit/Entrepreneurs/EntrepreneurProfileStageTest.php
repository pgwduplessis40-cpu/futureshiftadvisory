<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use PHPUnit\Framework\TestCase;

final class EntrepreneurProfileStageTest extends TestCase
{
    public function test_current_stage_uses_valid_enum_value(): void
    {
        $profile = new EntrepreneurProfile;
        $profile->setRawAttributes(['stage' => EntrepreneurStage::IDEA_VALIDATION->value], true);

        self::assertSame(EntrepreneurStage::IDEA_VALIDATION, $profile->currentStage());
        self::assertSame(EntrepreneurStage::IDEA_VALIDATION->value, $profile->currentStageValue());
        self::assertSame(EntrepreneurStage::IDEA_VALIDATION->label(), $profile->currentStageLabel());
    }

    public function test_current_stage_falls_back_for_legacy_stage_value(): void
    {
        $profile = new EntrepreneurProfile;
        $profile->setRawAttributes(['stage' => 'invite_accepted'], true);

        self::assertSame(EntrepreneurStage::ONBOARDING, $profile->currentStage());
        self::assertSame(EntrepreneurStage::ONBOARDING->value, $profile->currentStageValue());
        self::assertSame(EntrepreneurStage::ONBOARDING->label(), $profile->currentStageLabel());
    }
}
