<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\LearningUpdate;
use App\Models\RatingCriterion;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Support\Methodology\ProvidesMethodology;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RatingFrameworkManager implements ProvidesMethodology
{
    public const LEARNING_LAYER_ID = 18;

    public static function methodologyIds(): array
    {
        return ['entrepreneur.rating_framework'];
    }

    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function published(?string $industryVariant = null): RatingFramework
    {
        return RatingFramework::query()
            ->with('criteria')
            ->where('status', RatingFramework::STATUS_PUBLISHED)
            ->where('industry_variant', $industryVariant)
            ->latest('version')
            ->firstOrFail();
    }

    /**
     * @param  array<int, array{number:int,weight?:float,descriptors?:array<string, string>,industry_variants?:array<string, mixed>,is_placeholder?:bool}>  $criteriaUpdates
     */
    public function revise(RatingFramework $framework, array $criteriaUpdates, User $actor): RatingFramework
    {
        return DB::transaction(function () use ($framework, $criteriaUpdates, $actor): RatingFramework {
            $framework->loadMissing('criteria');
            $next = RatingFramework::query()->create([
                'version' => ((int) RatingFramework::query()
                    ->where('industry_variant', $framework->industry_variant)
                    ->max('version')) + 1,
                'status' => RatingFramework::STATUS_DRAFT,
                'industry_variant' => $framework->industry_variant,
                'production_ready' => false,
                'grade_bands' => $framework->grade_bands ?: RatingFramework::DEFAULT_GRADE_BANDS,
                'supersedes_framework_id' => $framework->getKey(),
                'created_by_user_id' => $actor->getKey(),
            ]);
            $updatesByNumber = collect($criteriaUpdates)->keyBy('number');

            foreach ($framework->criteria as $criterion) {
                /** @var RatingCriterion $criterion */
                $update = $updatesByNumber->get($criterion->number, []);
                $next->criteria()->create([
                    'number' => $criterion->number,
                    'name' => $criterion->name,
                    'weight' => (float) ($update['weight'] ?? $criterion->weight),
                    'descriptors' => $update['descriptors'] ?? $criterion->descriptors,
                    'industry_variants' => $update['industry_variants'] ?? $criterion->industry_variants ?? [],
                    'is_placeholder' => (bool) ($update['is_placeholder'] ?? $criterion->is_placeholder),
                ]);
            }

            $this->audit->record('entrepreneur.rating_framework_revised', subject: $next, actor: $actor, after: [
                'supersedes_framework_id' => $framework->getKey(),
                'version' => $next->version,
                'criteria_changed' => count($criteriaUpdates),
            ]);

            return $next->refresh()->load('criteria');
        });
    }

    public function publish(RatingFramework $framework, User $actor): RatingFramework
    {
        $framework->forceFill([
            'status' => RatingFramework::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record('entrepreneur.rating_framework_published', subject: $framework, actor: $actor, after: [
            'version' => $framework->version,
            'production_ready' => $framework->production_ready,
            'readiness' => $framework->readinessStatus(),
        ]);

        return $framework->refresh()->load('criteria');
    }

    /**
     * @param  array<int, array{number:int,weight:float,descriptors:array<string, string>,industry_variants?:array<string, mixed>}>  $criteriaValues
     */
    public function confirmFoundingValues(RatingFramework $framework, array $criteriaValues, User $actor): RatingFramework
    {
        $this->assertCompleteFoundingValues($criteriaValues);

        return DB::transaction(function () use ($framework, $criteriaValues, $actor): RatingFramework {
            $draft = $this->revise(
                framework: $framework,
                criteriaUpdates: array_map(static fn (array $criterion): array => [
                    ...$criterion,
                    'is_placeholder' => false,
                ], $criteriaValues),
                actor: $actor,
            );
            $draft->forceFill([
                'production_ready' => true,
            ])->save();

            return $this->publish($draft, $actor);
        });
    }

    public function gradeBand(float $percentage): string
    {
        return (new RatingFramework([
            'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
        ]))->gradeFor($percentage);
    }

    /**
     * @param  array<int, array{number:int,weight:float,descriptors:array<string, string>,industry_variants?:array<string, mixed>}>  $criteriaValues
     */
    private function assertCompleteFoundingValues(array $criteriaValues): void
    {
        $numbers = collect($criteriaValues)->pluck('number')->sort()->values()->all();
        if ($numbers !== array_keys(RatingFramework::FOUNDING_CRITERIA)) {
            throw new InvalidArgumentException('Owner-entered framework values must cover all 11 founding criteria.');
        }

        $weightTotal = round((float) collect($criteriaValues)->sum('weight'), 3);
        if (abs($weightTotal - 100.0) > 0.01) {
            throw new InvalidArgumentException('Owner-entered framework weights must total 100.');
        }

        foreach ($criteriaValues as $criterion) {
            $descriptors = $criterion['descriptors'] ?? [];
            foreach (array_keys(RatingFramework::DEFAULT_GRADE_BANDS) as $band) {
                if (trim((string) ($descriptors[$band] ?? '')) === '') {
                    throw new InvalidArgumentException("Missing {$band} descriptor for criterion {$criterion['number']}.");
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $proposedChange
     */
    public function queueGovernedChange(RatingFramework $framework, array $proposedChange, User $actor): LearningUpdate
    {
        $update = LearningUpdate::query()->create([
            'layer_id' => self::LEARNING_LAYER_ID,
            'source' => [
                'type' => 'entrepreneur_rating_framework',
                'rating_framework_id' => $framework->getKey(),
            ],
            'summary' => 'Entrepreneur rating framework change requires owner approval.',
            'proposed_change' => [
                ...$proposedChange,
                'automatic_application' => false,
            ],
            'impact_scope' => [
                'module' => 'entrepreneur_assessment',
                'framework_version' => $framework->version,
            ],
            'clients_affected' => 0,
            'magnitude' => 'medium',
            'confidence' => 0.7000,
            'evidence' => [
                'submitted_by_user_id' => $actor->getKey(),
                'governed_queue_only' => true,
            ],
            'status' => LearningUpdate::STATUS_DETECTED,
        ]);

        $this->audit->record('entrepreneur.rating_framework_change_queued', subject: $update, actor: $actor, after: [
            'rating_framework_id' => $framework->getKey(),
            'automatic_application' => false,
        ]);

        return $update->refresh();
    }
}
