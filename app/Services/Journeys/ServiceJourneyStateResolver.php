<?php

declare(strict_types=1);

namespace App\Services\Journeys;

use App\Enums\EngagementType;
use App\Enums\ReportType;
use App\Enums\SurveyAssignmentStatus;
use App\Models\Client;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\Document;
use App\Models\NpoEngagement;
use App\Models\OutcomeFollowUp;
use App\Models\PostAcquisitionMigration;
use App\Models\Report;
use App\Models\ServiceActivation;
use App\Models\SurveyAssignment;
use App\Services\Portal\OnboardingWizard;

/**
 * A conservative, replayable source of generic journey facts. It deliberately
 * awards no financial result, speed, or advisor-owned work as client points.
 */
final class ServiceJourneyStateResolver
{
    public function __construct(private readonly OnboardingWizard $wizard) {}

    /**
     * @return array<array-key, mixed>
     */
    public function forClient(Client $client, string $serviceKey): array
    {
        $serviceKey = ServiceJourneyPrograms::normalise($serviceKey);
        $program = ServiceJourneyPrograms::for($serviceKey);
        $progress = $this->wizard->progress($client);
        $reports = Report::query()
            ->where('client_id', $client->getKey())
            ->whereNotNull('generated_at')
            ->when($this->reportTypesFor($serviceKey) !== [], fn ($query) => $query->whereIn('type', $this->reportTypesFor($serviceKey)));
        $hasOutput = (clone $reports)->exists();
        $hasReview = (clone $reports)
            ->where(function ($query): void {
                $query->whereNotNull('reviewed_at')->orWhere('review_status', 'reviewed');
            })
            ->exists();
        $hasEvidence = $this->hasVerifiedEvidence($client, $serviceKey);
        $hasOpenFollowUp = OutcomeFollowUp::query()
            ->where('client_id', $client->getKey())
            ->where('status', OutcomeFollowUp::STATUS_PENDING)
            ->exists()
            || SurveyAssignment::query()
                ->where('client_id', $client->getKey())
                ->whereIn('status', SurveyAssignmentStatus::activeValues())
                ->exists();

        return [
            'service_key' => $serviceKey,
            'program_version' => $program['version'],
            'stages' => [
                'scope' => $this->scopeComplete($client, $serviceKey, (int) $progress['percentage']),
                'evidence' => $hasEvidence,
                'advisor_review' => $hasReview,
                'outputs' => $hasOutput,
                'outcomes' => $hasOutput && ! $hasOpenFollowUp,
            ],
        ];
    }

    private function scopeComplete(Client $client, string $serviceKey, int $onboardingPercentage): bool
    {
        if ($serviceKey === EngagementType::STANDARD_ADVISORY->value
            && ($onboardingPercentage >= 100 || $client->engagement_type_locked_at !== null)) {
            return true;
        }

        if (in_array($serviceKey, [
            ServiceActivation::SERVICE_DUE_DILIGENCE,
            ServiceActivation::SERVICE_INTEGRATION_SCOPING,
            ServiceActivation::SERVICE_INTEGRATION,
        ], true) && ServiceActivation::query()
            ->where('client_id', $client->getKey())
            ->where('service_type', $serviceKey)
            ->where('status', ServiceActivation::STATUS_ACTIVE)
            ->exists()) {
            return true;
        }

        return match ($serviceKey) {
            EngagementType::DUE_DILIGENCE->value => DdEngagement::query()
                ->where('client_id', $client->getKey())
                ->exists(),
            EngagementType::POST_ACQUISITION_ADVISORY->value => PostAcquisitionMigration::query()
                ->where('client_id', $client->getKey())
                ->exists(),
            EngagementType::NPO->value => NpoEngagement::query()
                ->where('client_id', $client->getKey())
                ->exists(),
            default => false,
        };
    }

    private function hasVerifiedEvidence(Client $client, string $serviceKey): bool
    {
        return match ($serviceKey) {
            EngagementType::DUE_DILIGENCE->value => DdDataRoomItem::query()
                ->where('client_id', $client->getKey())
                ->where('source', DdDataRoomItem::SOURCE_CLIENT_UPLOAD)
                ->exists(),
            EngagementType::NPO->value => Document::query()
                ->where('client_id', $client->getKey())
                ->whereNotNull('npo_engagement_id')
                ->where('scanner_result', Document::SCANNER_CLEAN)
                ->exists(),
            // These services have no persisted client-evidence link yet. Keep
            // recognition honest until their service adapters provide one.
            ServiceActivation::SERVICE_INTEGRATION_SCOPING,
            ServiceActivation::SERVICE_INTEGRATION => false,
            default => Document::query()
                ->where('client_id', $client->getKey())
                ->where('scanner_result', Document::SCANNER_CLEAN)
                ->exists(),
        };
    }

    /**
     * @return array<int, string>
     */
    private function reportTypesFor(string $serviceKey): array
    {
        return match ($serviceKey) {
            EngagementType::DUE_DILIGENCE->value => [
                ReportType::DueDiligence->value,
                ReportType::AcquisitionGoNoGo->value,
            ],
            EngagementType::POST_ACQUISITION_ADVISORY->value => [ReportType::PostAcquisitionGap->value],
            EngagementType::NPO->value => [
                ReportType::GovernanceReview->value,
                ReportType::NpoHealth->value,
                ReportType::NpoAdvisor->value,
                ReportType::FunderAccountability->value,
                ReportType::SocialEnterpriseDual->value,
                ReportType::ImpactSummary->value,
            ],
            ServiceActivation::SERVICE_INTEGRATION_SCOPING,
            ServiceActivation::SERVICE_INTEGRATION => ['__service_output_not_linked__'],
            default => [],
        };
    }
}
