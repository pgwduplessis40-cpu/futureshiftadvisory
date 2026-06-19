<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class SurveyPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return $this->allows($user, Permission::SURVEYS_MANAGE);
    }

    public function create(User $user): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return $this->allows($user, Permission::SURVEYS_MANAGE);
    }

    public function update(User $user, mixed $survey = null): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return $this->allows($user, Permission::SURVEYS_MANAGE);
    }

    public function delete(User $user, mixed $survey = null): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return $this->allows($user, Permission::SURVEYS_MANAGE);
    }
}
