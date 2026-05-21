<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\KnowledgeEntry;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class KnowledgeEntryPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_VIEW);
    }

    public function view(User $user, mixed $knowledgeEntry = null): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_VIEW)
            && $this->owns($user, $knowledgeEntry);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_MANAGE);
    }

    public function update(User $user, mixed $knowledgeEntry = null): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_MANAGE)
            && $this->owns($user, $knowledgeEntry);
    }

    public function delete(User $user, mixed $knowledgeEntry = null): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_MANAGE)
            && $this->owns($user, $knowledgeEntry);
    }

    public function publish(User $user, mixed $knowledgeEntry = null): bool
    {
        return $this->allows($user, Permission::KNOWLEDGE_PUBLISH)
            && $this->owns($user, $knowledgeEntry);
    }

    private function owns(User $user, mixed $knowledgeEntry): bool
    {
        if (! $knowledgeEntry instanceof KnowledgeEntry) {
            return true;
        }

        if ($user->user_type === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return (string) $knowledgeEntry->author_user_id === (string) $user->getKey();
    }
}
