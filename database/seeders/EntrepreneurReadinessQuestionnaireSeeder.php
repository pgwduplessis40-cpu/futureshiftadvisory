<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EntrepreneurReadinessQuestionnaireSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const QUESTIONS = [
        'How clearly can you explain the customer problem?',
        'How confident are you that customers urgently need the solution?',
        'What evidence do you have that customers will pay?',
        'How well do you understand the target customer segment?',
        'How much relevant industry experience do you have?',
        'How many hours per week can you commit for the next six months?',
        'What personal obligations may constrain your availability?',
        'How resilient do you feel when plans change?',
        'What financial runway do you have before revenue is required?',
        'How comfortable are you with sales conversations?',
        'How ready are you to receive direct feedback?',
        'What specialist capability gaps do you already know about?',
        'What legal or compliance questions are unresolved?',
        'Who will support you personally while you build?',
        'What would make you pause before launching?',
        'What does a successful first year look like?',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::ENTREPRENEUR_READINESS->value,
                    'version' => '1',
                ],
                [
                    'title' => 'Entrepreneur Readiness Assessment',
                    'published_at' => now(),
                ],
            );

            if ($questionnaire->sections()->exists()) {
                return;
            }

            $section = $questionnaire->sections()->create([
                'order' => 1,
                'title' => 'Founder readiness',
                'help_text' => 'A practical assessment of founder, concept, time, financial, and personal readiness.',
            ]);

            foreach (self::QUESTIONS as $index => $prompt) {
                $section->questions()->create([
                    'id' => (string) Str::uuid(),
                    'order' => $index + 1,
                    'type' => QuestionnaireQuestionType::LONG_TEXT->value,
                    'prompt' => $prompt,
                    'help_text' => null,
                    'options' => [],
                    'conditional_logic' => null,
                    'required' => true,
                ]);
            }
        });
    }
}
