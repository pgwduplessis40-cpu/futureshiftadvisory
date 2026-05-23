<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DdSpecificQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::DUE_DILIGENCE->value,
                    'version' => '1',
                ],
                [
                    'title' => 'Due Diligence Questionnaire',
                    'published_at' => now(),
                ],
            );

            if ($questionnaire->sections()->exists()) {
                return;
            }

            $sections = [
                [
                    'title' => 'Acquisition Target',
                    'help_text' => 'Target entity, vendor, and transaction context separate from buyer operating data.',
                    'questions' => [
                        $this->question('Target business legal name.', QuestionnaireQuestionType::TEXT),
                        $this->question('Target NZBN or registration reference.', QuestionnaireQuestionType::TEXT, required: false),
                        $this->question('Describe the proposed acquisition structure.', QuestionnaireQuestionType::LONG_TEXT),
                    ],
                ],
                [
                    'title' => 'Deal Context',
                    'help_text' => 'Commercial rationale, timeline, and decision constraints.',
                    'questions' => [
                        $this->question('What is the acquisition rationale?', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Indicative asking price or price range.', QuestionnaireQuestionType::CURRENCY, required: false),
                        $this->question('Target completion date.', QuestionnaireQuestionType::DATE, required: false),
                    ],
                ],
                [
                    'title' => 'Evidence Requested',
                    'help_text' => 'Initial DD evidence requests before the dedicated data room opens.',
                    'questions' => [
                        $this->question('Financial statements requested from the vendor.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
                        $this->question('Material contracts or leases requested from the vendor.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
                        $this->question('Known legal, tax, HR, or regulatory concerns.', QuestionnaireQuestionType::LONG_TEXT, required: false),
                    ],
                ],
            ];

            foreach ($sections as $sectionIndex => $sectionData) {
                $section = $questionnaire->sections()->create([
                    'order' => $sectionIndex + 1,
                    'title' => $sectionData['title'],
                    'help_text' => $sectionData['help_text'],
                ]);

                foreach ($sectionData['questions'] as $questionIndex => $questionData) {
                    $section->questions()->create([
                        ...$questionData,
                        'order' => $questionIndex + 1,
                    ]);
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function question(
        string $prompt,
        QuestionnaireQuestionType $type,
        bool $required = true,
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type->value,
            'prompt' => $prompt,
            'help_text' => null,
            'options' => [],
            'conditional_logic' => null,
            'required' => $required,
        ];
    }
}
