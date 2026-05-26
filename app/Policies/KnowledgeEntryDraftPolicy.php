<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\KnowledgeEntryDraft;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class KnowledgeEntryDraftPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_VIEW);
    }

    public function view(User $user, mixed $draft = null): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_VIEW)
            && $this->owns($user, $draft);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_MANAGE);
    }

    public function update(User $user, mixed $draft = null): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_MANAGE)
            && $this->owns($user, $draft);
    }

    public function delete(User $user, mixed $draft = null): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_MANAGE)
            && $this->owns($user, $draft);
    }

    private function owns(User $user, mixed $draft): bool
    {
        if (! $draft instanceof KnowledgeEntryDraft) {
            return true;
        }

        if ($user->user_type === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return (string) $draft->author_user_id === (string) $user->getKey();
    }
}
