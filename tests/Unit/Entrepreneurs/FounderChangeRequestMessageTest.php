<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\FounderChangeRequestMessage;
use Tests\TestCase;

final class FounderChangeRequestMessageTest extends TestCase
{
    public function test_it_addresses_the_founder_by_profile_first_name(): void
    {
        $message = app(FounderChangeRequestMessage::class)->build(
            new EntrepreneurProfile(['name' => 'Christo Louw']),
            ['Please strengthen the customer evidence before resubmitting.'],
        );

        $this->assertSame(
            "Dear Christo,\n\nPlease strengthen the customer evidence before resubmitting.",
            $message,
        );
    }

    public function test_it_falls_back_when_the_founder_name_is_unusable(): void
    {
        $message = app(FounderChangeRequestMessage::class)->fromAdvisorFeedback(
            new EntrepreneurProfile(['name' => 'founder@example.test']),
            'Please add a paid customer experiment.',
        );

        $this->assertStringStartsWith("Hello,\n\n", $message);
    }

    public function test_it_does_not_wrap_an_already_addressed_full_message(): void
    {
        $message = app(FounderChangeRequestMessage::class)->fromAdvisorFeedback(
            new EntrepreneurProfile(['name' => 'Christo Louw']),
            "Dear Christo,\n\nPlease update the validation with one more customer experiment.",
        );

        $this->assertSame(
            "Dear Christo,\n\nPlease update the validation with one more customer experiment.",
            $message,
        );
    }
}
