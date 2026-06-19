<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\NpoEngagement;
use App\Models\Proposal;
use App\Models\Template;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pv\PvWaterfallBuilder;
use App\Services\Reports\UploadedReportTemplateRenderer;
use Carbon\CarbonInterface;
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

        $npoEngagementId = $this->npoEngagementIdForProposal(
            $client,
            $feeCalculation,
            $input['npo_engagement_id'] ?? $options['npo_engagement_id'] ?? null,
        );

        return DB::transaction(function () use ($client, $feeCalculation, $input, $options, $npoEngagementId): Proposal {
            $createdByUserId = $this->normaliseUserId($options['created_by_user_id'] ?? null);
            $recalledProposalIds = $this->recallCurrentProposalsForClient($client, $createdByUserId);

            $proposal = Proposal::query()->create([
                'client_id' => $client->getKey(),
                'npo_engagement_id' => $npoEngagementId,
                'fee_calculation_id' => $feeCalculation->getKey(),
                'status' => ProposalStatus::Draft,
                'version' => 1,
                'scope' => $this->scope($client, $feeCalculation, $input),
                'services' => $this->services($feeCalculation, $input),
                'pv_summary' => $this->pvSummary($client, $feeCalculation, $npoEngagementId),
                'roi_ratio' => $feeCalculation->roi_ratio,
                'acceptance_terms' => $this->acceptanceTerms($feeCalculation),
                'created_by_user_id' => $createdByUserId,
            ]);

            $this->writeConsents($proposal, $input['consents'] ?? []);

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
        $proposal = $proposal->refresh();
        $expiryDays ??= $this->defaultExpiryDays();

        if (! in_array($proposal->status, [ProposalStatus::Draft, ProposalStatus::Renewed, ProposalStatus::Recalled], true)) {
            throw new InvalidArgumentException('Only draft, renewed, or recalled proposals can be released.');
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
        $includedDefault = match (true) {
            $isGovernanceReview => ['Governance evidence review', 'Board-ready Governance Review Report discussion', '12-month governance action plan'],
            $isNpoRetainer => ['NPO advisory retainer', 'Funding and accountability rhythm', 'Impact measurement check-ins'],
            default => ['Advisor review', 'Implementation roadmap', 'Progress check-in'],
        };
        $excludedDefault = match (true) {
            $isGovernanceReview => ['Ongoing retainer advisory work is not included in the fixed-fee Governance Review.'],
            $isNpoRetainer => ['Legal, audit, and trustee services are not included unless separately agreed.'],
            default => ['Digital signature and payment collection are Phase 3.'],
        };
        $included = is_array($scope['included'] ?? null) ? $scope['included'] : $includedDefault;
        $excluded = is_array($scope['excluded'] ?? null) ? $scope['excluded'] : $excludedDefault;
        $summary = $scope['summary'] ?? null;

        if (! is_string($summary) || $summary === '') {
            $summary = match (true) {
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

        return $payload;
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

        return [[
            'name' => 'Future Shift Advisory engagement',
            'fee_method' => $feeCalculation->method->value,
            'suggested_mid' => $feeCalculation->suggested_mid,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function pvSummary(Client $client, FeeCalculation $feeCalculation, ?string $npoEngagementId): array
    {
        $waterfall = $this->waterfalls->forClient($client);

        $summary = [
            'current_pv' => $waterfall['current_pv'],
            'improvement_pv_total' => $feeCalculation->improvement_pv_total,
            'risk_cost_pv_total' => $feeCalculation->risk_cost_pv_total,
            'target_pv' => $waterfall['target_pv'],
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
            'phase' => 'phase_3_signoff_enabled',
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
                return $html;
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

        return sprintf(
            <<<'HTML'
<section class="proposal-panel">
<h2>Scope</h2>
<p>%s</p>
</section>
<section class="proposal-panel">
<h2>Fee</h2>
<p>Method: %s</p>
<p>Suggested range: NZD %s - NZD %s - NZD %s</p>
<p>ROI ratio: %s</p>
</section>
%s
<section class="proposal-panel">
<h2>PV summary</h2>
<p>Improvement PV: NZD %s</p>
<p>Risk-cost PV: NZD %s</p>
<p>Target PV: NZD %s</p>
</section>
<section class="proposal-panel">
<h2>Consent elections</h2>
<ul>%s</ul>
</section>
<section class="proposal-panel">
<h2>Acceptance</h2>
<p>This proposal is release-controlled. Digital signature and payment collection are managed through the client sign-off flow when enabled.</p>
</section>
HTML,
            $this->escape((string) data_get($proposal->scope, 'summary')),
            $this->escape($proposal->feeCalculation?->method?->value ?? ''),
            number_format($proposal->feeCalculation?->suggested_low ?? 0, 0),
            number_format($proposal->feeCalculation?->suggested_mid ?? 0, 0),
            number_format($proposal->feeCalculation?->suggested_high ?? 0, 0),
            number_format($proposal->roi_ratio, 2),
            $conversionCredit,
            number_format((float) data_get($proposal->pv_summary, 'improvement_pv_total', 0), 0),
            number_format((float) data_get($proposal->pv_summary, 'risk_cost_pv_total', 0), 0),
            number_format((float) data_get($proposal->pv_summary, 'target_pv', 0), 0),
            $consents,
        );
    }

    private function brandedProposalHtml(Proposal $proposal, string $sections): string
    {
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
<header class="brand">
<h1>Future Shift Advisory</h1>
<p>Fee proposal v%s</p>
</header>
<section class="proposal-panel">
<h2>Client</h2>
<p>%s</p>
<p>Proposal status: %s</p>
</section>
%s
</body>
</html>
HTML,
            $this->proposalCss(null),
            $proposal->version,
            $this->escape($proposal->client?->legal_name ?? 'Client'),
            $this->escape($proposal->status->value),
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
        $primaryContact = $proposal->client?->primaryContact?->name ?: 'Client contact';
        $createdBy = $proposal->createdBy?->name ?: 'Future Shift Advisory';
        $feeCalculation = $proposal->feeCalculation;

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
            '{{ fee_low }}' => $this->money($feeCalculation?->suggested_low ?? 0),
            '{{fee_low}}' => $this->money($feeCalculation?->suggested_low ?? 0),
            '{{ fee_mid }}' => $this->money($feeCalculation?->suggested_mid ?? 0),
            '{{fee_mid}}' => $this->money($feeCalculation?->suggested_mid ?? 0),
            '{{ fee_high }}' => $this->money($feeCalculation?->suggested_high ?? 0),
            '{{fee_high}}' => $this->money($feeCalculation?->suggested_high ?? 0),
            '{{ roi_ratio }}' => number_format($proposal->roi_ratio, 2),
            '{{roi_ratio}}' => number_format($proposal->roi_ratio, 2),
            '{{ expires_at }}' => $this->escape($proposal->expires_at?->format('j M Y') ?? 'Not released'),
            '{{expires_at}}' => $this->escape($proposal->expires_at?->format('j M Y') ?? 'Not released'),
            '{{ sections }}' => $sections,
            '{{sections}}' => $sections,
            '{{{ sections }}}' => $sections,
            '{{{sections}}}' => $sections,
            '[Business Name]' => $this->escape($clientName),
            '[Report Type]' => 'Proposal',
            '[Date]' => $this->escape($proposalDate),
            '[Engagement Period]' => $this->escape('As at '.$proposalDate),
            '[Client Primary Contact]' => $this->escape($primaryContact),
            '[Title]' => 'Primary contact',
            '[Prepared By]' => $this->escape($createdBy),
        ];
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

    private function proposalCss(?Template $template): string
    {
        $accent = $this->templateLayoutColor($template, 'accent_color', '#2f6f5e');
        $accentDark = $this->templateLayoutColor($template, 'accent_dark', '#214f44');
        $ink = $this->templateLayoutColor($template, 'ink_color', '#17211b');
        $muted = $this->templateLayoutColor($template, 'muted_color', '#5d6b63');
        $paper = $this->templateLayoutColor($template, 'paper_color', '#ffffff');

        return <<<CSS
@page { margin: 16mm 15mm 18mm; }
* { box-sizing: border-box; }
body { background: {$paper}; color: {$ink}; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; margin: 0; }
.brand { border-bottom: 2px solid {$accent}; margin-bottom: 18px; padding-bottom: 12px; }
.brand h1 { color: {$accentDark}; font-size: 22px; margin: 0 0 4px; }
.proposal-panel { background: #fff; border: 1px solid #d8e2dc; border-left: 4px solid {$accent}; break-inside: avoid; margin-bottom: 16px; padding: 12px; }
.proposal-panel h2 { color: {$accentDark}; font-size: 15px; margin: 0 0 6px; }
.proposal-panel p { margin: 0 0 6px; }
.proposal-panel ul { margin: 0; padding-left: 18px; }
.proposal-panel li { margin: 0 0 4px; }
.proposal-muted { color: {$muted}; }
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
