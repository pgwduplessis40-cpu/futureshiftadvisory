<?php

declare(strict_types=1);

namespace App\Services\Panels;

use App\Models\Client;
use App\Models\ConflictDeclaration;
use App\Models\Consent;
use App\Models\Referral;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Conflicts\ConflictDeclarer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReferralConsentManager
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function grant(Client $client, User $actor, string $type, array $evidence = []): Consent
    {
        if (! in_array($type, Consent::types(), true)) {
            throw new InvalidArgumentException('Unsupported referral consent type.');
        }

        $consent = Consent::query()->create([
            'client_id' => $client->getKey(),
            'proposal_id' => null,
            'type' => $type,
            'election' => Consent::ELECTION_OPT_IN,
            'evidence' => [
                ...$evidence,
                'source' => 'referral_gate',
            ],
            'captured_by_user_id' => $actor->getKey(),
            'captured_at' => now(),
        ]);

        $this->audit->record('referral.consent_granted', subject: $consent, actor: $actor, after: [
            'client_id' => $client->getKey(),
            'type' => $type,
        ]);

        return $consent->refresh();
    }

    public function prepareForSending(Referral $referral, User $advisor, ConflictDeclaration $conflict, Consent $consent): Referral
    {
        $referral = $referral->refresh();
        $this->assertConflict($referral, $advisor, $conflict);
        $this->assertConsent($referral, $consent);

        $referral->forceFill([
            'conflict_declaration_id' => $conflict->getKey(),
            'consent_id' => $consent->getKey(),
        ])->save();

        $this->audit->record('referral.send_gate_satisfied', subject: $referral, actor: $advisor, after: [
            'conflict_declaration_id' => $conflict->getKey(),
            'consent_id' => $consent->getKey(),
        ]);

        return $referral->refresh();
    }

    public function revoke(Consent $consent, User $actor): int
    {
        return DB::transaction(function () use ($consent, $actor): int {
            $consent = $consent->refresh();
            $before = [
                'election' => $consent->election,
                'revoked_at' => $consent->revoked_at?->toIso8601String(),
            ];

            if ($consent->revoked_at === null) {
                $consent->forceFill([
                    'election' => Consent::ELECTION_OPT_OUT,
                    'revoked_by_user_id' => $actor->getKey(),
                    'revoked_at' => now(),
                ])->save();

                $this->audit->record('referral.consent_revoked', subject: $consent, actor: $actor, before: $before, after: [
                    'election' => Consent::ELECTION_OPT_OUT,
                    'revoked_at' => $consent->revoked_at?->toIso8601String(),
                ]);
            }

            $withdrawn = 0;
            Referral::query()
                ->where('consent_id', $consent->getKey())
                ->whereNotIn('stage', $this->terminalStages())
                ->get()
                ->each(function (Referral $referral) use ($actor, &$withdrawn): void {
                    $before = ['stage' => $referral->stage];
                    $referral->forceFill([
                        'stage' => Referral::STAGE_WITHDRAWN,
                        'closed_at' => now(),
                    ])->save();

                    $this->audit->record('referral.withdrawn_consent_revoked', subject: $referral, actor: $actor, before: $before, after: [
                        'stage' => Referral::STAGE_WITHDRAWN,
                    ]);

                    $withdrawn++;
                });

            return $withdrawn;
        });
    }

    public function assertReadyToSend(Referral $referral, User $actor): void
    {
        if ($referral->client_id === null) {
            return;
        }

        $conflict = $referral->conflictDeclaration;
        if (! $conflict instanceof ConflictDeclaration) {
            throw new InvalidArgumentException('A fresh conflict declaration is required before sending this referral.');
        }

        $this->assertConflict($referral, $actor, $conflict);

        $consent = $referral->consent;
        if (! $consent instanceof Consent) {
            throw new InvalidArgumentException('Active client consent is required before sending this referral.');
        }

        $this->assertConsent($referral, $consent);
    }

    private function assertConflict(Referral $referral, User $advisor, ConflictDeclaration $conflict): void
    {
        $expected = $this->conflictType($referral);

        if ((string) $conflict->client_id !== (string) $referral->client_id
            || (string) $conflict->advisor_id !== (string) $advisor->getKey()
            || $conflict->referralType() !== $expected
            || ! $conflict->isFreshFor(ConflictDeclarer::FRESH_FOR_DAYS)) {
            throw new InvalidArgumentException('A fresh conflict declaration is required before sending this referral.');
        }
    }

    private function assertConsent(Referral $referral, Consent $consent): void
    {
        if ((string) $consent->client_id !== (string) $referral->client_id
            || $consent->type !== $this->consentType($referral)
            || ! $consent->isActiveOptIn()) {
            throw new InvalidArgumentException('Active client consent is required before sending this referral.');
        }
    }

    private function conflictType(Referral $referral): string
    {
        return $referral->referral_type === Referral::TYPE_BROKER
            ? ConflictDeclarer::BROKER_REFERRAL
            : ConflictDeclarer::COACH_REFERRAL;
    }

    private function consentType(Referral $referral): string
    {
        return $referral->referral_type === Referral::TYPE_BROKER
            ? Consent::TYPE_INSURANCE_REFERRAL
            : Consent::TYPE_COACH_REFERRAL;
    }

    /**
     * @return array<int, string>
     */
    private function terminalStages(): array
    {
        return [
            Referral::STAGE_COMPLETED,
            Referral::STAGE_WITHDRAWN,
            Referral::STAGE_BROKER_COVER_PLACED,
            Referral::STAGE_BROKER_DECLINED,
            Referral::STAGE_BROKER_NO_RESPONSE,
            Referral::STAGE_COACH_CONCLUDED,
            Referral::STAGE_COACH_DECLINED,
        ];
    }
}
