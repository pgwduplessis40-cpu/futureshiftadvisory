<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Models\LearningLayerRun;
use App\Models\LearningLayerState;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\RatingCriterion;
use App\Models\RatingFramework;
use App\Services\Learning\ActiveLayerEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ActiveLayerEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_engine_syncs_and_runs_all_layers_as_candidates_only(): void
    {
        config()->set('learning.active_learning', true);
        config()->set('learning.require_approval', true);
        Carbon::setTestNow('2026-05-25 03:00:00');

        $engine = app(ActiveLayerEngine::class);

        $states = $engine->activateAll(now());
        $runs = $engine->runDue(now());

        $this->assertCount(37, $states);
        $this->assertCount(37, $runs);
        $this->assertSame(37, LearningLayerState::query()->where('active', true)->count());
        $this->assertSame(37, LearningLayerRun::query()->where('candidates_created', 1)->count());
        $this->assertSame(37, LearningUpdate::query()->where('status', LearningUpdate::STATUS_DETECTED)->count());
        $this->assertSame(0, LearningUpdateImplementation::query()->count());
        $this->assertTrue(LearningUpdate::query()->get()->every(
            fn (LearningUpdate $update): bool => ($update->proposed_change['automatic_application'] ?? true) === false
                && ($update->proposed_change['requires_approval'] ?? false) === true,
        ));
    }

    public function test_active_engine_is_feature_gated(): void
    {
        config()->set('learning.active_learning', false);

        $engine = app(ActiveLayerEngine::class);
        $engine->activateAll(now());

        $this->assertCount(0, $engine->runDue(now()));
        $this->assertSame(0, LearningLayerRun::query()->count());
        $this->assertSame(0, LearningUpdate::query()->count());
    }

    public function test_candidates_do_not_change_runtime_rating_configuration(): void
    {
        config()->set('learning.active_learning', true);
        Carbon::setTestNow('2026-05-25 03:00:00');

        $framework = RatingFramework::query()->create([
            'version' => 102,
            'status' => RatingFramework::STATUS_PUBLISHED,
            'industry_variant' => null,
            'production_ready' => true,
            'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
            'published_at' => now(),
        ]);
        $criterion = RatingCriterion::query()->create([
            'rating_framework_id' => $framework->id,
            'number' => 1,
            'name' => 'Type of business',
            'weight' => 9.5,
            'descriptors' => ['strong' => 'Clear, validated model.'],
            'industry_variants' => ['software' => ['weight' => 10]],
            'is_placeholder' => false,
        ]);

        app(ActiveLayerEngine::class)->activateAll(now());
        app(ActiveLayerEngine::class)->runDue(now());

        $criterion->refresh();

        $this->assertSame(9.5, $criterion->weight);
        $this->assertSame(['strong' => 'Clear, validated model.'], $criterion->descriptors);
        $this->assertSame(['software' => ['weight' => 10]], $criterion->industry_variants);
        $this->assertSame(0, LearningUpdateImplementation::query()->count());
    }
}
