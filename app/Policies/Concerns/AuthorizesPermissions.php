<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\User;

trait AuthorizesPermissions
{
    protected function allows(User $user, Permission $permission): bool
    {
        return $user->can($permission->value);
    }

    protected function deniesMutation(): bool
    {
        return false;
    }
}
