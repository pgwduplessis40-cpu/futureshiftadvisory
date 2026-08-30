<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\ServiceJourneyEnrollment;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ServiceJourneyRecognitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_enable_verified_service_journey_recognition_without_dashboard_write_side_effects(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();
        $this->completeVerifiedJourneyEvidence($client, $user);

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('journeyRecognition.available', true)
                ->where('journeyRecognition.enabled', false)
                ->where('journeyRecognition.service_key', EngagementType::STANDARD_ADVISORY->value)
                ->where('journeyRecognition.points.total', 0)
            );

        $this->assertDatabaseCount('service_journey_enrollments', 0);

        $this->actingAsMfa($user)
            ->post(route('portal.service-journey.preference'), [
                'service_key' => EngagementType::STANDARD_ADVISORY->value,
                'recognition_enabled' => true,
            ])
            ->assertRedirect(route('portal.dashboard', ['client' => $client->getKey()], absolute: false));

        $enrollment = ServiceJourneyEnrollment::query()->firstOrFail();

        $this->assertTrue($enrollment->recognition_enabled);
        $this->assertSame(5, $enrollment->milestoneAwards()->count());
        $this->assertSame(3, $enrollment->pointEvents()->count());
        $this->assertSame(140, (int) $enrollment->pointEvents()->sum('points'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'service_journey.recognition_enabled',
            'subject_id' => $enrollment->getKey(),
        ]);

        $this->actingAsMfa($user)
            ->post(route('portal.service-journey.preference'), [
                'service_key' => EngagementType::STANDARD_ADVISORY->value,
                'recognition_enabled' => true,
            ])
            ->assertRedirect();

        $this->assertSame(5, $enrollment->refresh()->milestoneAwards()->count());
        $this->assertSame(3, $enrollment->pointEvents()->count());

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('journeyRecognition.enabled', true)
                ->where('journeyRecognition.points.total', 140)
                ->where('journeyRecognition.points.milestone_count', 3)
                ->has('journeyRecognition.badges', 5)
                ->where('journeyRecognition.new_badge_count', 5)
            );

        $this->actingAsMfa($user)
            ->post(route('portal.service-journey.seen'), [
                'service_key' => EngagementType::STANDARD_ADVISORY->value,
            ])
            ->assertRedirect();

        $this->assertSame(0, $enrollment->refresh()->milestoneAwards()->whereNull('seen_at')->count());
    }

    public function test_client_cannot_enable_recognition_for_another_service_workspace(): void
    {
        $this->seed(RoleSeeder::class);
        [$user] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->post(route('portal.service-journey.preference'), [
                'service_key' => EngagementType::DUE_DILIGENCE->value,
                'recognition_enabled' => true,
            ])
            ->assertSessionHasErrors('service_key');

        $this->assertDatabaseCount('service_journey_enrollments', 0);
    }

    public function test_client_can_enable_a_separate_active_service_journey_without_reusing_primary_service_evidence(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();
        $this->completeVerifiedJourneyEvidence($client, $user);

        ServiceActivation::query()->create([
            'client_id' => $client->getKey(),
            'requested_by_user_id' => $user->getKey(),
            'service_type' => ServiceActivation::SERVICE_INTEGRATION_SCOPING,
            'client_label' => 'Systems integration scoping',
            'status' => ServiceActivation::STATUS_ACTIVE,
            'payment_status' => ServiceActivation::PAYMENT_NOT_REQUIRED,
            'accepted_at' => now(),
        ]);

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('secondaryJourneyRecognition', 1)
                ->where('secondaryJourneyRecognition.0.service_key', ServiceActivation::SERVICE_INTEGRATION_SCOPING)
                ->where('secondaryJourneyRecognition.0.points.total', 0)
            );

        $this->actingAsMfa($user)
            ->post(route('portal.service-journey.preference'), [
                'service_key' => ServiceActivation::SERVICE_INTEGRATION_SCOPING,
                'recognition_enabled' => true,
            ])
            ->assertRedirect();

        $enrollment = ServiceJourneyEnrollment::query()
            ->where('service_key', ServiceActivation::SERVICE_INTEGRATION_SCOPING)
            ->firstOrFail();

        $this->assertSame(1, $enrollment->milestoneAwards()->count());
        $this->assertSame(25, (int) $enrollment->pointEvents()->sum('points'));
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Journey Client',
            'email' => 'journey.client@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'engagement_type_locked_at' => now(),
            'nzbn' => '9429000000000',
            'legal_name' => 'Journey Test Limited',
            'trading_name' => 'Journey Test',
            'entity_type' => 'NZ Limited Company',
            'gst_registered' => true,
            'filing_status' => 'registered',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }

    private function completeVerifiedJourneyEvidence(Client $client, User $user): void
    {
        Document::query()->create([
            'client_id' => $client->getKey(),
            'category' => Document::CATEGORY_OTHER,
            'original_filename' => 'journey-evidence.pdf',
            'stored_path' => 'documents/testing/journey-evidence.pdf',
            'byte_size' => 1200,
            'mime_type' => 'application/pdf',
            'sha256' => str_repeat('a', 64),
            'uploaded_by_user_id' => $user->getKey(),
            'scanner_result' => Document::SCANNER_CLEAN,
            'scanner_payload' => [],
        ]);

        Report::query()->create([
            'client_id' => $client->getKey(),
            'type' => ReportType::Client,
            'title' => 'Journey advisory report',
            'pdf_path' => 'reports/testing/journey.pdf',
            'pdf_byte_size' => 1200,
            'generated_by_user_id' => $user->getKey(),
            'generated_at' => now(),
            'render_status' => Report::RENDER_STATUS_RENDERED,
            'review_status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $user->getKey(),
        ]);
    }
}
