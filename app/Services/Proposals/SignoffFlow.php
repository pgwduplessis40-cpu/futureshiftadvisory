<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Enums\ProposalStatus;
use App\Models\Consent;
use App\Models\PaymentAuthority;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Payments\AuthorityCapture;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class SignoffFlow
{
    public function __construct(
        private readonly AuthorityCapture $authorities,
        private readonly PdfRenderer $renderer,
        private readonly KeyEnvelope $envelope,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function complete(Proposal $proposal, string $step, array $payload, User $actor): Proposal
    {
        $step = $this->normaliseStep($step);
        $proposal = $proposal->refresh()->load(['client', 'consents', 'paymentAuthorities', 'signoffSteps']);
        $alreadyCompleted = $this->completedSteps($proposal)->contains($step);

        $this->assertClientOwnsProposal($proposal);
        $this->assertProposalCanEnterSignoff($proposal);
        $this->assertStepOrder($proposal, $step, $alreadyCompleted);

        try {
            return DB::transaction(function () use ($proposal, $step, $payload, $actor): Proposal {
                $stepPayload = match ($step) {
                    ProposalSignoffStep::STEP_REVIEW => $this->review($payload),
                    ProposalSignoffStep::STEP_INSURANCE_CONSENT => $this->consent($proposal, Consent::TYPE_INSURANCE_REFERRAL, $payload, $actor),
                    ProposalSignoffStep::STEP_COACH_CONSENT => $this->consent($proposal, Consent::TYPE_COACH_REFERRAL, $payload, $actor),
                    ProposalSignoffStep::STEP_PAYMENT_METHOD => $this->paymentMethod($payload),
                    ProposalSignoffStep::STEP_AUTHORITY => $this->authority($proposal, $payload, $actor),
                    ProposalSignoffStep::STEP_SIGNATURE => $this->signature($proposal, $payload, $actor),
                    ProposalSignoffStep::STEP_CONFIRMATION => $this->confirmation($proposal),
                    default => throw new InvalidArgumentException("Unsupported sign-off step [{$step}]."),
                };

                $this->recordStep($proposal, $step, $stepPayload, $actor);

                $this->audit->record('proposal.signoff_step_completed', subject: $proposal, actor: $actor, after: [
                    'step' => $step,
                    'status' => $proposal->refresh()->status->value,
                ]);

                return $proposal->refresh()->load(['consents', 'paymentAuthorities', 'signoffSteps']);
            });
        } catch (PaymentGatewayException $e) {
            $this->audit->record('proposal.authority_capture_failed', subject: $proposal, actor: $actor, after: [
                'step' => $step,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Proposal $proposal): array
    {
        $proposal->loadMissing(['signoffSteps', 'paymentAuthorities']);
        $completed = $this->completedSteps($proposal);
        $steps = collect(ProposalSignoffStep::orderedSteps())
            ->map(function (string $step) use ($proposal, $completed): array {
                $record = $proposal->signoffSteps->firstWhere('step', $step);

                return [
                    'step' => $step,
                    'label' => str($step)->replace('_', ' ')->title()->toString(),
                    'completed' => $completed->contains($step),
                    'completed_at' => $record?->completed_at?->toIso8601String(),
                    'url' => route('portal.proposals.signoff.step', [$proposal, $step], absolute: false),
                ];
            })
            ->values()
            ->all();

        return [
            'steps' => $steps,
            'next_step' => $this->nextStep($proposal),
            'active_authority' => $this->activeAuthority($proposal) instanceof PaymentAuthority,
        ];
    }

    private function review(array $payload): array
    {
        return [
            'reviewed' => (bool) ($payload['reviewed'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function consent(Proposal $proposal, string $type, array $payload, User $actor): array
    {
        $election = (string) ($payload['election'] ?? Consent::ELECTION_UNDECIDED);

        if (! in_array($election, Consent::elections(), true)) {
            throw new InvalidArgumentException('Unsupported consent election.');
        }

        $consent = Consent::query()->updateOrCreate(
            [
                'proposal_id' => $proposal->getKey(),
                'type' => $type,
            ],
            [
                'client_id' => $proposal->client_id,
                'election' => $election,
                'evidence' => [
                    'source' => 'proposal_signoff',
                    'step' => $type === Consent::TYPE_INSURANCE_REFERRAL
                        ? ProposalSignoffStep::STEP_INSURANCE_CONSENT
                        : ProposalSignoffStep::STEP_COACH_CONSENT,
                    'revocable' => true,
                ],
                'captured_by_user_id' => $actor->getKey(),
                'captured_at' => now(),
            ],
        );

        $this->audit->record('proposal.consent_captured', subject: $consent, actor: $actor, after: [
            'proposal_id' => $proposal->getKey(),
            'type' => $type,
            'election' => $election,
        ]);

        return [
            'type' => $type,
            'election' => $election,
            'consent_id' => $consent->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function paymentMethod(array $payload): array
    {
        $type = (string) ($payload['type'] ?? PaymentAuthority::TYPE_CARD);
        $gateway = (string) ($payload['gateway'] ?? PaymentAuthority::GATEWAY_STRIPE);

        if (! in_array($type, PaymentAuthority::types(), true)) {
            throw new InvalidArgumentException('Unsupported payment method type.');
        }

        if (! in_array($gateway, PaymentAuthority::gateways(), true)) {
            throw new InvalidArgumentException('Unsupported payment gateway.');
        }

        return [
            'type' => $type,
            'gateway' => $gateway,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function authority(Proposal $proposal, array $payload, User $actor): array
    {
        $method = $this->stepPayload($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD);
        $type = (string) ($payload['type'] ?? Arr::get($method, 'type', PaymentAuthority::TYPE_CARD));
        $gateway = (string) ($payload['gateway'] ?? Arr::get($method, 'gateway', PaymentAuthority::GATEWAY_STRIPE));

        $authority = $this->authorities->capture($proposal, $type, $gateway, $payload, $actor);

        $this->markAwaitingSignature($proposal);

        return [
            'type' => $type,
            'gateway' => $gateway,
            'payment_authority_id' => $authority->getKey(),
            'gateway_customer_ref' => $authority->gateway_customer_ref,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function signature(Proposal $proposal, array $payload, User $actor): array
    {
        if (! $this->activeAuthority($proposal) instanceof PaymentAuthority) {
            throw new InvalidArgumentException('A tokenised payment authority is required before signature.');
        }

        $typedName = trim((string) ($payload['signature_name'] ?? $payload['typed_name'] ?? ''));
        if ($typedName === '') {
            throw new InvalidArgumentException('A typed signature name is required.');
        }

        $signedAt = now();
        $pdf = $this->renderer->render($this->signatureHtml($proposal->refresh()->load(['client', 'feeCalculation', 'consents']), $actor, $typedName, $payload));
        $path = sprintf('proposals/%s/%s/signature-v%s.pdf', $proposal->client_id, Str::uuid(), $proposal->version);
        $written = Storage::disk('secure_local')->put($path, $pdf);

        if ($written !== true) {
            throw new RuntimeException('Signed proposal evidence could not be stored.');
        }

        $hashEnvelope = $this->envelope->encrypt(hash('sha256', $pdf));

        Proposal::allowSignoffStatusTransition(function () use ($proposal, $actor, $signedAt, $path, $hashEnvelope, $pdf): void {
            $proposal->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => $signedAt,
                'signed_by_user_id' => $actor->getKey(),
                'signature_evidence_path' => $path,
                'signature_evidence_sha256_envelope' => $hashEnvelope,
                'signature_envelope_meta' => $this->envelope->inspect($hashEnvelope),
                'signature_evidence_byte_size' => strlen($pdf),
            ])->save();
        });

        return [
            'signature_name' => $typedName,
            'signed_by_user_id' => $actor->getKey(),
            'signature_evidence_path' => $path,
        ];
    }

    private function confirmation(Proposal $proposal): array
    {
        if ($proposal->refresh()->status !== ProposalStatus::Signed) {
            throw new InvalidArgumentException('Confirmation requires a signed proposal.');
        }

        return [
            'confirmed' => true,
        ];
    }

    private function markAwaitingSignature(Proposal $proposal): void
    {
        if ($proposal->refresh()->status === ProposalStatus::AwaitingSignature) {
            return;
        }

        Proposal::allowSignoffStatusTransition(function () use ($proposal): void {
            $proposal->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordStep(Proposal $proposal, string $step, array $payload, User $actor): ProposalSignoffStep
    {
        return ProposalSignoffStep::query()->updateOrCreate(
            [
                'proposal_id' => $proposal->getKey(),
                'step' => $step,
            ],
            [
                'client_id' => $proposal->client_id,
                'completed_by_user_id' => $actor->getKey(),
                'completed_at' => now(),
                'payload' => $payload,
            ],
        );
    }

    private function activeAuthority(Proposal $proposal): ?PaymentAuthority
    {
        return PaymentAuthority::query()
            ->where('proposal_id', $proposal->getKey())
            ->where('status', PaymentAuthority::STATUS_ACTIVE)
            ->latest('authorised_at')
            ->first();
    }

    /**
     * @return Collection<int, string>
     */
    private function completedSteps(Proposal $proposal): Collection
    {
        $proposal->loadMissing('signoffSteps');

        return $proposal->signoffSteps->pluck('step')->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function stepPayload(Proposal $proposal, string $step): array
    {
        $proposal->loadMissing('signoffSteps');
        $payload = $proposal->signoffSteps->firstWhere('step', $step)?->payload;

        return is_array($payload) ? $payload : [];
    }

    private function nextStep(Proposal $proposal): ?string
    {
        $completed = $this->completedSteps($proposal);

        foreach (ProposalSignoffStep::orderedSteps() as $step) {
            if (! $completed->contains($step)) {
                return $step;
            }
        }

        return null;
    }

    private function assertStepOrder(Proposal $proposal, string $step, bool $alreadyCompleted): void
    {
        if ($alreadyCompleted) {
            return;
        }

        $ordered = ProposalSignoffStep::orderedSteps();
        $position = array_search($step, $ordered, true);
        $completed = $this->completedSteps($proposal);

        foreach (array_slice($ordered, 0, (int) $position) as $required) {
            if (! $completed->contains($required)) {
                throw new InvalidArgumentException("Step [{$required}] must be completed before [{$step}].");
            }
        }
    }

    private function assertClientOwnsProposal(Proposal $proposal): void
    {
        if ($proposal->client === null) {
            throw new InvalidArgumentException('Proposal must belong to a client.');
        }
    }

    private function assertProposalCanEnterSignoff(Proposal $proposal): void
    {
        if (! in_array($proposal->status, [
            ProposalStatus::Released,
            ProposalStatus::AwaitingSignature,
            ProposalStatus::Signed,
        ], true)) {
            throw new InvalidArgumentException('Only released proposals can enter the sign-off flow.');
        }
    }

    private function normaliseStep(string $step): string
    {
        $step = trim($step);

        if (! in_array($step, ProposalSignoffStep::orderedSteps(), true)) {
            throw new InvalidArgumentException("Unsupported sign-off step [{$step}].");
        }

        return $step;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signatureHtml(Proposal $proposal, User $actor, string $typedName, array $payload): string
    {
        return sprintf(
            <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Signed proposal evidence</title>
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
<p>Signed proposal evidence</p>
</header>
<section class="panel">
<h2>Proposal</h2>
<p>Client: %s</p>
<p>Proposal v%s</p>
<p>Suggested mid fee: NZD %s</p>
</section>
<section class="panel">
<h2>Signature</h2>
<p>Signed by: %s &lt;%s&gt;</p>
<p>Typed name: %s</p>
<p>User ID: %s</p>
<p>IP address: %s</p>
<p>User agent: %s</p>
</section>
</body>
</html>
HTML,
            $this->escape($proposal->client?->legal_name ?? 'Client'),
            $proposal->version,
            number_format($proposal->feeCalculation?->suggested_mid ?? 0, 0),
            $this->escape($actor->name),
            $this->escape($actor->email),
            $this->escape($typedName),
            $this->escape((string) $actor->getKey()),
            $this->escape((string) ($payload['ip'] ?? '')),
            $this->escape((string) ($payload['user_agent'] ?? '')),
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
