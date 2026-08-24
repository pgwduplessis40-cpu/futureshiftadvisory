<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\User;
use App\Models\WebsiteUrlConfirmation;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StandardAdvisoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_assigned_advisor_confirms_a_normalized_website_url_and_invalid_urls_are_rejected(): void
    {
        [$advisor, $client] = $this->advisorAndClient();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.standard-advisory.website-url', $client), [
                'url' => 'https://Example.test/our-service?campaign=summer',
                'source_answer_ids' => ['answer-1', 'answer-1', 'answer-2'],
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHas('status', 'website-url-confirmed');

        $this->assertDatabaseHas('website_url_confirmations', [
            'client_id' => $client->getKey(),
            'root_url' => 'https://example.test/our-service',
            'status' => WebsiteUrlConfirmation::STATUS_CONFIRMED,
            'confirmed_by_user_id' => $advisor->getKey(),
        ]);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.standard-advisory.website-url', $client), [
                'url' => 'https://username:password@example.test',
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHasErrors('url');
    }

    public function test_incomplete_standard_advisory_pack_returns_a_recoverable_validation_error(): void
    {
        [$advisor, $client] = $this->advisorAndClient();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.standard-advisory.pack', $client))
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHasErrors('standard_advisory');
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function advisorAndClient(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'legal_name' => 'Standard advisory controller client',
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
