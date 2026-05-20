<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class AuditEventPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::AUDIT_VIEW);
    }

    public function view(User $user, mixed $auditEvent = null): bool
    {
        return $this->allows($user, Permission::AUDIT_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->deniesMutation();
    }

    public function update(User $user, mixed $auditEvent = null): bool
    {
        return $this->deniesMutation();
    }

    public function delete(User $user, mixed $auditEvent = null): bool
    {
        return $this->deniesMutation();
    }
}
