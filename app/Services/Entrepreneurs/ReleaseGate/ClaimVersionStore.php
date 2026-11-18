<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs\ReleaseGate;

use App\Models\BusinessPlan;
use App\Models\PlanClaimVersion;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ClaimVersionStore
{
    public const KEYS = ['plan.profitability', 'plan.owner_pay', 'plan.opening_cash', 'plan.margin_min', 'plan.margin_max',
        'plan.margin_explanation', 'plan.commitment', 'plan.commitment_in_scope', 'scope.required_horizon',
        'runway.months', 'runway.start_cash', 'runway.includes_receipts', 'runway.includes_owner_pay'];

    /**
     * Claims whose value is an answer rather than an amount.
     *
     * A runway figure means nothing without them: PM-10 refuses to compare a
     * claim that counts future receipts against a model figure that does not,
     * so the two inclusions are confirmed alongside the months rather than
     * inferred from the prose that stated them.
     */
    public const BOOLEAN_KEYS = ['plan.commitment_in_scope', 'runway.includes_receipts', 'runway.includes_owner_pay'];

    /** Claims whose value is prose, and so has no number to validate. */
    public const TEXT_KEYS = ['plan.margin_explanation'];

    /**
     * The bases a runway can be claimed on, named by the producer that states
     * the model's own figures, so a claim cannot be recorded against a basis
     * the rule has nothing to compare it with.
     */
    public const RUNWAY_BASES = [RunwayFacts::FORECAST_CASH, RunwayFacts::NO_SALES];

    public function __construct(private AuditWriter $audit) {}

    public function confirm(BusinessPlan $plan, PlanSection $section, User $actor, Fact $fact, string $expectedBodyHash, string $reason): PlanClaimVersion
    {
        Gate::forUser($actor)->authorize('assess', $plan->entrepreneurProfile);
        abort_unless((string) $section->business_plan_id === (string) $plan->getKey(), 404);
        if (! in_array($fact->key, self::KEYS, true) || $fact->value === null || mb_strlen(trim($reason)) < 15) {
            throw ValidationException::withMessages(['claim' => 'Choose a supported plan claim and record its value and confirmation reason.']);
        }
        $numeric = ! in_array($fact->key, [...self::TEXT_KEYS, ...self::BOOLEAN_KEYS], true);
        if (($numeric && ($fact->number() === null || $fact->number()->isNegative()))
            || ($fact->key === 'scope.required_horizon' && FactValidation::horizon($fact) === null)
            || (in_array($fact->key, self::BOOLEAN_KEYS, true) && ! is_bool($fact->value))
            // A runway stated on a basis PM-10 does not measure, or in any unit
            // but months, is an answer the rule can never read: it would report
            // the claim's definition missing while the advisor saw it recorded.
            // Refusing it at entry says so while they are still looking at it.
            || ($fact->key === 'runway.months' && (! in_array($fact->basis, self::RUNWAY_BASES, true) || $fact->unit !== 'months'))
            || ($fact->asOf !== null && ! FactValidation::date($fact->asOf))) {
            throw ValidationException::withMessages(['claim' => 'Use a valid non-negative amount, whole forecast period, scope choice, calendar date, and a runway stated in months on a basis the model measures.']);
        }

        return DB::transaction(function () use ($plan, $section, $actor, $fact, $expectedBodyHash, $reason): PlanClaimVersion {
            BusinessPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $section = PlanSection::query()->lockForUpdate()->findOrFail($section->getKey());
            $hash = CanonicalJson::hash((string) $section->body);
            if (! hash_equals($hash, $expectedBodyHash)) {
                throw ValidationException::withMessages(['claim' => 'The plan answer changed. Review its current wording before confirming this claim.']);
            }
            $reference = new SourceReference('plan_section', (string) $section->getKey(), (string) data_get($section->metadata, 'requirement_key', $section->key), $hash);
            $confirmed = new Fact($fact->key, $fact->value, $fact->entity, $fact->period, $fact->scenario, $fact->unit, $fact->basis, $fact->asOf, true, [$reference]);
            $claimKey = hash('sha256', $fact->key.'|'.$fact->entity.'|'.$fact->period.'|'.$fact->scenario.'|'.$section->getKey());
            $revision = (int) PlanClaimVersion::query()->where('business_plan_id', $plan->getKey())->where('claim_key', $claimKey)->max('revision') + 1;
            $record = PlanClaimVersion::query()->create([
                'business_plan_id' => $plan->getKey(), 'claim_key' => $claimKey, 'revision' => $revision, 'state' => 'confirmed',
                'source_hash' => $hash, 'fact' => json_decode(CanonicalJson::encode($confirmed), true, 512, JSON_THROW_ON_ERROR),
                'reason' => trim($reason), 'actor_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('entrepreneur.plan_claim_confirmed', subject: $plan, actor: $actor,
                after: ['claim_version_id' => $record->getKey(), 'field' => $fact->key, 'section_id' => $section->getKey(), 'source_hash' => $hash, 'reason' => trim($reason)]);

            return $record;
        }, 3);
    }
}
