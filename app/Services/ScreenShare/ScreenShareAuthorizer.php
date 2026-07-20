<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\ScreenShareSession;
use App\Models\User;
use App\Support\RequestContext;

final class ScreenShareAuthorizer
{
    public function __construct(
        private readonly AdvisorClientAttachment $attachments,
        private readonly RequestContext $context,
    ) {}

    public function assertCanRequest(User $advisor, Client $client, User $clientUser): ScreenShareAttachment
    {
        abort_unless($this->isSupportOperator($advisor), 403);
        abort_unless(in_array($clientUser->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true), 403);
        abort_if((string) $advisor->getKey() === (string) $clientUser->getKey(), 403);
        abort_unless($this->isCurrentClientMember($client, $clientUser), 403);

        if ($this->isPlatformAdministrator($advisor)) {
            return new ScreenShareAttachment('super_admin');
        }

        $attachment = $this->attachments->resolve($advisor, $client);
        abort_unless($attachment instanceof ScreenShareAttachment, 403);

        return $attachment;
    }

    public function assertCanRequestForEntrepreneur(
        User $advisor,
        EntrepreneurProfile $profile,
        User $entrepreneur,
    ): ScreenShareAttachment {
        abort_unless($this->canRequestForEntrepreneur($advisor, $profile, $entrepreneur), 403);

        return new ScreenShareAttachment(
            $this->isPlatformAdministrator($advisor) ? 'super_admin' : 'entrepreneur_assignment',
        );
    }

    public function canRequestForEntrepreneur(
        User $advisor,
        EntrepreneurProfile $profile,
        User $entrepreneur,
    ): bool {
        return $this->isSupportOperator($advisor)
            && $entrepreneur->user_type === User::TYPE_ENTREPRENEUR
            && (string) $profile->user_id === (string) $entrepreneur->getKey()
            && (
                $this->isPlatformAdministrator($advisor)
                || (string) $profile->assigned_advisor_id === (string) $advisor->getKey()
            );
    }

    public function assertStillAuthorized(ScreenShareSession $session): ScreenShareAttachment
    {
        return $this->context->withSystemContext(function () use ($session): ScreenShareAttachment {
            $fresh = ScreenShareSession::query()->findOrFail($session->getKey());
            $advisor = User::query()->findOrFail($fresh->advisor_id);
            $clientUser = User::query()->findOrFail($fresh->client_user_id);
            if ($fresh->entrepreneur_profile_id !== null) {
                $profile = EntrepreneurProfile::query()->findOrFail($fresh->entrepreneur_profile_id);

                return $this->assertCanRequestForEntrepreneur($advisor, $profile, $clientUser);
            }
            $client = Client::query()->findOrFail($fresh->client_id);

            return $this->assertCanRequest($advisor, $client, $clientUser);
        });
    }

    public function assertClientMembership(User $clientUser, Client $client): void
    {
        abort_unless(in_array($clientUser->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true), 403);
        abort_unless($this->isCurrentClientMember($client, $clientUser), 403);
    }

    private function isCurrentClientMember(Client $client, User $user): bool
    {
        return $this->context->withSystemContext(fn (): bool => ClientTeamMember::query()
            ->where('client_id', $client->getKey())
            ->where('user_id', $user->getKey())
            ->exists());
    }

    private function isSupportOperator(User $user): bool
    {
        return in_array($user->fsaRole(), [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
        ], true);
    }

    private function isPlatformAdministrator(User $user): bool
    {
        return $user->fsaRole() === User::TYPE_SUPER_ADMIN;
    }
}
