<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Models\NpoBoardMember;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class NpoBoardAccess
{
    public const MAX_ACTIVE_MEMBERS = 10;

    public function __construct(private readonly AuditWriter $audit) {}

    public function activate(NpoEngagement $engagement, User $member, User $actor, bool $treasurer = false): NpoBoardMember
    {
        $engagement->loadMissing('client');

        return DB::transaction(function () use ($engagement, $member, $actor, $treasurer): NpoBoardMember {
            $existing = NpoBoardMember::query()
                ->where('npo_engagement_id', $engagement->getKey())
                ->where('user_id', $member->getKey())
                ->first();

            if (! $existing instanceof NpoBoardMember && $this->activeCount($engagement) >= self::MAX_ACTIVE_MEMBERS) {
                throw new InvalidArgumentException('An NPO engagement can have at most 10 active board members.');
            }

            /** @var NpoBoardMember $boardMember */
            $boardMember = NpoBoardMember::query()->updateOrCreate(
                [
                    'npo_engagement_id' => $engagement->getKey(),
                    'user_id' => $member->getKey(),
                ],
                [
                    'client_id' => $engagement->client_id,
                    'treasurer' => $treasurer,
                    'active' => true,
                    'revoked_at' => null,
                    'revoked_by_user_id' => null,
                    'created_by_user_id' => $actor->getKey(),
                ],
            );

            $this->audit->record('npo.board_member.activated', subject: $boardMember, actor: $actor, after: [
                'npo_engagement_id' => $engagement->getKey(),
                'user_id' => $member->getKey(),
                'treasurer' => $treasurer,
            ]);

            return $boardMember->refresh();
        });
    }

    public function revoke(NpoBoardMember $member, User $actor): NpoBoardMember
    {
        if ($member->revoked_at === null || $member->active) {
            $member->forceFill([
                'active' => false,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->record('npo.board_member.revoked', subject: $member, actor: $actor, after: [
                'npo_engagement_id' => $member->npo_engagement_id,
                'user_id' => $member->user_id,
            ]);
        }

        return $member->refresh();
    }

    public function isActiveMember(User $user, NpoEngagement|string $engagement): bool
    {
        $engagementId = $engagement instanceof NpoEngagement ? $engagement->getKey() : $engagement;

        return NpoBoardMember::query()
            ->where('npo_engagement_id', $engagementId)
            ->where('user_id', $user->getKey())
            ->where('active', true)
            ->whereNull('revoked_at')
            ->exists();
    }

    public function isTreasurer(User $user, NpoEngagement|string $engagement): bool
    {
        $engagementId = $engagement instanceof NpoEngagement ? $engagement->getKey() : $engagement;

        return NpoBoardMember::query()
            ->where('npo_engagement_id', $engagementId)
            ->where('user_id', $user->getKey())
            ->where('treasurer', true)
            ->where('active', true)
            ->whereNull('revoked_at')
            ->exists();
    }

    private function activeCount(NpoEngagement $engagement): int
    {
        return NpoBoardMember::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('active', true)
            ->whereNull('revoked_at')
            ->count();
    }
}
