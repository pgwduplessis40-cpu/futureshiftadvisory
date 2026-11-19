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
        'runway.months', 'runway.start_cash', 'runway.includes_receipts', 'runway.includes_owner_pay',
        'plan.ramp', 'plan.start_period'];

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
     * Claims whose value names a calendar month rather than measuring anything.
     *
     * PM-05 compares the plan's first forecast month with the model's before it
     * compares any figure in the schedule, because two schedules read from
     * different first months describe different ramps even when every number
     * in them matches.
     */
    public const MONTH_KEYS = ['plan.start_period'];

    /**
     * Claims that state nothing a basis could qualify.
     *
     * Opening cash names the account it was read from and a runway names the
     * cost basis it was measured on, because either figure means something
     * different read another way. A ramp and a start month have no such second
     * reading, and the model publishes none for them, so requiring one would
     * only block a claim the advisor can legitimately record.
     */
    public const BASIS_FREE_KEYS = ['plan.ramp', 'plan.start_period'];

    /**
     * Claims that may legitimately be negative.
     *
     * A ramp is month-on-month change, so a stream the plan expects to decline
     * states a negative one. Every other claim here is an amount, and a
     * negative amount is a data-entry error rather than a statement.
     */
    public const SIGNED_KEYS = ['plan.ramp'];

    /**
     * The bases a runway can be claimed on, named by the producer that states
     * the model's own figures, so a claim cannot be recorded against a basis
     * the rule has nothing to compare it with.
     */
    public const RUNWAY_BASES = [RunwayFacts::FORECAST_CASH, RunwayFacts::NO_SALES];

    /**
     * Claims a rule reads together, at one coordinate, as the lead claim and
     * the companions that give it meaning, each mapped to the unit it is
     * stated in.
     *
     * PM-10 reads a runway's inclusions at the same entity, period and
     * scenario as the figure, so four separate confirmations had to agree on
     * a coordinate nothing made them share. Recorded one month apart, or
     * against the business and then against a named offer, the rule finds the
     * figure and none of its companions and reports the claim's definition
     * missing - which reads to the advisor as evidence absent, for evidence
     * they entered. A grouped claim is confirmed as one act instead.
     *
     * The units are fixed here rather than asked for, because the rule reads
     * the companions by key and has no use for a start cash stated in weeks.
     */
    public const GROUPS = [
        'runway.months' => [
            'runway.start_cash' => 'NZD',
            'runway.includes_receipts' => null,
            'runway.includes_owner_pay' => null,
        ],
    ];

    public function __construct(private AuditWriter $audit) {}

    /** The lead claim of the group this key belongs to, or null if it stands alone. */
    public static function groupLeadFor(string $key): ?string
    {
        foreach (self::GROUPS as $lead => $companions) {
            if ($key === $lead || array_key_exists($key, $companions)) {
                return $lead;
            }
        }

        return null;
    }

    public function confirm(BusinessPlan $plan, PlanSection $section, User $actor, Fact $fact, string $expectedBodyHash, string $reason): PlanClaimVersion
    {
        // A grouped claim recorded on its own is a claim the rule must ignore,
        // so it is refused here rather than stored and silently unread.
        if (self::groupLeadFor($fact->key) !== null) {
            throw ValidationException::withMessages(['claim' => 'Confirm this claim together with the statements that give it meaning.']);
        }

        return $this->write($plan, $section, $actor, [$fact], $expectedBodyHash, $reason)[0];
    }

    /**
     * Confirm a lead claim and its companions as one act, at one coordinate.
     *
     * @param  list<Fact>  $facts
     * @return list<PlanClaimVersion>
     */
    public function confirmGroup(BusinessPlan $plan, PlanSection $section, User $actor, array $facts, string $expectedBodyHash, string $reason): array
    {
        $lead = self::groupLeadFor($facts[0]->key ?? '');
        $expected = $lead === null ? [] : [$lead, ...array_keys(self::GROUPS[$lead])];
        $given = array_map(fn (Fact $fact): string => $fact->key, $facts);
        sort($expected);
        sort($given);
        if ($lead === null || $expected !== $given) {
            throw ValidationException::withMessages(['claim' => 'Record the whole statement: the figure and each answer that says what it counts.']);
        }
        // One coordinate, or the rule reads the figure and none of its
        // companions - which is the failure the group exists to prevent.
        foreach ($facts as $fact) {
            if ($fact->entity !== $facts[0]->entity || $fact->period !== $facts[0]->period || $fact->scenario !== $facts[0]->scenario) {
                throw ValidationException::withMessages(['claim' => 'Every statement in this claim describes the same subject and period.']);
            }
        }

        return $this->write($plan, $section, $actor, $facts, $expectedBodyHash, $reason);
    }

    /**
     * @param  list<Fact>  $facts
     * @return list<PlanClaimVersion>
     */
    private function write(BusinessPlan $plan, PlanSection $section, User $actor, array $facts, string $expectedBodyHash, string $reason): array
    {
        Gate::forUser($actor)->authorize('assess', $plan->entrepreneurProfile);
        abort_unless((string) $section->business_plan_id === (string) $plan->getKey(), 404);
        foreach ($facts as $fact) {
            $this->validate($fact, $reason);
        }

        return DB::transaction(function () use ($plan, $section, $actor, $facts, $expectedBodyHash, $reason): array {
            BusinessPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $section = PlanSection::query()->lockForUpdate()->findOrFail($section->getKey());
            $hash = CanonicalJson::hash((string) $section->body);
            if (! hash_equals($hash, $expectedBodyHash)) {
                throw ValidationException::withMessages(['claim' => 'The plan answer changed. Review its current wording before confirming this claim.']);
            }
            $reference = new SourceReference('plan_section', (string) $section->getKey(), (string) data_get($section->metadata, 'requirement_key', $section->key), $hash);
            $records = [];
            foreach ($facts as $fact) {
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
                $records[] = $record;
            }

            return $records;
        }, 3);
    }

    private function validate(Fact $fact, string $reason): void
    {
        if (! in_array($fact->key, self::KEYS, true) || $fact->value === null || mb_strlen(trim($reason)) < 15) {
            throw ValidationException::withMessages(['claim' => 'Choose a supported plan claim and record its value and confirmation reason.']);
        }
        $numeric = ! in_array($fact->key, [...self::TEXT_KEYS, ...self::BOOLEAN_KEYS, ...self::MONTH_KEYS], true);
        $signed = in_array($fact->key, self::SIGNED_KEYS, true);
        if (($numeric && ($fact->number() === null || (! $signed && $fact->number()->isNegative())))
            || ($fact->key === 'scope.required_horizon' && FactValidation::horizon($fact) === null)
            || (in_array($fact->key, self::BOOLEAN_KEYS, true) && ! is_bool($fact->value))
            || (in_array($fact->key, self::MONTH_KEYS, true) && ! FactValidation::month($fact->value))
            // A ramp is stated for one stream in one month, in the unit the
            // model publishes its own ramp in. Recorded against the default
            // entity and period, or in any other unit, it is a claim PM-05
            // can never read: the rule would report the model ramp missing
            // while the advisor saw their own figure recorded.
            || ($fact->key === 'plan.ramp' && ($fact->unit !== 'percent' || $fact->entity === 'business'
                || ! FactValidation::period($fact->period)))
            // A runway stated on a basis PM-10 does not measure, or in any unit
            // but months, is an answer the rule can never read: it would report
            // the claim's definition missing while the advisor saw it recorded.
            // Refusing it at entry says so while they are still looking at it.
            || ($fact->key === 'runway.months' && (! in_array($fact->basis, self::RUNWAY_BASES, true) || $fact->unit !== 'months'))
            || ($fact->asOf !== null && ! FactValidation::date($fact->asOf))) {
            throw ValidationException::withMessages(['claim' => 'Use a valid amount, whole forecast period, scope choice, calendar date, a named stream and forecast month for a ramp stated in percent, a start period naming a calendar month, and a runway stated in months on a basis the model measures. Only a ramp may be negative.']);
        }
    }
}
