<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\NzResource;
use App\Models\PlanPhase;
use App\Models\PlanSection;
use App\Models\RatingFramework;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Entrepreneurs\Assessment;
use App\Services\Entrepreneurs\EntrepreneurPromptRegistry;
use App\Services\Entrepreneurs\Guidance;
use App\Services\Entrepreneurs\IdeaValidationService;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

final class AiContentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private RecordingAiClient $ai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->ai = new RecordingAiClient;
        $this->app->instance(AiClient::class, $this->ai);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_entrepreneur_ai_prompt_content_is_isolated_by_prompt_side(): void
    {
        $framework = $this->sentinelFramework();
        $this->sentinelResource();
        [$advisor, $profile, $plan, $section] = $this->entrepreneurFixture();

        app(IdeaValidationService::class)->evaluate($profile, [
            'problem' => 'Founder text says improve next steps and repeats RUBRIC_SENTINEL_DESC_ALPHA.',
            'target_customer' => 'Regional retail operators.',
            'solution' => 'A planning workflow with clear customer evidence.',
            'value_proposition' => 'Less wasted launch effort.',
            'demand_signal' => 'Five interviews are complete.',
            'revenue_model' => 'Subscription revenue with onboarding support.',
        ], $advisor);
        app(Guidance::class)->guide($section, $advisor);
        app(Assessment::class)->firstPass($plan->refresh()->load('sections'), $advisor);

        $ideaPrompt = $this->ai->singlePrompt('analyse', EntrepreneurPromptRegistry::IDEA_VALIDATION);
        $guidancePrompt = $this->ai->singlePrompt('summarise', EntrepreneurPromptRegistry::PLAN_GUIDANCE);
        $scorePrompt = $this->ai->singlePrompt('scoreCriterion', EntrepreneurPromptRegistry::PLAN_SCORE_CRITERION);
        $forbiddenRubric = $this->rubricForbiddenValues($framework);

        foreach ([$ideaPrompt, $guidancePrompt] as $prompt) {
            $this->assertForbiddenAbsent(
                haystack: $this->encodeForScan($this->nonExaminerScanFields($prompt)),
                forbidden: $forbiddenRubric,
                context: $prompt->id,
            );
        }

        $this->assertStringContainsString('RUBRIC_SENTINEL_DESC_ALPHA', $this->encodeForScan($ideaPrompt->toArray()));
        $this->assertStringContainsString('RUBRIC_SENTINEL_DESC_ALPHA', $this->encodeForScan($guidancePrompt->toArray()));
        $this->assertStringNotContainsString('RUBRIC_SENTINEL_DESC_ALPHA', $this->encodeForScan($this->nonExaminerScanFields($ideaPrompt)));
        $this->assertStringNotContainsString('RUBRIC_SENTINEL_DESC_ALPHA', $this->encodeForScan($this->nonExaminerScanFields($guidancePrompt)));

        $scoreScan = $this->encodeForScan($this->examinerCoachingScanFields($scorePrompt));
        foreach ([
            'Identify gaps, risks, and practical next steps.',
            'NZ_RESOURCE_COACHING_SENTINEL',
            'gap-fix',
            'next steps',
            'improve',
            'recommend',
        ] as $coachingMarker) {
            $this->assertStringNotContainsString($coachingMarker, $scoreScan);
        }
        $this->assertStringContainsString('improve', $this->encodeForScan($scorePrompt->toArray()));
        $this->assertStringContainsString('RUBRIC_SENTINEL_DESC_ALPHA', $this->encodeForScan($scorePrompt->toArray()));

        $this->assertSame([EntrepreneurPromptRegistry::IDEA_VALIDATION], $this->ai->promptIdsFor('analyse'));
        $this->assertSame([EntrepreneurPromptRegistry::PLAN_GUIDANCE], $this->ai->promptIdsFor('summarise'));
        $this->assertSame([EntrepreneurPromptRegistry::PLAN_SCORE_CRITERION], $this->ai->promptIdsFor('scoreCriterion'));
    }

    public function test_all_entrepreneur_prompt_ids_are_classified(): void
    {
        $promptIds = $this->entrepreneurPromptIdsInApp();
        $classifications = EntrepreneurPromptRegistry::classifications();

        $this->assertContains(EntrepreneurPromptRegistry::PLAN_SCORE_CRITERION, $promptIds);
        $this->assertContains(EntrepreneurPromptRegistry::PLAN_GUIDANCE, $promptIds);
        $this->assertContains(EntrepreneurPromptRegistry::IDEA_VALIDATION, $promptIds);
        $this->assertSame(
            [],
            array_values(array_diff($promptIds, array_keys($classifications))),
            'Every entrepreneur.* PromptEnvelope id must be classified as examiner or non-examiner.',
        );
        $this->assertSame(
            [],
            array_values(array_diff(array_values($classifications), [
                EntrepreneurPromptRegistry::EXAMINER,
                EntrepreneurPromptRegistry::NON_EXAMINER,
            ])),
            'Entrepreneur prompt classifications must use known sides.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function rubricForbiddenValues(RatingFramework $framework): array
    {
        $framework->loadMissing('criteria');
        $values = [];

        foreach ($framework->criteria as $criterion) {
            $values[] = (string) (float) $criterion->weight;
            foreach ($criterion->descriptors ?? [] as $descriptor) {
                $values[] = (string) $descriptor;
            }
        }

        foreach ($framework->grade_bands ?? [] as $band) {
            $values[] = (string) ($band['label'] ?? '');
            $min = (float) ($band['min'] ?? 0);
            if ($min > 0) {
                $values[] = (string) $min;
            }
        }

        return collect($values)
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => strlen($value) >= 4)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function nonExaminerScanFields(PromptEnvelope $prompt): array
    {
        $fields = $this->baseScanFields($prompt);

        $fields['input'] = match ($prompt->id) {
            EntrepreneurPromptRegistry::PLAN_GUIDANCE => [
                'plan_id' => $prompt->input['plan_id'] ?? null,
                'section' => [
                    'phase' => data_get($prompt->input, 'section.phase'),
                    'title' => data_get($prompt->input, 'section.title'),
                ],
                'gaps' => $prompt->input['gaps'] ?? [],
                'resources' => $prompt->input['resources'] ?? [],
                'past_plan_pattern' => $prompt->input['past_plan_pattern'] ?? [],
            ],
            EntrepreneurPromptRegistry::IDEA_VALIDATION => [
                'profile_id' => $prompt->input['profile_id'] ?? null,
                'past_plan_pattern' => $prompt->input['past_plan_pattern'] ?? [],
            ],
            default => $this->fail("Unexpected non-examiner prompt id [{$prompt->id}]."),
        };

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function examinerCoachingScanFields(PromptEnvelope $prompt): array
    {
        $fields = $this->baseScanFields($prompt);
        $fields['input'] = [
            'business_plan_id' => $prompt->input['business_plan_id'] ?? null,
        ];

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseScanFields(PromptEnvelope $prompt): array
    {
        return [
            'task' => $prompt->task,
            'body' => $prompt->body,
            'integrity_preamble' => $prompt->integrityPreamble,
            'data_quality_summary' => $prompt->dataQualitySummary,
            'source_references' => $prompt->sourceReferences,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function encodeForScan(array $fields): string
    {
        return json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<int, string>  $forbidden
     */
    private function assertForbiddenAbsent(string $haystack, array $forbidden, string $context): void
    {
        foreach ($forbidden as $value) {
            $this->assertStringNotContainsString($value, $haystack, "Forbidden rubric value leaked into {$context}: {$value}");
        }
    }

    private function sentinelFramework(): RatingFramework
    {
        $framework = RatingFramework::query()->create([
            'version' => 99,
            'status' => RatingFramework::STATUS_PUBLISHED,
            'industry_variant' => null,
            'production_ready' => true,
            'grade_bands' => [
                'sentinel_top' => ['min' => 91.77, 'label' => 'RUBRIC_SENTINEL_BAND_ALPHA'],
                'sentinel_low' => ['min' => 12.34, 'label' => 'RUBRIC_SENTINEL_BAND_OMEGA'],
            ],
            'published_at' => now(),
        ]);

        $framework->criteria()->create([
            'number' => 1,
            'name' => 'Public criterion name',
            'weight' => 37.13,
            'descriptors' => [
                'sentinel_top' => 'RUBRIC_SENTINEL_DESC_ALPHA improve follow-up gaps',
                'sentinel_low' => 'RUBRIC_SENTINEL_DESC_OMEGA',
            ],
            'industry_variants' => [],
            'is_placeholder' => false,
        ]);

        return $framework->refresh()->load('criteria');
    }

    private function sentinelResource(): NzResource
    {
        return NzResource::query()->create([
            'industry' => 'retail',
            'business_type' => 'startup',
            'title' => 'NZ_RESOURCE_COACHING_SENTINEL',
            'url' => 'https://example.test/resource',
            'gap_tags' => ['foundation', 'demand', 'financial', 'legal', 'market', 'strategy'],
            'metadata' => [],
            'active' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: EntrepreneurProfile, 2: BusinessPlan, 3: PlanSection}
     */
    private function entrepreneurFixture(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'email' => 'ai-isolation-founder@example.test',
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'AI Isolation Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::IDEA_VALIDATION,
            'concept_summary' => 'Founder concept asks for improve next steps and quotes RUBRIC_SENTINEL_DESC_ALPHA.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->getKey(),
            'title' => 'Business plan: AI Isolation Founder',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_BUILDING,
            'current_phase' => 1,
            'founding_advisory_payload' => ['industry' => 'retail'],
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $phase = PlanPhase::query()->create([
            'business_plan_id' => $plan->getKey(),
            'key' => 'market',
            'title' => 'Market',
            'position' => 1,
            'depends_on' => [],
            'status' => PlanPhase::STATUS_PENDING,
        ]);
        $section = PlanSection::query()->create([
            'business_plan_id' => $plan->getKey(),
            'plan_phase_id' => $phase->getKey(),
            'key' => 'market-demand',
            'title' => 'Market demand',
            'body' => 'Founder draft asks for improve next steps and repeats RUBRIC_SENTINEL_DESC_ALPHA while discussing early demand.',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'completeness_status' => PlanSection::STATUS_DRAFT,
            'metadata' => [],
        ]);

        return [$advisor, $profile, $plan, $section->refresh()];
    }

    /**
     * @return array<int, string>
     */
    private function entrepreneurPromptIdsInApp(): array
    {
        $ids = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (! is_string($contents)) {
                continue;
            }

            preg_match_all(
                "/id:\\s*(?:EntrepreneurPromptRegistry::([A-Z_]+)|['\"](entrepreneur\\.[^'\"]+)['\"])/",
                $contents,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $ids[] = ($match[2] ?? '') !== ''
                    ? $match[2]
                    : $this->registryConstantValue($match[1] ?? '');
            }
        }

        return collect($ids)
            ->filter(fn (?string $id): bool => is_string($id) && str_starts_with($id, 'entrepreneur.'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function registryConstantValue(string $name): ?string
    {
        $constant = EntrepreneurPromptRegistry::class.'::'.$name;

        return defined($constant) ? (string) constant($constant) : null;
    }
}

final class RecordingAiClient implements AiClient
{
    /**
     * @var array<string, array<int, PromptEnvelope>>
     */
    private array $prompts = [
        'analyse' => [],
        'verifyDocument' => [],
        'scoreCriterion' => [],
        'summarise' => [],
        'redFlag' => [],
    ];

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return $this->record('analyse', $prompt);
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->record('verifyDocument', $prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->record('scoreCriterion', $prompt);
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->record('summarise', $prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->record('redFlag', $prompt);
    }

    public function singlePrompt(string $method, string $id): PromptEnvelope
    {
        $matches = array_values(array_filter(
            $this->prompts[$method] ?? [],
            fn (PromptEnvelope $prompt): bool => $prompt->id === $id,
        ));

        Assert::assertCount(1, $matches, "Expected exactly one {$method} prompt with id [{$id}].");

        return $matches[0];
    }

    /**
     * @return array<int, string>
     */
    public function promptIdsFor(string $method): array
    {
        return array_map(
            fn (PromptEnvelope $prompt): string => $prompt->id,
            $this->prompts[$method] ?? [],
        );
    }

    private function record(string $method, PromptEnvelope $prompt): AiResponse
    {
        $this->prompts[$method][] = $prompt;

        return new AiResponse(
            text: 'Recorded AI response.',
            attributions: [
                [
                    'claim' => 'Recorded AI response.',
                    'source_reference' => 'test:recording-ai-client',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'recording-ai-client',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: str_word_count(json_encode($prompt->toArray(), JSON_THROW_ON_ERROR)),
            tokensOut: 3,
            metadata: ['method' => $method],
        );
    }
}
