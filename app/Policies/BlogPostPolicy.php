<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BlogPost;
use App\Models\User;

final class BlogPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, BlogPost $blogPost): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, BlogPost $blogPost): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, BlogPost $blogPost): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function publish(User $user, BlogPost $blogPost): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function unpublish(User $user, BlogPost $blogPost): bool
    {
        return $this->isSuperAdmin($user);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->fsaRole() === User::TYPE_SUPER_ADMIN;
    }
}
