<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanGateRun;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\ClaimVersionStore;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
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

        return Inertia::render('advisor/entrepreneurs/ReleaseGate', [
            'report' => $report->jsonSerialize(), 'configuration' => $report->configuration->jsonSerialize(), 'run_id' => $run->getKey(),
            'can_accept' => $actor->can(Permission::ENTREPRENEURS_ACCEPT_RELEASE_RISK->value),
            'can_configure' => $actor->can(Permission::ENTREPRENEURS_RELEASE_POLICY->value),
            'can_confirm' => Gate::forUser($actor)->allows('assess', $entrepreneurProfile),
            'sections' => $businessPlan->sections->map(fn (PlanSection $s): array => ['id' => (string) $s->getKey(), 'title' => $s->title, 'hash' => CanonicalJson::hash((string) $s->body)])->values()->all(),
            'urls' => ['review' => $url, 'decision' => $url.'/decisions', 'claim' => $url.'/claims', 'policy' => $url.'/policy',
                'workspace' => route('advisor.entrepreneurs.show', $entrepreneurProfile, absolute: false)],
        ]);
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
            'as_of' => ['nullable', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:15', 'max:2000']]);
        $section = $businessPlan->sections()->findOrFail($data['section_id']);
        $value = $data['key'] === 'plan.commitment_in_scope' ? filter_var($data['value'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : $data['value'];
        $fact = new Fact($data['key'], $value, $data['entity'], $data['period'], unit: $data['unit'] ?? null, basis: $data['basis'], asOf: $data['as_of'] ?? null);
        $claims->confirm($businessPlan, $section, $actor, $fact, $data['source_hash'], $data['reason']);

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
