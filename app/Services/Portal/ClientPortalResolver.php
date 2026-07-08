<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\ClientStatus;
use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\EntrepreneurProfile;
use App\Models\NpoBoardMember;
use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ClientPortalResolver
{
    public function __construct(
        private readonly EntrepreneurInviteReconciler $entrepreneurInvites,
        private readonly RequestContext $context,
    ) {}

    public function resolveFor(Request $request): Client
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpException(403);
        }

        if ($user->user_type === User::TYPE_NPO_BOARD_MEMBER) {
            return $this->resolveForNpoBoardMember($user);
        }

        abort_unless(in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true), 403);

        $clientIds = $user->accessibleClientIds();
        abort_if($clientIds === [], 403, 'No client portal is assigned to this account yet.');

        return Client::query()
            ->whereIn('id', $clientIds)
            ->where('status', '!=', ClientStatus::SUSPENDED->value)
            ->latest()
            ->firstOrFail();
    }

    public function resolveForServiceWorkspace(Request $request): Client
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpException(403);
        }

        if ($user->user_type === User::TYPE_ENTREPRENEUR) {
            return $this->resolveEntrepreneurWorkspaceClient($user);
        }

        return $this->resolveFor($request);
    }

    private function resolveForNpoBoardMember(User $user): Client
    {
        $membership = NpoBoardMember::query()
            ->where('user_id', $user->getKey())
            ->where('active', true)
            ->whereNull('revoked_at')
            ->latest()
            ->first();

        abort_unless($membership instanceof NpoBoardMember, 403, 'No NPO board portal is assigned to this account yet.');

        return Client::query()
            ->where('id', $membership->client_id)
            ->where('status', '!=', ClientStatus::SUSPENDED->value)
            ->firstOrFail();
    }

    private function resolveEntrepreneurWorkspaceClient(User $user): Client
    {
        $profile = $this->entrepreneurInvites->reconcile($user);

        abort_unless($profile instanceof EntrepreneurProfile, 403, 'No entrepreneur portal is assigned to this account yet.');

        $client = $this->context->withSystemContext(function () use ($profile, $user): Client {
            return DB::transaction(function () use ($profile, $user): Client {
                $lockedProfile = EntrepreneurProfile::query()
                    ->with('assignedAdvisor')
                    ->whereKey($profile->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $client = $lockedProfile->client_id !== null
                    ? Client::query()
                        ->whereKey($lockedProfile->client_id)
                        ->where('status', '!=', ClientStatus::SUSPENDED->value)
                        ->first()
                    : null;

                abort_if($lockedProfile->client_id !== null && ! $client instanceof Client, 403, 'The linked entrepreneur service workspace is not available.');

                if (! $client instanceof Client) {
                    $client = Client::query()->create([
                        'engagement_type' => EngagementType::ENTREPRENEUR_MODULE,
                        'legal_name' => $lockedProfile->name ?: ($user->name ?: $user->email),
                        'trading_name' => $lockedProfile->name ?: null,
                        'data_quality' => Client::DATA_QUALITY_LOW,
                        'registry_sources' => [
                            'source' => 'entrepreneur_portal_service_access',
                            'source_label' => 'Created to let an entrepreneur access additional portal services.',
                            'entrepreneur_profile_id' => $lockedProfile->getKey(),
                        ],
                        'created_by_user_id' => $lockedProfile->assigned_advisor_id ?? $user->getKey(),
                        'primary_contact_user_id' => $user->getKey(),
                    ]);

                    $lockedProfile->forceFill(['client_id' => $client->getKey()])->save();
                }

                $this->attachEntrepreneurServiceTeam($client, $lockedProfile, $user);

                return $client->refresh();
            });
        });

        $this->context->apply($user->fsaRole(), $user->accessibleClientIds(), (string) $user->getKey());

        return $client;
    }

    private function attachEntrepreneurServiceTeam(Client $client, EntrepreneurProfile $profile, User $user): void
    {
        $modules = [
            'portal',
            EngagementType::ENTREPRENEUR_MODULE->value,
            EngagementType::DUE_DILIGENCE->value,
        ];

        ClientTeamMember::query()->updateOrCreate(
            [
                'client_id' => $client->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'role' => 'primary_contact',
                'granted_modules' => $modules,
            ],
        );

        $advisor = $profile->assignedAdvisor;
        if ($advisor instanceof User && in_array($advisor->user_type, [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN], true)) {
            ClientTeamMember::query()->updateOrCreate(
                [
                    'client_id' => $client->getKey(),
                    'user_id' => $advisor->getKey(),
                ],
                [
                    'role' => 'lead_advisor',
                    'granted_modules' => $modules,
                ],
            );
        }
    }
}
