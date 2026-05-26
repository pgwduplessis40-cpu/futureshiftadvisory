<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Mail\NotificationDigestMail;
use App\Models\CommunicationPreference;
use App\Models\User;
use App\Notifications\ChannelAwareNotification;
use App\Services\Notifications\ChannelResolver;
use App\Services\Notifications\DigestDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ChannelResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_platform_weekly_user_never_receives_email_for_non_urgent_notification(): void
    {
        $user = $this->userWithPreference(
            channel: CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            frequency: CommunicationPreference::FREQUENCY_WEEKLY,
        );

        Notification::send($user, new TestChannelNotification('Review ready'));

        $notification = DB::table('notifications')->first();
        $decision = $this->decision($notification->channel_decision);

        $this->assertSame('normal', $notification->urgency);
        $this->assertSame(CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY, $decision['preference_channel']);
        $this->assertSame(CommunicationPreference::FREQUENCY_WEEKLY, $decision['frequency']);
        $this->assertFalse($decision['mail_now']);
        $this->assertFalse($decision['email_deferred']);
        $this->assertNotContains('mail', $decision['channels']);
    }

    public function test_urgent_notification_bypasses_channel_and_frequency_preferences(): void
    {
        $user = $this->userWithPreference(
            channel: CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            frequency: CommunicationPreference::FREQUENCY_WEEKLY,
        );
        $notification = new TestChannelNotification('Terms declined', urgency: 'urgent');

        $decision = app(ChannelResolver::class)->decisionFor($user, $notification);

        $this->assertSame('urgent', $decision->urgency);
        $this->assertTrue($decision->bypassedPreference);
        $this->assertTrue($decision->mailNow);
        $this->assertContains('mail', $decision->channels);
        $this->assertContains(ChannelResolver::DATABASE_CHANNEL, $decision->channels);
    }

    public function test_channel_decision_is_logged_on_database_notification(): void
    {
        $user = $this->userWithPreference(
            channel: CommunicationPreference::CHANNEL_BOTH,
            frequency: CommunicationPreference::FREQUENCY_IMMEDIATE,
        );

        Notification::send($user, new TestChannelNotification('Immediate notice'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'urgency' => 'normal',
        ]);

        $decision = $this->decision(DB::table('notifications')->value('channel_decision'));

        $this->assertSame(CommunicationPreference::CHANNEL_BOTH, $decision['preference_channel']);
        $this->assertTrue($decision['mail_now']);
        $this->assertFalse($decision['email_deferred']);
        $this->assertContains('mail', $decision['channels']);
    }

    public function test_daily_digest_dispatches_deferred_email_without_losing_future_window_notifications(): void
    {
        Mail::fake();
        $user = $this->userWithPreference(
            channel: CommunicationPreference::CHANNEL_BOTH,
            frequency: CommunicationPreference::FREQUENCY_DAILY,
        );

        Notification::send($user, new TestChannelNotification('First deferred notice'));
        Notification::send($user, new TestChannelNotification('Second deferred notice'));

        $sent = app(DigestDispatcher::class)->dispatch(CommunicationPreference::FREQUENCY_DAILY);

        $this->assertSame(2, $sent);
        Mail::assertSent(NotificationDigestMail::class, function (NotificationDigestMail $mail) use ($user): bool {
            return $mail->user->is($user)
                && $mail->frequency === CommunicationPreference::FREQUENCY_DAILY
                && count($mail->items) === 2;
        });

        $this->assertSame(0, $this->pendingDigestCount(CommunicationPreference::FREQUENCY_DAILY));

        Notification::send($user, new TestChannelNotification('Next window notice'));

        $this->assertSame(1, $this->pendingDigestCount(CommunicationPreference::FREQUENCY_DAILY));

        $sent = app(DigestDispatcher::class)->dispatch(CommunicationPreference::FREQUENCY_DAILY);

        $this->assertSame(1, $sent);
        Mail::assertSent(NotificationDigestMail::class, 2);
        $this->assertSame(0, $this->pendingDigestCount(CommunicationPreference::FREQUENCY_DAILY));
    }

    public function test_user_can_update_communication_preferences_from_settings(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAsMfa($user)
            ->put(route('communication.update'), [
                'channel' => CommunicationPreference::CHANNEL_EMAIL_ONLY,
                'frequency' => CommunicationPreference::FREQUENCY_WEEKLY,
                'timezone' => 'Pacific/Auckland',
            ])
            ->assertRedirect(route('communication.edit', absolute: false));

        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $user->id,
            'channel' => CommunicationPreference::CHANNEL_EMAIL_ONLY,
            'frequency' => CommunicationPreference::FREQUENCY_WEEKLY,
        ]);
    }

    public function test_entrepreneur_communication_settings_hide_delivery_channel(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);

        $this->actingAsMfa($user)
            ->get(route('communication.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/communication')
                ->where('canChooseChannel', false)
            );
    }

    public function test_entrepreneur_cannot_update_delivery_channel_from_settings(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $user->communicationPreference()->create([
            'channel' => CommunicationPreference::CHANNEL_BOTH,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);

        $this->actingAsMfa($user)
            ->put(route('communication.update'), [
                'channel' => CommunicationPreference::CHANNEL_EMAIL_ONLY,
                'frequency' => CommunicationPreference::FREQUENCY_WEEKLY,
                'timezone' => 'Pacific/Auckland',
            ])
            ->assertRedirect(route('communication.edit', absolute: false));

        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $user->id,
            'channel' => CommunicationPreference::CHANNEL_BOTH,
            'frequency' => CommunicationPreference::FREQUENCY_WEEKLY,
        ]);
    }

    private function userWithPreference(string $channel, string $frequency): User
    {
        $user = User::factory()->create();
        $user->communicationPreference()->create([
            'channel' => $channel,
            'frequency' => $frequency,
            'timezone' => 'Pacific/Auckland',
        ]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    private function pendingDigestCount(string $frequency): int
    {
        return DB::table('notifications')
            ->get()
            ->filter(function (object $row) use ($frequency): bool {
                $decision = $this->decision($row->channel_decision);

                return ($decision['email_deferred'] ?? false) === true
                    && ($decision['frequency'] ?? null) === $frequency
                    && ! isset($decision['digest_sent_at']);
            })
            ->count();
    }
}

final class TestChannelNotification extends ChannelAwareNotification
{
    public function __construct(
        private readonly string $title,
        private readonly string $urgency = 'normal',
    ) {}

    public function urgency(): string
    {
        return $this->urgency;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line('A Future Shift Advisory notification is ready.');
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => 'A Future Shift Advisory notification is ready.',
        ];
    }
}
