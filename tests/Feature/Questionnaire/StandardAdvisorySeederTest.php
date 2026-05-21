<?php

declare(strict_types=1);

namespace Tests\Feature\Questionnaire;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Database\Seeders\StandardAdvisoryQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StandardAdvisorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_advisory_questionnaire_seeds_ten_sections_and_all_phase_one_types(): void
    {
        $this->seed(StandardAdvisoryQuestionnaireSeeder::class);

        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::STANDARD_ADVISORY)
            ->with('sections.questions')
            ->firstOrFail();

        $this->assertSame('1', $questionnaire->version);
        $this->assertTrue($questionnaire->isPublished());
        $this->assertSame([
            'Business Overview',
            'Products and Services',
            'Market and Customers',
            'Financial Position',
            'People and HR',
            'Operations',
            'Sales and Marketing',
            'Strategy and Goals',
            'Compliance and Risk',
            'Owner and Leadership',
        ], $questionnaire->sections->pluck('title')->all());

        $types = $questionnaire->sections
            ->flatMap(fn ($section) => $section->questions)
            ->pluck('type')
            ->map(fn (QuestionnaireQuestionType $type): string => $type->value)
            ->unique()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(QuestionnaireQuestionType::values(), $types);
        $this->assertTrue(
            $questionnaire->sections
                ->flatMap(fn ($section) => $section->questions)
                ->contains(fn ($question): bool => is_array($question->conditional_logic)),
        );
    }
}
