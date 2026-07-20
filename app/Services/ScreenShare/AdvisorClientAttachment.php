<?php

declare(strict_types=1);

namespace App\Services\ScreenShare;

use App\Models\Client;
use App\Models\User;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final class AdvisorClientAttachment
{
    public function __construct(private readonly RequestContext $context) {}

    public function resolve(User $advisor, Client $client): ?ScreenShareAttachment
    {
        return $this->context->withSystemContext(function () use ($advisor, $client): ?ScreenShareAttachment {
            $advisorId = (string) $advisor->getKey();
            $clientId = (string) $client->getKey();

            if (DB::table('client_team')
                ->where('client_id', $clientId)
                ->where('user_id', $advisorId)
                ->exists()) {
                return new ScreenShareAttachment('direct_client_team');
            }

            $leadTeamId = DB::table('client_team as client_team')
                ->join('advisor_teams', 'advisor_teams.id', '=', 'client_team.advisor_team_id')
                ->where('client_team.client_id', $clientId)
                ->where('advisor_teams.lead_advisor_user_id', $advisorId)
                ->value('advisor_teams.id');

            if (is_string($leadTeamId) && $leadTeamId !== '') {
                return new ScreenShareAttachment('advisor_team', $leadTeamId);
            }

            $memberTeamId = DB::table('client_team as client_team')
                ->join('advisor_team_members', 'advisor_team_members.advisor_team_id', '=', 'client_team.advisor_team_id')
                ->where('client_team.client_id', $clientId)
                ->where('advisor_team_members.user_id', $advisorId)
                ->where('advisor_team_members.role', 'lead')
                ->whereNull('advisor_team_members.left_at')
                ->value('advisor_team_members.advisor_team_id');

            return is_string($memberTeamId) && $memberTeamId !== ''
                ? new ScreenShareAttachment('advisor_team', $memberTeamId)
                : null;
        });
    }
}
