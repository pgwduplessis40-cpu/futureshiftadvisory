<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class PasswordResetNotificationFailureTest extends TestCase
{
    public function test_password_reset_delivery_failure_is_returned_as_form_error(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('audit_events')
            ->andReturn(false);

        $user = new class(['email' => 'client@example.test']) extends User
        {
            public function notify($instance)
            {
                throw new RuntimeException('Provider transport is unavailable.');
            }
        };

        try {
            $user->sendPasswordResetNotification('reset-token');
            $this->fail('Expected password reset delivery failure to raise a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertStringContainsString('could not send', $exception->errors()['email'][0]);
            $this->assertStringNotContainsString('Provider transport', $exception->errors()['email'][0]);
        }
    }
}
