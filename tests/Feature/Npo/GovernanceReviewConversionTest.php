<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoConversionStatus;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Notifications\GovernanceReviewConversionNudgeNotification;
use App\Services\Npo\GovernanceReviewConversion;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GovernanceReviewConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_report_delivery_sets_conversion_status_reengagement_and_advisor_panels(): void
    {
        $this->travelTo(Carbon::parse('2026-05-27 10:00:00'));
        [$advisor, $client, $engagement] = $this->npoClient();
        $deliveredAt = Carbon::parse('2026-05-01 09:00:00');

        $updated = app(GovernanceReviewConversion::class)->markReportDelivered($engagement, $advisor, $deliveredAt);

        $this->assertSame(NpoConversionStatus::ReportDelivered, $updated->conversion_status);
        $this->assertSame($deliveredAt->toDateString(), $updated->report_delivered_at?->toDateString());
        $this->assertSame('2029-05-01', $updated->reengagement_due_at?->toDateString());

        $this->actingAsMfa($advisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/Dashboard')
                ->where('npoPendingConversions.summary.total', 1)
                ->where('npoPendingConversions.summary.report_delivered', 1)
                ->where('npoPendingConversions.items.0.id', $engagement->id)
                ->where('npoPendingConversions.items.0.client_id', $client->id)
                ->where('npoPendingConversions.items.0.status', NpoConversionStatus::ReportDelivered->value));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.npo_conversion.id', $engagement->id)
                ->where('client.npo_conversion.status', NpoConversionStatus::ReportDelivered->value)
                ->where('client.npo_conversion.reengagement_due_at', '2029-05-01')
                ->where('client.npo_conversion.decline_url', route('advisor.npo-engagements.conversion.decline', $engagement, absolute: false)));

        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.governance_review_report_delivered',
            'subject_id' => $engagement->id,
        ]);
    }

    public function test_thirty_and_ninety_day_nudges_fire_once_for_unconverted_reviews(): void
    {
        $this->travelTo(Carbon::parse('2026-05-27 10:00:00'));
        [$advisor, , $engagement] = $this->npoClient('governance-nudge-advisor@example.test');
        app(GovernanceReviewConversion::class)->markReportDelivered($engagement, $advisor, now()->subDays(31));

        Notification::fake();
        $this->artisan('npo:send-governance-review-conversion-nudges')
            ->expectsOutput('1 Governance Review conversion nudge sent.')
            ->assertSuccessful();
        Notification::assertSentTo(
            $advisor,
            GovernanceReviewConversionNudgeNotification::class,
            fn (GovernanceReviewConversionNudgeNotification $notification): bool => $notification->nudgeDay === GovernanceReviewConversion::NUDGE_30_DAYS,
        );

        Notification::fake();
        $this->artisan('npo:send-governance-review-conversion-nudges')
            ->expectsOutput('0 Governance Review conversion nudges sent.')
            ->assertSuccessful();
        Notification::assertNothingSent();

        $this->travelTo(Carbon::parse('2026-07-27 10:00:00'));
        Notification::fake();
        $this->artisan('npo:send-governance-review-conversion-nudges')
            ->expectsOutput('1 Governance Review conversion nudge sent.')
            ->assertSuccessful();
        Notification::assertSentTo(
            $advisor,
            GovernanceReviewConversionNudgeNotification::class,
            fn (GovernanceReviewConversionNudgeNotification $notification): bool => $notification->nudgeDay === GovernanceReviewConversion::NUDGE_90_DAYS,
        );

        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.governance_review_conversion_nudge_sent',
            'subject_id' => $engagement->id,
        ]);
    }

    public function test_decline_reason_is_recorded_as_durable_signal(): void
    {
        [$advisor, , $engagement] = $this->npoClient('governance-decline-advisor@example.test');
        app(GovernanceReviewConversion::class)->markReportDelivered($engagement, $advisor, Carbon::parse('2026-05-01 09:00:00'));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.npo-engagements.conversion.decline', $engagement), [
                'reason' => 'The board deferred full advisory until after its annual planning cycle.',
            ])
            ->assertRedirect();

        $engagement = $engagement->refresh();
        $this->assertSame(NpoConversionStatus::Declined, $engagement->conversion_status);
        $this->assertSame('The board deferred full advisory until after its annual planning cycle.', $engagement->conversion_decline_reason);
        $this->assertSame('2029-05-01', $engagement->reengagement_due_at?->toDateString());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.governance_review_conversion_declined',
            'subject_id' => $engagement->id,
        ]);
    }

    public function test_conversion_creates_standard_npo_shell_once(): void
    {
        [$advisor, $client, $engagement] = $this->npoClient('governance-convert-advisor@example.test');
        app(GovernanceReviewConversion::class)->markReportDelivered($engagement, $advisor, Carbon::parse('2026-05-01 09:00:00'));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.npo-engagements.conversion.convert', $engagement))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $engagement = $engagement->refresh();
        $this->assertSame(NpoConversionStatus::Converted, $engagement->conversion_status);
        $converted = NpoEngagement::query()
            ->where('converted_from_npo_engagement_id', $engagement->id)
            ->firstOrFail();
        $this->assertSame($client->id, $converted->client_id);
        $this->assertSame(NpoEngagementSubType::StandardNpo, $converted->sub_type);
        $this->assertSame($engagement->legal_structure, $converted->legal_structure);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.npo-engagements.conversion.convert', $engagement))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $this->assertSame(1, NpoEngagement::query()
            ->where('converted_from_npo_engagement_id', $engagement->id)
            ->where('sub_type', NpoEngagementSubType::StandardNpo->value)
            ->count());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.governance_review_converted',
            'subject_id' => $engagement->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClient(
        string $advisorEmail = 'governance-conversion-advisor@example.test',
        string $clientName = 'Governance Conversion Trust',
    ): array {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::GovernanceReview,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'isa_2022_reregistered' => null,
        ]);

        return [$advisor, $client, $engagement];
    }
}
