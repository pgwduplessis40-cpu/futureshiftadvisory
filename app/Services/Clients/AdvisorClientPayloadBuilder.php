<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\InviteToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Typed resource contract for the Advisor client index and workspace header.
 * Invitation lifecycle fields are calculated here rather than leaking the
 * client registry payload across the Inertia boundary.
 *
 * @phpstan-type AdvisorAssignment array{advisor_name:?string,role:string,team_name:?string}
 * @phpstan-type AdvisorClientSummary array{
 *     id:string,
 *     engagement_type:string,
 *     engagement_type_label:string,
 *     is_npo:bool,
 *     status:string,
 *     status_label:string,
 *     account_status:string,
 *     account_status_label:string,
 *     nzbn:?string,
 *     legal_name:?string,
 *     trading_name:?string,
 *     entity_type:?string,
 *     gst_registered:bool,
 *     filing_status:?string,
 *     data_quality:string,
 *     advisor_assignments?:list<AdvisorAssignment>
 * }
 * @phpstan-type ClientInvitationSummary array{
 *     email:string,
 *     status:string,
 *     status_label:string,
 *     accepted_at:?string,
 *     expires_at:?string,
 *     resend_url:?string,
 *     cancel_url:?string
 * }
 */
final class AdvisorClientPayloadBuilder
{
    /** @return AdvisorClientSummary */
    public function summary(
        Client $client,
        bool $includeAdvisorAssignments = false,
        ?InviteToken $invite = null,
        ?bool $hasActivatedAccount = null,
    ): array {
        $engagementType = $client->engagement_type instanceof EngagementType
            ? $client->engagement_type
            : EngagementType::from((string) $client->engagement_type);
        $status = $client->status instanceof ClientStatus
            ? $client->status
            : ClientStatus::from((string) ($client->status ?? ClientStatus::ACTIVE->value));
        [$accountStatus, $accountStatusLabel] = $this->accountStatus(
            $client,
            $invite,
            $status,
            $hasActivatedAccount ?? $this->hasActivatedAccount($client),
        );

        $summary = [
            'id' => (string) $client->getKey(),
            'engagement_type' => $engagementType->value,
            'engagement_type_label' => $engagementType->label(),
            'is_npo' => $engagementType === EngagementType::NPO,
            'status' => $status->value,
            'status_label' => $status->label(),
            'account_status' => $accountStatus,
            'account_status_label' => $accountStatusLabel,
            'nzbn' => $client->nzbn,
            'legal_name' => $client->legal_name,
            'trading_name' => $client->trading_name,
            'entity_type' => $client->entity_type,
            'gst_registered' => (bool) $client->gst_registered,
            'filing_status' => $client->filing_status,
            'data_quality' => (string) $client->data_quality,
        ];

        if ($includeAdvisorAssignments) {
            $summary['advisor_assignments'] = $client->teamMembers
                ->map(fn (ClientTeamMember $member): array => [
                    'advisor_name' => $member->user?->name,
                    'role' => $member->role,
                    'team_name' => $member->advisorTeam?->name,
                ])
                ->values()
                ->all();
        }

        return $summary;
    }

    public function tokenId(Client $client): ?string
    {
        $registrySources = is_array($client->registry_sources) ? $client->registry_sources : [];
        if (($registrySources['source'] ?? null) !== 'advisor_client_invite') {
            return null;
        }

        $inviteId = $registrySources['invite_token_id'] ?? null;

        return is_string($inviteId) && $inviteId !== '' ? $inviteId : null;
    }

    public function inviteFor(Client $client): ?InviteToken
    {
        $inviteId = $this->tokenId($client);

        return $inviteId !== null
            ? InviteToken::query()->find($inviteId)
            : null;
    }

    public function inviteEmail(Client $client): string
    {
        $registrySources = is_array($client->registry_sources) ? $client->registry_sources : [];
        $email = Str::lower(trim((string) ($registrySources['invite_email'] ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    public function canResendInvite(Client $client, ?InviteToken $invite): bool
    {
        $email = $this->inviteEmail($client);

        return $this->tokenId($client) !== null
            && $client->primary_contact_user_id === null
            && $invite?->accepted_at === null
            && $email !== ''
            && ! User::query()->whereRaw('lower(trim(email)) = ?', [$email])->exists();
    }

    public function canCancelInvite(Client $client, ?InviteToken $invite): bool
    {
        $registrySources = is_array($client->registry_sources) ? $client->registry_sources : [];

        return $this->canResendInvite($client, $invite)
            && $invite instanceof InviteToken
            && ! isset($registrySources['invite_cancelled_at']);
    }

    public function hasActivatedAccount(Client $client): bool
    {
        $email = $this->inviteEmail($client);

        return $email !== ''
            && User::query()
                ->whereIn('user_type', [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM])
                ->whereRaw('lower(trim(email)) = ?', [$email])
                ->exists();
    }

    /** @return array{0:string,1:string} */
    public function accountStatus(
        Client $client,
        ?InviteToken $invite,
        ClientStatus $lifecycle,
        bool $hasActivatedAccount,
    ): array {
        if ($this->tokenId($client) === null) {
            return [$lifecycle->value, $lifecycle->label()];
        }

        if ($client->primary_contact_user_id !== null || $invite?->isAccepted() || $hasActivatedAccount) {
            return [$lifecycle->value, $lifecycle->label()];
        }

        $registrySources = is_array($client->registry_sources) ? $client->registry_sources : [];
        if (isset($registrySources['invite_cancelled_at'])) {
            return ['invite_cancelled', 'Invite cancelled'];
        }

        return ['awaiting_activation', 'Awaiting activation'];
    }

    /** @return ClientInvitationSummary|null */
    public function invitationSummary(Client $client, ?InviteToken $invite): ?array
    {
        if ($this->tokenId($client) === null) {
            return null;
        }

        [$status, $statusLabel] = $this->accountStatus(
            $client,
            $invite,
            $client->status instanceof ClientStatus
                ? $client->status
                : ClientStatus::from((string) ($client->status ?? ClientStatus::ACTIVE->value)),
            $this->hasActivatedAccount($client),
        );

        return [
            'email' => $this->inviteEmail($client),
            'status' => $status,
            'status_label' => $statusLabel,
            'accepted_at' => $invite?->accepted_at?->toIso8601String(),
            'expires_at' => $invite?->expires_at?->toIso8601String(),
            'resend_url' => $this->canResendInvite($client, $invite)
                ? route('advisor.clients.invite.resend', $client, absolute: false)
                : null,
            'cancel_url' => $this->canCancelInvite($client, $invite)
                ? route('advisor.clients.invite.cancel', $client, absolute: false)
                : null,
        ];
    }
}
