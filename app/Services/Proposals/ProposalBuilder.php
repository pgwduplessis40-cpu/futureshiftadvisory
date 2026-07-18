<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Enums\AnalysisLens;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\AnalysisFinding;
use App\Models\Client;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\IntegrationScope;
use App\Models\NpoEngagement;
use App\Models\Proposal;
use App\Models\ServiceActivation;
use App\Models\StrategicPlan;
use App\Models\Template;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Fees\ProposalPricingTerms;
use App\Services\Integrations\IntegrationScopeProposalGuard;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Reports\UploadedReportTemplateRenderer;
use App\Support\Reports\SourceReferenceLabeler;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ProposalBuilder
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly PvWaterfallBuilder $waterfalls,
        private readonly AuditWriter $audit,
        private readonly UploadedReportTemplateRenderer $uploadedTemplates,
        private readonly IntegrationScopeProposalGuard $integrationScopeGuard,
        private readonly ProposalPricingTerms $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array{created_by_user_id?: int|string|null, npo_engagement_id?: string|null}  $options
     */
    public function generate(Client $client, FeeCalculation $feeCalculation, array $input = [], array $options = []): Proposal
    {
        if ((string) $feeCalculation->client_id !== (string) $client->getKey()) {
            throw new InvalidArgumentException('Fee calculation must belong to the proposal client.');
        }

        $integrationScope = $this->integrationScopeGuard->assertFeeCalculationReady($feeCalculation);

        $npoEngagementId = $this->npoEngagementIdForProposal(
            $client,
            $feeCalculation,
            $input['npo_engagement_id'] ?? $options['npo_engagement_id'] ?? null,
        );

        return DB::transaction(function () use ($client, $feeCalculation, $input, $options, $npoEngagementId, $integrationScope): Proposal {
            // Serialize proposal generation per client before assigning the next visible version.
            Client::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();
            $createdByUserId = $this->normaliseUserId($options['created_by_user_id'] ?? null);
            $recalledProposalIds = $this->recallCurrentProposalsForClient($client, $createdByUserId);
            $nextVersion = ((int) Proposal::query()
                ->where('client_id', $client->getKey())
                ->max('version')) + 1;

            $proposal = Proposal::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $npoEngagementId,
                'fee_calculation_id' => $feeCalculation->getKey(),
                'status' => ProposalStatus::Draft,
                'version' => $nextVersion,
                'scope' => $this->scope($client, $feeCalculation, $input),
                'services' => $this->services($feeCalculation, $input),
                'pv_summary' => $this->pvSummary($client, $feeCalculation, $npoEngagementId),
                'roi_ratio' => $feeCalculation->roi_ratio,
                'acceptance_terms' => $this->acceptanceTerms($feeCalculation),
                'pricing_terms' => $this->pricing->snapshot($client, $feeCalculation),
                'created_by_user_id' => $createdByUserId,
            ]);

            if ($feeCalculation->method !== FeeMethod::Integration) {
                $this->writeConsents($proposal, $input['consents'] ?? []);
            }

            if ($integrationScope !== null) {
                $integrationScope->forceFill(['proposal_id' => $proposal->getKey()])->save();
            }

            $this->audit->record('proposal.generated', subject: $proposal, after: [
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $npoEngagementId,
                'fee_calculation_id' => $feeCalculation->getKey(),
                'status' => ProposalStatus::Draft->value,
                'recalled_proposal_ids' => $recalledProposalIds,
            ]);

            return $proposal->refresh()->load('consents');
        });
    }

    public function release(Proposal $proposal, User $actor, ?int $expiryDays = null): Proposal
    {
        $proposal = $proposal->refresh()->load('feeCalculation.integrationScope');
        $this->integrationScopeGuard->assertProposalReady($proposal);
        $expiryDays ??= $this->defaultExpiryDays();

        if (! in_array($proposal->status, [ProposalStatus::Draft, ProposalStatus::Renewed], true)) {
            throw new InvalidArgumentException('Only draft or renewed proposals can be released.');
        }

        $this->recallCurrentProposalsForClient($proposal->client, $actor->getKey(), $proposal);

        $proposal->forceFill([
            'status' => ProposalStatus::Released,
            'released_at' => now(),
            'released_by_user_id' => $actor->getKey(),
            'expires_at' => now()->addDays(max(1, $expiryDays)),
            'recalled_at' => null,
            'recalled_by_user_id' => null,
            'expired_at' => null,
        ])->save();

        $this->audit->record('proposal.released', subject: $proposal, actor: $actor, after: [
            'expires_at' => $proposal->expires_at?->toIso8601String(),
        ]);

        return $proposal->refresh();
    }

    public function recall(Proposal $proposal, User $actor): Proposal
    {
        $proposal = $proposal->refresh();

        if ($proposal->status !== ProposalStatus::Released) {
            throw new InvalidArgumentException('Only released proposals can be recalled.');
        }

        $proposal->forceFill([
            'status' => ProposalStatus::Recalled,
            'recalled_at' => now(),
            'recalled_by_user_id' => $actor->getKey(),
            'expires_at' => null,
        ])->save();

        $this->audit->record('proposal.recalled', subject: $proposal, actor: $actor);

        return $proposal->refresh();
    }

    public function renew(Proposal $proposal, User $actor): Proposal
    {
        $proposal = $proposal->refresh()->load(['client', 'feeCalculation', 'consents']);
        $proposal->loadMissing('feeCalculation.integrationScope');
        $this->integrationScopeGuard->assertProposalReady($proposal);

        if ($proposal->status !== ProposalStatus::Expired) {
            throw new InvalidArgumentException('Only expired proposals can be renewed.');
        }

        return DB::transaction(function () use ($proposal, $actor): Proposal {
            $recalledProposalIds = $this->recallCurrentProposalsForClient($proposal->client, $actor->getKey(), $proposal);

            $renewed = Proposal::query()->create([
                'client_id' => $proposal->client_id,
                'npo_engagement_id' => $proposal->npo_engagement_id,
                'fee_calculation_id' => $proposal->fee_calculation_id,
                'status' => ProposalStatus::Renewed,
                'version' => $proposal->version + 1,
                'scope' => $proposal->scope,
                'services' => $proposal->services,
                'pv_summary' => $proposal->pv_summary,
                'roi_ratio' => $proposal->roi_ratio,
                'acceptance_terms' => $proposal->acceptance_terms,
                'renewed_from_proposal_id' => $proposal->getKey(),
                'created_by_user_id' => $actor->getKey(),
            ]);

            foreach ($proposal->consents as $consent) {
                Consent::query()->create([
                    'client_id' => $renewed->client_id,
                    'proposal_id' => $renewed->getKey(),
                    'type' => $consent->type,
                    'election' => $consent->election,
                    'evidence' => [
                        ...($consent->evidence ?? []),
                        'renewed_from_consent_id' => $consent->id,
                    ],
                    'captured_by_user_id' => $actor->getKey(),
                    'captured_at' => now(),
                ]);
            }

            $this->audit->record('proposal.renewed', subject: $renewed, actor: $actor, after: [
                'renewed_from_proposal_id' => $proposal->getKey(),
                'version' => $renewed->version,
                'recalled_proposal_ids' => $recalledProposalIds,
            ]);

            return $renewed->refresh()->load('consents');
        });
    }

    public function expireDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $expired = 0;

        Proposal::query()
            ->where('status', ProposalStatus::Released->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->each(function (Proposal $proposal) use (&$expired, $now): void {
                $proposal->forceFill([
                    'status' => ProposalStatus::Expired,
                    'expired_at' => $now,
                ])->save();

                $this->audit->record('proposal.expired', subject: $proposal, after: [
                    'expired_at' => $proposal->expired_at?->toIso8601String(),
                ]);

                $expired++;
            });

        return $expired;
    }

    public function defaultExpiryDays(): int
    {
        return max(1, (int) config('proposals.expiry_days', 30));
    }

    /**
     * @return array<int, string>
     */
    private function recallCurrentProposalsForClient(Client $client, mixed $actorUserId = null, ?Proposal $except = null): array
    {
        $recalled = [];
        $normalisedActorUserId = $this->normaliseUserId($actorUserId);

        Proposal::query()
            ->where('client_id', $client->getKey())
            ->whereIn('status', $this->currentProposalStatuses())
            ->when($except instanceof Proposal, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->oldest()
            ->each(function (Proposal $proposal) use (&$recalled, $normalisedActorUserId): void {
                $before = [
                    'status' => $proposal->status->value,
                    'released_at' => $proposal->released_at?->toIso8601String(),
                    'expires_at' => $proposal->expires_at?->toIso8601String(),
                ];

                $proposal->forceFill([
                    'status' => ProposalStatus::Recalled,
                    'recalled_at' => now(),
                    'recalled_by_user_id' => $normalisedActorUserId,
                    'expires_at' => null,
                ])->save();

                $recalled[] = (string) $proposal->getKey();

                $this->audit->record('proposal.auto_recalled', subject: $proposal, before: $before, after: [
                    'status' => ProposalStatus::Recalled->value,
                    'recalled_at' => $proposal->recalled_at?->toIso8601String(),
                    'recalled_by_user_id' => $normalisedActorUserId,
                    'reason' => 'superseded_by_new_current_proposal',
                ]);
            });

        return $recalled;
    }

    /**
     * @return array<int, string>
     */
    private function currentProposalStatuses(): array
    {
        return [
            ProposalStatus::Draft->value,
            ProposalStatus::Released->value,
            ProposalStatus::Renewed->value,
            ProposalStatus::AwaitingSignature->value,
        ];
    }

    public function rerenderPdf(Proposal $proposal): Proposal
    {
        $this->renderAndStorePdf($proposal->refresh()->load(['client.primaryContact', 'feeCalculation', 'consents', 'createdBy']));

        $this->audit->record('proposal.rerendered', subject: $proposal, after: [
            'pdf_path' => $proposal->pdf_path,
        ]);

        return $proposal->refresh();
    }

    public function previewHtml(Proposal $proposal): string
    {
        return $this->html($proposal->refresh()->load(['client.primaryContact', 'feeCalculation', 'consents', 'createdBy']));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function scope(Client $client, FeeCalculation $feeCalculation, array $input): array
    {
        $scope = is_array($input['scope'] ?? null) ? $input['scope'] : [];
        $isGovernanceReview = $feeCalculation->method === FeeMethod::GovernanceReview;
        $isNpoRetainer = $feeCalculation->method === FeeMethod::NpoRetainer;
        $integrationScope = $feeCalculation->method === FeeMethod::Integration
            ? $feeCalculation->integrationScope
            : null;
        $isIntegration = $integrationScope instanceof IntegrationScope;
        $includedDefault = match (true) {
            $isIntegration => ['Integration design and delivery plan', 'Build and test the agreed system connections', 'Post-launch savings measurement'],
            $isGovernanceReview => ['Governance evidence review', 'Board-ready Governance Review Report discussion', '12-month governance action plan'],
            $isNpoRetainer => ['NPO advisory retainer', 'Funding and accountability rhythm', 'Impact measurement check-ins'],
            default => ['Advisor review', 'Implementation roadmap', 'Progress check-in'],
        };
        $excludedDefault = match (true) {
            $isIntegration => ['Third-party software subscriptions, vendor fees, and out-of-scope integrations require written approval.'],
            $isGovernanceReview => ['Ongoing retainer advisory work is not included in the fixed-fee Governance Review.'],
            $isNpoRetainer => ['Legal, audit, and trustee services are not included unless separately agreed.'],
            default => ['Digital signature and payment collection are Phase 3.'],
        };
        $included = is_array($scope['included'] ?? null) ? $scope['included'] : $includedDefault;
        $excluded = is_array($scope['excluded'] ?? null) ? $scope['excluded'] : $excludedDefault;
        $summary = $scope['summary'] ?? null;

        if (! is_string($summary) || $summary === '') {
            $summary = match (true) {
                $isIntegration => 'Systems Integration Efficiency delivery proposal for '.$client->legal_name.'.',
                $isGovernanceReview => 'Fixed-fee Governance Review proposal for '.$client->legal_name.'.',
                $isNpoRetainer => 'NPO retainer proposal for '.$client->legal_name.'.',
                default => 'Advisory engagement proposal for '.$client->legal_name.'.',
            };
        }

        $payload = [
            'summary' => $summary,
            'included' => array_values($included),
            'excluded' => array_values($excluded),
        ];

        if (isset($scope['term_months']) && is_numeric($scope['term_months'])) {
            $payload['term_months'] = max(1, (int) $scope['term_months']);
        }

        if (is_array($scope['budget'] ?? null)) {
            $payload['budget'] = $scope['budget'];
        }

        $focusAreas = is_array($scope['focus_areas'] ?? null)
            ? array_values(array_filter($scope['focus_areas'], 'is_array'))
            : ($isIntegration ? [] : $this->proposalFocusAreas($client));

        if ($focusAreas !== []) {
            $payload['focus_areas'] = $focusAreas;
        }

        if ($isGovernanceReview) {
            $payload['proposal_variant'] = FeeMethod::GovernanceReview->value;
            $payload['fixed_fee'] = true;
            $payload['retainer_structure'] = null;
            $payload['size_band'] = data_get($feeCalculation->justification, 'size_band');
            $payload['conversion_credit'] = $this->conversionCredit($feeCalculation);
        }

        if ($isNpoRetainer) {
            $payload['proposal_variant'] = FeeMethod::NpoRetainer->value;
            $payload['budget_band'] = data_get($feeCalculation->justification, 'budget_band');
            $payload['pro_bono'] = data_get($feeCalculation->justification, 'pro_bono');
            $payload['social_enterprise_rate_rule'] = data_get($feeCalculation->justification, 'social_enterprise_rate_rule');
        }

        if ($isIntegration) {
            $computed = $integrationScope->computed ?? [];
            $payload['proposal_variant'] = FeeMethod::Integration->value;
            $payload['integration_quote_pack'] = [
                'integration_scope_id' => $integrationScope->getKey(),
                'delivery_mode' => $integrationScope->delivery_mode,
                'systems' => $integrationScope->systems ?? [],
                'tasks' => $computed['task_rows'] ?? $integrationScope->tasks ?? [],
                'connections' => $computed['connection_rows'] ?? $integrationScope->connections ?? [],
                'complexity_band' => $computed['complexity_band'] ?? null,
                'scope_description' => data_get($computed, 'quote_range.scope_description'),
                'hosting' => $this->clientFacingHosting($computed['hosting'] ?? null),
                'annual_hours_wasted' => $computed['annual_hours_wasted'] ?? null,
                'annual_savings' => $computed['annual_savings'] ?? null,
                'pv_savings' => $computed['pv_savings'] ?? null,
                'quoted_fee' => $computed['quoted_fee'] ?? $feeCalculation->suggested_mid,
                'payback_months' => $computed['payback_months'] ?? null,
                'flags' => $integrationScope->flags ?? [],
            ];
        }

        return $payload;
    }

    /** @return array{enabled:bool,monthly_fee:float,annual_fee:float,currency:string} */
    private function clientFacingHosting(mixed $hosting): array
    {
        if (! is_array($hosting)) {
            return [];
        }

        return [
            'enabled' => (bool) ($hosting['enabled'] ?? false),
            'monthly_fee' => (float) ($hosting['monthly_fee'] ?? 0),
            'annual_fee' => (float) ($hosting['annual_fee'] ?? 0),
            'currency' => (string) ($hosting['currency'] ?? 'NZD'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function services(FeeCalculation $feeCalculation, array $input = []): array
    {
        if (is_array($input['services'] ?? null) && $input['services'] !== []) {
            return array_values(array_filter($input['services'], 'is_array'));
        }

        $services = data_get($feeCalculation->justification, 'services');

        if (is_array($services) && $services !== []) {
            return array_values($services);
        }

        if ($feeCalculation->method === FeeMethod::Integration && $feeCalculation->integrationScope instanceof IntegrationScope) {
            $scope = $feeCalculation->integrationScope;

            return [[
                'name' => 'Systems Integration Efficiency delivery',
                'fee_method' => FeeMethod::Integration->value,
                'delivery_mode' => $scope->delivery_mode,
                'complexity_band' => data_get($scope->computed, 'complexity_band'),
                'line_total' => $feeCalculation->suggested_mid,
            ]];
        }

        return [[
            'name' => 'Future Shift Advisory engagement',
            'fee_method' => $feeCalculation->method->value,
            'suggested_mid' => $feeCalculation->suggested_mid,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proposalFocusAreas(Client $client): array
    {
        $findings = AnalysisFinding::query()
            ->with('run')
            ->where('client_id', $client->getKey())
            ->where('lens', AnalysisLens::Prescriptive->value)
            ->latest()
            ->limit(80)
            ->get()
            ->sort(function (AnalysisFinding $left, AnalysisFinding $right): int {
                return [
                    $this->severityRank($right),
                    $right->created_at?->getTimestamp() ?? 0,
                ] <=> [
                    $this->severityRank($left),
                    $left->created_at?->getTimestamp() ?? 0,
                ];
            })
            ->unique(fn (AnalysisFinding $finding): string => $this->focusAreaFingerprint($finding))
            ->values();

        if ($findings->isEmpty()) {
            return [];
        }

        $selected = $this->shouldLeadWithWebsiteFinding($client)
            ? $this->websiteLedFindings($findings)
            : $findings->take(6)->values();

        return $selected
            ->map(fn (AnalysisFinding $finding): array => [
                'analysis_finding_id' => (string) $finding->getKey(),
                'module' => $finding->run?->module?->value,
                'lens' => $finding->lens->value,
                'severity' => $finding->severity->value,
                'title' => $finding->title,
                'body' => Str::limit($finding->body, 750),
                'attributions' => $finding->attributions,
            ])
            ->all();
    }

    private function focusAreaFingerprint(AnalysisFinding $finding): string
    {
        $source = collect($finding->attributions)
            ->filter(fn (mixed $attribution): bool => is_array($attribution))
            ->pluck('source_reference')
            ->filter()
            ->map(fn (mixed $source): string => mb_strtolower(trim((string) $source)))
            ->first();

        return hash('sha256', implode('|', [
            $finding->run?->module?->value ?? '',
            mb_strtolower(trim($finding->title)),
            $finding->lens->value,
            $finding->severity->value,
            $source ?? '',
        ]));
    }

    private function severityRank(AnalysisFinding $finding): int
    {
        return match ($finding->severity->value) {
            'critical' => 5,
            'high' => 4,
            'medium' => 3,
            'low' => 2,
            default => 1,
        };
    }

    /**
     * Keep website-audit work prominent for first/prospect-style proposals, but
     * let severity lead once a client has moved into delivery.
     */
    private function shouldLeadWithWebsiteFinding(Client $client): bool
    {
        $hasAcceptedProposal = Proposal::query()
            ->where('client_id', $client->getKey())
            ->where('status', ProposalStatus::Signed->value)
            ->exists();

        if ($hasAcceptedProposal) {
            return false;
        }

        $hasStrategicPlan = StrategicPlan::query()
            ->where('client_id', $client->getKey())
            ->exists();

        if ($hasStrategicPlan) {
            return false;
        }

        return ! $client->serviceActivations()
            ->whereIn('status', [
                ServiceActivation::STATUS_ACTIVE,
                ServiceActivation::STATUS_CLOSED,
            ])
            ->exists();
    }

    /**
     * @param  Collection<int, AnalysisFinding>  $findings
     * @return Collection<int, AnalysisFinding>
     */
    private function websiteLedFindings(Collection $findings): Collection
    {
        $websiteFinding = $findings->first(
            fn (AnalysisFinding $finding): bool => $finding->run?->module?->value === 'website_audit',
        );

        return collect([$websiteFinding])
            ->filter()
            ->merge($findings->reject(
                fn (AnalysisFinding $finding): bool => $websiteFinding instanceof AnalysisFinding
                    && (string) $finding->getKey() === (string) $websiteFinding->getKey(),
            ))
            ->take(6)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function pvSummary(Client $client, FeeCalculation $feeCalculation, ?string $npoEngagementId): array
    {
        if ($feeCalculation->method === FeeMethod::Integration && $feeCalculation->integrationScope instanceof IntegrationScope) {
            $scope = $feeCalculation->integrationScope;
            $pv = (float) data_get($scope->computed, 'pv_savings', $feeCalculation->improvement_pv_total);

            return [
                'proposal_variant' => FeeMethod::Integration->value,
                'current_pv' => 0.0,
                'improvement_pv_total' => $pv,
                'risk_cost_pv_total' => 0.0,
                'target_pv' => $pv,
                'target_pv_label' => 'Scoped integration savings PV',
                'target_pv_range' => null,
                'target_pv_assumptions' => 'Only the saved integration-scope savings assumptions are included.',
                'roi_ratio' => $feeCalculation->roi_ratio,
                'fee_suggested_mid' => $feeCalculation->suggested_mid,
                'integration_scope_id' => $scope->getKey(),
            ];
        }

        $waterfall = $this->waterfalls->forClient($client);

        $summary = [
            'current_pv' => $waterfall['current_pv'],
            'improvement_pv_total' => $feeCalculation->improvement_pv_total,
            'risk_cost_pv_total' => $feeCalculation->risk_cost_pv_total,
            'target_pv' => $waterfall['target_pv'],
            'target_pv_label' => $waterfall['target_pv_label'] ?? 'Modelled upside PV',
            'target_pv_range' => $waterfall['target_pv_range'] ?? null,
            'target_pv_assumptions' => $waterfall['target_pv_assumptions'] ?? null,
            'roi_ratio' => $feeCalculation->roi_ratio,
            'fee_suggested_mid' => $feeCalculation->suggested_mid,
        ];

        if ($npoEngagementId !== null) {
            $summary['npo_engagement_id'] = $npoEngagementId;
        }

        if ($feeCalculation->method === FeeMethod::GovernanceReview) {
            $summary['proposal_variant'] = FeeMethod::GovernanceReview->value;
            $summary['fixed_fee'] = true;
            $summary['conversion_credit'] = $this->conversionCredit($feeCalculation);
        }

        if ($feeCalculation->method === FeeMethod::NpoRetainer) {
            $summary['proposal_variant'] = FeeMethod::NpoRetainer->value;
            $summary['budget_band'] = data_get($feeCalculation->justification, 'budget_band');
            $summary['monthly_retainer_fee'] = data_get($feeCalculation->justification, 'monthly_retainer_fee');
            $summary['pro_bono'] = data_get($feeCalculation->justification, 'pro_bono');
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptanceTerms(FeeCalculation $feeCalculation): array
    {
        $terms = [
            'phase' => 'client_signoff',
            'client_acceptance_section_present' => true,
            'payment_authority_capture_enabled' => true,
            'digital_signature_enabled' => true,
            'signoff_managed_statuses' => [
                ProposalStatus::AwaitingSignature->value,
                ProposalStatus::Signed->value,
            ],
        ];

        if ($feeCalculation->method === FeeMethod::GovernanceReview) {
            $terms['proposal_variant'] = FeeMethod::GovernanceReview->value;
            $terms['fixed_fee'] = true;
            $terms['no_retainer_structure'] = true;
            $terms['conversion_credit'] = $this->conversionCredit($feeCalculation);
        }

        if ($feeCalculation->method === FeeMethod::NpoRetainer) {
            $terms['proposal_variant'] = FeeMethod::NpoRetainer->value;
            $terms['npo_discount_applied'] = data_get($feeCalculation->justification, 'npo_discount_applied');
            $terms['pro_bono'] = data_get($feeCalculation->justification, 'pro_bono');
            $terms['bespoke_accountability_report_addon'] = data_get($feeCalculation->justification, 'bespoke_accountability_report_addon');
        }

        if ($feeCalculation->method === FeeMethod::Integration) {
            $terms['proposal_variant'] = FeeMethod::Integration->value;
            $terms['referral_consents_required'] = false;
        }

        return $terms;
    }

    private function writeConsents(Proposal $proposal, mixed $elections): void
    {
        $elections = is_array($elections) ? $elections : [];

        foreach (Consent::proposalTypes() as $type) {
            $election = (string) ($elections[$type] ?? Consent::ELECTION_UNDECIDED);

            if (! in_array($election, Consent::elections(), true)) {
                throw new InvalidArgumentException("Unsupported consent election [{$election}].");
            }

            Consent::query()->create([
                'client_id' => $proposal->client_id,
                'proposal_id' => $proposal->getKey(),
                'type' => $type,
                'election' => $election,
                'evidence' => [
                    'source' => 'proposal_generation',
                    'phase_two_only' => true,
                ],
                'captured_by_user_id' => $proposal->created_by_user_id,
                'captured_at' => now(),
            ]);
        }
    }

    private function renderAndStorePdf(Proposal $proposal): void
    {
        $pdf = $this->renderer->render($this->html($proposal));
        $path = is_string($proposal->pdf_path) && trim($proposal->pdf_path) !== ''
            ? $proposal->pdf_path
            : sprintf(
                'proposals/%s/%s/proposal-v%s.pdf',
                $proposal->client_id,
                Str::uuid(),
                $proposal->version,
            );

        $written = Storage::disk('secure_local')->put($path, $pdf);

        if ($written !== true) {
            throw new RuntimeException('Proposal PDF could not be stored.');
        }

        $proposal->forceFill([
            'pdf_path' => $path,
            'pdf_byte_size' => strlen($pdf),
        ])->save();
    }

    private function html(Proposal $proposal): string
    {
        $proposal->loadMissing(['client.primaryContact', 'feeCalculation', 'consents', 'createdBy']);
        $template = $this->activeProposalTemplate();
        $sections = $this->proposalSectionsHtml($proposal);

        if ($template instanceof Template) {
            $html = $this->uploadedTemplates->renderDocument(
                $this->proposalTitle($proposal),
                $template,
                $sections,
                $this->proposalTemplateTokens($proposal, $template, $sections),
                $this->proposalCss($template),
            );

            if (is_string($html)) {
                return $this->polishUploadedProposalHtml($html, $proposal, $sections);
            }
        }

        return $this->brandedProposalHtml($proposal, $sections);
    }

    private function proposalSectionsHtml(Proposal $proposal): string
    {
        $consents = $proposal->consents
            ->map(fn (Consent $consent): string => sprintf(
                '<li>%s: %s</li>',
                $this->escape(str_replace('_', ' ', $consent->type)),
                $this->escape(str_replace('_', ' ', $consent->election)),
            ))
            ->implode('');
        $conversionCredit = $this->conversionCreditHtml($proposal);
        $budgetReadiness = $this->budgetReadinessHtml($proposal);
        $integrationQuotePack = $this->integrationQuotePackHtml($proposal);
        $focusAreas = $this->proposalFocusAreasHtml($proposal);
        $roiLine = $this->proposalHasPositiveFee($proposal)
            ? sprintf(
                '<p>For every NZD 1 of advisory fee, the model shows NZD %s of potential value.</p>',
                number_format($proposal->roi_ratio, 2),
            )
            : '';

        return sprintf(
            <<<'HTML'
<section class="proposal-panel">
<h2>Scope</h2>
<p>%s</p>
</section>
%s
%s
<section class="proposal-panel">
<h2>Fee</h2>
<p>Method: %s</p>
%s
%s
</section>
%s
%s
<section class="proposal-panel">
<h2>PV summary</h2>
<p>PV (present value) means future benefits expressed as a single today's-dollars figure.</p>
<p>Improvement PV: NZD %s</p>
<p>Risk-cost PV: NZD %s</p>
<p>Modelled upside PV: NZD %s (planning range NZD %s - NZD %s)</p>
<p>This midpoint assumes surfaced improvements and risk mitigations are fully captured; the range is a planning sensitivity, not a guaranteed outcome.</p>
</section>
<section class="proposal-panel">
<h2>Consent elections</h2>
<ul>%s</ul>
</section>
<section class="proposal-panel">
<h2>Acceptance</h2>
<p>This proposal is release-controlled. Digital signature and payment collection are managed through the client sign-off flow when enabled. Quoted amounts are GST exclusive.</p>
</section>
HTML,
            $this->escape((string) data_get($proposal->scope, 'summary')),
            $integrationQuotePack,
            $focusAreas,
            $this->escape(Str::headline($proposal->feeCalculation?->method?->value ?? '')),
            $this->feeSummaryHtml($proposal),
            $roiLine,
            $conversionCredit,
            $budgetReadiness,
            number_format((float) data_get($proposal->pv_summary, 'improvement_pv_total', 0), 0),
            number_format((float) data_get($proposal->pv_summary, 'risk_cost_pv_total', 0), 0),
            number_format((float) data_get($proposal->pv_summary, 'target_pv', 0), 0),
            number_format((float) data_get($proposal->pv_summary, 'target_pv_range.low', data_get($proposal->pv_summary, 'target_pv', 0)), 0),
            number_format((float) data_get($proposal->pv_summary, 'target_pv_range.high', data_get($proposal->pv_summary, 'target_pv', 0)), 0),
            $consents,
        );
    }

    private function integrationQuotePackHtml(Proposal $proposal): string
    {
        $pack = data_get($proposal->scope, 'integration_quote_pack');
        if (! is_array($pack)) {
            return '';
        }

        $systems = collect((array) ($pack['systems'] ?? []))
            ->filter(static fn (mixed $system): bool => is_array($system))
            ->map(fn (array $system): string => $this->escape((string) ($system['name'] ?? $system['vendor'] ?? 'System')))
            ->unique()
            ->take(12)
            ->map(fn (string $name): string => '<li>'.$name.'</li>')
            ->implode('');
        $connections = collect((array) ($pack['connections'] ?? []))
            ->filter(static fn (mixed $connection): bool => is_array($connection))
            ->take(12)
            ->map(function (array $connection): string {
                $from = (string) ($connection['from_system'] ?? 'Source system');
                $to = (string) ($connection['to_system'] ?? 'Target system');
                $direction = (string) ($connection['direction'] ?? data_get($connection, 'drivers.direction', 'one_way'));

                return '<li>'.$this->escape($from.' to '.$to.' ('.str_replace('_', ' ', $direction).')').'</li>';
            })
            ->implode('');
        $hosting = (array) ($pack['hosting'] ?? []);
        $hostingEnabled = (bool) ($hosting['enabled'] ?? false) && (float) ($hosting['monthly_fee'] ?? 0) > 0;
        $hostingHtml = ! $hostingEnabled
            ? ''
            : '<h3>FSA-hosted application</h3><p>FSA will host and operate the application for NZD '.number_format((float) $hosting['monthly_fee'], 2).' per month ex GST. This direct hosting charge is payable even when an advisory fee waiver applies.</p>';

        return sprintf(
            <<<'HTML'
<section class="proposal-panel">
<h2>Integration Quote Pack</h2>
<p>Delivery model: %s. Complexity band: %s.</p>
%s
%s
<p>Manual work identified: %s hours each year. Modelled annual savings: NZD %s. Investment payback: %s months.</p>
<h3>Systems in scope</h3><ul>%s</ul>
<h3>Connections in scope</h3><ul>%s</ul>
</section>
HTML,
            $this->escape((string) ($pack['delivery_mode'] ?? 'To be confirmed')),
            $this->escape((string) ($pack['complexity_band'] ?? 'To be confirmed')),
            filled($pack['scope_description'] ?? null)
                ? '<h3>Scope</h3><p>'.$this->escape((string) $pack['scope_description']).'</p>'
                : '',
            $hostingHtml,
            number_format((float) ($pack['annual_hours_wasted'] ?? 0), 0),
            number_format((float) ($pack['annual_savings'] ?? 0), 0),
            number_format((float) ($pack['payback_months'] ?? 0), 1),
            $systems !== '' ? $systems : '<li>Systems to be confirmed</li>',
            $connections !== '' ? $connections : '<li>Connections to be confirmed</li>',
        );
    }

    private function proposalFocusAreasHtml(Proposal $proposal): string
    {
        $focusAreas = data_get($proposal->scope, 'focus_areas', []);

        if (! is_array($focusAreas) || $focusAreas === []) {
            return '';
        }

        $items = collect($focusAreas)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->take(6)
            ->map(fn (array $area): string => sprintf(
                '<li><strong>%s</strong><br><span>%s</span>%s</li>',
                $this->escape((string) ($area['title'] ?? 'Advisory focus area')),
                $this->escape((string) ($area['body'] ?? '')),
                $this->focusAreaSourcesHtml($area),
            ))
            ->implode('');

        if ($items === '') {
            return '';
        }

        return sprintf(
            <<<'HTML'
<section class="proposal-panel">
<h2>What needs to be fixed</h2>
<ul>%s</ul>
</section>
HTML,
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $area
     */
    private function focusAreaSourcesHtml(array $area): string
    {
        $sources = collect((array) ($area['attributions'] ?? []))
            ->filter(fn (mixed $attribution): bool => is_array($attribution) && is_string($attribution['source_reference'] ?? null))
            ->map(fn (array $attribution): string => SourceReferenceLabeler::label(
                (string) $attribution['source_reference'],
                isset($attribution['claim']) ? (string) $attribution['claim'] : null,
            ))
            ->filter()
            ->unique()
            ->take(3)
            ->values();

        if ($sources->isEmpty()) {
            return '';
        }

        return sprintf(
            '<br><small>Sources: %s</small>',
            $this->escape($sources->implode(', ')),
        );
    }

    private function brandedProposalHtml(Proposal $proposal, string $sections): string
    {
        $clientName = $proposal->client?->legal_name ?? 'Client';
        $proposalDate = $this->proposalDate($proposal);
        $feeMid = $this->pricing->payableMid($proposal);
        $createdBy = $proposal->createdBy?->name ?: 'Future Shift Advisory';
        $roiSnapshot = $this->proposalHasPositiveFee($proposal)
            ? sprintf('<div><dt>Modelled fee return</dt><dd>NZD %s per NZD 1 fee</dd></div>', number_format($proposal->roi_ratio, 2))
            : '';

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en-NZ">
<head>
<meta charset="utf-8">
<title>Future Shift Advisory proposal</title>
<style>
%s
</style>
</head>
<body>
<header class="letterhead">
<div class="brand-lockup">
<div class="brand-mark"><span></span><span></span><span></span></div>
<div><p class="brand-name">Future Shift</p><p class="brand-subtitle">ADVISORY</p></div>
</div>
<div class="document-tag">Fee proposal</div>
</header>
<section class="proposal-hero">
<p class="eyebrow">Proposal v%s</p>
<h1>%s</h1>
<p>%s</p>
</section>
<section class="proposal-snapshot">
<h2>Proposal snapshot</h2>
<dl>
<div><dt>Client</dt><dd>%s</dd></div>
<div><dt>Status</dt><dd>%s</dd></div>
<div><dt>Generated</dt><dd>%s</dd></div>
<div><dt>Prepared by</dt><dd>%s</dd></div>
<div><dt>Fee</dt><dd>%s</dd></div>
%s
</dl>
</section>
%s
</body>
</html>
HTML,
            $this->proposalCss(null),
            $proposal->version,
            $this->escape($this->proposalTitle($proposal)),
            $this->escape((string) data_get($proposal->scope, 'summary', 'Future Shift Advisory proposal for the agreed engagement scope.')),
            $this->escape($clientName),
            $this->escape(Str::headline($proposal->status->value)),
            $this->escape($proposalDate),
            $this->escape($createdBy),
            $this->money($feeMid),
            $roiSnapshot,
            $sections,
        );
    }

    private function activeProposalTemplate(): ?Template
    {
        return Template::query()
            ->usable()
            ->where('category', Template::CATEGORY_PROPOSAL)
            ->latest('updated_at')
            ->latest()
            ->first();
    }

    /**
     * @return array<string, string>
     */
    private function proposalTemplateTokens(Proposal $proposal, Template $template, string $sections): array
    {
        $clientName = $proposal->client?->legal_name ?? 'Client';
        $proposalDate = $this->proposalDate($proposal);
        $primaryContact = $proposal->client?->primaryContact?->name ?: $clientName;
        $createdBy = $proposal->createdBy?->name ?: 'Future Shift Advisory';
        $feeCalculation = $proposal->feeCalculation;
        $termMonths = $this->proposalTermMonths($proposal);
        $monthlyInvestment = $this->proposalMonthlyInvestment($proposal, $termMonths);
        $improvementPv = $this->proposalImprovementPv($proposal);
        $expiryDate = $proposal->expires_at?->format('j M Y') ?? 'Not released';
        $validityLine = $proposal->expires_at === null
            ? 'Validity period starts on release'
            : 'Valid until '.$expiryDate;

        return [
            '{{ proposal_title }}' => $this->escape($this->proposalTitle($proposal)),
            '{{proposal_title}}' => $this->escape($this->proposalTitle($proposal)),
            '{{ proposal_type }}' => 'Proposal',
            '{{proposal_type}}' => 'Proposal',
            '{{ client_name }}' => $this->escape($clientName),
            '{{client_name}}' => $this->escape($clientName),
            '{{ generated_at }}' => $this->escape($proposalDate),
            '{{generated_at}}' => $this->escape($proposalDate),
            '{{ proposal_date }}' => $this->escape($proposalDate),
            '{{proposal_date}}' => $this->escape($proposalDate),
            '{{ proposal_version }}' => (string) $proposal->version,
            '{{proposal_version}}' => (string) $proposal->version,
            '{{ proposal_status }}' => $this->escape($proposal->status->value),
            '{{proposal_status}}' => $this->escape($proposal->status->value),
            '{{ template_title }}' => $this->escape($template->title),
            '{{template_title}}' => $this->escape($template->title),
            '{{ template_version }}' => (string) $template->version,
            '{{template_version}}' => (string) $template->version,
            '{{ scope_summary }}' => $this->escape((string) data_get($proposal->scope, 'summary')),
            '{{scope_summary}}' => $this->escape((string) data_get($proposal->scope, 'summary')),
            '{{ fee_method }}' => $this->escape($feeCalculation?->method?->value ?? ''),
            '{{fee_method}}' => $this->escape($feeCalculation?->method?->value ?? ''),
            '{{ fee_low }}' => $this->money(data_get($this->pricing->for($proposal), 'payable_fee.low', 0)),
            '{{fee_low}}' => $this->money(data_get($this->pricing->for($proposal), 'payable_fee.low', 0)),
            '{{ fee_mid }}' => $this->money($this->pricing->payableMid($proposal)),
            '{{fee_mid}}' => $this->money($this->pricing->payableMid($proposal)),
            '{{ fee_high }}' => $this->money(data_get($this->pricing->for($proposal), 'payable_fee.high', 0)),
            '{{fee_high}}' => $this->money(data_get($this->pricing->for($proposal), 'payable_fee.high', 0)),
            '{{ monthly_investment }}' => $this->money($monthlyInvestment),
            '{{monthly_investment}}' => $this->money($monthlyInvestment),
            '{{ monthly_investment_plain }}' => number_format($monthlyInvestment, 0),
            '{{monthly_investment_plain}}' => number_format($monthlyInvestment, 0),
            '{{ engagement_months }}' => (string) $termMonths,
            '{{engagement_months}}' => (string) $termMonths,
            '{{ roi_ratio }}' => $this->proposalHasPositiveFee($proposal) ? number_format($proposal->roi_ratio, 2) : 'not applicable',
            '{{roi_ratio}}' => $this->proposalHasPositiveFee($proposal) ? number_format($proposal->roi_ratio, 2) : 'not applicable',
            '{{ improvement_pv_total }}' => $this->money($improvementPv),
            '{{improvement_pv_total}}' => $this->money($improvementPv),
            '{{ improvement_pv_total_plain }}' => number_format($improvementPv, 0),
            '{{improvement_pv_total_plain}}' => number_format($improvementPv, 0),
            '{{ expires_at }}' => $this->escape($expiryDate),
            '{{expires_at}}' => $this->escape($expiryDate),
            '{{ sections }}' => $sections,
            '{{sections}}' => $sections,
            '{{{ sections }}}' => $sections,
            '{{{sections}}}' => $sections,
            '[Business Name]' => $this->escape($clientName),
            '[Client Name]' => $this->escape($primaryContact),
            '[Report Type]' => 'Proposal',
            '[Date]' => $this->escape($proposalDate),
            'Valid until [Expiry Date]' => $this->escape($validityLine),
            '[Expiry Date]' => $this->escape($expiryDate),
            '[Engagement Period]' => $this->escape('As at '.$proposalDate),
            '[Client Primary Contact]' => $this->escape($primaryContact),
            '[Title]' => 'Primary contact',
            '[Prepared By]' => $this->escape($createdBy),
            '[X,XXX]' => number_format($monthlyInvestment, 0),
            '[XXX,XXX]' => number_format($improvementPv, 0),
            '[X]-month engagement' => $termMonths.'-month engagement',
            '[X]x return' => number_format($proposal->roi_ratio, 2).'x return',
            '[X]× return' => number_format($proposal->roi_ratio, 2).'x return',
        ] + $this->proposalTemplateInstructionTokens();
    }

    private function polishUploadedProposalHtml(string $html, Proposal $proposal, string $sections): string
    {
        $html = preg_replace(
            '/\[\s*X\s*\]\s*[x×]\s+return/iu',
            number_format($proposal->roi_ratio, 2).'x return',
            $html,
        ) ?? $html;

        $generatedContent = '</div><div class="uploaded-docx-report-template proposal-generated-page"><main class="report-content proposal-generated-content">'.$sections.'</main>';
        $reportPlaceholderPattern = '/<[^>]+>\s*1\.\s*Financial Health Assessment\s*<\/[^>]+>.*?(?=<\/div>\s*<\/body>\s*<\/html>)/is';

        if (preg_match($reportPlaceholderPattern, $html) !== 1) {
            return $html;
        }

        $html = preg_replace(
            '/<div class="docx-page-break"><\/div>\s*<main class="report-content">.*?<\/main>/is',
            '',
            $html,
            1,
        ) ?? $html;

        return preg_replace($reportPlaceholderPattern, $generatedContent, $html, 1) ?? $html;
    }

    private function proposalTitle(Proposal $proposal): string
    {
        return sprintf('Proposal v%d - %s', $proposal->version, $proposal->client?->legal_name ?? 'Client');
    }

    private function proposalDate(Proposal $proposal): string
    {
        return ($proposal->released_at ?? $proposal->created_at ?? now())->format('j M Y');
    }

    private function money(float|int|string|null $value): string
    {
        return 'NZD '.number_format((float) $value, 0);
    }

    private function proposalTermMonths(Proposal $proposal): int
    {
        $months = data_get($proposal->scope, 'term_months')
            ?? data_get($proposal->acceptance_terms, 'term_months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer.months')
            ?? data_get($proposal->feeCalculation?->justification, 'retainer_months');

        return max(1, (int) (is_numeric($months) ? $months : 6));
    }

    private function proposalMonthlyInvestment(Proposal $proposal, int $termMonths): float
    {
        return $this->pricing->monthlyAmount($proposal, $termMonths);
    }

    private function proposalImprovementPv(Proposal $proposal): float
    {
        $value = data_get($proposal->pv_summary, 'improvement_pv_total')
            ?? $proposal->feeCalculation?->improvement_pv_total
            ?? 0;

        return (float) $value;
    }

    private function proposalHasPositiveFee(Proposal $proposal): bool
    {
        return $this->pricing->hasPayableAdvisoryFee($proposal);
    }

    private function feeSummaryHtml(Proposal $proposal): string
    {
        $terms = $this->pricing->for($proposal);

        if ($this->pricing->isPilotWaiver($proposal)) {
            $hostingCharge = $this->pricing->hostingMonthlyFee($proposal);

            return $hostingCharge > 0
                ? '<p><strong>Pilot programme advisory fee waiver: NZD 0 ex GST.</strong></p><p>The FSA-hosted application charge of NZD '.number_format($hostingCharge, 2).' per month ex GST remains payable.</p>'
                : '<p><strong>Pilot programme advisory fee waiver: NZD 0 ex GST.</strong></p><p>No payment will be collected for this proposal.</p>';
        }

        if (! (bool) ($terms['fee_active'] ?? false)) {
            return '<p><strong>Fee: NZD 0 ex GST.</strong></p><p>No payment will be collected for this proposal.</p>';
        }

        return sprintf(
            '<p>Suggested range: NZD %s - NZD %s - NZD %s</p><p>All proposal rates and amounts are GST exclusive. GST at 15%% is added to any final payment collected.</p>',
            number_format((float) data_get($terms, 'payable_fee.low', 0), 0),
            number_format($this->pricing->payableMid($proposal), 0),
            number_format((float) data_get($terms, 'payable_fee.high', 0), 0),
        );
    }

    /**
     * @return array<string, string>
     */
    private function proposalTemplateInstructionTokens(): array
    {
        $emDash = "\u{2014}";
        $bodyInstruction = 'Arial 9.5pt, Dark Grey. State the finding directly in the first sentence. Evidence follows. Every claim is referenced to the source data.';

        return [
            '[Body text - '.$bodyInstruction.']' => '',
            '[Body text '.$emDash.' '.$bodyInstruction.']' => '',
            '[Key Finding: [Plain English summary of the most important finding in this section. One to two sentences maximum. Written for a business owner, not an accountant.]' => '',
            '[Specific, actionable recommendations. Each recommendation names the action, expected outcome, and timeframe. No generic advice.]' => '',
            '[Specific, actionable recommendations. Each recommendation names the action, the expected outcome, and the timeframe. No generic advice.]' => '',
        ];
    }

    private function proposalCss(?Template $template): string
    {
        $accent = $this->templateLayoutColor($template, 'accent_color', '#0d7a7a');
        $accentDark = $this->templateLayoutColor($template, 'accent_dark', '#1c2f4a');
        $ink = $this->templateLayoutColor($template, 'ink_color', '#13233a');
        $muted = $this->templateLayoutColor($template, 'muted_color', '#667282');
        $paper = $this->templateLayoutColor($template, 'paper_color', '#ffffff');

        return <<<CSS
@page { margin: 15mm 15mm 18mm; }
* { box-sizing: border-box; }
body { background: {$paper}; color: {$ink}; font-family: Arial, sans-serif; font-size: 11.5px; line-height: 1.55; margin: 0; }
.letterhead { align-items: center; border-top: 7px solid #1c2f4a; border-bottom: 1px solid #d8d1c2; display: flex; justify-content: space-between; margin-bottom: 18px; padding: 13px 0 12px; }
.brand-lockup { align-items: center; display: inline-flex; gap: 13px; }
.brand-mark { align-items: end; display: inline-flex; gap: 3px; height: 36px; width: 38px; }
.brand-mark span { background: {$accent}; display: block; width: 8px; }
.brand-mark span:nth-child(1) { height: 14px; opacity: .55; }
.brand-mark span:nth-child(2) { height: 24px; opacity: .78; }
.brand-mark span:nth-child(3) { height: 34px; }
.brand-name { color: {$accentDark}; font-size: 15px; font-weight: 700; line-height: 1; margin: 0; }
.brand-subtitle { color: #5a7a70; font-size: 8px; font-weight: 700; letter-spacing: .06em; margin: 4px 0 0; }
.document-tag { background: #f4efe3; border: 1px solid #d8d1c2; border-radius: 999px; color: {$accentDark}; font-size: 10px; font-weight: 700; padding: 5px 11px; }
.proposal-hero { background: #f8f5ee; border: 1px solid #ded6c7; border-left: 5px solid #b8860b; margin-bottom: 14px; padding: 16px 18px; }
.eyebrow { color: {$accent}; font-size: 9px; font-weight: 700; letter-spacing: .04em; margin: 0 0 5px; text-transform: uppercase; }
.proposal-hero h1 { color: {$ink}; font-size: 24px; line-height: 1.15; margin: 0 0 6px; }
.proposal-hero p { color: {$muted}; margin: 0; }
.proposal-snapshot { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid {$accent}; break-inside: avoid; margin-bottom: 16px; padding: 15px 18px; }
.proposal-snapshot h2 { color: {$accentDark}; font-size: 15px; margin: 0 0 10px; }
.proposal-snapshot dl { border-top: 1px solid #eee7db; display: grid; gap: 9px 18px; grid-template-columns: 1fr 1fr 1fr; margin: 0; padding-top: 10px; }
.proposal-snapshot dt { color: {$muted}; font-size: 8.5px; font-weight: 700; margin: 0 0 2px; text-transform: uppercase; }
.proposal-snapshot dd { margin: 0; overflow-wrap: anywhere; }
.proposal-panel { background: #fff; border: 1px solid #ded6c7; border-left: 4px solid {$accent}; break-inside: avoid; margin-bottom: 14px; padding: 13px 15px; }
.proposal-panel h2 { color: {$accentDark}; font-size: 14px; margin: 0 0 7px; }
.proposal-panel p { margin: 0 0 6px; }
.proposal-panel ul { margin: 0; padding-left: 18px; }
.proposal-panel li { margin: 0 0 4px; }
.proposal-muted { color: {$muted}; }
.proposal-generated-page { break-before: page; page-break-before: always; }
@media screen {
  body[data-report-template-source="uploaded-docx"] { background: #eef2f4; padding: 20px 12px; }
  body[data-report-template-source="uploaded-docx"] > .docx-template-header,
  body[data-report-template-source="uploaded-docx"] > .uploaded-docx-report-template {
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
    margin-left: auto;
    margin-right: auto;
    max-width: 210mm;
    overflow: hidden;
    width: min(100%, 210mm);
  }
  body[data-report-template-source="uploaded-docx"] > .docx-template-header {
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    margin-bottom: 0;
    padding: 18mm 25.4mm 0;
  }
  body[data-report-template-source="uploaded-docx"] > .uploaded-docx-report-template {
    min-height: 297mm;
    padding: 0 25.4mm 18mm;
  }
  body[data-report-template-source="uploaded-docx"] > .proposal-generated-page {
    margin-top: 20px;
    padding-top: 18mm;
  }
}
CSS;
    }

    private function templateLayoutColor(?Template $template, string $key, string $default): string
    {
        $value = $template instanceof Template ? data_get($template->structure, 'layout.'.$key) : null;

        if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $default;
        }

        return $value;
    }

    private function budgetReadinessHtml(Proposal $proposal): string
    {
        $budget = data_get($proposal->scope, 'budget');

        if (! is_array($budget)) {
            return '';
        }

        $override = $budget['override'] ?? null;
        if (! is_array($override)) {
            return sprintf(
                <<<'HTML'
<section class="proposal-panel">
<h2>Business Plan &amp; Budget readiness</h2>
<p>Status: %s. Confidence score: %s/100.</p>
<p>The budget was advisor-approved before proposal generation.</p>
</section>
HTML,
                $this->escape((string) ($budget['status_label'] ?? 'Approved')),
                number_format((float) ($budget['confidence_score'] ?? 0), 0),
            );
        }

        return sprintf(
            <<<'HTML'
<section class="proposal-panel">
<h2>Budget readiness acknowledgement</h2>
<p>The Business Plan &amp; Budget was not advisor-approved before this proposal was generated.</p>
<p>Reason: %s.</p>
<p>Advisor notes: %s</p>
<p>This may adversely affect proposal confidence, recommended package, fee level, payment terms, and affordability checks.</p>
</section>
HTML,
            $this->escape(str((string) ($override['category'] ?? 'other'))->replace('_', ' ')->title()->toString()),
            $this->escape((string) ($override['notes'] ?? '')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function conversionCredit(FeeCalculation $feeCalculation): array
    {
        $credit = data_get($feeCalculation->justification, 'conversion_credit');

        return is_array($credit) ? $credit : [];
    }

    private function conversionCreditHtml(Proposal $proposal): string
    {
        $credit = data_get($proposal->acceptance_terms, 'conversion_credit');

        if (! is_array($credit) || $credit === []) {
            return '';
        }

        return sprintf(
            <<<'HTML'
<section class="panel">
<h2>Conversion credit</h2>
<p>%s%% creditable to the first retainer month, at advisor discretion.</p>
<p>Indicative mid-fee credit: NZD %s</p>
</section>
HTML,
            number_format((float) ($credit['percent'] ?? 0), 0),
            number_format((float) ($credit['amount_mid'] ?? 0), 0),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function normaliseUserId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    private function npoEngagementIdForProposal(Client $client, FeeCalculation $feeCalculation, mixed $requested): ?string
    {
        $feeCalculationEngagementId = $feeCalculation->npo_engagement_id === null
            ? null
            : (string) $feeCalculation->npo_engagement_id;

        if ($feeCalculation->method === FeeMethod::GovernanceReview && $feeCalculationEngagementId === null) {
            throw new InvalidArgumentException('Governance Review proposals require the fee calculation to belong to a governance-review NPO engagement.');
        }

        if ($feeCalculationEngagementId !== null) {
            $this->assertNpoEngagementBelongsToClient($client, $feeCalculationEngagementId);
        }

        $requestedEngagementId = $this->normaliseNpoEngagementId($client, $requested);

        if ($requestedEngagementId === null) {
            return $feeCalculationEngagementId;
        }

        if ($feeCalculationEngagementId === null || $requestedEngagementId !== $feeCalculationEngagementId) {
            throw new InvalidArgumentException('Proposal NPO engagement must match the fee calculation NPO engagement.');
        }

        return $feeCalculationEngagementId;
    }

    private function normaliseNpoEngagementId(Client $client, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof NpoEngagement) {
            if ((string) $value->client_id !== (string) $client->getKey()) {
                throw new InvalidArgumentException('NPO engagement must belong to the proposal client.');
            }

            return (string) $value->getKey();
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('NPO engagement id must be a UUID string.');
        }

        $this->assertNpoEngagementBelongsToClient($client, $value);

        return $value;
    }

    private function assertNpoEngagementBelongsToClient(Client $client, string $npoEngagementId): void
    {
        $exists = NpoEngagement::query()
            ->whereKey($npoEngagementId)
            ->where('client_id', $client->getKey())
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('NPO engagement must belong to the proposal client.');
        }
    }
}
