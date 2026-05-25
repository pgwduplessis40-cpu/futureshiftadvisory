<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\Consent;
use App\Models\PeerNetworkMember;
use App\Models\PeerPost;
use App\Models\PeerPostModeration;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PeerNetwork
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function optIn(User $user, string $community, ?User $actor = null): PeerNetworkMember
    {
        $community = $this->normaliseCommunity($community);

        return DB::transaction(function () use ($user, $community, $actor): PeerNetworkMember {
            /** @var Consent $consent */
            $consent = Consent::query()->create([
                'client_id' => null,
                'subject_user_id' => $user->id,
                'proposal_id' => null,
                'type' => Consent::TYPE_PEER_NETWORK,
                'election' => Consent::ELECTION_OPT_IN,
                'evidence' => [
                    'community' => $community,
                    'moderation' => 'manual_before_visibility',
                    'pseudonymous' => true,
                ],
                'captured_by_user_id' => $actor?->id,
                'captured_at' => now(),
            ]);

            /** @var PeerNetworkMember $member */
            $member = PeerNetworkMember::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'community' => $community,
                    'membership_type' => PeerNetworkMember::TYPE_PEER_NETWORK,
                ],
                [
                    'pseudonym' => $this->pseudonym($community, (string) $user->id),
                    'joined_at' => now(),
                    'consent_id' => $consent->id,
                    'status' => PeerNetworkMember::STATUS_ACTIVE,
                    'suspended_at' => null,
                    'revoked_at' => null,
                ],
            );

            $this->audit->record('peer_network.opted_in', subject: $member, actor: $actor, after: [
                'community' => $community,
                'consent_id' => $consent->id,
                'moderation_required' => true,
            ]);

            return $member->refresh()->load('consent');
        });
    }

    public function revoke(PeerNetworkMember $member, ?User $actor = null): PeerNetworkMember
    {
        return DB::transaction(function () use ($member, $actor): PeerNetworkMember {
            $member->loadMissing('consent');
            $member->consent?->forceFill([
                'election' => Consent::ELECTION_OPT_OUT,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor?->id,
            ])->save();
            $member->forceFill([
                'status' => PeerNetworkMember::STATUS_REVOKED,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('peer_network.revoked', subject: $member, actor: $actor, after: [
                'community' => $member->community,
            ]);

            return $member->refresh()->load('consent');
        });
    }

    public function post(PeerNetworkMember $member, string $body): PeerPost
    {
        $member->loadMissing('consent');

        if (
            $member->membership_type !== PeerNetworkMember::TYPE_PEER_NETWORK
            || $member->status !== PeerNetworkMember::STATUS_ACTIVE
            || $member->consent?->isActiveOptIn() !== true
        ) {
            throw new InvalidArgumentException('Peer posting requires active peer-network consent.');
        }

        return DB::transaction(function () use ($member, $body): PeerPost {
            /** @var PeerPost $post */
            $post = PeerPost::query()->create([
                'peer_network_member_id' => $member->id,
                'community' => $member->community,
                'body' => trim($body),
                'posted_at' => now(),
                'visible_at' => null,
            ]);

            PeerPostModeration::query()->create([
                'peer_post_id' => $post->id,
                'status' => PeerPostModeration::STATUS_PENDING,
            ]);

            $this->audit->record('peer_network.post_submitted', subject: $post, after: [
                'community' => $member->community,
                'moderation_status' => PeerPostModeration::STATUS_PENDING,
                'pseudonymous' => true,
            ]);

            return $post->refresh()->load('moderation', 'member');
        });
    }

    public function moderate(PeerPost $post, User $moderator, string $decision, ?string $reason = null): PeerPost
    {
        if (! in_array($decision, [PeerPostModeration::STATUS_APPROVED, PeerPostModeration::STATUS_REJECTED], true)) {
            throw new InvalidArgumentException('Moderation decision must be approved or rejected.');
        }

        return DB::transaction(function () use ($post, $moderator, $decision, $reason): PeerPost {
            $post->loadMissing('moderation');
            $post->moderation?->forceFill([
                'status' => $decision,
                'moderated_by_user_id' => $moderator->id,
                'reason' => $reason,
                'moderated_at' => now(),
            ])->save();
            $post->forceFill([
                'visible_at' => $decision === PeerPostModeration::STATUS_APPROVED ? now() : null,
            ])->save();

            $this->audit->record('peer_network.post_moderated', subject: $post, actor: $moderator, after: [
                'community' => $post->community,
                'decision' => $decision,
            ]);

            return $post->refresh()->load('moderation', 'member');
        });
    }

    public function report(PeerPost $post, User $reporter, string $reason): PeerPost
    {
        $post->forceFill([
            'reported_by_user_id' => $reporter->id,
            'reported_at' => now(),
            'report_reason' => $reason,
        ])->save();

        $this->audit->record('peer_network.post_reported', subject: $post, actor: $reporter, after: [
            'community' => $post->community,
        ]);

        return $post->refresh();
    }

    public function suspend(PeerNetworkMember $member, User $moderator, ?string $reason = null): PeerNetworkMember
    {
        $member->forceFill([
            'status' => PeerNetworkMember::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ])->save();

        $this->audit->record('peer_network.member_suspended', subject: $member, actor: $moderator, after: [
            'community' => $member->community,
            'reason' => $reason,
        ]);

        return $member->refresh();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function visiblePosts(string $community): Collection
    {
        $community = $this->normaliseCommunity($community);

        return PeerPost::query()
            ->with('member')
            ->where('community', $community)
            ->whereNotNull('visible_at')
            ->latest('visible_at')
            ->get()
            ->filter(fn (PeerPost $post): bool => $post->member?->status === PeerNetworkMember::STATUS_ACTIVE)
            ->map(fn (PeerPost $post): array => [
                'id' => $post->id,
                'community' => $post->community,
                'pseudonym' => $post->member?->pseudonym,
                'body' => $post->body,
                'visible_at' => $post->visible_at?->toIso8601String(),
            ])
            ->values();
    }

    private function normaliseCommunity(string $community): string
    {
        $community = strtolower(trim($community));

        if (! in_array($community, [PeerNetworkMember::COMMUNITY_SME, PeerNetworkMember::COMMUNITY_ENTREPRENEUR], true)) {
            throw new InvalidArgumentException('Peer community must be sme or entrepreneur.');
        }

        return $community;
    }

    private function pseudonym(string $community, string $userId): string
    {
        return $community.'-peer-'.Str::lower(Str::substr(hash('sha256', $community.'|'.$userId.'|peer'), 0, 10));
    }
}
