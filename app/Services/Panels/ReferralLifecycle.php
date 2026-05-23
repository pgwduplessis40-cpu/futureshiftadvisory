<?php

declare(strict_types=1);

namespace App\Services\Panels;

use App\Enums\EntrepreneurStage;
use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Models\PanelMember;
use App\Models\ProspectLead;
use App\Models\Referral;
use App\Models\ReferralMessage;
use App\Models\ReverseReferral;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReferralLifecycle
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $sharedAllowedNext = [
        Referral::STAGE_DRAFT => [Referral::STAGE_SENT, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_SENT => [Referral::STAGE_ACCEPTED, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_ACCEPTED => [Referral::STAGE_IN_PROGRESS, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_IN_PROGRESS => [Referral::STAGE_COMPLETED, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_COMPLETED => [],
        Referral::STAGE_WITHDRAWN => [],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private array $brokerAllowedNext = [
        Referral::STAGE_DRAFT => [Referral::STAGE_BROKER_REFERRAL_SENT, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_BROKER_REFERRAL_SENT => [Referral::STAGE_BROKER_ACKNOWLEDGED, Referral::STAGE_BROKER_NO_RESPONSE, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_BROKER_ACKNOWLEDGED => [Referral::STAGE_BROKER_QUOTE_REQUESTED, Referral::STAGE_BROKER_DECLINED, Referral::STAGE_BROKER_NO_RESPONSE, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_BROKER_QUOTE_REQUESTED => [Referral::STAGE_BROKER_COVER_PLACED, Referral::STAGE_BROKER_DECLINED, Referral::STAGE_BROKER_NO_RESPONSE, Referral::STAGE_WITHDRAWN],
        Referral::STAGE_BROKER_COVER_PLACED => [],
        Referral::STAGE_BROKER_DECLINED => [],
        Referral::STAGE_BROKER_NO_RESPONSE => [],
        Referral::STAGE_WITHDRAWN => [],
    ];

    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Client $client, PanelMember $member, User $advisor, array $payload = []): Referral
    {
        $member = $member->refresh();

        if ($member->status !== PanelMember::STATUS_ACTIVE) {
            throw new PanelAccessException('Referral requires an active panel member.');
        }

        $referralType = $member->panel_type === PanelMember::TYPE_BROKER
            ? Referral::TYPE_BROKER
            : Referral::TYPE_COACH;

        $referral = Referral::query()->create([
            'client_id' => $client->getKey(),
            'panel_member_id' => $member->getKey(),
            'panel_type' => $member->panel_type,
            'referral_type' => $referralType,
            'stage' => Referral::STAGE_DRAFT,
            'payload' => $payload,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $this->audit->record('referral.created', subject: $referral, actor: $advisor, after: [
            'panel_type' => $member->panel_type,
            'stage' => Referral::STAGE_DRAFT,
        ]);

        return $referral->refresh();
    }

    public function transition(Referral $referral, string $stage, User $actor): Referral
    {
        $referral = $referral->refresh();

        if (! in_array($stage, Referral::stages(), true)) {
            throw new InvalidArgumentException('Unsupported referral stage.');
        }

        if (! in_array($stage, $this->allowedNextFor($referral)[$referral->stage] ?? [], true)) {
            throw new InvalidArgumentException('Referral stage transition is not allowed.');
        }

        $before = ['stage' => $referral->stage];
        $referral->forceFill([
            'stage' => $stage,
            'sent_at' => in_array($stage, [Referral::STAGE_SENT, Referral::STAGE_BROKER_REFERRAL_SENT], true) ? now() : $referral->sent_at,
            'closed_at' => in_array($stage, $this->terminalStages(), true) ? now() : $referral->closed_at,
        ])->save();

        $this->audit->record('referral.stage_changed', subject: $referral, actor: $actor, before: $before, after: [
            'stage' => $stage,
        ]);

        return $referral->refresh();
    }

    public function message(Referral $referral, User $sender, string $body): ReferralMessage
    {
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('Referral message body is required.');
        }

        $message = ReferralMessage::query()->create([
            'referral_id' => $referral->getKey(),
            'client_id' => $referral->client_id,
            'sender_user_id' => $sender->getKey(),
            'body' => $body,
            'sent_at' => now(),
        ]);

        $this->audit->record('referral.message_sent', subject: $message, actor: $sender, after: [
            'referral_id' => $referral->getKey(),
        ]);

        return $message->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reverseReferral(PanelMember $member, string $targetType, string $name, string $email, ?string $company = null, array $payload = []): ReverseReferral
    {
        $member = $member->refresh();

        if ($member->status !== PanelMember::STATUS_ACTIVE) {
            throw new PanelAccessException('Reverse referrals require an active panel member.');
        }

        if (! in_array($targetType, [ReverseReferral::TARGET_PROSPECT, ReverseReferral::TARGET_ENTREPRENEUR], true)) {
            throw new InvalidArgumentException('Unsupported reverse referral target type.');
        }

        return DB::transaction(function () use ($member, $targetType, $name, $email, $company, $payload): ReverseReferral {
            $prospect = null;
            $profile = null;

            if ($targetType === ReverseReferral::TARGET_ENTREPRENEUR) {
                $profile = EntrepreneurProfile::query()->create([
                    'user_id' => null,
                    'assigned_advisor_id' => $this->defaultAdvisorId($member),
                    'invite_token_id' => null,
                    'name' => $name,
                    'email' => Str::lower($email),
                    'stage' => EntrepreneurStage::INVITED,
                    'concept_summary' => $company === null ? null : 'Reverse referral from '.$company.'.',
                ]);
            } else {
                $prospect = ProspectLead::query()->create([
                    'name' => $name,
                    'email' => Str::lower($email),
                    'company' => $company,
                    'message' => (string) ($payload['message'] ?? 'Reverse referral from panel member.'),
                    'source' => 'reverse_referral',
                    'status' => ProspectLead::STATUS_NEW,
                    'dedupe_key' => hash('sha256', $member->getKey().'|'.Str::lower($email).'|'.now()->toIso8601String()),
                    'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'intake_payload' => $payload,
                ]);
            }

            $reverse = ReverseReferral::query()->create([
                'panel_member_id' => $member->getKey(),
                'target_type' => $targetType,
                'name' => $name,
                'email' => Str::lower($email),
                'company' => $company,
                'payload' => [
                    ...$payload,
                    'no_platform_access_granted' => true,
                ],
                'prospect_lead_id' => $prospect?->getKey(),
                'entrepreneur_profile_id' => $profile?->getKey(),
                'submitted_at' => now(),
            ]);

            $this->audit->record('reverse_referral.created', subject: $reverse, actor: $member->user, after: [
                'target_type' => $targetType,
                'prospect_lead_id' => $prospect?->getKey(),
                'entrepreneur_profile_id' => $profile?->getKey(),
                'no_platform_access_granted' => true,
            ]);

            return $reverse->refresh();
        });
    }

    private function defaultAdvisorId(PanelMember $member): int|string
    {
        $advisor = User::query()
            ->whereIn('user_type', [User::TYPE_ADVISOR, User::TYPE_SUPER_ADMIN])
            ->orderBy('id')
            ->first();

        return $advisor?->getKey() ?? $member->user_id;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function allowedNextFor(Referral $referral): array
    {
        return $referral->referral_type === Referral::TYPE_BROKER
            ? $this->brokerAllowedNext
            : $this->sharedAllowedNext;
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
        ];
    }
}
