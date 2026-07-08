<?php

declare(strict_types=1);

namespace Tests\Feature\Questionnaire;

use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\DdSpecificQuestionnaireV2Seeder;
use Database\Seeders\StandardAdvisoryQuestionnaireSeeder;
use Database\Seeders\StandardAdvisoryQuestionnaireV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class QuestionnaireBaseContentV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_standard_advisory_v2_is_the_active_base_with_why_help_text(): void
    {
        $this->seed(StandardAdvisoryQuestionnaireSeeder::class);
        $this->seed(StandardAdvisoryQuestionnaireV2Seeder::class);

        $active = $this->active(QuestionnaireSet::STANDARD_ADVISORY);

        $this->assertSame('2', $active->version, 'v2 must be the active (latest published) standard advisory set.');
        $this->assertSame([
            'Business Overview',
            'Products and Services',
            'Market and Outcomes',
            'Financial Position',
            'People and HR',
            'Operations',
            'Sales and Marketing',
            'Strategy and Goals',
            'Compliance and Risk',
            'Owner and Leadership',
        ], $active->sections->pluck('title')->all());

        $questions = $this->questions($active);
        $this->assertGreaterThanOrEqual(50, $questions->count());
        $prompts = $questions->pluck('prompt')->all();
        $this->assertContains('Website URL and main product/service pages.', $prompts);
        $this->assertContains('How accurately does the website describe what you sell?', $prompts);
        $this->assertContains('How do customers find you online?', $prompts);
        $this->assertContains('Where do you enter the same information more than once?', $prompts);
        $this->assertContains('Manual tasks that take a lot of time each week.', $prompts);
        $this->assertContains('Automation constraints and approvals.', $prompts);
        // Every question carries its "why we need this" as help_text.
        $this->assertTrue(
            $questions->every(fn ($q): bool => is_string($q->help_text) && $q->help_text !== ''),
            'Every standard advisory question must carry help_text (the "why we need this").',
        );
        // v1 is retained, unaffected.
        $this->assertTrue(Questionnaire::query()->forSet(QuestionnaireSet::STANDARD_ADVISORY)->where('version', '1')->exists());
    }

    public function test_dd_v2_is_active_excludes_non_target_sections_and_has_why_help_text(): void
    {
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        $this->seed(DdSpecificQuestionnaireV2Seeder::class);

        $active = $this->active(QuestionnaireSet::DUE_DILIGENCE);

        $this->assertSame('2', $active->version, 'v2 must be the active (latest published) DD set.');

        $titles = $active->sections->pluck('title')->all();
        $this->assertContains('Deal Summary and Structure', $titles);
        $this->assertContains('Integration, Synergies and Post-Close Costs', $titles);
        // The two non-target sections are intentionally excluded.
        $this->assertNotContains('Red Flags Checklist', $titles);
        $this->assertNotContains('Clarifying questions for you', $titles);

        $this->assertTrue(
            $this->questions($active)->every(fn ($q): bool => is_string($q->help_text) && $q->help_text !== ''),
            'Every DD question must carry help_text (the "why we need this").',
        );
        $this->assertTrue(Questionnaire::query()->forSet(QuestionnaireSet::DUE_DILIGENCE)->where('version', '1')->exists());
    }

    private function active(QuestionnaireSet $set): Questionnaire
    {
        return Questionnaire::query()
            ->forSet($set)
            ->published()
            ->with('sections.questions')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->firstOrFail();
    }

    /**
     * @return Collection<int, QuestionnaireQuestion>
     */
    private function questions(Questionnaire $questionnaire): Collection
    {
        return $questionnaire->sections->flatMap(fn ($section) => $section->questions);
    }
}
