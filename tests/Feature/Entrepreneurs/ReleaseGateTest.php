<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\Permission;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanClaimVersion;
use App\Models\PlanGateRun;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Entrepreneurs\BudgetCalculator;
use App\Services\Entrepreneurs\BudgetPackBuilder;
use App\Services\Entrepreneurs\ReleaseGate\AssessmentGate;
use App\Services\Entrepreneurs\ReleaseGate\BudgetDraft;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\ClaimVersionStore;
use App\Services\Entrepreneurs\ReleaseGate\DraftDelivery;
use App\Services\Entrepreneurs\ReleaseGate\Fact;
use App\Services\Entrepreneurs\ReleaseGate\GeneratedNarrative;
use App\Services\Entrepreneurs\ReleaseGate\LegacyGateAdapter;
use App\Services\Entrepreneurs\ReleaseGate\PdfDraftRenderer;
use App\Services\Entrepreneurs\ReleaseGate\ReleaseGate;
use App\Services\Entrepreneurs\ReleaseGate\RenderedDraft;
use App\Services\Entrepreneurs\ReleaseGate\RiskDecisionStore;
use App\Services\Entrepreneurs\ReleaseGate\RuleConfiguration;
use App\Services\Entrepreneurs\ReleaseGate\RulePolicyStore;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ReleaseGateTest extends TestCase
{
    use RefreshDatabase;

    private BusinessPlan $plan;

    private PlanSection $section;

    private User $principal;

    private User $advisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $this->principal = User::factory()->withTwoFactor()->create(['user_type' => 'super_admin', 'primary_role' => 'super_admin']);
        $this->principal->assignRole('super_admin');
        $this->advisor = User::factory()->withTwoFactor()->create(['user_type' => 'advisor', 'primary_role' => 'advisor']);
        $this->advisor->assignRole('advisor');
        $owner = User::factory()->create(['user_type' => 'entrepreneur', 'primary_role' => 'entrepreneur']);
        $profile = EntrepreneurProfile::query()->create(['name' => 'Synthetic Founder', 'email' => $owner->email, 'user_id' => $owner->id, 'assigned_advisor_id' => $this->advisor->id, 'stage' => 'idea_validation']);
        $this->plan = BusinessPlan::query()->create(['entrepreneur_profile_id' => $profile->id, 'title' => 'Synthetic plan', 'source_type' => 'entrepreneur']);
        $phase = PlanPhase::query()->create(['business_plan_id' => $this->plan->id, 'key' => 'financial', 'title' => 'Financial', 'position' => 1]);
        $this->section = PlanSection::query()->create(['business_plan_id' => $this->plan->id, 'plan_phase_id' => $phase->id, 'key' => 'financial-assumptions', 'title' => 'Financial assumptions', 'body' => 'Opening cash is $1000.', 'source_type' => 'entrepreneur', 'metadata' => ['requirement_key' => 'financial-assumptions']]);
    }

    public function test_run_is_reproducible_and_append_only_even_through_sql(): void
    {
        $gate = app(ReleaseGate::class);
        $report = $gate->evaluate($this->plan);
        $run = $gate->record($this->plan, $report, $this->principal);
        self::assertCount(17, $report->deferred());
        self::assertSame($report->context->hash(), $run->getAttribute('context_hash'));
        self::assertFalse($run->getAttribute('result_payload')['can_certify']);
        self::assertSame($report->context->hash(), $gate->evaluate($this->plan)->context->hash());
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.release_gate_evaluated']);
        $this->expectException(QueryException::class);
        DB::table('plan_gate_runs')->where('id', $run->id)->update(['document_kind' => 'plan']);
    }

    public function test_principal_acceptance_retains_unknown_result_and_survives_derived_summary_only(): void
    {
        $gate = app(ReleaseGate::class);
        $report = $gate->evaluate($this->plan);
        $run = $gate->record($this->plan, $report, $this->principal);
        app(RiskDecisionStore::class)->accept($this->plan, $run, $this->principal, 'PM-07', 'target', 'The margin target will be reviewed at the next advisory session.');
        $find = fn () => collect($gate->evaluate($this->plan)->findings())->firstWhere('rule_id', 'PM-07');
        self::assertTrue($find()['accepted']);
        $summary = $this->section->replicate(['id', 'created_at', 'updated_at']);
        $summary->forceFill(['key' => 'executive-summary', 'title' => 'Executive summary', 'body' => 'Generated narrative.', 'metadata' => ['requirement_key' => 'executive-summary', 'executive_summary' => ['generated' => true]]])->save();
        self::assertTrue($find()['accepted']);
        self::assertSame('cannot_verify', $find()['subcheck']->status->value);
        $this->section->forceFill(['body' => 'Opening cash is $1200.'])->save();
        self::assertFalse($find()['accepted']);
    }

    public function test_integrity_cannot_be_accepted_by_principal(): void
    {
        $gate = app(ReleaseGate::class);
        $run = $gate->record($this->plan, $gate->evaluate($this->plan), $this->principal);
        $this->expectException(ValidationException::class);
        app(RiskDecisionStore::class)->accept($this->plan, $run, $this->principal, 'MI-01', 'horizon', 'Accept this despite missing cash integrity information.');
    }

    public function test_an_ordinary_assigned_advisor_has_no_risk_acceptance_authority(): void
    {
        self::assertFalse($this->advisor->can(Permission::ENTREPRENEURS_ACCEPT_RELEASE_RISK->value));
        $gate = app(ReleaseGate::class);
        $run = $gate->record($this->plan, $gate->evaluate($this->plan), $this->advisor);
        $this->expectException(HttpException::class);
        app(RiskDecisionStore::class)->accept($this->plan, $run, $this->advisor, 'PM-07', 'target', 'An ordinary advisor cannot accept this business risk.');
    }

    public function test_policy_version_invalidates_a_previously_displayed_run(): void
    {
        $gate = app(ReleaseGate::class);
        $report = $gate->evaluate($this->plan);
        $run = $gate->record($this->plan, $report, $this->principal);
        app(RulePolicyStore::class)->save($this->principal, new RuleConfiguration(['material_cost' => '500']), $report->configuration->hash(), 'Use a lower cost materiality threshold for review.');
        $this->expectException(ValidationException::class);
        app(RiskDecisionStore::class)->accept($this->plan, $run, $this->principal, 'PM-07', 'target', 'This decision was made against a superseded policy.');
    }

    public function test_claim_versions_keep_originals_and_reject_changed_answers(): void
    {
        $store = app(ClaimVersionStore::class);
        $hash = CanonicalJson::hash($this->section->body);
        $fact = new Fact('plan.opening_cash', '1000', unit: 'NZD', basis: 'unrestricted bank account', asOf: '2026-11-01');
        $first = $store->confirm($this->plan, $this->section, $this->advisor, $fact, $hash, 'Confirmed against the dated bank balance.');
        $second = $store->confirm($this->plan, $this->section, $this->advisor, $fact, $hash, 'Confirmed again with the same account scope.');
        self::assertSame(1, $first->revision);
        self::assertSame(2, $second->revision);
        self::assertSame(2, PlanClaimVersion::query()->count());
        $facts = app(ReleaseGate::class)->evaluate($this->plan)->context->facts->all('plan.opening_cash');
        self::assertCount(1, $facts);
        self::assertTrue($facts[0]->confirmed);
        $this->section->forceFill(['body' => 'Opening cash is $2000.'])->save();
        $this->expectException(ValidationException::class);
        $store->confirm($this->plan, $this->section, $this->advisor, $fact, $hash, 'This confirmation must not bind to the changed answer.');
    }

    public function test_review_route_enforces_profile_ownership_and_preserves_results(): void
    {
        $url = route('advisor.entrepreneurs.plans.release-gate.show', [$this->plan->entrepreneurProfile, $this->plan]);
        $this->actingAsMfa($this->advisor)->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('advisor/entrepreneurs/ReleaseGate')->where('report.can_certify', false)->has('report.results', 37)->where('can_accept', false));
        app(RequestContext::class)->apply('system', []);
        $other = User::factory()->withTwoFactor()->create(['user_type' => 'advisor', 'primary_role' => 'advisor']);
        $other->assignRole('advisor');
        self::assertContains($this->actingAsMfa($other)->get($url)->status(), [403, 404]);
    }

    public function test_fallback_is_audited_as_unverified_and_source_edits_during_render_are_rejected(): void
    {
        $delivery = app(DraftDelivery::class);
        self::assertSame('%PDF-draft', $delivery->render($this->plan, 'brief', fn () => new RenderedDraft('%PDF-draft', 'fallback')));
        $run = PlanGateRun::query()->latest('created_at')->firstOrFail();
        self::assertFalse($run->result_payload['can_certify']);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.draft_document_delivered']);
        $this->expectException(HttpException::class);
        $delivery->render($this->plan, 'plan', function (): RenderedDraft {
            $this->section->forceFill(['body' => 'Changed while rendering.'])->save();

            return new RenderedDraft('%PDF-stale', 'primary', '<p>Old answer</p>');
        });
    }

    public function test_http_claim_and_policy_forms_enforce_authority_current_inputs_and_integrity_floors(): void
    {
        $url = route('advisor.entrepreneurs.plans.release-gate.show', [$this->plan->entrepreneurProfile, $this->plan]);
        $policy = ['configuration_hash' => (new RuleConfiguration)->hash(), 'thresholds' => [], 'severities' => [], 'messages' => [], 'reason' => 'Reviewed the current materiality policy with the Principal.'];
        $this->actingAsMfa($this->advisor)->post($url.'/policy', $policy)->assertForbidden();
        $this->actingAsMfa($this->principal)->post($url.'/policy', [...$policy, 'severities' => ['MI-01' => 'review']])->assertSessionHasErrors('policy');
        $this->post($url.'/policy', [...$policy, 'thresholds' => ['material_cost' => '500']])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url.'/policy', $policy)->assertSessionHasErrors('policy');
        $claim = ['section_id' => $this->section->id, 'source_hash' => CanonicalJson::hash($this->section->body), 'key' => 'plan.opening_cash', 'value' => '1000', 'entity' => 'business', 'period' => 'current', 'unit' => 'NZD', 'basis' => 'bank-A:unrestricted', 'as_of' => '2026-11-01', 'reason' => 'Checked the dated unrestricted opening balance.'];
        $this->actingAsMfa($this->advisor)->post($url.'/claims', $claim)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url.'/claims', [...$claim, 'value' => '-5'])->assertSessionHasErrors('claim');
        $this->assertDatabaseCount('plan_claim_versions', 1);
        $this->assertDatabaseCount('plan_rule_config_versions', 1);
    }

    public function test_http_risk_decision_cannot_accept_integrity_or_use_a_stale_run(): void
    {
        $url = route('advisor.entrepreneurs.plans.release-gate.show', [$this->plan->entrepreneurProfile, $this->plan]);
        $gate = app(ReleaseGate::class);
        $run = $gate->record($this->plan, $gate->evaluate($this->plan, 'plan'), $this->principal);
        $data = ['run_id' => $run->id, 'rule_id' => 'PM-07', 'subcheck_id' => 'target', 'reason' => 'The margin target will be reviewed with the founder.'];
        $this->actingAsMfa($this->advisor)->post($url.'/decisions', $data)->assertForbidden();
        $this->actingAsMfa($this->principal)->post($url.'/decisions', [...$data, 'rule_id' => 'MI-01', 'subcheck_id' => 'horizon'])->assertSessionHasErrors('decision');
        $this->post($url.'/decisions', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->section->forceFill(['body' => 'Opening cash is $1500.'])->save();
        $this->post($url.'/decisions', $data)->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('plan_rule_decisions', 1);
    }

    public function test_primary_and_historical_drafts_record_their_own_evidence(): void
    {
        $delivery = app(DraftDelivery::class);
        $delivery->render($this->plan, 'plan', fn () => new RenderedDraft('%PDF-primary', 'primary', '<h1>Draft plan</h1><p>1 days</p>'));
        $primary = PlanGateRun::query()->orderByDesc('id')->firstOrFail();
        $dh = collect($primary->result_payload['results'])->first(fn (array $r): bool => $r['rule']['id'] === 'DH-05');
        self::assertSame('fail', $dh['result']);
        $delivery->render($this->plan, 'plan', fn () => new RenderedDraft('%PDF-historical', 'primary', '<p>Old plan</p>'), '{"revision":"historical"}');
        $historical = PlanGateRun::query()->orderByDesc('id')->firstOrFail();
        self::assertSame('internal', $historical->audience);
        self::assertNotSame($primary->source_hash, $historical->source_hash);
        self::assertFalse($historical->result_payload['can_certify']);
    }

    public function test_database_policy_does_not_let_an_assigned_advisor_write_global_policy(): void
    {
        app(RequestContext::class)->apply('advisor', [], (string) $this->advisor->id);
        self::assertFalse((bool) DB::selectOne("SELECT fsa_release_permission('entrepreneurs.release_policy') AS allowed")->allowed);
        $this->expectException(QueryException::class);
        $this->withNonBypassRole(fn () => DB::table('plan_rule_config_versions')->insert(['id' => (string) Str::uuid(), 'configuration_hash' => (new RuleConfiguration)->hash(), 'configuration' => '{}', 'reason' => 'Attempted direct database policy change.', 'actor_user_id' => $this->advisor->id, 'created_at' => now()]));
    }

    public function test_database_scope_hides_another_advisors_gate_records(): void
    {
        $gate = app(ReleaseGate::class);
        $gate->record($this->plan, $gate->evaluate($this->plan), $this->principal);
        app(RequestContext::class)->apply('advisor', [], '999999999');
        $this->withNonBypassRole(fn () => self::assertSame(0, DB::table('plan_gate_runs')->count()));
    }

    private function withNonBypassRole(callable $assertion): void
    {
        $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
        if (! $role->rolsuper && ! $role->rolbypassrls) {
            $assertion();

            return;
        }
        DB::unprepared("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'fsa_release_gate_rls_test') THEN CREATE ROLE fsa_release_gate_rls_test NOLOGIN NOSUPERUSER NOBYPASSRLS; END IF; END $$;
            GRANT USAGE ON SCHEMA public TO fsa_release_gate_rls_test;
            GRANT SELECT ON ALL TABLES IN SCHEMA public TO fsa_release_gate_rls_test;
            GRANT INSERT ON plan_rule_config_versions TO fsa_release_gate_rls_test;");
        try {
            DB::transaction(function () use ($assertion): void {
                DB::statement('SET LOCAL ROLE fsa_release_gate_rls_test');
                $assertion();
            });
        } finally {
            DB::statement('RESET ROLE');
        }
    }

    public function test_generated_text_audit_preserves_saved_answer_and_manual_summary_invalidates_risk(): void
    {
        $gate = app(ReleaseGate::class);
        $run = $gate->record($this->plan, $gate->evaluate($this->plan), $this->principal);
        app(RiskDecisionStore::class)->accept($this->plan, $run, $this->principal, 'PM-07', 'target', 'Review the margin target during the next advisory meeting.');
        $summary = $this->section->replicate(['id', 'created_at', 'updated_at']);
        $summary->forceFill(['key' => 'executive-summary', 'title' => 'Executive summary', 'body' => 'Generated — summary.', 'metadata' => ['executive_summary' => ['generated' => true]]])->save();
        self::assertSame('Generated - summary.', app(GeneratedNarrative::class)->body($summary));
        self::assertSame('Generated — summary.', $summary->refresh()->body);
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.generated_text_autofixed']);
        $summary->forceFill(['metadata' => [], 'body' => 'A manually revised financial summary.'])->save();
        self::assertFalse(collect($gate->evaluate($this->plan)->findings())->firstWhere('rule_id', 'PM-07')['accepted']);
    }

    public function test_assessment_and_guide_use_the_same_gate_without_legacy_numeric_competitors(): void
    {
        $record = app(AssessmentGate::class)->capture($this->plan, $this->advisor);
        self::assertSame('draft', $record['status']);
        self::assertTrue(PlanGateRun::query()->whereKey($record['run_id'])->exists());
        $report = app(ReleaseGate::class)->evaluate($this->plan);
        $issues = (new LegacyGateAdapter)->guide($report, [
            ['key' => 'opening_cash_balance', 'title' => 'Old mismatch', 'detail' => 'Assumed comparable balances.', 'action' => 'Edit cash.', 'requirement' => 'financial-assumptions', 'focus' => 'funding-sources'],
            ['key' => 'revenue_scope', 'title' => 'Offer scope', 'detail' => 'Check the offer scope.', 'action' => 'Review scope.', 'requirement' => 'financial-assumptions', 'focus' => 'revenue-forecast'],
        ]);
        self::assertNotContains('opening_cash_balance', array_column($issues, 'key'));
        self::assertContains('revenue_scope', array_column($issues, 'key'));
        self::assertTrue(collect($issues)->contains(fn (array $i): bool => str_starts_with($i['key'], 'PM-03:')));
    }

    public function test_budget_primary_and_fallback_downloads_remain_draft_and_external_issue_is_blocked(): void
    {
        $computed = app(BudgetCalculator::class)->compute([], [], [], [], null, forecastYears: 1);
        EntrepreneurBudget::query()->create(['business_plan_id' => $this->plan->id, 'forecast_years' => 1, 'computed' => $computed, 'status' => 'partial']);
        foreach ([false, true] as $fallback) {
            $renderer = new class($fallback) implements PdfRenderer
            {
                public function __construct(private bool $fallback) {}

                public function render(string $html): string
                {
                    if ($this->fallback) {
                        throw new \RuntimeException('Synthetic primary renderer unavailable.');
                    }
                    self::verify($html);

                    return '%PDF-primary-draft';
                }

                private static function verify(string $html): void
                {
                    Assert::assertStringContainsString('INTERNAL DRAFT', $html);
                    Assert::assertStringContainsString('Proposed external request', $html);
                    Assert::assertStringContainsString('Not recorded', $html);
                }
            };
            $this->app->instance(PdfRenderer::class, $renderer);
            $bytes = app(BudgetDraft::class)->pdf($this->plan->entrepreneurProfile, $this->plan);
            self::assertStringStartsWith('%PDF', $bytes);
            if (! $fallback) {
                self::assertSame('%PDF-primary-draft', $bytes);
            }
            $rendered = app(PdfDraftRenderer::class)->render('<p>INTERNAL DRAFT; Draft - release checks outstanding; Proposed external request: Not recorded</p>', fn () => '%PDF-fallback');
            self::assertSame($fallback ? 'fallback' : 'primary', $rendered->renderer);
            self::assertSame($fallback ? null : '<p>INTERNAL DRAFT; Draft - release checks outstanding; Proposed external request: Not recorded</p>', $rendered->candidateHtml);
        }
        self::assertFalse(app(BudgetPackBuilder::class)->payload($this->plan->entrepreneurProfile, $this->plan)['funding_decision']['external_issue_ready']);
        $this->assertDatabaseCount('plan_gate_runs', 2);
        $this->expectException(HttpException::class);
        app(ReleaseGate::class)->assertExternalRelease($this->plan, 'budget', $this->principal);
    }
}
