<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Template;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class TemplatePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::TEMPLATE_VIEW);
    }

    public function view(User $user, mixed $template = null): bool
    {
        if (! $this->allows($user, Permission::TEMPLATE_VIEW)) {
            return false;
        }

        return ! $template instanceof Template
            || $template->status !== Template::STATUS_DRAFT
            || $this->allows($user, Permission::TEMPLATE_MANAGE);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::TEMPLATE_MANAGE);
    }

    public function update(User $user, mixed $template = null): bool
    {
        return $this->allows($user, Permission::TEMPLATE_MANAGE);
    }

    public function delete(User $user, mixed $template = null): bool
    {
        return $this->allows($user, Permission::TEMPLATE_MANAGE);
    }
}
