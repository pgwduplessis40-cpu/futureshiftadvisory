<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Services\Entrepreneurs\PlanRequirements;
use App\Services\Pdf\PdfRenderer;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use RuntimeException;
use Tests\Concerns\MakesIdeaReviewEligible;
use Tests\TestCase;

final class PlanBuilderTest extends TestCase
{
    use MakesIdeaReviewEligible, RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_entrepreneur_plan_rls_app';

    private bool $connectionBypassesRls = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON business_plans, plan_phases, plan_sections, entrepreneur_profiles FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_plan_builder_stays_locked_until_advisor_gate_passes(): void
    {
        [$advisor, $profile] = $this->profile();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('locked until an advisor passes');

        app(PlanBuilder::class)->start($profile, $advisor);
    }

    public function test_plan_builder_creates_ordered_five_phase_plan_after_gate(): void
    {
        [$advisor, $profile] = $this->profile('ordered-plan-founder@example.test');
        $this->openIdeaGate($profile, $advisor);

        $plan = app(PlanBuilder::class)->start($profile, $advisor);

        $this->assertSame(BusinessPlan::SOURCE_ENTREPRENEUR, $plan->source_type);
        $this->assertSame(BusinessPlan::STATUS_BUILDING, $plan->status);
        $this->assertSame($profile->id, $plan->entrepreneur_profile_id);
        $this->assertSame([
            'foundation',
            'market',
            'strategy',
            'legal_operations',
            'financial',
        ], $plan->phases->sortBy('position')->pluck('key')->values()->all());
        $this->assertSame(['foundation', 'strategy'], $plan->phases->firstWhere('key', 'financial')?->depends_on);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_started',
            'subject_id' => $plan->id,
        ]);
    }

    public function test_entrepreneur_plan_requirements_include_viable_systems_question(): void
    {
        $legalOperations = PlanRequirements::definitions()['legal_operations']['requirements'];

        $this->assertContains(
            [
                'key' => 'systems-software-processes',
                'title' => 'What systems/software/processes will be required to run this business if viable?',
                'description' => 'List the software, operating systems, workflows, responsibilities, suppliers, controls, and implementation gaps needed to run the business if the concept proves viable.',
            ],
            $legalOperations,
        );
    }

    public function test_entrepreneur_plan_workspace_exposes_viable_systems_question(): void
    {
        [, $profile] = $this->profile('systems-question-founder@example.test');
        $entrepreneur = $profile->user()->firstOrFail();

        $this->actingAsMfa($entrepreneur)
            ->get(route('portal.entrepreneur.plan.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('planTemplate.3.title', 'Legal & Operations')
                ->where('planTemplate.3.requirements.2.key', 'systems-software-processes')
                ->where('planTemplate.3.requirements.2.title', 'What systems/software/processes will be required to run this business if viable?')
                ->where('planTemplate.3.requirements.2.description', 'List the software, operating systems, workflows, responsibilities, suppliers, controls, and implementation gaps needed to run the business if the concept proves viable.')
            );
    }

    public function test_jump_ahead_dependency_warning_is_stored_on_section_metadata(): void
    {
        [$advisor, $profile] = $this->profile('jump-plan-founder@example.test');
        $this->openIdeaGate($profile, $advisor);
        $plan = app(PlanBuilder::class)->start($profile, $advisor);

        $section = app(PlanBuilder::class)->upsertSection(
            plan: $plan,
            phaseKey: 'financial',
            key: 'early-financial-plan',
            title: 'Early financial plan',
            body: 'A draft financial model before the strategy phase is complete.',
            actor: $advisor,
        );

        $this->assertInstanceOf(PlanSection::class, $section);
        $this->assertSame(PlanSection::STATUS_COMPLETE, $section->completeness_status);
        $this->assertTrue((bool) data_get($section->metadata, 'dependency_warning.blocked'));
        $this->assertSame(['strategy'], data_get($section->metadata, 'dependency_warning.missing_dependencies'));
        $this->assertSame(5, $plan->refresh()->current_phase);
    }

    public function test_plan_section_autosave_stores_short_text_as_draft(): void
    {
        [$advisor, $profile] = $this->profile('autosave-plan-founder@example.test');
        $this->openIdeaGate($profile, $advisor);
        $plan = app(PlanBuilder::class)->start($profile, $advisor);
        $entrepreneur = $profile->user()->firstOrFail();

        $this->actingAsMfa($entrepreneur)
            ->postJson(route('portal.entrepreneur.plan.sections.store'), [
                '_autosave' => true,
                'phase_key' => 'market',
                'requirement_key' => 'industry-context',
                'title' => 'Industry and customer demand',
                'body' => 'I am still writing this section.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'entrepreneur-plan-section-autosaved')
            ->assertJsonPath('section.completeness_status', PlanSection::STATUS_DRAFT);

        $section = PlanSection::query()
            ->where('business_plan_id', $plan->getKey())
            ->where('key', 'founder-market-industry-context')
            ->firstOrFail();

        $this->assertSame('I am still writing this section.', $section->body);
        $this->assertSame(PlanSection::STATUS_DRAFT, $section->completeness_status);

        $this->actingAsMfa($entrepreneur)
            ->post(route('portal.entrepreneur.plan.sections.store'), [
                'phase_key' => 'market',
                'requirement_key' => 'industry-context',
                'title' => 'Industry and customer demand',
                'body' => str_repeat('Customer demand evidence, tested pricing, competitors, and local market context are described clearly. ', 2),
            ])
            ->assertRedirect(route('portal.entrepreneur.plan.show'));

        $this->assertSame(PlanSection::STATUS_COMPLETE, $section->refresh()->completeness_status);
    }

    public function test_business_plan_preview_renders_markdown_formatting_safely(): void
    {
        [$advisor, $profile] = $this->profile('markdown-preview-founder@example.test');
        $this->openIdeaGate($profile, $advisor);
        $plan = app(PlanBuilder::class)->start($profile, $advisor);
        $renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return "%PDF-1.4\n".strip_tags($html);
            }
        };

        $this->app->instance(PdfRenderer::class, $renderer);

        app(PlanBuilder::class)->upsertSection(
            plan: $plan,
            phaseKey: 'market',
            key: 'industry-context',
            title: 'Market demand',
            body: "Launch risks include **cash timing** and *supplier dependency*.\n- Validate weekend demand\n- Confirm lead times\n<script>alert('x')</script>",
            actor: $advisor,
            metadata: ['requirement_key' => 'industry-context'],
        );

        $this->actingAsMfa($profile->user()->firstOrFail())
            ->get(route('portal.entrepreneur.plan.preview'))
            ->assertOk();

        $this->assertStringContainsString('<strong>cash timing</strong>', $renderer->html);
        $this->assertStringContainsString('<em>supplier dependency</em>', $renderer->html);
        $this->assertStringContainsString('<li>Validate weekend demand</li>', $renderer->html);
        $this->assertStringNotContainsString('<script>', $renderer->html);
    }

    public function test_business_plan_preview_returns_a_fallback_pdf_when_the_browser_renderer_fails(): void
    {
        [$advisor, $profile] = $this->profile('fallback-preview-founder@example.test');
        $this->openIdeaGate($profile, $advisor);
        app(PlanBuilder::class)->start($profile, $advisor);

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html): string
            {
                throw new RuntimeException('Chromium is unavailable.');
            }
        });

        $response = $this->actingAsMfa($profile->user()->firstOrFail())
            ->get(route('portal.entrepreneur.plan.preview'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringContainsString('Business Plan - Plan Founder', $response->getContent());
        $this->assertStringNotContainsString('Browser-formatted PDF generation', $response->getContent());
    }

    public function test_entrepreneur_plan_tables_are_profile_scoped_by_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Entrepreneur plan RLS assertions require Postgres.');
        }

        [$advisorA, $profileA] = $this->profile('plan-rls-a@example.test');
        [, $profileB] = $this->profile('plan-rls-b@example.test');
        $this->openIdeaGate($profileA, $advisorA);
        $this->openIdeaGate($profileB, $advisorA);
        $planA = app(PlanBuilder::class)->start($profileA, $advisorA);
        $planB = app(PlanBuilder::class)->start($profileB, $advisorA);

        app(RequestContext::class)->apply('advisor', [], (string) $advisorA->id);
        $visiblePlanIds = $this->withRlsRole(fn (): array => DB::table('business_plans')->pluck('id')->all());
        $visiblePhasePlanIds = $this->withRlsRole(fn (): array => DB::table('plan_phases')->pluck('business_plan_id')->unique()->values()->all());
        $visibleSectionPlanIds = $this->withRlsRole(fn (): array => DB::table('plan_sections')->pluck('business_plan_id')->unique()->values()->all());

        foreach ([$visiblePlanIds, $visiblePhasePlanIds, $visibleSectionPlanIds] as $visibleIds) {
            $this->assertContains($planA->id, $visibleIds);
            $this->assertNotContains($planB->id, $visibleIds);
        }
    }

    /**
     * @return array{0: User, 1: EntrepreneurProfile}
     */
    private function profile(string $email = 'plan-founder@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);

        return [$advisor, EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Plan Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'A practical plan-builder concept.',
        ])];
    }

    private function openIdeaGate(EntrepreneurProfile $profile, User $advisor): void
    {
        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Regional operators need clearer workflow accountability before growth.',
            'target_customer' => 'Owner-operated service businesses with small teams.',
            'solution' => 'A five-phase planning and accountability system.',
            'value_proposition' => 'The owner can focus effort before committing expensive resources.',
            'demand_signal' => 'Four discovery calls and two pilot letters are recorded.',
            'revenue_model' => 'Monthly advisory subscription with milestone-based support.',
        ], $advisor);

        app(IdeaValidationService::class)->passAdvisorGate($this->completedIdeaReview($validation), $advisor, 'Ready to start planning.');
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON business_plans, plan_phases, plan_sections, entrepreneur_profiles TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);

        try {
            return $callback();
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
