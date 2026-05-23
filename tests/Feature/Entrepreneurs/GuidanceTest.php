<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Fake\FakeAiClient;
use App\Services\Entrepreneurs\Guidance;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Services\Entrepreneurs\PlanBuilder;
use App\Support\RequestContext;
use Database\Seeders\NzResourceSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(NzResourceSeeder::class);
        $this->app->bind(AiClient::class, FakeAiClient::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_guidance_is_evidence_cited_and_persists_predictive_score(): void
    {
        [$advisor, $section] = $this->section(body: 'Regional retail owners lose margin when demand signals, customer interviews, competitor channels, pricing, revenue, tax, legal duties, IP boundaries, goals, and milestones are not tied together in one plan.');

        $guidance = app(Guidance::class)->guide($section, $advisor);

        $sources = collect($guidance['attributions'])->pluck('source_reference');
        $this->assertTrue($sources->contains(fn (string $source): bool => str_starts_with($source, 'past_plan_patterns:')));
        $this->assertTrue($sources->contains(fn (string $source): bool => str_starts_with($source, 'nz_resource:')));
        $this->assertNotNull($section->refresh()->predictive_score);
        $this->assertSame($guidance['predictive_score']['score'], $section->predictive_score['score']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'entrepreneur.plan_guidance_generated',
            'subject_id' => $section->id,
        ]);
    }

    public function test_predictive_score_is_not_inflated_for_thin_or_unsupported_sections(): void
    {
        [$advisor, $section] = $this->section(
            email: 'thin-guidance-founder@example.test',
            body: 'We will grow fast.',
        );

        $guidance = app(Guidance::class)->guide($section, $advisor);

        $this->assertLessThan(60, $guidance['predictive_score']['score']);
        $this->assertSame('needs_work', $guidance['predictive_score']['band']);
        $this->assertTrue($guidance['predictive_score']['no_flattery']);
        $this->assertStringContainsString('not ready yet', $guidance['summary']);
        $this->assertStringNotContainsString('excellent', strtolower($guidance['summary']));
    }

    public function test_resources_are_recommended_by_industry_and_gap(): void
    {
        $resources = app(Guidance::class)->recommendResources('retail', 'startup', ['demand']);

        $this->assertTrue($resources->contains('title', 'Retail NZ Startup Guidance'));
        $this->assertFalse($resources->contains('title', 'Inland Revenue New Business Checklist'));
    }

    /**
     * @return array{0: User, 1: PlanSection}
     */
    private function section(string $email = 'guidance-founder@example.test', string $body = 'A useful section body.'): array
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
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Guidance Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Retail guidance concept.',
        ]);
        $validation = app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Retail operators need clearer planning before launch.',
            'target_customer' => 'Regional retail owners.',
            'solution' => 'A guided planning workflow.',
            'value_proposition' => 'Less wasted effort and clearer launch risks.',
            'demand_signal' => 'Five customer interviews are complete.',
            'revenue_model' => 'Monthly subscription and setup support.',
        ], $advisor);
        app(IdeaValidationService::class)->passAdvisorGate($validation, $advisor, 'Ready for guided planning.');
        $plan = app(PlanBuilder::class)->start($profile, $advisor);
        $section = app(PlanBuilder::class)->upsertSection(
            plan: $plan,
            phaseKey: 'market',
            key: 'market-demand',
            title: 'Market demand',
            body: $body,
            actor: $advisor,
        );

        return [$advisor, $section];
    }
}
