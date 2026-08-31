<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\PanelAgreement;
use App\Models\PanelMember;
use App\Models\Referral;
use App\Models\ReferralMessage;
use App\Models\ReverseReferral;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PanelCalendarActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_broker_calendar_surfaces_referrals_messages_agreements_and_reverse_referrals(): void
    {
        $broker = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_BROKER,
            'primary_role' => User::TYPE_BROKER,
        ]);
        $broker->assignRole(User::TYPE_BROKER);
        $member = PanelMember::query()->create([
            'user_id' => $broker->getKey(),
            'panel_type' => PanelMember::TYPE_BROKER,
            'status' => PanelMember::STATUS_ACTIVE,
            'application' => ['company' => 'Calendar Brokers Limited'],
            'approved_at' => now()->subWeek(),
        ]);
        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Referral Client Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
        ]);
        $referral = Referral::query()->create([
            'client_id' => $client->getKey(),
            'panel_member_id' => $member->getKey(),
            'panel_type' => PanelMember::TYPE_BROKER,
            'referral_type' => Referral::TYPE_BROKER,
            'stage' => Referral::STAGE_BROKER_COVER_PLACED,
            'payload' => ['reason' => 'Insurance cover review.'],
            'created_by_user_id' => $broker->getKey(),
            'sent_at' => now()->subDays(3),
            'closed_at' => now()->subDay(),
        ]);
        ReferralMessage::query()->create([
            'referral_id' => $referral->getKey(),
            'client_id' => $client->getKey(),
            'sender_user_id' => $broker->getKey(),
            'body' => 'The client is ready to discuss a suitable insurance option.',
            'sent_at' => now()->subHours(12),
        ]);
        PanelAgreement::query()->create([
            'panel_member_id' => $member->getKey(),
            'status' => PanelAgreement::STATUS_SIGNED,
            'terms' => ['panel_type' => PanelMember::TYPE_BROKER],
            'signed_by_user_id' => $broker->getKey(),
            'generated_at' => now()->subDays(5),
            'signed_at' => now()->subDays(4),
        ]);
        ReverseReferral::query()->create([
            'panel_member_id' => $member->getKey(),
            'target_type' => ReverseReferral::TARGET_PROSPECT,
            'name' => 'Reverse referral lead',
            'email' => 'reverse-referral@example.test',
            'company' => 'Reverse Referral Company',
            'payload' => ['source' => 'calendar test'],
            'submitted_at' => now()->subHours(6),
        ]);

        $expectedTitles = [
            'Referral sent: Referral Client Limited',
            'Referral closed: Referral Client Limited',
            'Message: Referral Client Limited',
            'Panel agreement generated',
            'Panel agreement signed',
            'Reverse referral: Reverse referral lead',
        ];

        $this->actingAsMfa($broker)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('calendar/Index')
                ->where('title', 'Broker calendar')
                ->where('events', fn (mixed $events): bool => $this->calendarContainsTitles(collect($events)->all(), $expectedTitles)
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === route('dashboard', absolute: false).'#broker-referrals')
                    && collect($events)->contains(fn (array $event): bool => $event['href'] === route('dashboard', absolute: false).'#panel-agreement')));
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<int, string>  $titles
     */
    private function calendarContainsTitles(array $events, array $titles): bool
    {
        $eventTitles = collect($events)->pluck('title');

        return collect($titles)->every(fn (string $title): bool => $eventTitles->contains($title));
    }
}
