<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Models\Consent;
use App\Models\PeerNetworkMember;
use App\Models\PeerPostModeration;
use App\Models\User;
use App\Services\Intelligence\PeerNetwork;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PeerNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_peer_network_consent_post_moderation_pseudonymity_and_reporting(): void
    {
        $this->assertContains(Consent::TYPE_PEER_NETWORK, Consent::types());

        $service = app(PeerNetwork::class);
        $user = User::factory()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $moderator = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);

        $member = $service->optIn($user, PeerNetworkMember::COMMUNITY_SME);
        $post = $service->post($member, 'How are others handling seasonal cash flow?');

        $this->assertSame(Consent::TYPE_PEER_NETWORK, $member->consent->type);
        $this->assertSame(PeerNetworkMember::TYPE_PEER_NETWORK, $member->membership_type);
        $this->assertSame(PeerPostModeration::STATUS_PENDING, $post->moderation->status);
        $this->assertNull($post->visible_at);
        $this->assertCount(0, $service->visiblePosts(PeerNetworkMember::COMMUNITY_SME));

        $approved = $service->moderate($post, $moderator, PeerPostModeration::STATUS_APPROVED);
        $visible = $service->visiblePosts(PeerNetworkMember::COMMUNITY_SME);

        $this->assertNotNull($approved->visible_at);
        $this->assertCount(1, $visible);
        $this->assertSame($member->pseudonym, $visible[0]['pseudonym']);
        $this->assertArrayNotHasKey('user_id', $visible[0]);
        $this->assertArrayNotHasKey('email', $visible[0]);

        $this->assertCount(0, $service->visiblePosts(PeerNetworkMember::COMMUNITY_ENTREPRENEUR));

        $reported = $service->report($approved, $moderator, 'Needs review');
        $this->assertNotNull($reported->reported_at);

        $suspended = $service->suspend($member, $moderator, 'Repeated reports');
        $this->assertSame(PeerNetworkMember::STATUS_SUSPENDED, $suspended->status);
        $this->assertCount(0, $service->visiblePosts(PeerNetworkMember::COMMUNITY_SME));
    }

    public function test_peer_network_revocation_removes_posting_rights(): void
    {
        $service = app(PeerNetwork::class);
        $member = $service->optIn(User::factory()->create(), PeerNetworkMember::COMMUNITY_ENTREPRENEUR);
        $revoked = $service->revoke($member);

        $this->assertSame(PeerNetworkMember::STATUS_REVOKED, $revoked->status);
        $this->assertSame(Consent::ELECTION_OPT_OUT, $revoked->consent->election);

        $this->expectException(\InvalidArgumentException::class);
        $service->post($revoked, 'This should not be accepted.');
    }
}
