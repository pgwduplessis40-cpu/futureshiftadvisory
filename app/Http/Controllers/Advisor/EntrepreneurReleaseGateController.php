<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanClaimVersion;
use App\Models\PlanGateRun;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\ClaimVersionStore;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\FactCodec;
use App\Services\Entrepreneurs\ReleaseGate\ReleaseGate;
use App\Services\Entrepreneurs\ReleaseGate\RiskDecisionStore;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RulePolicyStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class EntrepreneurReleaseGateController extends Controller
{
    public function show(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, ReleaseGate $gate): Response
    {
        $actor = $this->authorise($request, $entrepreneurProfile, $businessPlan);
        $data = $request->validate(['document' => ['sometimes', 'in:plan,budget,brief']]);
        $report = $gate->evaluate($businessPlan, $data['document'] ?? 'plan');
        $run = $gate->record($businessPlan, $report, $actor);
        $url = route('advisor.entrepreneurs.plans.release-gate.show', [$entrepreneurProfile, $businessPlan], absolute: false);

        $sections = $businessPlan->sections->map(fn (PlanSection $s): array => ['id' => (string) $s->getKey(), 'title' => $s->title, 'hash' => CanonicalJson::hash((string) $s->body)])->values()->all();

        return Inertia::render('advisor/entrepreneurs/ReleaseGate', [
            'report' => $report->jsonSerialize(), 'configuration' => $report->configuration->jsonSerialize(), 'run_id' => $run->getKey(),
            'can_accept' => $actor->can(Permission::ENTREPRENEURS_ACCEPT_RELEASE_RISK->value),
            'can_configure' => $actor->can(Permission::ENTREPRENEURS_RELEASE_POLICY->value),
            'can_confirm' => Gate::forUser($actor)->allows('assess', $entrepreneurProfile),
            'sections' => $sections,
            'claims' => $this->confirmedClaims($businessPlan, $sections),
            'urls' => ['review' => $url, 'decision' => $url.'/decisions', 'claim' => $url.'/claims', 'policy' => $url.'/policy',
                'workspace' => route('advisor.entrepreneurs.show', $entrepreneurProfile, absolute: false)],
        ]);
    }

    /**
     * What the gate is holding, and whether it still counts.
     *
     * A confirmed claim binds to the wording it was confirmed against, so
     * PlanFacts drops one whose plan answer has since been edited. Nothing
     * said so: the advisor saw the claim saved, and then saw the rule report
     * its evidence missing, with no way to connect the two. The page now
     * states which claims the gate reads and which it has stopped reading.
     *
     * Read for display only, and deliberately not from the captured source
     * records, which carry no actor, reason or timestamp. Those are hashed
     * into the source version that risk decisions are taken against, so
     * widening them to show a name here would invalidate every stored
     * decision.
     *
     * @param  list<array{id:string,title:string,hash:string}>  $sections
     * @return list<array<string,mixed>>
     */
    private function confirmedClaims(BusinessPlan $businessPlan, array $sections): array
    {
        $hashes = array_column($sections, 'hash', 'id');
        $titles = array_column($sections, 'title', 'id');

        return PlanClaimVersion::query()->where('business_plan_id', $businessPlan->getKey())
            ->with('actor')->orderBy('claim_key')->orderBy('revision')->get()
            // Ordered by revision and keyed last-wins, which is how PlanFacts
            // picks the record the gate reads. A superseded revision is not a
            // second claim.
            ->keyBy('claim_key')
            ->map(function (PlanClaimVersion $claim) use ($hashes, $titles): array {
                /** @var array{key:string,value:string|int|bool|null,entity?:string,period?:string,scenario?:string,unit?:string|null,basis?:string|null,as_of?:string|null,confirmed?:bool,sources?:list<array{kind:string,id:string,location:string,version:string}>} $data */
                $data = $claim->fact;
                $fact = FactCodec::decode($data);
                $section = null;
                foreach ($fact->sources as $reference) {
                    if ($reference->kind === 'plan_section') {
                        $section = $reference;
                    }
                }

                return [
                    'key' => $fact->key, 'value' => $fact->value, 'entity' => $fact->entity, 'period' => $fact->period,
                    'unit' => $fact->unit, 'basis' => $fact->basis, 'as_of' => $fact->asOf, 'revision' => $claim->revision,
                    'section_title' => $section === null ? null : ($titles[$section->id] ?? null),
                    'applies' => $section !== null && hash_equals($hashes[$section->id] ?? '', $section->version),
                    'confirmed_by' => $claim->actor?->name, 'confirmed_at' => $claim->created_at?->toIso8601String(),
                    'reason' => $claim->reason,
                ];
            })
            // Read in the order they were entered rather than by claim_key,
            // which is a hash and would scatter a group or put a runway's
            // inclusions above the figure they qualify.
            ->sortBy(fn (array $claim): array => [
                $claim['entity'], $claim['period'],
                ClaimVersionStore::groupLeadFor($claim['key']) ?? $claim['key'],
                ClaimVersionStore::groupLeadFor($claim['key']) === $claim['key'] ? 0 : 1,
                $claim['key'],
            ])->values()->all();
    }

    public function decision(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, RiskDecisionStore $decisions): RedirectResponse
    {
        $actor = $this->authorise($request, $entrepreneurProfile, $businessPlan);
        $data = $request->validate(['run_id' => ['required', 'uuid'], 'rule_id' => ['required', 'string', 'max:8'],
            'subcheck_id' => ['required', 'string', 'max:180'], 'reason' => ['required', 'string', 'min:15', 'max:2000']]);
        $run = PlanGateRun::query()->where('business_plan_id', $businessPlan->getKey())->findOrFail($data['run_id']);
        $decisions->accept($businessPlan, $run, $actor, $data['rule_id'], $data['subcheck_id'], $data['reason']);

        return back()->with('status', 'release-risk-recorded');
    }

    public function claim(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, ClaimVersionStore $claims): RedirectResponse
    {
        $actor = $this->authorise($request, $entrepreneurProfile, $businessPlan);
        $data = $request->validate(['section_id' => ['required', 'uuid'], 'source_hash' => ['required', 'string', 'size:64'],
            'key' => ['required', 'in:'.implode(',', ClaimVersionStore::KEYS)], 'value' => ['required', 'string', 'max:2000'],
            'entity' => ['required', 'string', 'max:100'], 'period' => ['required', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:30'], 'basis' => ['required', 'string', 'max:100'],
            'as_of' => ['nullable', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:15', 'max:2000'],
            'companions' => ['sometimes', 'array'], 'companions.*' => ['required', 'string', 'max:2000']]);
        $section = $businessPlan->sections()->findOrFail($data['section_id']);
        $read = fn (string $key, string $value): string|bool|null => in_array($key, ClaimVersionStore::BOOLEAN_KEYS, true)
            ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : $value;
        $fact = new Fact($data['key'], $read($data['key'], $data['value']), $data['entity'], $data['period'], unit: $data['unit'] ?? null, basis: $data['basis'], asOf: $data['as_of'] ?? null);
        $group = ClaimVersionStore::GROUPS[$data['key']] ?? null;
        if ($group === null) {
            $claims->confirm($businessPlan, $section, $actor, $fact, $data['source_hash'], $data['reason']);

            return back()->with('status', 'plan-claim-confirmed');
        }
        // The companions share the lead's coordinate by construction rather
        // than by the advisor entering it the same way four times, and their
        // units come from the group so the rule cannot be handed a start cash
        // stated in weeks.
        $facts = [$fact];
        foreach ($group as $key => $unit) {
            // An unanswered companion stays unanswered. An empty string reads
            // as false to filter_var, so supplying one for a missing answer
            // would record "not counted" as a confirmed statement nobody made,
            // and PM-10 would hold the model against it.
            $given = $data['companions'][$key] ?? null;
            $facts[] = new Fact($key, $given === null ? null : $read($key, $given), $data['entity'], $data['period'],
                unit: $unit, basis: $data['basis'], asOf: $data['as_of'] ?? null);
        }
        $claims->confirmGroup($businessPlan, $section, $actor, $facts, $data['source_hash'], $data['reason']);

        return back()->with('status', 'plan-claim-confirmed');
    }

    public function policy(Request $request, EntrepreneurProfile $entrepreneurProfile, BusinessPlan $businessPlan, RulePolicyStore $policies): RedirectResponse
    {
        $actor = $this->authorise($request, $entrepreneurProfile, $businessPlan);
        $data = $request->validate(['configuration_hash' => ['required', 'string', 'size:64'], 'reason' => ['required', 'string', 'min:15', 'max:2000'],
            'thresholds' => ['present', 'array'], 'thresholds.*' => ['required', 'string', 'max:30'],
            'severities' => ['present', 'array'], 'severities.*' => ['required', 'in:block,review,info,autofix'],
            'messages' => ['present', 'array'], 'messages.*' => ['required', 'string', 'min:5', 'max:1000']]);
        try {
            $configuration = new RuleConfiguration($data['thresholds'], $data['severities'], $data['messages']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['policy' => $e->getMessage()]);
        }
        $policies->save($actor, $configuration, $data['configuration_hash'], $data['reason']);

        return back()->with('status', 'release-policy-version-saved');
    }

    private function authorise(Request $request, EntrepreneurProfile $profile, BusinessPlan $plan): User
    {
        Gate::authorize('reviewBrief', $profile);
        abort_unless($plan->source_type === BusinessPlan::SOURCE_ENTREPRENEUR && (string) $plan->entrepreneur_profile_id === (string) $profile->getKey(), 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
