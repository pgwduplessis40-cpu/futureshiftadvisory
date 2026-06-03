<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PortalNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The notification bell (in the shared layout) reads notificationSummary and its
     * popover renders summary.latest. The client portal dashboard must therefore expose
     * the full summary shape, not a counts-only override — otherwise opening the bell
     * crashes the page to a blank screen.
     */
    public function test_client_portal_dashboard_exposes_full_notification_summary(): void
    {
        $this->seed(RoleSeeder::class);
        [$user] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('portal/Dashboard')
                ->has('notificationSummary.latest')
                ->where('notificationSummary.index_url', route('notifications.index', absolute: false))
                ->where('notificationSummary.mark_all_read_url', route('notifications.mark-all-read', absolute: false))
            );
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Bell Viewer',
            'email' => 'bell.viewer@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000048',
            'legal_name' => 'Bell Portal Test Limited',
            'trading_name' => 'Bell Co',
            'entity_type' => 'NZ Limited Company',
            'gst_registered' => true,
            'filing_status' => 'registered',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$user, $client];
    }
}
