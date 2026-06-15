<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\NpoBoardMember;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ClientPortalResolver
{
    public function resolveFor(Request $request): Client
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpException(403);
        }

        if ($user->user_type === User::TYPE_NPO_BOARD_MEMBER) {
            return $this->resolveForNpoBoardMember($user);
        }

        abort_unless(in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true), 403);

        $clientIds = $user->accessibleClientIds();
        abort_if($clientIds === [], 403, 'No client portal is assigned to this account yet.');

        return Client::query()
            ->whereIn('id', $clientIds)
            ->where('status', '!=', ClientStatus::SUSPENDED->value)
            ->latest()
            ->firstOrFail();
    }

    private function resolveForNpoBoardMember(User $user): Client
    {
        $membership = NpoBoardMember::query()
            ->where('user_id', $user->getKey())
            ->where('active', true)
            ->whereNull('revoked_at')
            ->latest()
            ->first();

        abort_unless($membership instanceof NpoBoardMember, 403, 'No NPO board portal is assigned to this account yet.');

        return Client::query()
            ->where('id', $membership->client_id)
            ->where('status', '!=', ClientStatus::SUSPENDED->value)
            ->firstOrFail();
    }
}
