<?php

declare(strict_types=1);

namespace App\Services\Npo;

use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\NpoComplianceAlert;
use App\Models\NpoEngagement;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Integration\CharitiesServices\Contracts\CharitiesServicesClient;
use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class NpoComplianceLookup
{
    public function __construct(
        private readonly CharitiesServicesClient $charities,
        private readonly CompaniesOfficeClient $companiesOffice,
        private readonly AuditWriter $auditWriter,
    ) {}

    /**
     * @return array{
     *     npo_engagement_id:string,
     *     charity:array<string, mixed>|null,
     *     incorporated_society:array<string, mixed>|null,
     *     source_badges:array<string, string>,
     *     critical_alerts:array<int, array<string, mixed>>,
     *     analysis_blocked:bool
     * }
     */
    public function refresh(
        NpoEngagement $engagement,
        ?User $actor = null,
        ?string $charityRegistrationNumber = null,
        ?string $incorporatedSocietyNumber = null,
    ): array {
        $engagement->loadMissing('client');
        $client = $engagement->client;
        if (! $client instanceof Client) {
            throw new InvalidArgumentException('NPO engagement must belong to a client.');
        }

        $structure = $this->legalStructure($engagement);
        $charity = $this->requiresCharityLookup($structure)
            ? $this->charities->charityProfile($this->lookupKey($charityRegistrationNumber, $client))
            : null;
        $incorporatedSociety = $this->requiresIncorporatedSocietyLookup($structure)
            ? $this->companiesOffice->incorporatedSocietyProfile($this->lookupKey($incorporatedSocietyNumber, $client))
            : null;

        if ($incorporatedSociety !== null) {
            $this->syncIsa2022Status($engagement, $incorporatedSociety);
        }

        $this->auditWriter->record('npo.compliance_status_refreshed', subject: $engagement, actor: $actor, after: [
            'client_id' => $client->getKey(),
            'charity_source' => $charity['source_badge'] ?? null,
            'incorporated_society_source' => $incorporatedSociety['source_badge'] ?? null,
            'analysis_blocked' => $this->blocksAnalysis($engagement),
        ]);

        return [
            'npo_engagement_id' => (string) $engagement->getKey(),
            'charity' => $charity,
            'incorporated_society' => $incorporatedSociety,
            'source_badges' => [
                'charities_services' => (string) ($charity['source_badge'] ?? 'not_required'),
                'incorporated_societies' => (string) ($incorporatedSociety['source_badge'] ?? 'not_required'),
            ],
            'critical_alerts' => $this->criticalAlerts($engagement),
            'analysis_blocked' => $this->blocksAnalysis($engagement),
        ];
    }

    public function acknowledge(NpoComplianceAlert $alert, User $advisor): NpoComplianceAlert
    {
        $alert->forceFill([
            'acknowledged_at' => now(),
            'acknowledged_by_user_id' => $advisor->getKey(),
        ])->save();

        $this->auditWriter->record('npo.compliance_alert_acknowledged', subject: $alert, actor: $advisor, after: [
            'client_id' => $alert->client_id,
            'npo_engagement_id' => $alert->npo_engagement_id,
            'type' => $alert->type,
            'severity' => $alert->severity,
        ]);

        return $alert->refresh();
    }

    public function blocksAnalysis(NpoEngagement $engagement): bool
    {
        return NpoComplianceAlert::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('severity', NpoComplianceAlert::SEVERITY_CRITICAL)
            ->whereNull('acknowledged_at')
            ->whereNull('resolved_at')
            ->exists();
    }

    private function syncIsa2022Status(NpoEngagement $engagement, array $incorporatedSociety): void
    {
        $isReregistered = (bool) Arr::get($incorporatedSociety, 'isa_2022.reregistered', false);

        $engagement->forceFill([
            'isa_2022_reregistered' => $isReregistered,
        ])->save();

        if ($isReregistered) {
            $this->resolveIsa2022Alert($engagement);

            return;
        }

        $this->raiseIsa2022Alert($engagement, $incorporatedSociety);
    }

    private function raiseIsa2022Alert(NpoEngagement $engagement, array $incorporatedSociety): void
    {
        $engagement->loadMissing('client');
        $client = $engagement->client;
        if (! $client instanceof Client) {
            throw new InvalidArgumentException('NPO engagement must belong to a client.');
        }

        $alert = NpoComplianceAlert::query()->firstOrNew([
            'client_id' => $client->getKey(),
            'npo_engagement_id' => $engagement->getKey(),
            'type' => NpoComplianceAlert::TYPE_ISA_2022_REREGISTRATION_MISSING,
        ]);

        if (! $alert->exists) {
            $alert->triggered_at = now();
        }

        $alert->forceFill([
            'severity' => NpoComplianceAlert::SEVERITY_CRITICAL,
            'message' => 'Incorporated Societies Act 2022 re-registration is not recorded. Advisor acknowledgement is required before downstream NPO analysis continues.',
            'source' => (string) ($incorporatedSociety['source_badge'] ?? 'unknown'),
            'metadata' => [
                'society_number' => $incorporatedSociety['society_number'] ?? null,
                'status' => $incorporatedSociety['status'] ?? null,
                'isa_2022' => $incorporatedSociety['isa_2022'] ?? null,
            ],
            'resolved_at' => null,
        ])->save();
    }

    private function resolveIsa2022Alert(NpoEngagement $engagement): void
    {
        NpoComplianceAlert::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('type', NpoComplianceAlert::TYPE_ISA_2022_REREGISTRATION_MISSING)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalAlerts(NpoEngagement $engagement): array
    {
        return NpoComplianceAlert::query()
            ->where('npo_engagement_id', $engagement->getKey())
            ->where('severity', NpoComplianceAlert::SEVERITY_CRITICAL)
            ->whereNull('resolved_at')
            ->latest('triggered_at')
            ->get()
            ->map(fn (NpoComplianceAlert $alert): array => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'message' => $alert->message,
                'source' => $alert->source,
                'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
                'blocks_analysis' => $alert->blocksAnalysis(),
            ])
            ->values()
            ->all();
    }

    private function lookupKey(?string $explicit, Client $client): string
    {
        $candidate = trim((string) ($explicit ?: $client->nzbn ?: $client->legal_name));

        return $candidate === '' ? (string) $client->getKey() : $candidate;
    }

    private function legalStructure(NpoEngagement $engagement): NpoLegalStructure
    {
        return $engagement->legal_structure instanceof NpoLegalStructure
            ? $engagement->legal_structure
            : NpoLegalStructure::from((string) $engagement->legal_structure);
    }

    private function requiresCharityLookup(NpoLegalStructure $structure): bool
    {
        return in_array($structure, [
            NpoLegalStructure::RegisteredCharity,
            NpoLegalStructure::RegisteredCharityAndIncorporatedSociety,
            NpoLegalStructure::CharitableTrustBoard,
            NpoLegalStructure::SocialEnterpriseRegisteredCharity,
        ], true);
    }

    private function requiresIncorporatedSocietyLookup(NpoLegalStructure $structure): bool
    {
        return in_array($structure, [
            NpoLegalStructure::IncorporatedSociety,
            NpoLegalStructure::RegisteredCharityAndIncorporatedSociety,
        ], true);
    }
}
