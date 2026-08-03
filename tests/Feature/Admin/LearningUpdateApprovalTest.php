<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateDecision;
use App\Models\LearningUpdateImplementation;
use App\Models\User;
use App\Services\Learning\ApprovalFlow;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class LearningUpdateApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_queue_cards_surface_governance_summary_fields(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $candidate = $this->candidate();

        $this->actingAsMfa($admin)
            ->get(route('admin.learning-updates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/learning/Index')
                ->has('cards', 1)
                ->where('cards.0.id', $candidate->id)
                ->where('cards.0.summary', 'Adjust prompt calibration')
                ->where('cards.0.source.type', 'analysis_feedback')
                ->where('cards.0.proposed_change.action', 'revise_prompt')
                ->where('cards.0.impact_scope.modules.0', 'financial')
                ->where('cards.0.clients_affected', 12)
                ->where('cards.0.magnitude', 'medium')
                ->where('cards.0.confidence', 0.82)
                ->where('cards.0.evidence.samples', 9)
                ->where('cards.0.plain_english.what_we_learnt', 'The analysis feedback signal found a possible prompt update for financial analysis.')
                ->where('cards.0.plain_english.why_it_matters', 'This could affect financial analysis or advice, so assumptions, calculations, evidence, and wording need checking before use.')
                ->where('cards.0.plain_english.signals', [])
                ->where('cards.0.capability_profile', fn (Collection $profile): bool => collect($profile->get('capabilities', []))->contains('Finance')
                    && collect($profile->get('capabilities', []))->contains('decision-toolkit')
                    && collect($profile->get('ai_surfaces', []))->contains('analysis_modules')
                    && data_get($profile->all(), 'governance.automatic_application') === false
                    && data_get($profile->all(), 'advice_quality.methodology_review_required') === true
                    && data_get($profile->all(), 'advice_quality.calculation_validation_required') === true
                    && data_get($profile->all(), 'advice_quality.truthfulness_review_required') === true),
            );
    }

    public function test_learning_queue_translates_bias_signals_to_plain_english(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();

        $this->candidate([
            'source' => [
                'type' => 'bias_detector',
                'prompt_id' => 'entrepreneur_plan_score_criterion',
            ],
            'summary' => 'Bias detector heuristic flagged AI output for governed review.',
            'proposed_change' => [
                'action' => 'review_prompt_or_output_policy',
                'signals' => [[
                    'term' => 'exceptional',
                    'type' => 'praise_language',
                    'reason' => 'Phase 1 heuristic flagged praise-oriented wording for advisor review.',
                    'severity' => 'review',
                ]],
            ],
            'evidence' => [
                'signals' => [[
                    'term' => 'exceptional',
                    'type' => 'praise_language',
                    'reason' => 'Phase 1 heuristic flagged praise-oriented wording for advisor review.',
                    'severity' => 'review',
                ]],
            ],
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.learning-updates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/learning/Index')
                ->where('cards.0.plain_english.what_we_learnt', 'The bias detector found possible praise language: exceptional. It was seen in the entrepreneur plan score criterion prompt or output.')
                ->where('cards.0.plain_english.why_it_matters', 'Praise-heavy or biased wording can make analysis sound more certain or favourable than the evidence supports, so it needs human review before it influences advice.')
                ->where('cards.0.plain_english.review_decision', 'Check whether the wording or rule should be changed, kept with a clear reason, deferred for more evidence, or rejected.')
                ->where('cards.0.plain_english.signals.0', 'Praise language signal for exceptional: Phase 1 heuristic flagged praise-oriented wording for advisor review. Severity: review.'),
            );
    }

    public function test_approval_records_decision_and_schedules_notice_and_review(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $candidate = $this->candidate();

        $this->actingAsMfa($admin)
            ->patch(route('admin.learning-updates.decide', $candidate), [
                'decision' => LearningUpdateDecision::DECISION_APPROVE,
                'reason' => 'Looks sound.',
            ])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $candidate->refresh();

        $this->assertSame(LearningUpdate::STATUS_APPROVED, $candidate->status);
        $this->assertTrue($candidate->effective_date->equalTo(now()->addDays(7)));
        $this->assertTrue($candidate->pre_implementation_notice_at->equalTo(now()));
        $this->assertTrue($candidate->review_due_at->equalTo($candidate->effective_date->copy()->addDays(30)));
        $this->assertDatabaseHas('learning_update_decisions', [
            'learning_update_id' => $candidate->id,
            'decision' => LearningUpdateDecision::DECISION_APPROVE,
            'reason' => 'Looks sound.',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'learning_update.decided']);
        $this->assertSame(0, LearningUpdateImplementation::query()->count());
    }

    public function test_approval_with_modified_date_uses_requested_date_and_review_window(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $candidate = $this->candidate();
        $effective = now()->addDays(12)->setHour(9);

        $this->actingAsMfa($admin)
            ->patch(route('admin.learning-updates.decide', $candidate), [
                'decision' => LearningUpdateDecision::DECISION_APPROVE_MODIFIED_DATE,
                'effective_date' => $effective->toIso8601String(),
            ])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $candidate->refresh();

        $this->assertSame(LearningUpdate::STATUS_APPROVED, $candidate->status);
        $this->assertTrue($candidate->effective_date->equalTo($effective));
        $this->assertTrue($candidate->review_due_at->equalTo($effective->copy()->addDays(30)));
        $this->assertDatabaseHas('learning_update_decisions', [
            'learning_update_id' => $candidate->id,
            'decision' => LearningUpdateDecision::DECISION_APPROVE_MODIFIED_DATE,
        ]);
    }

    public function test_defer_and_reject_paths_record_audited_decisions_without_schedule(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $deferred = $this->candidate(['summary' => 'Defer me']);
        $rejected = $this->candidate(['summary' => 'Reject me']);

        $this->actingAsMfa($admin)
            ->patch(route('admin.learning-updates.decide', $deferred), [
                'decision' => LearningUpdateDecision::DECISION_DEFER,
                'reason' => 'Needs more evidence.',
            ])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $this->actingAsMfa($admin)
            ->patch(route('admin.learning-updates.decide', $rejected), [
                'decision' => LearningUpdateDecision::DECISION_REJECT,
                'reason' => 'Not appropriate.',
            ])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $deferred->refresh();
        $rejected->refresh();

        $this->assertSame(LearningUpdate::STATUS_DEFERRED, $deferred->status);
        $this->assertNull($deferred->effective_date);
        $this->assertNull($deferred->review_due_at);
        $this->assertSame(LearningUpdate::STATUS_REJECTED, $rejected->status);
        $this->assertNull($rejected->pre_implementation_notice_at);
        $this->assertDatabaseHas('learning_update_decisions', [
            'learning_update_id' => $deferred->id,
            'decision' => LearningUpdateDecision::DECISION_DEFER,
        ]);
        $this->assertDatabaseHas('learning_update_decisions', [
            'learning_update_id' => $rejected->id,
            'decision' => LearningUpdateDecision::DECISION_REJECT,
        ]);
        $this->assertSame(2, LearningUpdateDecision::query()->count());
        $this->assertSame(2, AuditEvent::query()->where('action', 'learning_update.decided')->count());
    }

    public function test_implementation_guard_blocks_unapproved_and_future_effective_updates(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        $candidate = $this->candidate();
        $flow = app(ApprovalFlow::class);

        try {
            $flow->assertImplementationAllowed($candidate);
            $this->fail('Detected learning update should not be implementable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('requires explicit approval', $exception->getMessage());
        }

        $candidate->forceFill([
            'status' => LearningUpdate::STATUS_APPROVED,
            'effective_date' => now()->addDay(),
        ])->save();

        try {
            $flow->assertImplementationAllowed($candidate->refresh());
            $this->fail('Future effective learning update should not be implementable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('blocked until the approved effective date', $exception->getMessage());
        }

        $candidate->forceFill(['effective_date' => now()->subMinute()])->save();

        $flow->assertImplementationAllowed($candidate->refresh());
        $this->assertTrue(true);
    }

    public function test_due_approved_updates_are_implemented_and_receive_impact_review(): void
    {
        Carbon::setTestNow('2026-05-23 10:00:00');
        $this->seed(RoleSeeder::class);
        $admin = $this->superAdmin();
        $candidate = $this->candidate([
            'status' => LearningUpdate::STATUS_APPROVED,
            'effective_date' => now()->subMinute(),
            'review_due_at' => now()->subMinute(),
            'proposed_change' => [
                'action' => 'revise_prompt',
                'automatic_application' => false,
                'target' => ['type' => 'prompt', 'id' => 'analysis.financial'],
            ],
        ]);

        $implemented = app(ApprovalFlow::class)->implementDue(now(), $admin);

        $this->assertCount(1, $implemented);
        $implementation = $implemented->first();
        $this->assertSame(LearningUpdate::STATUS_IMPLEMENTED, $candidate->refresh()->status);
        $this->assertTrue($implementation->review_due->equalTo(now()->subMinute()));
        $this->assertSame('prompt', $implementation->target_type);
        $this->assertSame('analysis.financial', $implementation->target_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'learning_update.implemented']);

        $this->actingAsMfa($admin)
            ->get(route('admin.learning-updates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('impact_reviews', 1)
                ->where('impact_reviews.0.id', $implementation->id)
                ->where('impact_reviews.0.summary', 'Adjust prompt calibration')
                ->where('impact_reviews.0.suggested_metrics.impact_outcome', 'neutral')
                ->where('impact_reviews.0.suggested_metrics.affected_surface', 'financial')
                ->where('impact_reviews.0.suggested_metrics.rollback_required', false));

        $this->actingAsMfa($admin)
            ->patch(route('admin.learning-update-implementations.review', $implementation), [
                'review_outcome' => 'No client impact exceptions after 30-day review.',
                'impact_outcome' => 'improved',
                'affected_surface' => 'prompt_calibration',
                'metric_name' => 'advisor_acceptance_rate',
                'before_metric' => 0.62,
                'after_metric' => 0.74,
                'sample_size' => 18,
                'rollback_required' => false,
            ])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $implementation->refresh();

        $this->assertSame('No client impact exceptions after 30-day review.', $implementation->review_outcome);
        $this->assertSame('improved', data_get($implementation->review_metrics, 'impact_outcome'));
        $this->assertSame('prompt_calibration', data_get($implementation->review_metrics, 'affected_surface'));
        $this->assertSame('advisor_acceptance_rate', data_get($implementation->review_metrics, 'metric_name'));
        $this->assertEquals(0.62, data_get($implementation->review_metrics, 'before_metric'));
        $this->assertEquals(0.74, data_get($implementation->review_metrics, 'after_metric'));
        $this->assertSame(18, data_get($implementation->review_metrics, 'sample_size'));
        $this->assertFalse(data_get($implementation->review_metrics, 'rollback_required'));
        $this->assertDatabaseHas('audit_events', ['action' => 'learning_update.impact_reviewed']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function candidate(array $overrides = []): LearningUpdate
    {
        return LearningUpdate::query()->create(array_merge([
            'layer_id' => 4,
            'source' => [
                'type' => 'analysis_feedback',
                'prompt_id' => 'analysis.financial',
            ],
            'summary' => 'Adjust prompt calibration',
            'proposed_change' => [
                'action' => 'revise_prompt',
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'modules' => ['financial'],
                'tenant_scope' => 'global',
            ],
            'clients_affected' => 12,
            'magnitude' => 'medium',
            'confidence' => 0.82,
            'evidence' => [
                'samples' => 9,
                'finding_ids' => ['finding-1'],
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ], $overrides));
    }
}
