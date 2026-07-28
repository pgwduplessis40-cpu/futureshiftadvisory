<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MessageInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_read_inbound_messages_remain_visible_in_the_pending_inbox_filter(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'message-advisor@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $founder = User::factory()->withTwoFactor()->create([
            'email' => 'message-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $founder->assignRole(User::TYPE_ENTREPRENEUR);

        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $founder->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Message Founder',
            'email' => $founder->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'gamification_on' => true,
        ]);

        $thread = MessageThread::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'created_by_user_id' => $founder->getKey(),
            'subject' => 'Founder needs a response',
            'last_activity_at' => now()->subMinute(),
        ]);

        MessageThreadParticipant::query()->create([
            'thread_id' => $thread->getKey(),
            'user_id' => $advisor->getKey(),
            'last_read_at' => now(),
        ]);

        Message::query()->create([
            'thread_id' => $thread->getKey(),
            'sender_user_id' => $founder->getKey(),
            'body' => 'Could you please review this?',
            'sent_at' => now()->subMinute(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('messagesPending.total', 1)
                ->where('messagesPending.index_url', route('advisor.messages.index', ['filter' => 'pending'], absolute: false)));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.messages.index', ['filter' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/messages/Index')
                ->where('initial_filter', 'pending')
                ->where('counts.all', 1)
                ->where('counts.pending', 1)
                ->where('threads.0.subject', 'Founder needs a response')
                ->where('threads.0.pending', true)
                ->where('threads.0.unread_count', 0));
    }
}
