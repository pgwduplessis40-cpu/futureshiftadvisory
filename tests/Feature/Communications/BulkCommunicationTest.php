<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Mail\BulkCommunicationMail;
use App\Models\BulkCommunication;
use App\Models\BulkCommunicationRecipient;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\Message;
use App\Models\User;
use App\Services\Communications\BulkCommunicationService;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BulkCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        app(RequestContext::class)->apply('system', []);
    }

    public function test_selected_and_all_client_audiences_create_expected_recipients(): void
    {
        $advisor = $this->advisor();
        $first = $this->clientWithContact('Selected Audience Limited', $advisor, $this->clientUser('selected@example.test'));
        $second = $this->clientWithContact('All Audience Limited', $advisor, $this->clientUser('all@example.test'));
        $paused = $this->clientWithContact(
            'Paused Audience Limited',
            $advisor,
            $this->clientUser('paused@example.test'),
            ClientStatus::PAUSED,
        );
        $service = app(BulkCommunicationService::class);
        $now = CarbonImmutable::parse('2026-05-23 10:00:00 Pacific/Auckland');

        $selected = $service->schedule($this->payload([
            'title' => 'Selected update',
            'audience_type' => BulkCommunication::AUDIENCE_SELECTED_CLIENTS,
            'selected_client_ids' => [$first->id],
            'scheduled_at' => $now,
        ]), $advisor);

        $service->sendDue($now);

        $this->assertSame(1, $selected->refresh()->recipients()->count());
        $this->assertDatabaseHas('bulk_communication_recipients', [
            'bulk_communication_id' => $selected->id,
            'client_id' => $first->id,
        ]);
        $this->assertDatabaseMissing('bulk_communication_recipients', [
            'bulk_communication_id' => $selected->id,
            'client_id' => $second->id,
        ]);

        $all = $service->schedule($this->payload([
            'title' => 'All update',
            'audience_type' => BulkCommunication::AUDIENCE_ALL_CLIENTS,
            'selected_client_ids' => [],
            'scheduled_at' => $now->addMinute(),
        ]), $advisor);

        $service->sendDue($now->addMinute());

        $clientIds = BulkCommunicationRecipient::query()
            ->where('bulk_communication_id', $all->id)
            ->pluck('client_id')
            ->all();

        $this->assertContains($first->id, $clientIds);
        $this->assertContains($second->id, $clientIds);
        $this->assertNotContains($paused->id, $clientIds);
    }

    public function test_channel_preferences_route_email_and_in_platform_deliveries(): void
    {
        $advisor = $this->advisor();
        $emailUser = $this->clientUser('email-only@example.test', CommunicationPreference::CHANNEL_EMAIL_ONLY);
        $platformUser = $this->clientUser('platform-only@example.test', CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY);
        $emailClient = $this->clientWithContact('Email Preference Limited', $advisor, $emailUser);
        $platformClient = $this->clientWithContact('Platform Preference Limited', $advisor, $platformUser);
        $service = app(BulkCommunicationService::class);
        $now = CarbonImmutable::parse('2026-05-23 11:00:00 Pacific/Auckland');

        $communication = $service->schedule($this->payload([
            'audience_type' => BulkCommunication::AUDIENCE_SELECTED_CLIENTS,
            'selected_client_ids' => [$emailClient->id, $platformClient->id],
            'scheduled_at' => $now,
        ]), $advisor);

        $service->sendDue($now);

        Mail::assertSent(BulkCommunicationMail::class, 1);
        Mail::assertSent(BulkCommunicationMail::class, function (BulkCommunicationMail $mail) use ($emailUser): bool {
            return $mail->hasTo($emailUser->email);
        });
        Mail::assertNotSent(BulkCommunicationMail::class, function (BulkCommunicationMail $mail) use ($platformUser): bool {
            return $mail->hasTo($platformUser->email);
        });

        $this->assertDatabaseHas('bulk_communication_recipients', [
            'bulk_communication_id' => $communication->id,
            'client_id' => $emailClient->id,
            'channel' => BulkCommunicationRecipient::CHANNEL_EMAIL,
            'preference_channel' => CommunicationPreference::CHANNEL_EMAIL_ONLY,
            'status' => BulkCommunicationRecipient::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('bulk_communication_recipients', [
            'bulk_communication_id' => $communication->id,
            'client_id' => $platformClient->id,
            'channel' => BulkCommunicationRecipient::CHANNEL_IN_PLATFORM,
            'preference_channel' => CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            'status' => BulkCommunicationRecipient::STATUS_SENT,
        ]);
        $this->assertSame(1, Message::query()->where('channel', Message::CHANNEL_IN_APP)->count());
    }

    public function test_scheduled_send_only_fires_when_due(): void
    {
        $advisor = $this->advisor();
        $client = $this->clientWithContact('Scheduled Audience Limited', $advisor, $this->clientUser('scheduled@example.test'));
        $service = app(BulkCommunicationService::class);
        $now = CarbonImmutable::parse('2026-05-23 12:00:00 Pacific/Auckland');

        $communication = $service->schedule($this->payload([
            'audience_type' => BulkCommunication::AUDIENCE_SELECTED_CLIENTS,
            'selected_client_ids' => [$client->id],
            'scheduled_at' => $now->addHour(),
        ]), $advisor);

        $this->assertCount(0, $service->sendDue($now));
        $this->assertSame(BulkCommunication::STATUS_SCHEDULED, $communication->refresh()->status);
        $this->assertSame(0, BulkCommunicationRecipient::query()->count());

        $this->assertCount(1, $service->sendDue($now->addHour()->addMinute()));
        $this->assertSame(BulkCommunication::STATUS_SENT, $communication->refresh()->status);
        $this->assertSame(1, BulkCommunicationRecipient::query()->count());
    }

    public function test_open_tracking_is_idempotent_and_updates_rate(): void
    {
        $advisor = $this->advisor();
        $client = $this->clientWithContact('Open Rate Limited', $advisor, $this->clientUser('open@example.test'));
        $service = app(BulkCommunicationService::class);
        $now = CarbonImmutable::parse('2026-05-23 13:00:00 Pacific/Auckland');

        $communication = $service->schedule($this->payload([
            'audience_type' => BulkCommunication::AUDIENCE_SELECTED_CLIENTS,
            'selected_client_ids' => [$client->id],
            'scheduled_at' => $now,
        ]), $advisor);

        $service->sendDue($now);
        $recipient = BulkCommunicationRecipient::query()->where('bulk_communication_id', $communication->id)->firstOrFail();

        $this->assertNotNull($recipient->open_token);
        $service->trackOpen((string) $recipient->open_token);
        $firstOpenedAt = $recipient->refresh()->opened_at?->toIso8601String();
        $service->trackOpen((string) $recipient->open_token);

        $communication = $communication->refresh();
        $this->assertSame($firstOpenedAt, $recipient->refresh()->opened_at?->toIso8601String());
        $this->assertSame(1, $communication->metrics['opens_count']);
        $this->assertEquals(1.0, $communication->metrics['open_rate']);
    }

    public function test_advisor_page_schedules_a_branded_template_batch(): void
    {
        $advisor = $this->advisor();
        $client = $this->clientWithContact('Route Batch Limited', $advisor, $this->clientUser('route-batch@example.test'));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.bulk-communications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/bulk-communications/Index')
                ->has('clients', 1)
                ->where('clients.0.id', $client->id)
                ->has('templates', 3));

        $this->actingAsMfa($advisor)
            ->post(route('advisor.bulk-communications.store'), $this->payload([
                'title' => 'Route update',
                'template_key' => BulkCommunication::TEMPLATE_ACTION_REQUIRED,
                'audience_type' => BulkCommunication::AUDIENCE_SELECTED_CLIENTS,
                'selected_client_ids' => [$client->id],
            ]))
            ->assertRedirect(route('advisor.bulk-communications.index'));

        $this->assertDatabaseHas('bulk_communications', [
            'title' => 'Route update',
            'template_key' => BulkCommunication::TEMPLATE_ACTION_REQUIRED,
            'audience_type' => BulkCommunication::AUDIENCE_SELECTED_CLIENTS,
        ]);
    }

    private function advisor(): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientUser(string $email, string $preferenceChannel = CommunicationPreference::CHANNEL_EMAIL_ONLY): User
    {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);
        $user->communicationPreference()->create([
            'channel' => $preferenceChannel,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);

        return $user;
    }

    private function clientWithContact(
        string $name,
        User $advisor,
        User $clientUser,
        ClientStatus $status = ClientStatus::ACTIVE,
    ): Client {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'status' => $status->value,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $clientUser->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Quarterly advisory update',
            'template_key' => BulkCommunication::TEMPLATE_GENERAL_UPDATE,
            'subject' => 'Quarterly advisory update',
            'body' => 'A short update from Future Shift Advisory.',
            'audience_type' => BulkCommunication::AUDIENCE_SELECTED_CLIENTS,
            'selected_client_ids' => [],
            'scheduled_at' => '2026-05-23 09:00:00',
        ], $overrides);
    }
}
