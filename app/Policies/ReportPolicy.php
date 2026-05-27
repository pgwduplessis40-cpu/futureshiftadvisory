<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReportType;
use App\Models\NpoEngagement;
use App\Models\Report;
use App\Models\User;
use App\Services\Npo\NpoBoardAccess;

final class ReportPolicy
{
    public function __construct(private readonly NpoBoardAccess $boardAccess) {}

    public function view(User $user, Report $report): bool
    {
        if (! $user->isNpoBoardMember()) {
            return $report->client_id !== null
                && in_array((string) $report->client_id, $user->accessibleClientIds(), true);
        }

        if (! in_array($report->type, [ReportType::GovernanceReview, ReportType::NpoHealth], true)) {
            return false;
        }

        return is_string($report->npo_engagement_id)
            && $this->boardAccess->isActiveMember($user, $report->npo_engagement_id);
    }

    public function viewFinancialDetail(User $user, Report $report): bool
    {
        return ! $user->isNpoBoardMember() && $this->view($user, $report);
    }

    public function viewFundingStrategy(User $user, NpoEngagement $engagement): bool
    {
        if (! $user->isNpoBoardMember()) {
            return in_array((string) $engagement->client_id, $user->accessibleClientIds(), true);
        }

        return $this->boardAccess->isTreasurer($user, $engagement);
    }
}
