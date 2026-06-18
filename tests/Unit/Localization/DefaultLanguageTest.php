<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Tests\TestCase;

final class DefaultLanguageTest extends TestCase
{
    public function test_application_defaults_to_new_zealand_english(): void
    {
        $this->assertSame('en_NZ', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('en_NZ', config('app.faker_locale'));
    }
}
