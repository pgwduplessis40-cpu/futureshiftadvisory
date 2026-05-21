<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Enums\EngagementType;
use App\Mail\ClientEmailFromApp;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Communications\EmailFromApp;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EmailFromAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_can_compose_and_send_email_that_is_logged_to_client_messages(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser();
        $client = $this->clientWithUsers($clientUser, $advisor);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.compose', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Compose')
                ->where('client.id', $client->id)
                ->has('recipients', 1)
                ->where('recipients.0.id', $clientUser->id));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.email.store', $client), [
                'recipient_user_ids' => [$clientUser->id],
                'subject' => 'Next steps',
                'body' => 'Here are the next steps from our review.',
            ])
            ->assertRedirect();

        Mail::assertSent(ClientEmailFromApp::class, function (ClientEmailFromApp $mail) use ($clientUser): bool {
            return $mail->subjectLine === 'Next steps'
                && $mail->hasTo($clientUser->email);
        });

        $message = Message::query()->where('channel', Message::CHANNEL_EMAIL)->firstOrFail();

        $this->assertSame(Message::DELIVERY_SENT, $message->delivery_state);
        $this->assertSame('Next steps', $message->email_subject);
        $this->assertSame($clientUser->email, $message->email_recipients[0]['email']);
        $this->assertSame(Message::DELIVERY_SENT, $message->email_recipients[0]['delivery_state']);
        $this->assertTrue($message->channel_decision['recipients'][0]['mail_now']);
        $this->assertDatabaseHas('message_threads', [
            'id' => $message->thread_id,
            'client_id' => $client->id,
        ]);
    }

    public function test_in_platform_only_recipient_is_logged_as_skipped_by_preference(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser();
        $clientUser->communicationPreference()->create([
            'channel' => CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);
        $client = $this->clientWithUsers($clientUser, $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.email.store', $client), [
                'recipient_user_ids' => [$clientUser->id],
                'subject' => 'Portal-only note',
                'body' => 'This should remain inside the platform.',
            ])
            ->assertRedirect();

        Mail::assertNothingSent();

        $message = Message::query()->where('channel', Message::CHANNEL_EMAIL)->firstOrFail();

        $this->assertSame(Message::DELIVERY_SKIPPED_PREFERENCE, $message->delivery_state);
        $this->assertSame('preference', $message->channel_decision['recipients'][0]['skipped_reason']);
        $this->assertFalse($message->channel_decision['recipients'][0]['mail_now']);
        $this->assertSame(CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY, $message->channel_decision['recipients'][0]['preference_channel']);
    }

    public function test_parallel_in_app_message_suppresses_email_delivery_for_same_logical_message(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser();
        $client = $this->clientWithUsers($clientUser, $advisor);
        $logicalKey = 'client-update-2026-05';

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());
        $thread = MessageThread::query()->create([
            'client_id' => $client->id,
            'created_by_user_id' => $advisor->getKey(),
            'subject' => 'Client update',
            'last_activity_at' => now(),
        ]);
        Message::query()->create([
            'thread_id' => $thread->id,
            'sender_user_id' => $advisor->getKey(),
            'channel' => Message::CHANNEL_IN_APP,
            'body' => 'The in-app version already exists.',
            'delivery_state' => Message::DELIVERY_SENT,
            'logical_message_key' => $logicalKey,
            'sent_at' => now(),
        ]);

        $first = app(EmailFromApp::class)->send(
            client: $client,
            sender: $advisor,
            recipientUserIds: [$clientUser->id],
            subject: 'Client update',
            body: 'The in-app version already exists.',
            logicalMessageKey: $logicalKey,
        );
        $second = app(EmailFromApp::class)->send(
            client: $client,
            sender: $advisor,
            recipientUserIds: [$clientUser->id],
            subject: 'Client update',
            body: 'The in-app version already exists.',
            logicalMessageKey: $logicalKey,
        );

        Mail::assertNothingSent();
        $this->assertTrue($first->is($second));
        $this->assertSame($thread->id, $first->thread_id);
        $this->assertSame(Message::DELIVERY_SKIPPED_PARALLEL_IN_APP, $first->delivery_state);
        $this->assertTrue($first->channel_decision['parallel_in_app_exists']);
        $this->assertSame(1, Message::query()->where('channel', Message::CHANNEL_EMAIL)->count());
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

    private function clientUser(string $email = 'client@example.com'): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        return $user;
    }

    private function clientWithUsers(User $clientUser, User $advisor, string $name = 'Email Test Limited'): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }
}
