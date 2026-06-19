<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ClientTeamMember;
use App\Models\SurveyAssignment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesPermissions;

final class SurveyAssignmentPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        return $this->allows($user, Permission::SURVEYS_VIEW);
    }

    public function view(User $user, SurveyAssignment $assignment): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return true;
        }

        if ($assignment->client_id !== null) {
            if (in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)) {
                return in_array((string) $assignment->client_id, $user->accessibleClientIds(), true);
            }

            return $this->allows($user, Permission::SURVEYS_VIEW)
                && ClientTeamMember::query()
                    ->where('client_id', $assignment->client_id)
                    ->where('user_id', $user->getKey())
                    ->exists();
        }

        if ($assignment->entrepreneurProfile === null) {
            $assignment->load('entrepreneurProfile');
        }

        $profile = $assignment->entrepreneurProfile;

        if ($profile === null) {
            return false;
        }

        return (string) $profile->user_id === (string) $user->getKey()
            || ($this->allows($user, Permission::SURVEYS_VIEW)
                && (string) $profile->assigned_advisor_id === (string) $user->getKey());
    }

    public function respond(User $user, SurveyAssignment $assignment): bool
    {
        if (! $assignment->isActive()) {
            return false;
        }

        if ($assignment->client_id !== null) {
            return in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
                && in_array((string) $assignment->client_id, $user->accessibleClientIds(), true);
        }

        if ($assignment->entrepreneurProfile === null) {
            $assignment->load('entrepreneurProfile');
        }

        return $assignment->entrepreneurProfile !== null
            && (string) $assignment->entrepreneurProfile->user_id === (string) $user->getKey();
    }

    public function cancel(User $user, SurveyAssignment $assignment): bool
    {
        if ($user->fsaRole() === User::TYPE_SUPER_ADMIN) {
            return $assignment->isActive();
        }

        if (! $this->allows($user, Permission::SURVEYS_MANAGE)) {
            return false;
        }

        return $assignment->isActive();
    }
}
