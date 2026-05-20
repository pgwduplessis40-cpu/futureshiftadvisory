<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class DocumentPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::DOCUMENTS_VIEW);
    }

    public function view(User $user, mixed $document = null): bool
    {
        return $this->allows($user, Permission::DOCUMENTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::DOCUMENTS_UPLOAD);
    }

    public function update(User $user, mixed $document = null): bool
    {
        return $this->allows($user, Permission::DOCUMENTS_MANAGE);
    }

    public function delete(User $user, mixed $document = null): bool
    {
        return $this->allows($user, Permission::DOCUMENTS_MANAGE);
    }

    public function verify(User $user, mixed $document = null): bool
    {
        return $this->allows($user, Permission::DOCUMENTS_VERIFY);
    }
}
