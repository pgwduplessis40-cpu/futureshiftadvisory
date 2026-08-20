<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class ClientPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::CLIENTS_VIEW);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->allows($user, Permission::CLIENTS_VIEW)
            && $this->canAccessClient($user, $client);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::CLIENTS_MANAGE);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->allows($user, Permission::CLIENTS_MANAGE)
            && $this->canAccessClient($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->allows($user, Permission::CLIENTS_MANAGE)
            && $this->canAccessClient($user, $client);
    }

    private function canAccessClient(User $user, Client $client): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return in_array((string) $client->getKey(), $user->accessibleClientIds(), true);
    }
}
