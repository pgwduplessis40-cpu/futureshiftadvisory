<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClientInviteReconciler
{
    public function __construct(
        private readonly AuditWriter $auditWriter,
        private readonly RequestContext $context,
    ) {}

    public function reconcile(User $user, ?InviteToken $invite = null): ?Client
    {
        if (! in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)) {
            return null;
        }

        $email = Str::lower(trim((string) $user->email));
        if ($email === '') {
            return null;
        }

        $reconciledFromLogin = ! $invite instanceof InviteToken;

        return $this->context->withSystemContext(
            fn (): ?Client => DB::transaction(function () use ($email, $invite, $reconciledFromLogin, $user): ?Client {
                $acceptedInvite = $this->acceptedInviteFor($user, $email, $invite);
                $client = $acceptedInvite instanceof InviteToken
                    ? $this->clientFor($user, $email, $acceptedInvite)
                    : Client::query()
                        ->where('primary_contact_user_id', $user->getKey())
                        ->latest()
                        ->first();
                if (! $client instanceof Client) {
                    return null;
                }

                if (
                    $client->primary_contact_user_id !== null
                    && (string) $client->primary_contact_user_id !== (string) $user->getKey()
                ) {
                    return null;
                }

                if ($acceptedInvite instanceof InviteToken && $acceptedInvite->accepted_by_user_id === null) {
                    $acceptedInvite->forceFill([
                        'accepted_by_user_id' => $user->getKey(),
                    ])->save();
                }

                $registrySources = is_array($client->registry_sources) ? $client->registry_sources : [];
                $updates = [
                    'primary_contact_user_id' => $user->getKey(),
                ];
                if ($acceptedInvite instanceof InviteToken) {
                    $updates['registry_sources'] = [
                        ...$registrySources,
                        'invite_token_id' => $acceptedInvite->getKey(),
                        'accepted_by_user_id' => $user->getKey(),
                        'accepted_at' => $acceptedInvite->accepted_at?->toIso8601String() ?? now()->toIso8601String(),
                    ];
                }

                if (str_starts_with((string) $client->legal_name, 'Invited client -') && trim((string) $user->name) !== '') {
                    $updates['legal_name'] = $user->name;
                }

                $client->forceFill($updates)->save();

                $engagement = $client->engagement_type instanceof \BackedEnum
                    ? (string) $client->engagement_type->value
                    : (string) $client->engagement_type;

                ClientTeamMember::query()->updateOrCreate(
                    [
                        'client_id' => $client->getKey(),
                        'user_id' => $user->getKey(),
                    ],
                    [
                        'role' => 'primary_contact',
                        'granted_modules' => array_values(array_filter(['portal', $engagement])),
                    ],
                );

                $this->auditWriter->record(
                    ! $acceptedInvite instanceof InviteToken
                        ? 'client.primary_contact_reconciled'
                        : ($reconciledFromLogin ? 'client.invite_reconciled' : 'client.invite_accepted'),
                    subject: $client,
                    actor: $user,
                    after: [
                        'client_id' => $client->getKey(),
                        'invite_token_id' => $acceptedInvite?->getKey(),
                        'primary_contact_user_id' => $user->getKey(),
                        'reconciled_from_login' => $reconciledFromLogin,
                    ],
                );

                return $client->refresh();
            }),
        );
    }

    private function acceptedInviteFor(User $user, string $email, ?InviteToken $invite): ?InviteToken
    {
        if (
            $invite instanceof InviteToken
            && $invite->isAccepted()
            && $invite->target_user_type === $user->user_type
            && Str::lower(trim((string) $invite->email)) === $email
        ) {
            return $invite;
        }

        return InviteToken::query()
            ->whereRaw('lower(trim(email)) = ?', [$email])
            ->where('target_user_type', $user->user_type)
            ->whereNotNull('accepted_at')
            ->where(function ($query) use ($user): void {
                $query
                    ->where('accepted_by_user_id', $user->getKey())
                    ->orWhereNull('accepted_by_user_id');
            })
            ->latest('accepted_at')
            ->latest()
            ->first();
    }

    private function clientFor(User $user, string $email, InviteToken $invite): ?Client
    {
        $client = Client::query()
            ->where('registry_sources->source', 'advisor_client_invite')
            ->where('registry_sources->invite_token_id', $invite->getKey())
            ->lockForUpdate()
            ->latest()
            ->first();

        if ($client instanceof Client) {
            return $client;
        }

        $client = Client::query()
            ->whereNull('primary_contact_user_id')
            ->where('registry_sources->source', 'advisor_client_invite')
            ->whereRaw("lower(trim(registry_sources->>'invite_email')) = ?", [$email])
            ->lockForUpdate()
            ->latest()
            ->first();

        if ($client instanceof Client) {
            return $client;
        }

        return Client::query()
            ->where('primary_contact_user_id', $user->getKey())
            ->latest()
            ->first();
    }
}
