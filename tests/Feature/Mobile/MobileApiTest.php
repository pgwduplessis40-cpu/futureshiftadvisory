<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DeviceRegistration;
use App\Models\User;
use App\Services\Mobile\MobileTokenIssuer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_token_accesses_me_clients_and_voice_shortcut_session(): void
    {
        $this->seed(RoleSeeder::class);
        [$advisor, $client] = $this->advisorAndClient();

        $issued = app(MobileTokenIssuer::class)->issue($advisor, [
            'device_id' => 'ios-device-1',
            'platform' => 'ios',
            'device_name' => 'Advisor iPhone',
            'app_version' => '1.0.0',
            'capabilities' => ['voice_shortcuts' => true],
        ]);

        $this->assertStringStartsWith('fsa_mobile_', $issued['token']);
        $this->assertSame(DeviceRegistration::STATUS_ACTIVE, $issued['device']->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'mobile_device.registered']);

        $this->withToken($issued['token'])
            ->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', (string) $advisor->getKey())
            ->assertJsonPath('device.platform', 'ios');

        $this->withToken($issued['token'])
            ->getJson('/api/mobile/v1/clients/'.$client->getKey())
            ->assertOk()
            ->assertJsonPath('client.id', (string) $client->getKey());

        $this->withToken($issued['token'])
            ->postJson('/api/mobile/v1/voice-assistant/sessions', [
                'client_id' => $client->getKey(),
                'intent' => 'capture_call_note',
                'context' => ['source' => 'ios_shortcut'],
            ])
            ->assertOk()
            ->assertJsonPath('session.status', 'started')
            ->assertJsonPath('session.shortcut_payload.intent', 'capture_call_note');

        $this->assertNotNull($issued['device']->refresh()->last_used_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'mobile_api.call']);
    }

    public function test_mobile_device_registration_requires_mfa(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $user->assignRole(User::TYPE_ADVISOR);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mobile device registration requires MFA enrolment.');

        app(MobileTokenIssuer::class)->issue($user, [
            'device_id' => 'ios-device-2',
            'platform' => 'ios',
        ]);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function advisorAndClient(): array
    {
        app(RequestContext::class)->apply('system', []);

        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Mobile API Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }
}
