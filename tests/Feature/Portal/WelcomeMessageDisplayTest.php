<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Portal\OnboardingWizard;
use App\Services\Portal\Welcome\WelcomeMessageManager;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WelcomeMessageDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_message_renders_on_the_onboarding_welcome_step(): void
    {
        $this->seed(RoleSeeder::class);
        [$user, $client] = $this->clientUserWithClient();
        $this->publishWelcomeMessage();

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_WELCOME]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/onboarding/Step')
                ->where('welcomeMessage.has_message', true)
                ->where('welcomeMessage.version', 1)
                ->has('welcomeMessage.html')
            );

        $this->assertNotNull($client->trading_name);
    }

    public function test_welcome_message_is_provided_to_the_portal_dashboard(): void
    {
        $this->seed(RoleSeeder::class);
        [$user] = $this->clientUserWithClient();
        $this->publishWelcomeMessage();

        $this->actingAsMfa($user)
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/Dashboard')
                ->where('welcomeMessage.has_message', true)
                ->where('welcomeMessage.version', 1)
            );
    }

    public function test_welcome_message_is_provided_to_the_entrepreneur_dashboard(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Wessel Du Plessis',
            'email' => 'wessel@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $user->assignRole(User::TYPE_ENTREPRENEUR);

        EntrepreneurProfile::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Wessel',
            'email' => $user->email,
            'stage' => EntrepreneurStage::ONBOARDING,
            'concept_summary' => 'Testing the entrepreneur welcome flow.',
        ]);

        $this->publishWelcomeMessage();

        $this->actingAsMfa($user)
            ->get(route('portal.entrepreneur.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/entrepreneur/Dashboard')
                ->where('welcomeMessage.has_message', true)
                ->where('welcomeMessage.version', 1)
                ->has('welcomeMessage.html')
            );
    }

    public function test_no_active_message_yields_no_welcome_content(): void
    {
        $this->seed(RoleSeeder::class);
        [$user] = $this->clientUserWithClient();

        $this->actingAsMfa($user)
            ->get(route('portal.onboarding.step', ['step' => OnboardingWizard::STEP_WELCOME]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/onboarding/Step')
                ->where('welcomeMessage.has_message', false)
            );
    }

    private function publishWelcomeMessage(): void
    {
        $author = User::factory()->superAdmin()->create();
        app(WelcomeMessageManager::class)->publish(
            'Kia ora {{contact_first_name}}, welcome to {{practice_name}}. We are glad to work with {{business_name}}.',
            $author,
        );
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'Aria Tane',
            'email' => 'aria.tane@example.com',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000024',
            'legal_name' => 'Future Shift Advisory Test Limited',
            'trading_name' => 'Tane Engineering',
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
