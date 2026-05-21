<?php

declare(strict_types=1);

namespace Tests\Feature\Offboarding;

use App\Console\Commands\SendReengagementReminders;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Notifications\OffboardingCompletedNotification;
use App\Notifications\ReengagementReminderNotification;
use App\Services\Clients\AdvisorClientCapacity;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OffboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');
        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                return "%PDF-1.4\n".strip_tags($html);
            }
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_advisor_can_open_offboarding_form(): void
    {
        [$advisor, $client, $clientUser] = $this->clientWithTeam();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.offboarding.create', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Offboard')
                ->where('client.id', $client->id)
                ->where('client.primary_contact_email', $clientUser->email)
                ->where('reengagementDays', 90)
                ->where('submitUrl', route('advisor.clients.offboarding.store', $client, absolute: false))
                ->where('latestOffboarding', null));
    }

    public function test_triggering_offboarding_creates_artifacts_notifies_client_and_releases_capacity(): void
    {
        Notification::fake();
        Config::set('fsa.offboarding.reengagement_days', 45);
        $this->travelTo(now()->setMicrosecond(0));
        [$advisor, $client, $clientUser] = $this->clientWithTeam();

        $this->assertSame(1, app(AdvisorClientCapacity::class)->summary($advisor)['active_count']);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.offboarding.store', $client), [
                'exit_interview_notes' => 'Owner is ready to pause advisory support.',
                'handover_notes' => 'Send final supplier checklist.',
                'privacy_acknowledged' => true,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $record = OffboardingRecord::query()->firstOrFail();

        $this->assertSame($client->id, $record->client_id);
        $this->assertSame($advisor->id, $record->triggered_by_user_id);
        $this->assertTrue($record->advisor_capacity_released);
        $this->assertSame(1, $record->advisor_capacity_before);
        $this->assertSame(0, $record->advisor_capacity_after);
        $this->assertSame(-1, $record->advisor_capacity_delta);
        $this->assertSame(now()->addDays(45)->toDateString(), $record->reengagement_due?->toDateString());
        $this->assertSame(0, app(AdvisorClientCapacity::class)->summary($advisor)['active_count']);

        foreach ($this->artifactPaths($record) as $path) {
            Storage::disk('secure_local')->assertExists($path);
            $this->assertStringContainsString('Phase 2 will enrich', Storage::disk('secure_local')->get($path));
        }

        Notification::assertSentTo($clientUser, OffboardingCompletedNotification::class);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'offboarding.triggered',
            'client_id' => $client->id,
        ]);
    }

    public function test_privacy_acknowledgement_is_required(): void
    {
        [$advisor, $client] = $this->clientWithTeam();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.offboarding.store', $client), [
                'exit_interview_notes' => 'Done.',
                'handover_notes' => 'Done.',
                'privacy_acknowledged' => false,
            ])
            ->assertSessionHasErrors('privacy_acknowledged');

        $this->assertDatabaseCount('offboarding_records', 0);
    }

    public function test_reengagement_reminder_command_sends_due_reminders_once(): void
    {
        Notification::fake();
        [$advisor, $client] = $this->clientWithTeam();

        $record = OffboardingRecord::query()->create([
            'client_id' => $client->id,
            'triggered_by_user_id' => $advisor->id,
            'status' => OffboardingRecord::STATUS_COMPLETED,
            'triggered_at' => now()->subDays(91),
            'final_report_path' => 'offboarding/final.pdf',
            'engagement_summary_path' => 'offboarding/summary.pdf',
            'handover_path' => 'offboarding/handover.pdf',
            'exit_interview_path' => 'offboarding/exit.pdf',
            'privacy_notice_path' => 'offboarding/privacy.pdf',
            'reengagement_due' => now()->subDay(),
            'advisor_capacity_released' => true,
            'metadata' => ['phase' => 1],
        ]);

        $this->artisan(SendReengagementReminders::class)
            ->assertSuccessful();

        Notification::assertSentTo($advisor, ReengagementReminderNotification::class);
        $this->assertNotNull($record->refresh()->reengagement_reminder_sent_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'offboarding.reengagement_reminder.sent',
            'client_id' => $client->id,
        ]);

        $this->artisan(SendReengagementReminders::class)
            ->assertSuccessful();

        Notification::assertSentToTimes($advisor, ReengagementReminderNotification::class, 1);
    }

    /**
     * @return array{0: User, 1: Client, 2: User}
     */
    private function clientWithTeam(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'name' => 'Client Owner',
            'email' => 'client.owner@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', []);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000300',
            'legal_name' => 'Offboarding Test Limited',
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

        return [$advisor, $client, $clientUser];
    }

    /**
     * @return array<int, string>
     */
    private function artifactPaths(OffboardingRecord $record): array
    {
        return [
            $record->final_report_path,
            $record->engagement_summary_path,
            $record->handover_path,
            $record->exit_interview_path,
            $record->privacy_notice_path,
        ];
    }
}
