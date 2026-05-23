<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\Document;
use App\Models\DocumentExpiryReminder;
use App\Models\User;
use App\Services\Documents\DocumentExpiryReminderService;
use App\Support\RequestContext;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentExpiryReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        app(RequestContext::class)->apply('system', []);
    }

    public function test_reminders_are_sent_only_for_clean_documents_inside_the_lookahead_window(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser('document-client@example.test');
        $client = $this->client($advisor, $clientUser);
        $now = CarbonImmutable::parse('2026-05-23 09:00:00 Pacific/Auckland');

        $due = $this->document($client, $clientUser, $now->addDays(10), 'insurance-certificate.pdf');
        $this->document($client, $clientUser, $now->addDays(31), 'future-certificate.pdf');
        $this->document($client, $clientUser, $now->addDays(5), 'infected-certificate.pdf', Document::SCANNER_INFECTED);

        $sent = app(DocumentExpiryReminderService::class)->sendDue(30, $now);

        $this->assertSame(2, $sent);
        $this->assertSame(2, DocumentExpiryReminder::query()->count());
        $this->assertDatabaseHas('document_expiry_reminders', [
            'document_id' => $due->id,
            'user_id' => $advisor->id,
        ]);
        $this->assertDatabaseHas('document_expiry_reminders', [
            'document_id' => $due->id,
            'user_id' => $clientUser->id,
        ]);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_reminder_ledger_makes_scheduler_idempotent(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser('idempotent-client@example.test');
        $client = $this->client($advisor, $clientUser);
        $now = CarbonImmutable::parse('2026-05-23 10:00:00 Pacific/Auckland');
        $this->document($client, $clientUser, $now->addDays(3), 'expiring-contract.pdf');
        $service = app(DocumentExpiryReminderService::class);

        $this->assertSame(2, $service->sendDue(30, $now));
        $this->assertSame(0, $service->sendDue(30, $now->addHour()));

        $this->assertSame(2, DocumentExpiryReminder::query()->count());
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_notifications_are_routed_through_channel_preferences(): void
    {
        $advisor = $this->advisor();
        $advisor->communicationPreference()->create([
            'channel' => CommunicationPreference::CHANNEL_BOTH,
            'frequency' => CommunicationPreference::FREQUENCY_IMMEDIATE,
            'timezone' => 'Pacific/Auckland',
        ]);
        $clientUser = $this->clientUser(
            'platform-expiry@example.test',
            CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            CommunicationPreference::FREQUENCY_WEEKLY,
        );
        $client = $this->client($advisor, $clientUser);
        $now = CarbonImmutable::parse('2026-05-23 11:00:00 Pacific/Auckland');
        $this->document($client, $clientUser, $now->addDays(14), 'preference-certificate.pdf');

        app(DocumentExpiryReminderService::class)->sendDue(30, $now);

        $advisorDecision = $this->decision(DB::table('notifications')
            ->where('notifiable_id', $advisor->id)
            ->value('channel_decision'));
        $clientDecision = $this->decision(DB::table('notifications')
            ->where('notifiable_id', $clientUser->id)
            ->value('channel_decision'));

        $this->assertTrue($advisorDecision['mail_now']);
        $this->assertContains('mail', $advisorDecision['channels']);
        $this->assertFalse($clientDecision['mail_now']);
        $this->assertNotContains('mail', $clientDecision['channels']);
        $this->assertSame(CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY, $clientDecision['preference_channel']);
        $this->assertSame(CommunicationPreference::FREQUENCY_WEEKLY, $clientDecision['frequency']);
    }

    public function test_command_runs_reminder_service_with_configurable_window(): void
    {
        $advisor = $this->advisor();
        $clientUser = $this->clientUser('command-client@example.test');
        $client = $this->client($advisor, $clientUser);
        $now = CarbonImmutable::parse('2026-05-23 12:00:00 Pacific/Auckland');
        CarbonImmutable::setTestNow($now);
        $this->document($client, $clientUser, $now->addDays(7), 'command-certificate.pdf');

        $this->artisan('documents:expiry-reminders', ['--days' => 7])
            ->expectsOutput('2 document expiry reminders sent.')
            ->assertSuccessful();

        CarbonImmutable::setTestNow();
        $this->assertSame(2, DocumentExpiryReminder::query()->count());
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

    private function clientUser(
        string $email,
        string $channel = CommunicationPreference::CHANNEL_BOTH,
        string $frequency = CommunicationPreference::FREQUENCY_IMMEDIATE,
    ): User {
        $user = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);
        $user->communicationPreference()->create([
            'channel' => $channel,
            'frequency' => $frequency,
            'timezone' => 'Pacific/Auckland',
        ]);

        return $user;
    }

    private function client(User $advisor, User $clientUser): Client
    {
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => fake()->company().' Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'status' => ClientStatus::ACTIVE->value,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        foreach ([[$advisor, 'lead_advisor'], [$clientUser, 'primary_contact']] as [$user, $role]) {
            ClientTeamMember::query()->create([
                'client_id' => $client->getKey(),
                'user_id' => $user->getKey(),
                'role' => $role,
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ]);
        }

        return $client;
    }

    private function document(
        Client $client,
        User $uploader,
        CarbonImmutable $expiresAt,
        string $filename,
        string $scannerResult = Document::SCANNER_CLEAN,
    ): Document {
        return Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_INSURANCE_CERTIFICATE,
            'original_filename' => $filename,
            'stored_path' => 'documents/'.Str::uuid().'/'.$filename,
            'byte_size' => 2048,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', $filename),
            'uploaded_by_user_id' => $uploader->getKey(),
            'scanner_result' => $scannerResult,
            'expires_at' => $expiresAt,
        ]);
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
}
