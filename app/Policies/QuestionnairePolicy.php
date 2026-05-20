<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class QuestionnairePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::QUESTIONNAIRES_VIEW);
    }

    public function view(User $user, mixed $questionnaire = null): bool
    {
        return $this->allows($user, Permission::QUESTIONNAIRES_VIEW);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::QUESTIONNAIRES_DRAFT);
    }

    public function update(User $user, mixed $questionnaire = null): bool
    {
        return $this->allows($user, Permission::QUESTIONNAIRES_DRAFT);
    }

    public function publish(User $user, mixed $questionnaire = null): bool
    {
        return $this->allows($user, Permission::QUESTIONNAIRES_PUBLISH);
    }

    public function delete(User $user, mixed $questionnaire = null): bool
    {
        return $this->allows($user, Permission::QUESTIONNAIRES_PUBLISH);
    }
}
