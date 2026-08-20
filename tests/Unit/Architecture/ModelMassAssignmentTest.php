<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\Document;
use App\Models\PlanAssessment;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModelMassAssignmentTest extends TestCase
{
    public function test_critical_models_use_explicit_fillable_fields(): void
    {
        foreach ([Client::class, BusinessPlan::class, Document::class, PlanAssessment::class, AdvisoryReadinessSignal::class] as $model) {
            $instance = new $model;

            $this->assertNotSame([], $instance->getGuarded(), $model.' must not use broad unguarded assignment.');
            $this->assertNotSame([], $instance->getFillable(), $model.' must declare explicit fillable fields.');
        }
    }

    public function test_legacy_broad_guarded_model_count_does_not_expand(): void
    {
        $broadGuardedModels = collect(File::allFiles(app_path('Models')))
            ->filter(fn ($file): bool => str_contains((string) file_get_contents($file->getPathname()), 'protected $guarded = [];'))
            ->count();

        $this->assertLessThanOrEqual(
            192,
            $broadGuardedModels,
            'Do not add new models with protected $guarded = []; migrate touched models to explicit fillable fields instead.',
        );
    }
}
