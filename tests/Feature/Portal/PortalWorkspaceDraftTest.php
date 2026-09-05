<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PortalWorkspaceDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_client_draft_is_persisted_on_the_server_and_recovered_after_refresh(): void
    {
        [$user, $client] = $this->clientUser('Karin');
        $key = 'onboarding:goals';

        $this->actingAsMfa($user)
            ->postJson(route('portal.drafts.store', ['draftKey' => $key]), [
                'payload' => [
                    'primary_goal' => 'Make the client journey reliable.',
                    'success_measure' => 'No work is lost on refresh.',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('saved_at', fn ($value): bool => is_string($value));

        $this->assertDatabaseHas('portal_workspace_drafts', [
            'user_id' => $user->getKey(),
            'client_id' => $client->getKey(),
            'draft_key' => $key,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'portal.workspace_draft_started',
            'client_id' => $client->getKey(),
        ]);

        $this->actingAsMfa($user)
            ->getJson(route('portal.drafts.show', ['draftKey' => $key]))
            ->assertOk()
            ->assertJsonPath('payload.primary_goal', 'Make the client journey reliable.')
            ->assertJsonPath('payload.success_measure', 'No work is lost on refresh.')
            ->assertJsonPath('saved_at', fn ($value): bool => is_string($value));
    }

    public function test_workspace_drafts_are_never_shared_between_client_users(): void
    {
        [$firstUser] = $this->clientUser('Karin');
        [$secondUser] = $this->clientUser('Dave');
        $key = 'service-request:due_diligence';

        $this->actingAsMfa($firstUser)
            ->postJson(route('portal.drafts.store', ['draftKey' => $key]), [
                'payload' => ['motivation' => 'First client draft'],
            ])
            ->assertOk();

        $this->actingAsMfa($secondUser)
            ->getJson(route('portal.drafts.show', ['draftKey' => $key]))
            ->assertOk()
            ->assertJsonPath('payload', [])
            ->assertJsonPath('saved_at', null);
    }

    public function test_entrepreneur_drafts_do_not_require_a_client_portal_record(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $user->assignRole(User::TYPE_ENTREPRENEUR);

        $this->actingAsMfa($user)
            ->postJson(route('portal.drafts.store', ['draftKey' => 'message:new']), [
                'payload' => ['subject' => 'Draft question', 'body' => 'Please call me.'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('portal_workspace_drafts', [
            'user_id' => $user->getKey(),
            'client_id' => null,
            'draft_key' => 'message:new',
        ]);
    }

    public function test_unknown_draft_keys_are_not_accepted(): void
    {
        [$user] = $this->clientUser('Karin');

        $this->actingAsMfa($user)
            ->postJson(route('portal.drafts.store', ['draftKey' => 'proposal:acceptance']), [
                'payload' => ['accepted' => true],
            ])
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUser(string $name): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => $name,
            'email' => strtolower($name).'.draft@example.test',
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => $name.' Draft Limited',
            'trading_name' => $name.' Draft',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
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
}
