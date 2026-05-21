<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class EntrepreneurProfilePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::ENTREPRENEURS_VIEW);
    }

    public function view(User $user, EntrepreneurProfile $profile): bool
    {
        if (! $this->allows($user, Permission::ENTREPRENEURS_VIEW)) {
            return false;
        }

        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return (string) $profile->assigned_advisor_id === (string) $user->getKey()
            || (string) $profile->user_id === (string) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::ENTREPRENEURS_ASSESS);
    }
}
