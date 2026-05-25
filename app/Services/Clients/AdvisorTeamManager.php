<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\ClientStatus;
use App\Models\AdvisorTeam;
use App\Models\AdvisorTeamMember;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\OffboardingRecord;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AdvisorTeamManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function createTeam(string $name, User $leadAdvisor): AdvisorTeam
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Advisor team name is required.');
        }

        return DB::transaction(function () use ($name, $leadAdvisor): AdvisorTeam {
            $team = AdvisorTeam::query()->create([
                'name' => $name,
                'lead_advisor_user_id' => $leadAdvisor->id,
                'status' => AdvisorTeam::STATUS_ACTIVE,
                'metadata' => [],
            ]);

            AdvisorTeamMember::query()->create([
                'advisor_team_id' => $team->id,
                'user_id' => $leadAdvisor->id,
                'role' => AdvisorTeamMember::ROLE_LEAD,
                'joined_at' => now(),
            ]);

            $this->audit->record('advisor_team.created', subject: $team, after: [
                'name' => $team->name,
                'lead_advisor_user_id' => $leadAdvisor->id,
            ]);

            return $team->refresh();
        });
    }

    public function addMember(AdvisorTeam $team, User $user, string $role = AdvisorTeamMember::ROLE_MEMBER): AdvisorTeamMember
    {
        if (! in_array($role, [AdvisorTeamMember::ROLE_LEAD, AdvisorTeamMember::ROLE_MEMBER, AdvisorTeamMember::ROLE_OPERATIONS], true)) {
            throw new InvalidArgumentException("Unsupported advisor team role [{$role}].");
        }

        $member = AdvisorTeamMember::query()->updateOrCreate(
            [
                'advisor_team_id' => $team->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'joined_at' => now(),
                'left_at' => null,
            ],
        );

        $this->audit->record('advisor_team.member_added', subject: $team, after: [
            'member_user_id' => $user->id,
            'role' => $role,
        ]);

        return $member;
    }

    public function assignClient(
        AdvisorTeam $team,
        Client $client,
        User $advisor,
        string $clientRole = 'lead_advisor',
        ?array $grantedModules = null,
    ): ClientTeamMember {
        $membershipExists = AdvisorTeamMember::query()
            ->where('advisor_team_id', $team->id)
            ->where('user_id', $advisor->id)
            ->whereNull('left_at')
            ->exists();

        if (! $membershipExists) {
            $this->addMember($team, $advisor);
        }

        $clientTeamMember = ClientTeamMember::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'user_id' => $advisor->id,
            ],
            [
                'advisor_team_id' => $team->id,
                'role' => $clientRole,
                'granted_modules' => $grantedModules,
            ],
        );

        $this->audit->record('advisor_team.client_assigned', subject: $client, after: [
            'advisor_team_id' => $team->id,
            'advisor_user_id' => $advisor->id,
            'client_role' => $clientRole,
        ]);

        return $clientTeamMember->refresh();
    }

    public function reassignClient(AdvisorTeam $fromTeam, AdvisorTeam $toTeam, Client $client, User $actor): int
    {
        $before = DB::table('client_team')
            ->where('client_id', $client->id)
            ->where('advisor_team_id', $fromTeam->id)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        $count = DB::table('client_team')
            ->where('client_id', $client->id)
            ->where('advisor_team_id', $fromTeam->id)
            ->update([
                'advisor_team_id' => $toTeam->id,
                'updated_at' => now(),
            ]);

        $this->audit->record('advisor_team.client_reassigned', subject: $client, before: [
            'advisor_team_id' => $fromTeam->id,
            'rows' => $before,
        ], after: [
            'advisor_team_id' => $toTeam->id,
            'rows_updated' => $count,
            'actor_user_id' => $actor->id,
        ]);

        return $count;
    }

    /**
     * @return array{team_id: string, advisor_count: int, active_client_count: int, capacity_limit: int, remaining: int, warning: bool, blocked: bool}
     */
    public function capacity(AdvisorTeam $team): array
    {
        $advisorCount = (int) AdvisorTeamMember::query()
            ->where('advisor_team_id', $team->id)
            ->whereNull('left_at')
            ->count();
        $perAdvisorLimit = max(1, (int) config('clients.capacity.limit', 30));
        $capacityLimit = max(1, $advisorCount) * $perAdvisorLimit;
        $activeClientCount = (int) DB::table('client_team')
            ->join('clients', 'clients.id', '=', 'client_team.client_id')
            ->where('client_team.advisor_team_id', $team->id)
            ->where('clients.status', '!=', ClientStatus::OFFBOARDED->value)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('offboarding_records')
                    ->whereColumn('offboarding_records.client_id', 'clients.id')
                    ->where('offboarding_records.status', OffboardingRecord::STATUS_COMPLETED);
            })
            ->distinct('clients.id')
            ->count('clients.id');

        return [
            'team_id' => (string) $team->id,
            'advisor_count' => $advisorCount,
            'active_client_count' => $activeClientCount,
            'capacity_limit' => $capacityLimit,
            'remaining' => max(0, $capacityLimit - $activeClientCount),
            'warning' => $activeClientCount >= (int) floor($capacityLimit * 0.8) && $activeClientCount < $capacityLimit,
            'blocked' => $activeClientCount >= $capacityLimit,
        ];
    }
}
