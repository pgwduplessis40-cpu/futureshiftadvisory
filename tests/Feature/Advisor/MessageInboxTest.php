<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\MessageThreadParticipant;
use App\Models\ServiceActivation;
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

    public function test_read_inbound_messages_are_not_pending(): void
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
                ->where('messagesPending.total', 0)
                ->where('messagesPending.index_url', route('advisor.messages.index', ['filter' => 'pending'], absolute: false)));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.messages.index', ['filter' => 'pending']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/messages/Index')
                ->where('initial_filter', 'pending')
                ->where('counts.all', 1)
                ->where('counts.pending', 0)
                ->where('threads.0.subject', 'Founder needs a response')
                ->where('threads.0.pending', false)
                ->where('threads.0.unread_count', 0));
    }

    public function test_approved_service_request_no_longer_needs_advisor_attention(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => 'service-request-advisor@example.test',
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'email' => 'service-request-client@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Service Request Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $clientUser->getKey(),
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        $thread = MessageThread::query()->create([
            'client_id' => $client->getKey(),
            'created_by_user_id' => $clientUser->getKey(),
            'subject' => 'Service workspace request: Explore buying a business',
            'last_activity_at' => now(),
        ]);

        MessageThreadParticipant::query()->create([
            'thread_id' => $thread->getKey(),
            'user_id' => $advisor->getKey(),
            'last_read_at' => null,
        ]);

        Message::query()->create([
            'thread_id' => $thread->getKey(),
            'sender_user_id' => $clientUser->getKey(),
            'body' => 'Please approve this service workspace request.',
            'sent_at' => now(),
        ]);

        $activation = ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $clientUser->getKey(),
            'advisor_id' => $advisor->getKey(),
            'service_type' => ServiceActivation::SERVICE_DUE_DILIGENCE,
            'client_label' => 'Explore buying a business',
            'status' => ServiceActivation::STATUS_REQUESTED,
            'client_message_thread_id' => $thread->getKey(),
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('messagesPending.total', 1));

        $activation->forceFill([
            'approved_by_user_id' => $advisor->getKey(),
            'status' => ServiceActivation::STATUS_PACKAGE_SELECTED,
        ])->save();

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('messagesPending.total', 0));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.messages.index', ['filter' => 'pending']))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('counts.all', 1)
                ->where('counts.pending', 0)
                ->where('threads.0.pending', false));
    }
}
