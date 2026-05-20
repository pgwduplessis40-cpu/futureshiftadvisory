<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class ClientPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::CLIENTS_VIEW);
    }

    public function view(User $user, mixed $client = null): bool
    {
        return $this->allows($user, Permission::CLIENTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::CLIENTS_MANAGE);
    }

    public function update(User $user, mixed $client = null): bool
    {
        return $this->allows($user, Permission::CLIENTS_MANAGE);
    }

    public function delete(User $user, mixed $client = null): bool
    {
        return $this->allows($user, Permission::CLIENTS_MANAGE);
    }
}
