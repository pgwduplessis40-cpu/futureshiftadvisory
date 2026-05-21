<?php

declare(strict_types=1);

namespace Tests\Feature\Conflicts;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\ConflictDeclaration;
use App\Models\User;
use App\Services\Conflicts\ConflictDeclarationRequiredException;
use App\Services\Conflicts\ConflictDeclarer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ConflictDeclarerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_client_creation_still_requires_conflict_declaration(): void
    {
        $advisor = $this->advisor();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.store'), [
                'engagement_type' => EngagementType::STANDARD_ADVISORY->value,
                'nzbn' => '9429000000000',
                'conflict' => [
                    'declared' => false,
                    'referral_type' => ConflictDeclarer::CLIENT_CREATION,
                    'existing_relationship' => false,
                    'details' => null,
                ],
            ])
            ->assertSessionHasErrors('conflict.declared');

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('conflict_declarations', 0);
    }

    public function test_declare_records_audited_conflict_for_client_creation(): void
    {
        [$advisor, $client] = $this->advisorWithClient();

        $declaration = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::CLIENT_CREATION,
            existingRelationship: true,
            details: 'Introduced through an existing board relationship.',
        );

        $this->assertSame($client->id, $declaration->client_id);
        $this->assertSame(ConflictDeclarer::CLIENT_CREATION, $declaration->referralType());
        $this->assertTrue($declaration->declaration['existing_relationship']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'conflict.declared',
            'client_id' => $client->id,
        ]);
    }

    public function test_requires_fresh_declaration_for_each_referral_type(): void
    {
        [$advisor, $client] = $this->advisorWithClient();
        $declarer = app(ConflictDeclarer::class);

        $declarer->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::BROKER_REFERRAL,
            existingRelationship: false,
        );

        $this->assertInstanceOf(
            ConflictDeclaration::class,
            $declarer->require($advisor, $client, ConflictDeclarer::BROKER_REFERRAL),
        );

        $this->expectException(ConflictDeclarationRequiredException::class);

        $declarer->require($advisor, $client, ConflictDeclarer::COACH_REFERRAL);
    }

    public function test_stale_declaration_blocks_phase_three_referral_placeholder(): void
    {
        [$advisor, $client] = $this->advisorWithClient();

        $stale = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::COACH_REFERRAL,
            existingRelationship: false,
        );
        $stale->forceFill([
            'declared_at' => now()->subDays(ConflictDeclarer::FRESH_FOR_DAYS + 1),
        ])->save();

        $referral = new class(app(ConflictDeclarer::class))
        {
            public function __construct(private readonly ConflictDeclarer $conflicts) {}

            public function send(User $advisor, Client $client): string
            {
                $this->conflicts->require($advisor, $client, ConflictDeclarer::COACH_REFERRAL);

                return 'coach-referral-sent';
            }
        };

        $this->expectException(ConflictDeclarationRequiredException::class);

        $referral->send($advisor, $client);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function advisorWithClient(): array
    {
        $advisor = $this->advisor();

        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000210',
            'legal_name' => 'Conflict Primitive Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        app(RequestContext::class)->apply('advisor', [(string) $client->getKey()], (string) $advisor->getKey());

        return [$advisor, $client];
    }

    private function advisor(): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }
}
