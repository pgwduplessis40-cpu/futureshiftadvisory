<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class NotificationPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::NOTIFICATIONS_VIEW);
    }

    public function view(User $user, mixed $notification = null): bool
    {
        return $this->allows($user, Permission::NOTIFICATIONS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::NOTIFICATIONS_MANAGE);
    }

    public function update(User $user, mixed $notification = null): bool
    {
        return $this->allows($user, Permission::NOTIFICATIONS_MANAGE);
    }

    public function delete(User $user, mixed $notification = null): bool
    {
        return $this->allows($user, Permission::NOTIFICATIONS_MANAGE);
    }
}
