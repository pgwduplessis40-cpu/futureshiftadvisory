<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

final class ModelRelationshipContractTest extends TestCase
{
    public function test_every_declared_eloquent_relationship_resolves_to_a_relation_object(): void
    {
        $relationships = 0;

        foreach (File::allFiles(app_path('Models')) as $file) {
            $relativePath = $file->getRelativePathname();
            $class = 'App\\Models\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativePath);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            /** @var Model $model */
            $model = $reflection->newInstance();

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || $method->getDeclaringClass()->getName() !== $class || $method->getNumberOfRequiredParameters() > 0) {
                    continue;
                }

                $returnType = $method->getReturnType();
                if (! $returnType instanceof ReflectionNamedType || $returnType->isBuiltin() || ! is_a($returnType->getName(), Relation::class, true)) {
                    continue;
                }

                $relation = $model->{$method->getName()}();

                $this->assertInstanceOf(Relation::class, $relation, "{$class}::{$method->getName()} must return an Eloquent relation.");
                $relationships++;
            }
        }

        $this->assertGreaterThan(300, $relationships, 'The model relationship contract should cover the platform relationship map.');
    }
}
