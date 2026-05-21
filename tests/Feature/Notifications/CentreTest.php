<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\CommunicationPreference;
use App\Models\User;
use App\Notifications\ChannelAwareNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CentreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    public function test_shared_notification_summary_exposes_unread_and_urgent_counts(): void
    {
        $advisor = $this->advisor();

        Notification::send($advisor, new CentreTestNotification('Review ready'));
        Notification::send($advisor, new CentreTestNotification('Discrepancy flagged', 'urgent'));

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('notificationSummary.unread', 2)
                ->where('notificationSummary.urgent', 1)
                ->has('notificationSummary.latest', 2)
                ->where('notificationSummary.index_url', route('notifications.index', absolute: false)));
    }

    public function test_notifications_page_lists_items_and_bulk_marks_read(): void
    {
        $advisor = $this->advisor();

        Notification::send($advisor, new CentreTestNotification('First notice'));
        Notification::send($advisor, new CentreTestNotification('Second notice'));

        $this->actingAsMfa($advisor)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('notifications/Index')
                ->where('summary.unread', 2)
                ->has('notifications', 2));

        $this->actingAsMfa($advisor)
            ->patch(route('notifications.mark-all-read'))
            ->assertRedirect();

        $this->assertSame(0, $advisor->refresh()->unreadNotifications()->count());

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('notificationSummary.unread', 0)
                ->where('notificationSummary.urgent', 0));
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $advisor = $this->advisor('advisor@example.com');
        $other = $this->advisor('other@example.com');
        Notification::send($other, new CentreTestNotification('Private notice'));
        $notification = $other->notifications()->firstOrFail();

        $this->actingAsMfa($advisor)
            ->patch(route('notifications.mark-read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_urgent_items_show_preference_bypass_metadata(): void
    {
        $advisor = $this->advisor();
        $advisor->communicationPreference()->create([
            'channel' => CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            'frequency' => CommunicationPreference::FREQUENCY_WEEKLY,
            'timezone' => 'Pacific/Auckland',
        ]);

        Notification::send($advisor, new CentreTestNotification('Terms declined', 'urgent'));

        $this->actingAsMfa($advisor)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('notifications.0.urgency', 'urgent')
                ->where('notifications.0.channel_decision.bypassed_preference', true)
                ->where('notifications.0.channel_decision.mail_now', true));
    }

    private function advisor(string $email = 'advisor@example.com'): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }
}

final class CentreTestNotification extends ChannelAwareNotification
{
    public function __construct(
        private readonly string $title,
        private readonly string $urgency = 'normal',
    ) {}

    public function urgency(): string
    {
        return $this->urgency;
    }

    public function databaseType(): string
    {
        return 'test.notification_centre';
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
            'url' => route('dashboard', absolute: false),
        ];
    }
}
