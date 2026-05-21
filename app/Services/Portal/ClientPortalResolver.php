<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Client;
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

        abort_unless(in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true), 403);

        $clientIds = $user->accessibleClientIds();
        abort_if($clientIds === [], 403, 'No client portal is assigned to this account yet.');

        return Client::query()
            ->whereIn('id', $clientIds)
            ->latest()
            ->firstOrFail();
    }
}
