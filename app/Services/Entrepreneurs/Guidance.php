<?php

declare(strict_types=1);

namespace App\Services\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\IdeaValidation;
use App\Models\NzResource;
use App\Models\PlanSection;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Audit\AuditWriter;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * @param  array<string, mixed>  $requirement
     * @return array{title:string,draft:string,summary:string,checklist:array<int, string>,ai_summary:string,model:string,prompt_hash:string,attributions:array<int, array{claim:string, source_reference:string}>,resources:array<int, array<string, mixed>>}
     */
    public function draftRequirement(
        BusinessPlan $plan,
        EntrepreneurProfile $profile,
        array $requirement,
        ?IdeaValidation $ideaValidation,
        string $currentDraft,
        User $actor,
    ): array {
        $plan->loadMissing('phases.sections');
        $industry = $this->industry($plan);
        $gaps = $this->requirementGapTags($requirement, $currentDraft);
        $resources = $this->recommendResources($industry, 'startup', $gaps);
        $existingSections = $plan->sections
            ->sortBy('created_at')
            ->take(8)
            ->map(fn (PlanSection $section): array => [
                'title' => $section->title,
                'body_excerpt' => Str::limit($section->body, 600, ''),
                'requirement_key' => data_get($section->metadata, 'requirement_key'),
            ])
            ->values()
            ->all();
        $sourceReferences = [
            'business_plan:'.$plan->getKey(),
            'entrepreneur_profile:'.$profile->getKey(),
            ...($ideaValidation instanceof IdeaValidation ? ['idea_validation:'.$ideaValidation->getKey()] : []),
            ...$resources->map(fn (NzResource $resource): string => 'nz_resource:'.$resource->getKey())->all(),
        ];
        $prompt = new PromptEnvelope(
            id: EntrepreneurPromptRegistry::PLAN_REQUIREMENT_ASSIST,
            version: '2026-06-24',
            task: 'Draft founder-facing business plan requirement assistance.',
            body: 'Create concise editable business plan wording for the selected requirement. Use only supplied idea validation, current draft, existing plan sections, and NZ resource context. Mark assumptions clearly and avoid unsupported claims.',
            input: [
                'plan_id' => $plan->getKey(),
                'profile' => [
                    'name' => $profile->name,
                    'stage' => $profile->stage instanceof BackedEnum
                        ? $profile->stage->value
                        : (string) $profile->stage,
                    'concept_summary' => $profile->concept_summary,
                ],
                'requirement' => [
                    'phase' => $requirement['phase_title'] ?? null,
                    'key' => $requirement['key'] ?? null,
                    'title' => $requirement['title'] ?? null,
                    'description' => $requirement['description'] ?? null,
                ],
                'idea_validation' => $ideaValidation instanceof IdeaValidation ? [
                    'problem' => $ideaValidation->problem,
                    'target_customer' => $ideaValidation->target_customer,
                    'solution' => $ideaValidation->solution,
                    'value_proposition' => $ideaValidation->value_proposition,
                    'demand_signal' => $ideaValidation->demand_signal,
                    'revenue_model' => $ideaValidation->revenue_model,
                ] : null,
                'current_draft' => Str::limit($currentDraft, 2500, ''),
                'existing_sections' => $existingSections,
                'detected_gaps' => $gaps,
                'resources' => $resources->pluck('title')->all(),
            ],
            dataQualitySummary: [
                'level' => 'entrepreneur_draft_assist',
                'message' => 'Draft assistance is based on the founder plan context and must be reviewed before saving.',
            ],
            sourceReferences: $sourceReferences,
        );
        $response = $this->ai->summarise($prompt);
        $fallback = $this->fallbackRequirementDraft($profile, $requirement, $ideaValidation, $currentDraft);
        $aiText = trim($response->text);
        $draft = $aiText !== '' && ! str_contains(strtolower($aiText), 'ai unavailable')
            ? $aiText
            : $fallback;
        $checklist = $this->requirementChecklist($requirement, $ideaValidation);
        $payload = [
            'title' => (string) ($requirement['title'] ?? 'Business plan requirement'),
            'draft' => $draft,
            'summary' => 'AI draft added. Review the assumptions, add real evidence, then save the requirement.',
            'checklist' => $checklist,
            'ai_summary' => $response->text,
            'model' => $response->model,
            'prompt_hash' => $response->promptHash,
            'attributions' => [
                ...$response->attributions,
                ...$resources->map(fn (NzResource $resource): array => [
                    'claim' => 'NZ resource context considered: '.$resource->title,
                    'source_reference' => 'nz_resource:'.$resource->getKey(),
                ])->all(),
            ],
            'resources' => $resources->map(fn (NzResource $resource): array => [
                'id' => $resource->getKey(),
                'title' => $resource->title,
                'url' => $resource->url,
                'gap_tags' => $resource->gap_tags,
            ])->values()->all(),
        ];

        $this->audit->record('entrepreneur.plan_requirement_assisted', subject: $plan, actor: $actor, after: [
            'entrepreneur_profile_id' => $profile->getKey(),
            'requirement_key' => (string) ($requirement['key'] ?? ''),
            'resource_count' => count($payload['resources']),
            'prompt_hash' => $response->promptHash,
        ]);

        return $payload;
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
     * @param  array<string, mixed>  $requirement
     * @return array<int, string>
     */
    private function requirementGapTags(array $requirement, string $currentDraft): array
    {
        $text = strtolower(implode(' ', [
            (string) ($requirement['title'] ?? ''),
            (string) ($requirement['description'] ?? ''),
            $currentDraft,
        ]));
        $gaps = ['foundation'];

        foreach ([
            'market' => ['customer', 'demand', 'market', 'competitor', 'alternative'],
            'strategy' => ['goal', 'milestone', 'success', 'vision', 'culture'],
            'financial' => ['revenue', 'price', 'margin', 'cash', 'funding'],
            'legal' => ['legal', 'privacy', 'contract', 'licence', 'intellectual', 'ip'],
        ] as $tag => $needles) {
            if (collect($needles)->contains(fn (string $needle): bool => str_contains($text, $needle))) {
                $gaps[] = $tag;
            }
        }

        return array_values(array_unique($gaps));
    }

    /**
     * @param  array<string, mixed>  $requirement
     */
    private function fallbackRequirementDraft(
        EntrepreneurProfile $profile,
        array $requirement,
        ?IdeaValidation $ideaValidation,
        string $currentDraft,
    ): string {
        $title = (string) ($requirement['title'] ?? 'Business plan requirement');
        $description = (string) ($requirement['description'] ?? 'Complete this requirement with clear business context.');
        $concept = trim((string) ($profile->concept_summary ?: $profile->name));
        $existingDraft = trim($currentDraft);
        $ideaLines = $ideaValidation instanceof IdeaValidation
            ? [
                'Problem: '.$ideaValidation->problem,
                'Target customer: '.$ideaValidation->target_customer,
                'Solution: '.$ideaValidation->solution,
                'Value proposition: '.$ideaValidation->value_proposition,
                'Demand evidence: '.$ideaValidation->demand_signal,
                'Revenue model: '.$ideaValidation->revenue_model,
            ]
            : ['Idea validation detail has not been captured yet; add the customer problem, solution, demand evidence, and revenue logic before relying on this section.'];

        return trim(implode("\n", [
            $title,
            '',
            'Starter draft for review',
            $existingDraft !== ''
                ? 'Use the current draft below as source material, then tighten it into advisor-ready wording.'
                : 'Use this as a starting point and replace assumptions with Wessel\'s actual details before saving.',
            '',
            'Known context',
            '- Business concept: '.($concept !== '' ? $concept : 'Add a concise description of the business concept.'),
            ...array_map(fn (string $line): string => '- '.$line, $ideaLines),
            '',
            'What this section needs to cover',
            '- '.$description,
            '- Explain the decision, evidence, assumptions, risks, and next action clearly enough for an advisor to rely on it.',
            '- Attach supporting evidence where it exists, such as customer interviews, quotes, supplier notes, financial workings, or legal documents.',
            '',
            'Draft wording',
            sprintf(
                '%s should explain how %s will address this requirement. Based on the validated idea, the section should connect the customer problem, the proposed solution, the evidence gathered so far, and the risks still needing advisor review.',
                $title,
                $profile->name ?: 'the business',
            ),
            'Assumptions to confirm: location, operating model, pricing, delivery capacity, required licences, evidence sources, and any constraints that may change the plan.',
        ]));
    }

    /**
     * @param  array<string, mixed>  $requirement
     * @return array<int, string>
     */
    private function requirementChecklist(array $requirement, ?IdeaValidation $ideaValidation): array
    {
        $checklist = [
            'Replace assumptions with the founder\'s actual details.',
            'Add evidence the advisor can rely on.',
            'State risks or unknowns plainly.',
        ];

        if (! $ideaValidation instanceof IdeaValidation) {
            array_unshift($checklist, 'Complete idea validation context first.');
        }

        $title = strtolower((string) ($requirement['title'] ?? ''));
        if (str_contains($title, 'revenue') || str_contains($title, 'funding')) {
            $checklist[] = 'Include pricing, cost, cash timing, and funding assumptions.';
        }
        if (str_contains($title, 'legal') || str_contains($title, 'intellectual')) {
            $checklist[] = 'Identify licences, contracts, privacy, IP, or compliance obligations.';
        }
        if (str_contains($title, 'customer') || str_contains($title, 'market') || str_contains($title, 'apart')) {
            $checklist[] = 'Name the target customer, alternatives, competitors, and demand signal.';
        }

        return array_values(array_unique($checklist));
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
