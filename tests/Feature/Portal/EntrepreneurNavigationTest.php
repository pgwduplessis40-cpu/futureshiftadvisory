<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\InviteToken;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EntrepreneurNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrepreneur_navigation_targets_are_available(): void
    {
        $this->seed(RoleSeeder::class);

        $advisor = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->getKey(),
            'assigned_advisor_id' => $advisor->getKey(),
            'name' => 'Tania Hassounia',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Portal navigation test profile.',
        ]);

        foreach ($this->entrepreneurUrls() as $url) {
            $this->actingAsMfa($entrepreneur)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_entrepreneur_navigation_recovers_accepted_invite_profile_before_loading_targets(): void
    {
        $this->seed(RoleSeeder::class);

        foreach ($this->entrepreneurUrls() as $index => $url) {
            $advisor = User::factory()->create([
                'user_type' => User::TYPE_ADVISOR,
                'primary_role' => User::TYPE_ADVISOR,
            ]);
            $entrepreneur = User::factory()->withTwoFactor()->create([
                'email' => "accepted-nav-{$index}@example.test",
                'user_type' => User::TYPE_ENTREPRENEUR,
                'primary_role' => User::TYPE_ENTREPRENEUR,
            ]);
            $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
            $invite = InviteToken::query()->create([
                'email' => $entrepreneur->email,
                'target_role' => User::TYPE_ENTREPRENEUR,
                'target_user_type' => User::TYPE_ENTREPRENEUR,
                'token_hash' => InviteToken::hashToken("accepted-nav-token-{$index}"),
                'expires_at' => now()->addDays(5),
                'accepted_at' => now(),
                'accepted_by_user_id' => $entrepreneur->getKey(),
            ]);
            $profile = EntrepreneurProfile::query()->create([
                'assigned_advisor_id' => $advisor->getKey(),
                'invite_token_id' => $invite->getKey(),
                'name' => "Accepted Nav {$index}",
                'email' => strtoupper($entrepreneur->email),
                'stage' => EntrepreneurStage::INVITED,
                'concept_summary' => 'Accepted invite should reconcile before portal navigation renders.',
            ]);

            $this->actingAsMfa($entrepreneur)
                ->get($url)
                ->assertOk();

            $profile->refresh();
            $this->assertSame((string) $entrepreneur->getKey(), (string) $profile->user_id);
            $this->assertSame(EntrepreneurStage::ONBOARDING, $profile->stage);
        }
    }

    /**
     * @return array<int, string>
     */
    private function entrepreneurUrls(): array
    {
        return [
            route('portal.entrepreneur.dashboard', absolute: false),
            route('portal.entrepreneur.plan.show', absolute: false),
            route('portal.calendar.index', absolute: false),
            route('portal.inspiration-board.index', absolute: false),
            route('portal.entrepreneur.surveys.index', absolute: false),
            route('portal.messages.index', absolute: false),
            route('notifications.index', absolute: false),
        ];
    }
}
