<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Enums\EngagementType;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdEngagement;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AdvisoryConversion
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function convert(EntrepreneurProfile $profile, User $actor, ?BusinessPlan $sourcePlan = null): Client
    {
        $profile->loadMissing('user', 'advisoryReadinessSignals');
        $sourcePlan ??= BusinessPlan::query()
            ->where('entrepreneur_profile_id', $profile->getKey())
            ->latest('updated_at')
            ->first();

        return DB::transaction(function () use ($profile, $actor, $sourcePlan): Client {
            $client = Client::query()->create([
                'engagement_type' => EngagementType::STANDARD_ADVISORY,
                'legal_name' => $profile->name,
                'data_quality' => Client::DATA_QUALITY_LOW,
                'registry_sources' => [
                    'source' => 'entrepreneur',
                    'source_label' => 'Sourced from Entrepreneur Module',
                    'entrepreneur_profile_id' => $profile->getKey(),
                    'business_plan_id' => $sourcePlan?->getKey(),
                    'concept_summary' => $profile->concept_summary,
                    'stage_at_conversion' => $profile->currentStageValue(),
                    'founding_advisory_payload' => $sourcePlan?->founding_advisory_payload ?? [],
                    'advisory_readiness_signal_id' => $profile->advisoryReadinessSignals->sortByDesc('surfaced_at')->first()?->id,
                ],
                'created_by_user_id' => $actor->getKey(),
                'primary_contact_user_id' => $profile->user_id,
                'engagement_type_locked_at' => now(),
            ]);

            $this->attachTeam($client, $actor, $profile->user);

            if ($sourcePlan instanceof BusinessPlan) {
                $sourcePlan->forceFill([
                    'client_id' => $client->getKey(),
                    'status' => BusinessPlan::STATUS_LAUNCHED,
                ])->save();
            }

            $this->audit->record('entrepreneur.advisory_converted', subject: $client, actor: $actor, after: [
                'entrepreneur_profile_id' => $profile->getKey(),
                'business_plan_id' => $sourcePlan?->getKey(),
                'client_id' => $client->getKey(),
            ]);

            return $client->refresh()->load('teamMembers');
        });
    }

    public function handoffDdPlan(BusinessPlan $plan, User $actor): Client
    {
        $plan->loadMissing('ddEngagement.client');
        $engagement = $plan->ddEngagement;

        if ($plan->source_type !== BusinessPlan::SOURCE_DUE_DILIGENCE || ! $engagement instanceof DdEngagement) {
            throw new InvalidArgumentException('DD plan handoff requires a due-diligence owned business plan.');
        }

        return DB::transaction(function () use ($plan, $engagement, $actor): Client {
            $client = Client::query()->create([
                'engagement_type' => EngagementType::STANDARD_ADVISORY,
                'nzbn' => data_get($engagement->target_details, 'nzbn'),
                'legal_name' => $engagement->target_name,
                'data_quality' => Client::DATA_QUALITY_LOW,
                'registry_sources' => [
                    'source' => 'due_diligence_plan',
                    'source_label' => 'Sourced from DD Business Plan',
                    'dd_engagement_id' => $engagement->getKey(),
                    'buyer_client_id' => $engagement->client_id,
                    'business_plan_id' => $plan->getKey(),
                    'target_details' => $engagement->target_details ?? [],
                    'founding_advisory_payload' => $plan->founding_advisory_payload ?? [],
                ],
                'created_by_user_id' => $actor->getKey(),
                'primary_contact_user_id' => $engagement->client?->primary_contact_user_id,
                'engagement_type_locked_at' => now(),
            ]);

            $this->attachTeam($client, $actor, $engagement->client?->primaryContact);
            $plan->forceFill([
                'client_id' => $client->getKey(),
                'status' => BusinessPlan::STATUS_FOUNDING,
            ])->save();

            $this->audit->record('entrepreneur.dd_plan_handoff_converted', subject: $client, actor: $actor, after: [
                'dd_engagement_id' => $engagement->getKey(),
                'business_plan_id' => $plan->getKey(),
                'client_id' => $client->getKey(),
            ]);

            return $client->refresh()->load('teamMembers');
        });
    }

    private function attachTeam(Client $client, User $advisor, ?User $primaryContact): void
    {
        ClientTeamMember::query()->updateOrCreate(
            [
                'client_id' => $client->getKey(),
                'user_id' => $advisor->getKey(),
            ],
            [
                'role' => 'lead_advisor',
                'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
            ],
        );

        if ($primaryContact instanceof User) {
            ClientTeamMember::query()->updateOrCreate(
                [
                    'client_id' => $client->getKey(),
                    'user_id' => $primaryContact->getKey(),
                ],
                [
                    'role' => 'primary_contact',
                    'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
                ],
            );
        }
    }
}
