<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Models\AdvisoryReadinessSignal;
use App\Models\BusinessPlan;
use App\Models\Client;
use App\Models\ClientFunderRecord;
use App\Models\Document;
use App\Models\FeeCalculation;
use App\Models\PlanAssessment;
use App\Models\ServiceRatePackage;
use App\Models\AccountingInvoice;
use Illuminate\Database\Eloquent\Model;
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

    public function test_sensitive_model_registry_uses_non_empty_allow_lists(): void
    {
        /** @var array<class-string<Model>, string> $models */
        $models = config('production_quality.sensitive_models');

        foreach ($models as $model => $path) {
            $this->assertFileExists(base_path($path), $model.' must remain in the sensitive-model registry.');

            $instance = new $model;

            $this->assertNotSame([], $instance->getGuarded(), $model.' must not use broad unguarded assignment.');
            $this->assertNotSame([], $instance->getFillable(), $model.' must declare a non-empty allow-list.');
        }
    }

    public function test_legacy_broad_guarded_model_count_does_not_expand(): void
    {
        $broadGuardedModels = collect(File::allFiles(app_path('Models')))
            ->filter(fn ($file): bool => preg_match(
                '/(?:public|protected)\s+\$guarded\s*=\s*\[\s*\]\s*;/',
                (string) file_get_contents($file->getPathname()),
            ) === 1)
            ->count();

        $this->assertLessThanOrEqual(
            (int) config('production_quality.legacy_broad_guarded_model_ceiling'),
            $broadGuardedModels,
            'Do not add new models with protected $guarded = []; migrate touched models to explicit fillable fields instead.',
        );
    }

    public function test_financial_models_reject_unexpected_mass_assigned_fields(): void
    {
        foreach ([
            AccountingInvoice::class,
            FeeCalculation::class,
            ClientFunderRecord::class,
            ServiceRatePackage::class,
        ] as $model) {
            $instance = new $model;
            $instance->fill(['untrusted_financial_field' => 'must-not-persist']);

            $this->assertArrayNotHasKey(
                'untrusted_financial_field',
                $instance->getAttributes(),
                $model.' must reject unexpected request-backed attributes.',
            );
        }
    }
}
