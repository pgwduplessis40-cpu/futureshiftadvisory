<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\LearningUpdate;
use App\Models\RatingCriterion;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

final class RatingFrameworkManager
{
    public const LEARNING_LAYER_ID = 18;

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

    public function gradeBand(float $percentage): string
    {
        return (new RatingFramework([
            'grade_bands' => RatingFramework::DEFAULT_GRADE_BANDS,
        ]))->gradeFor($percentage);
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
