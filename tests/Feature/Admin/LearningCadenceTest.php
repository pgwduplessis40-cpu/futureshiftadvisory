<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\LearningLayerRun;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\User;
use App\Services\Learning\LayerCadenceRegistry;
use App\Services\Learning\LayerCadenceRunner;
use App\Services\Learning\LearningMonitorDashboard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class LearningCadenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_contains_thirty_seven_layers_with_expected_cadences(): void
    {
        $registry = app(LayerCadenceRegistry::class);
        $definitions = $registry->definitions();

        $this->assertCount(37, $definitions);
        $this->assertSame(LayerCadenceRegistry::CADENCE_DAILY, $registry->definition(3)['cadence']);
        $this->assertSame(LayerCadenceRegistry::CADENCE_DAILY, $registry->definition(12)['cadence']);
        $this->assertSame(LayerCadenceRegistry::CADENCE_MONTHLY, $registry->definition(15)['cadence']);
        $this->assertSame(LayerCadenceRegistry::CADENCE_WEEKLY, $registry->definition(LayerCadenceRegistry::LAYER_TEMPLATE_SUGGESTIONS)['cadence']);
        $this->assertSame('templates:suggest', $registry->definition(LayerCadenceRegistry::LAYER_TEMPLATE_SUGGESTIONS)['command']);
        $this->assertSame('npo:funder-registry-learning', $registry->definition(LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES)['command']);
        $this->assertSame(
            'layer_34_admin_approval_required',
            $registry->definition(LayerCadenceRegistry::LAYER_NPO_FUNDER_DATABASE_UPDATES)['metadata']['governance_gate'],
        );
        $this->assertSame(
            10,
            $registry->definition(LayerCadenceRegistry::LAYER_NPO_COST_PER_BENEFICIARY_BENCHMARKS)['metadata']['min_sample_guard']['programmes_per_type'],
        );
        $this->assertTrue($registry->definition(LayerCadenceRegistry::LAYER_NPO_FUNDING_CONCENTRATION_THRESHOLDS)['metadata']['requires_full_data_justification']);
        $this->assertTrue($definitions->every(fn (array $definition): bool => $definition['governed_candidates_only'] === true));
    }

    public function test_cadence_runner_records_due_runs_for_each_registered_layer(): void
    {
        Carbon::setTestNow('2026-05-23 03:00:00');

        $runs = app(LayerCadenceRunner::class)->recordDueRuns(now());

        $this->assertCount(37, $runs);
        $this->assertSame(37, LearningLayerRun::query()->count());
        $this->assertDatabaseHas('learning_layer_runs', [
            'layer_id' => 1,
            'status' => LearningLayerRun::STATUS_COMPLETED,
        ]);
        $this->assertTrue($runs->every(
            fn (LearningLayerRun $run): bool => $run->window['governed_candidates_only'] === true
                && $run->window['automatic_application'] === false
                && $run->candidates_created === 0,
        ));
    }

    public function test_runner_respects_cadence_windows_unless_layer_is_forced(): void
    {
        Carbon::setTestNow('2026-05-23 03:00:00');
        app(LayerCadenceRunner::class)->recordDueRuns(now(), [29]);

        $noneDue = app(LayerCadenceRunner::class)->recordDueRuns(now()->addMinutes(30));
        $forced = app(LayerCadenceRunner::class)->recordDueRuns(now()->addMinutes(30), [29]);

        $this->assertCount(36, $noneDue);
        $this->assertCount(1, $forced);
        $this->assertSame(38, LearningLayerRun::query()->where('layer_id', 29)->orWhere('layer_id', '<>', 29)->count());
    }

    public function test_monitor_dashboard_shows_queue_and_history(): void
    {
        $run = LearningLayerRun::query()->create([
            'layer_id' => 16,
            'ran_at' => now(),
            'candidates_created' => 2,
            'window' => ['governed_candidates_only' => true, 'automatic_application' => false],
            'status' => LearningLayerRun::STATUS_COMPLETED,
        ]);
        LearningUpdate::query()->create([
            'layer_id' => 16,
            'source' => ['type' => 'dashboard_test'],
            'summary' => 'Dashboard queue candidate',
            'proposed_change' => ['action' => 'review', 'automatic_application' => false],
            'impact_scope' => ['surface' => 'test'],
            'clients_affected' => 1,
            'magnitude' => 'low',
            'confidence' => 0.7,
            'evidence' => ['run_id' => $run->id],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $dashboard = app(LearningMonitorDashboard::class)->dashboard();

        $this->assertSame(37, $dashboard['summary']['registered_layers']);
        $this->assertSame(1, $dashboard['summary']['queued_candidates']);
        $this->assertSame(1, $dashboard['summary']['recent_runs']);
        $this->assertSame(16, $dashboard['recent_runs'][0]['layer_id']);
    }

    public function test_admin_learning_dashboard_includes_queue_and_history_payload(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        LearningLayerRun::query()->create([
            'layer_id' => 12,
            'ran_at' => now(),
            'candidates_created' => 0,
            'window' => ['governed_candidates_only' => true, 'automatic_application' => false],
            'status' => LearningLayerRun::STATUS_COMPLETED,
        ]);

        $this->actingAsMfa($admin)
            ->get(route('admin.learning-updates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/learning/Index')
                ->where('monitor.summary.registered_layers', 37)
                ->where('monitor.recent_runs.0.layer_id', 12),
            );
    }

    public function test_admin_can_rerun_due_layers_and_due_approved_updates_are_implemented(): void
    {
        Carbon::setTestNow('2026-05-23 03:00:00');
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->superAdmin()->withTwoFactor()->create();
        $admin->assignRole(User::TYPE_SUPER_ADMIN);

        LearningUpdate::query()->create([
            'layer_id' => 29,
            'source' => ['type' => 'rerun_test'],
            'summary' => 'Approved rerun candidate',
            'proposed_change' => ['action' => 'refresh_threshold', 'automatic_application' => false],
            'impact_scope' => ['surface' => 'dashboard'],
            'clients_affected' => 3,
            'magnitude' => 'low',
            'confidence' => 0.9,
            'evidence' => ['samples' => 12],
            'status' => LearningUpdate::STATUS_APPROVED,
            'effective_date' => now()->subDay(),
            'review_due_at' => now()->addDays(29),
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.learning-updates.rerun'), ['layer_ids' => [29]])
            ->assertRedirect(route('admin.learning-updates.index', absolute: false));

        $this->assertSame(1, LearningLayerRun::query()->where('layer_id', 29)->count());
        $this->assertSame(1, LearningUpdateImplementation::query()->count());
        $this->assertDatabaseHas('learning_updates', [
            'summary' => 'Approved rerun candidate',
            'status' => LearningUpdate::STATUS_IMPLEMENTED,
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'learning_layer.approved_updates_implemented']);
    }
}
