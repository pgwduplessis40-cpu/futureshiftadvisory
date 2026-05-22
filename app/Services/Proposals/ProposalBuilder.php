<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\Proposal;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pv\PvWaterfallBuilder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

final class ProposalBuilder
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly PvWaterfallBuilder $waterfalls,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array{created_by_user_id?: int|string|null}  $options
     */
    public function generate(Client $client, FeeCalculation $feeCalculation, array $input = [], array $options = []): Proposal
    {
        if ((string) $feeCalculation->client_id !== (string) $client->getKey()) {
            throw new InvalidArgumentException('Fee calculation must belong to the proposal client.');
        }

        return DB::transaction(function () use ($client, $feeCalculation, $input, $options): Proposal {
            $proposal = Proposal::query()->create([
                'client_id' => $client->getKey(),
                'fee_calculation_id' => $feeCalculation->getKey(),
                'status' => $this->phaseTwoStatus(ProposalStatus::Draft),
                'version' => 1,
                'scope' => $this->scope($client, $input),
                'services' => $this->services($feeCalculation, $input),
                'pv_summary' => $this->pvSummary($client, $feeCalculation),
                'roi_ratio' => $feeCalculation->roi_ratio,
                'acceptance_terms' => $this->acceptanceTerms(),
                'created_by_user_id' => $this->normaliseUserId($options['created_by_user_id'] ?? null),
            ]);

            $this->writeConsents($proposal, $input['consents'] ?? []);
            $this->renderAndStorePdf($proposal->refresh()->load(['client', 'feeCalculation', 'consents']));

            $this->audit->record('proposal.generated', subject: $proposal, after: [
                'client_id' => $client->getKey(),
                'fee_calculation_id' => $feeCalculation->getKey(),
                'status' => ProposalStatus::Draft->value,
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

        $proposal->forceFill([
            'status' => $this->phaseTwoStatus(ProposalStatus::Released),
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
            'status' => $this->phaseTwoStatus(ProposalStatus::Recalled),
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
            $renewed = Proposal::query()->create([
                'client_id' => $proposal->client_id,
                'fee_calculation_id' => $proposal->fee_calculation_id,
                'status' => $this->phaseTwoStatus(ProposalStatus::Renewed),
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

            $this->renderAndStorePdf($renewed->refresh()->load(['client', 'feeCalculation', 'consents']));

            $this->audit->record('proposal.renewed', subject: $renewed, actor: $actor, after: [
                'renewed_from_proposal_id' => $proposal->getKey(),
                'version' => $renewed->version,
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
                    'status' => $this->phaseTwoStatus(ProposalStatus::Expired),
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
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function scope(Client $client, array $input): array
    {
        $scope = is_array($input['scope'] ?? null) ? $input['scope'] : [];
        $included = is_array($scope['included'] ?? null) ? $scope['included'] : ['Advisor review', 'Implementation roadmap', 'Progress check-in'];
        $excluded = is_array($scope['excluded'] ?? null) ? $scope['excluded'] : ['Digital signature and payment collection are Phase 3.'];

        return [
            'summary' => (string) ($scope['summary'] ?? 'Advisory engagement proposal for '.$client->legal_name.'.'),
            'included' => array_values($included),
            'excluded' => array_values($excluded),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function services(FeeCalculation $feeCalculation, array $input): array
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
    private function pvSummary(Client $client, FeeCalculation $feeCalculation): array
    {
        $waterfall = $this->waterfalls->forClient($client);

        return [
            'current_pv' => $waterfall['current_pv'],
            'improvement_pv_total' => $feeCalculation->improvement_pv_total,
            'risk_cost_pv_total' => $feeCalculation->risk_cost_pv_total,
            'target_pv' => $waterfall['target_pv'],
            'roi_ratio' => $feeCalculation->roi_ratio,
            'fee_suggested_mid' => $feeCalculation->suggested_mid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptanceTerms(): array
    {
        return [
            'phase' => 'phase_2_release_only',
            'client_acceptance_section_present' => true,
            'payment_collection_enabled' => false,
            'digital_signature_enabled' => false,
            'reserved_phase_three_statuses' => [
                ProposalStatus::AwaitingSignature->value,
                ProposalStatus::Signed->value,
            ],
        ];
    }

    private function writeConsents(Proposal $proposal, mixed $elections): void
    {
        $elections = is_array($elections) ? $elections : [];

        foreach (Consent::types() as $type) {
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
        $path = sprintf(
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
        $proposal->loadMissing(['client', 'feeCalculation', 'consents']);
        $consents = $proposal->consents
            ->map(fn (Consent $consent): string => sprintf(
                '<li>%s: %s</li>',
                $this->escape(str_replace('_', ' ', $consent->type)),
                $this->escape(str_replace('_', ' ', $consent->election)),
            ))
            ->implode('');

        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Future Shift Advisory proposal</title>
<style>
body { color: #17211b; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.55; margin: 0; }
.brand { border-bottom: 2px solid #2f6f5e; margin-bottom: 18px; padding-bottom: 12px; }
.brand h1 { font-size: 22px; margin: 0 0 4px; }
.panel { background: #f4f7f5; border: 1px solid #d8e2dc; margin-bottom: 16px; padding: 12px; }
h2 { color: #214f44; font-size: 15px; margin: 0 0 6px; }
p { margin: 0 0 6px; }
</style>
</head>
<body>
<header class="brand">
<h1>Future Shift Advisory</h1>
<p>Fee proposal v%s</p>
</header>
<section class="panel">
<h2>Client</h2>
<p>%s</p>
<p>Proposal status: %s</p>
</section>
<section class="panel">
<h2>Scope</h2>
<p>%s</p>
</section>
<section class="panel">
<h2>Fee</h2>
<p>Method: %s</p>
<p>Suggested range: NZD %s - NZD %s - NZD %s</p>
<p>ROI ratio: %s</p>
</section>
<section class="panel">
<h2>PV summary</h2>
<p>Improvement PV: NZD %s</p>
<p>Risk-cost PV: NZD %s</p>
<p>Target PV: NZD %s</p>
</section>
<section class="panel">
<h2>Consent elections</h2>
<ul>%s</ul>
</section>
<section class="panel">
<h2>Acceptance</h2>
<p>This Phase 2 proposal is release-controlled. Digital signature and payment collection are Phase 3.</p>
</section>
</body>
</html>
HTML,
            $proposal->version,
            $this->escape($proposal->client?->legal_name ?? 'Client'),
            $this->escape($proposal->status->value),
            $this->escape((string) data_get($proposal->scope, 'summary')),
            $this->escape($proposal->feeCalculation?->method?->value ?? ''),
            number_format($proposal->feeCalculation?->suggested_low ?? 0, 0),
            number_format($proposal->feeCalculation?->suggested_mid ?? 0, 0),
            number_format($proposal->feeCalculation?->suggested_high ?? 0, 0),
            number_format($proposal->roi_ratio, 2),
            number_format((float) data_get($proposal->pv_summary, 'improvement_pv_total', 0), 0),
            number_format((float) data_get($proposal->pv_summary, 'risk_cost_pv_total', 0), 0),
            number_format((float) data_get($proposal->pv_summary, 'target_pv', 0), 0),
            $consents,
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

    private function phaseTwoStatus(ProposalStatus $status): ProposalStatus
    {
        if ($status->phaseTwoReserved()) {
            throw new LogicException('Phase 3 proposal statuses are reserved in Phase 2.');
        }

        return $status;
    }
}
