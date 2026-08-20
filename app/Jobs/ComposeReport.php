<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NpoEngagementSubType;
use App\Enums\ReportType;
use App\Models\Client;
use App\Models\DdEngagement;
use App\Models\NpoEngagement;
use App\Models\PostAcquisitionMigration;
use App\Models\User;
use App\Services\Dd\DdAdviceReportGenerator;
use App\Services\Reports\ReportComposer;
use App\Support\RequestContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ComposeReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 420;

    public function __construct(
        public readonly string $clientId,
        public readonly string $reportType,
        public readonly int $actorId,
    ) {}

    public function handle(
        RequestContext $context,
        ReportComposer $reports,
        DdAdviceReportGenerator $ddAdviceReports,
    ): void {
        $context->apply('system', []);

        $client = Client::query()->find($this->clientId);
        $actor = User::query()->find($this->actorId);
        $type = ReportType::tryFrom($this->reportType);

        if (! $client instanceof Client || ! $actor instanceof User || ! $type instanceof ReportType) {
            return;
        }

        $this->compose($client, $type, $actor, $reports, $ddAdviceReports);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    private function compose(
        Client $client,
        ReportType $type,
        User $actor,
        ReportComposer $reports,
        DdAdviceReportGenerator $ddAdviceReports,
    ): void {
        if ($type === ReportType::Valuation) {
            $reports->composeValuation($client, $actor);

            return;
        }

        if ($type === ReportType::DueDiligence) {
            $engagement = $this->latestDdEngagement($client);

            if ($engagement instanceof DdEngagement) {
                $ddAdviceReports->generateIfReady($engagement, $actor, returnCurrent: true);
            }

            return;
        }

        if ($type === ReportType::AcquisitionGoNoGo) {
            $engagement = $this->latestDdEngagement($client);

            if ($engagement instanceof DdEngagement) {
                $reports->composeAcquisitionGoNoGo($engagement, $actor);
            }

            return;
        }

        if ($type === ReportType::PostAcquisitionGap) {
            $migration = PostAcquisitionMigration::query()
                ->where('advisory_client_id', $client->getKey())
                ->latest('migrated_at')
                ->latest()
                ->first();

            if ($migration instanceof PostAcquisitionMigration) {
                $reports->composePostAcquisitionGap($migration, $actor);
            }

            return;
        }

        if ($type === ReportType::SuccessionValueGap) {
            $reports->composeSuccessionValueGap($client, $actor);

            return;
        }

        if ($type === ReportType::GovernanceReview) {
            $engagement = NpoEngagement::query()
                ->where('client_id', $client->getKey())
                ->where('sub_type', NpoEngagementSubType::GovernanceReview->value)
                ->latest()
                ->first();

            if ($engagement instanceof NpoEngagement) {
                $reports->composeGovernanceReview($engagement, $actor);
            }

            return;
        }

        if (in_array($type, [
            ReportType::NpoHealth,
            ReportType::NpoAdvisor,
            ReportType::SocialEnterpriseDual,
            ReportType::FunderAccountability,
            ReportType::ImpactSummary,
        ], true)) {
            $engagement = NpoEngagement::query()
                ->where('client_id', $client->getKey())
                ->whereIn('sub_type', $type === ReportType::SocialEnterpriseDual
                    ? [NpoEngagementSubType::SocialEnterprise->value]
                    : [
                        NpoEngagementSubType::StandardNpo->value,
                        NpoEngagementSubType::SocialEnterprise->value,
                    ])
                ->latest()
                ->first();

            if (! $engagement instanceof NpoEngagement) {
                return;
            }

            match ($type) {
                ReportType::NpoHealth => $reports->composeNpoHealth($engagement, $actor),
                ReportType::NpoAdvisor => $reports->composeNpoAdvisor($engagement, $actor),
                ReportType::SocialEnterpriseDual => $reports->composeSocialEnterpriseDual($engagement, $actor),
                ReportType::FunderAccountability => $reports->composeFunderAccountability($engagement, actor: $actor),
                ReportType::ImpactSummary => $reports->composeImpactSummary($engagement, [
                    'summary' => 'Impact Summary draft pending client narrative.',
                    'metrics' => [],
                    'platform_metrics' => [],
                ], $actor),
                default => null,
            };

            return;
        }

        $reports->compose($client, $type, $actor);
    }

    private function latestDdEngagement(Client $client): ?DdEngagement
    {
        return DdEngagement::query()
            ->where('client_id', $client->getKey())
            ->latest()
            ->first();
    }
}
