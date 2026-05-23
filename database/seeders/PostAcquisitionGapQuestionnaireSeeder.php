<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PostAcquisitionGapQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::POST_ACQUISITION_GAP->value,
                    'version' => '1',
                ],
                [
                    'title' => 'Post-acquisition Gap Questionnaire',
                    'published_at' => now(),
                ],
            );

            if ($questionnaire->sections()->exists()) {
                return;
            }

            $section = $questionnaire->sections()->create([
                'order' => 1,
                'title' => 'DD Handoff Gaps',
                'help_text' => 'Fields pre-populated from due diligence are shown for confirmation; remaining gaps are completed by the client post-close.',
            ]);

            foreach ([
                $this->question('Confirm acquired business details from DD.', QuestionnaireQuestionType::LONG_TEXT),
                $this->question('Review inherited due diligence risks.', QuestionnaireQuestionType::LONG_TEXT),
                $this->question('Confirm migrated DD document set.', QuestionnaireQuestionType::LONG_TEXT),
                $this->question('What changed after settlement that was not covered by DD?', QuestionnaireQuestionType::LONG_TEXT),
            ] as $index => $question) {
                $section->questions()->create([
                    ...$question,
                    'order' => $index + 1,
                ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function question(string $prompt, QuestionnaireQuestionType $type): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type->value,
            'prompt' => $prompt,
            'help_text' => null,
            'options' => [],
            'conditional_logic' => null,
            'required' => true,
        ];
    }
}
