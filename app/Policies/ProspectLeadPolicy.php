<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class ProspectLeadPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::PROSPECTS_VIEW);
    }

    public function view(User $user, mixed $prospectLead = null): bool
    {
        return $this->allows($user, Permission::PROSPECTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::PROSPECTS_TRIAGE);
    }

    public function update(User $user, mixed $prospectLead = null): bool
    {
        return $this->allows($user, Permission::PROSPECTS_TRIAGE);
    }

    public function triage(User $user, mixed $prospectLead = null): bool
    {
        return $this->allows($user, Permission::PROSPECTS_TRIAGE);
    }
}
