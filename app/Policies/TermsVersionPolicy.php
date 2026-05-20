<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class TermsVersionPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::TERMS_VIEW);
    }

    public function view(User $user, mixed $termsVersion = null): bool
    {
        return $this->allows($user, Permission::TERMS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::TERMS_MANAGE);
    }

    public function update(User $user, mixed $termsVersion = null): bool
    {
        return $this->allows($user, Permission::TERMS_MANAGE);
    }

    public function publish(User $user, mixed $termsVersion = null): bool
    {
        return $this->allows($user, Permission::TERMS_PUBLISH);
    }

    public function delete(User $user, mixed $termsVersion = null): bool
    {
        return $this->allows($user, Permission::TERMS_PUBLISH);
    }
}
