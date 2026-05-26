<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NpoConversionStatus;
use App\Enums\NpoEngagementSubType;
use App\Models\NpoEngagement;
use App\Services\Npo\GovernanceReviewConversion;
use Illuminate\Console\Command;

final class SendGovernanceReviewConversionNudges extends Command
{
    protected $signature = 'npo:send-governance-review-conversion-nudges {--dry-run : Count due nudges without sending notifications}';

    protected $description = 'Send scheduled 30-day and 90-day advisor nudges for unconverted Governance Review engagements.';

    public function handle(GovernanceReviewConversion $conversion): int
    {
        if ($this->option('dry-run')) {
            $count = NpoEngagement::query()
                ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
                ->where('conversion_status', NpoConversionStatus::ReportDelivered->value)
                ->whereNotNull('report_delivered_at')
                ->where('report_delivered_at', '<=', now()->subDays(GovernanceReviewConversion::NUDGE_30_DAYS))
                ->count();

            $this->info("{$count} Governance Review conversion engagement".($count === 1 ? '' : 's').' may have due nudges.');

            return self::SUCCESS;
        }

        $sent = $conversion->sendDueNudges();

        $this->info("{$sent} Governance Review conversion nudge".($sent === 1 ? '' : 's').' sent.');

        return self::SUCCESS;
    }
}
