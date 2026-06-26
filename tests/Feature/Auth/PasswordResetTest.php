<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\Auth\PasswordResetLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordResetLinkNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordResetLinkNotification::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordResetLinkNotification::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'New-password-123!',
                'password_confirmation' => 'New-password-123!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'New-password-123!',
            'password_confirmation' => 'New-password-123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_password_link_request_records_mailer_audit(): void
    {
        Config::set('mail.default', 'graph');
        Config::set('mail.from.address', 'pieter@futureshiftadvisory.nz');
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset-audit@example.test',
        ]);

        $this->post(route('password.email'), ['email' => $user->email]);

        $event = AuditEvent::query()
            ->where('action', 'auth.password_reset_link_sent')
            ->where('subject_id', (string) $user->getKey())
            ->firstOrFail();

        $this->assertSame('graph', $event->after['mailer'] ?? null);
        $this->assertTrue($event->after['mail_from_configured'] ?? false);
        $this->assertNotSame($user->email, $event->after['email_hash'] ?? null);
    }
}
