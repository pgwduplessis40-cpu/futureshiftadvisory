<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\NzResource;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class Guidance
{
    public function __construct(
        private readonly AiClient $ai,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function guide(PlanSection $section, User $actor): array
    {
        $section->loadMissing('businessPlan.entrepreneurProfile', 'phase');
        $plan = $section->businessPlan;
        $industry = $this->industry($plan);
        $gaps = $this->gapTags($section);
        $resources = $this->recommendResources($industry, 'startup', $gaps);
        $pastPattern = $this->pastPlanPattern($section, $industry);
        $prompt = new PromptEnvelope(
            id: EntrepreneurPromptRegistry::PLAN_GUIDANCE,
            version: '2026-05-23',
            task: 'Give section-specific entrepreneur plan guidance with honest predictive scoring and NZ resources.',
            body: 'Identify gaps, risks, and practical next steps. Do not flatter. Cite aggregate past-plan pattern and NZ resource context.',
            input: [
                'plan_id' => $plan?->getKey(),
                'section' => [
                    'phase' => $section->phase?->key,
                    'title' => $section->title,
                    'body' => $section->body,
                ],
                'gaps' => $gaps,
                'resources' => $resources->pluck('title')->all(),
                'past_plan_pattern' => $pastPattern,
            ],
            dataQualitySummary: [
                'level' => 'entrepreneur_draft',
                'message' => 'Guidance is based on the current draft section and aggregate pattern context.',
            ],
            sourceReferences: [
                $pastPattern['source_reference'],
                ...$resources->map(fn (NzResource $resource): string => 'nz_resource:'.$resource->getKey())->all(),
            ],
        );
        $response = $this->ai->summarise($prompt);
        $score = $this->predictiveScore($section, $gaps);
        $guidance = [
            'summary' => $this->guidanceSummary($section, $score, $gaps),
            'ai_summary' => $response->text,
            'model' => $response->model,
            'prompt_hash' => $response->promptHash,
            'attributions' => [
                ...$response->attributions,
                [
                    'claim' => 'Guidance compared against aggregate prior plan pattern context.',
                    'source_reference' => $pastPattern['source_reference'],
                ],
                ...$resources->map(fn (NzResource $resource): array => [
                    'claim' => 'NZ resource recommendation: '.$resource->title,
                    'source_reference' => 'nz_resource:'.$resource->getKey(),
                ])->all(),
            ],
            'past_plan_pattern' => $pastPattern,
            'gaps' => $gaps,
            'resources' => $resources->map(fn (NzResource $resource): array => [
                'id' => $resource->getKey(),
                'title' => $resource->title,
                'url' => $resource->url,
                'gap_tags' => $resource->gap_tags,
            ])->values()->all(),
            'predictive_score' => $score,
        ];

        DB::transaction(function () use ($section, $guidance, $score, $actor): void {
            $metadata = is_array($section->metadata) ? $section->metadata : [];
            $section->forceFill([
                'metadata' => [
                    ...$metadata,
                    'ai_guidance' => $guidance,
                ],
                'predictive_score' => $score,
            ])->save();

            $this->audit->record('entrepreneur.plan_guidance_generated', subject: $section, actor: $actor, after: [
                'business_plan_id' => $section->business_plan_id,
                'score' => $score['score'],
                'resource_count' => count($guidance['resources']),
            ]);
        });

        return $guidance;
    }

    /**
     * @param  array<int, string>  $gapTags
     * @return Collection<int, NzResource>
     */
    public function recommendResources(string $industry, string $businessType, array $gapTags): Collection
    {
        return NzResource::query()
            ->where('active', true)
            ->get()
            ->filter(function (NzResource $resource) use ($industry, $businessType, $gapTags): bool {
                $industryMatches = in_array($resource->industry, ['general', $industry], true);
                $typeMatches = in_array($resource->business_type, ['general', $businessType], true);
                $tagMatches = array_intersect($resource->gap_tags ?? [], $gapTags) !== [];

                return $industryMatches && $typeMatches && $tagMatches;
            })
            ->values();
    }

    /**
     * @param  array<int, string>|null  $gaps
     * @return array{score:int,band:string,gaps:array<int, string>,reasons:array<int, string>,no_flattery:bool}
     */
    public function predictiveScore(PlanSection $section, ?array $gaps = null): array
    {
        $gaps ??= $this->gapTags($section);
        $wordCount = str_word_count($section->body);
        $score = min(88, 35 + min(35, (int) floor($wordCount / 4)) - (count($gaps) * 7));

        if ($wordCount < 35) {
            $score = min($score, 58);
        }

        $score = max(15, $score);

        return [
            'score' => $score,
            'band' => $score >= 75 ? 'strong' : ($score >= 60 ? 'developing' : 'needs_work'),
            'gaps' => $gaps,
            'reasons' => $this->scoreReasons($section, $gaps, $wordCount),
            'no_flattery' => true,
        ];
    }

    private function industry(?BusinessPlan $plan): string
    {
        $payload = $plan?->founding_advisory_payload ?? [];
        $industry = data_get($payload, 'industry')
            ?? data_get($payload, 'target_details.industry')
            ?? data_get($plan?->entrepreneurProfile?->concept_summary, 'industry');

        if (is_string($industry) && trim($industry) !== '') {
            return strtolower(trim($industry));
        }

        $text = strtolower((string) $plan?->entrepreneurProfile?->concept_summary);

        return str_contains($text, 'retail') ? 'retail' : 'general';
    }

    /**
     * @return array<int, string>
     */
    private function gapTags(PlanSection $section): array
    {
        $body = strtolower($section->body);
        $gaps = [];

        if (str_word_count($body) < 35) {
            $gaps[] = 'foundation';
        }

        foreach ([
            'demand' => ['customer', 'demand', 'pilot', 'survey', 'interview'],
            'financial' => ['revenue', 'margin', 'price', 'cash', 'cost'],
            'legal' => ['legal', 'privacy', 'contract', 'ip', 'intellectual'],
            'market' => ['market', 'competitor', 'segment', 'channel'],
            'strategy' => ['goal', 'milestone', 'focus', 'position'],
        ] as $tag => $needles) {
            if (! collect($needles)->contains(fn (string $needle): bool => str_contains($body, $needle))) {
                $gaps[] = $tag;
            }
        }

        return array_values(array_unique($gaps));
    }

    /**
     * @return array{source_reference:string, cohort:int, phase:string, note:string}
     */
    private function pastPlanPattern(PlanSection $section, string $industry): array
    {
        $phaseKey = (string) $section->phase?->key;
        $cohort = PlanSection::query()
            ->where('key', $section->key)
            ->whereHas('businessPlan', fn ($query) => $query
                ->where('source_type', BusinessPlan::SOURCE_ENTREPRENEUR)
                ->where('status', BusinessPlan::STATUS_FINALISED))
            ->count();

        return [
            'source_reference' => 'past_plan_patterns:'.$industry.':'.$phaseKey,
            'cohort' => $cohort,
            'phase' => $phaseKey,
            'note' => $cohort > 0
                ? "Based on {$cohort} finalised comparable {$industry} plan section(s)."
                : "No finalised comparable {$industry} plan sections yet; guidance remains conservative.",
        ];
    }

    /**
     * @param  array{score:int,band:string,gaps:array<int, string>,reasons:array<int, string>,no_flattery:bool}  $score
     * @param  array<int, string>  $gaps
     */
    private function guidanceSummary(PlanSection $section, array $score, array $gaps): string
    {
        if ($score['score'] < 60) {
            return sprintf(
                'This section is not ready yet. Current predictive score is %d/100 because these gaps need work: %s.',
                $score['score'],
                implode(', ', $gaps),
            );
        }

        return sprintf(
            'This section is developing. Current predictive score is %d/100; strengthen %s before relying on it.',
            $score['score'],
            implode(', ', $gaps ?: ['specific evidence']),
        );
    }

    /**
     * @param  array<int, string>  $gaps
     * @return array<int, string>
     */
    private function scoreReasons(PlanSection $section, array $gaps, int $wordCount): array
    {
        $reasons = ["Draft length: {$wordCount} words."];

        if ($gaps !== []) {
            $reasons[] = 'Detected gaps: '.implode(', ', $gaps).'.';
        }

        $reasons[] = 'Score is capped for draft-stage uncertainty and must not be treated as encouragement to launch.';

        return $reasons;
    }
}
