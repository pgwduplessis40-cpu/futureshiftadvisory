<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateSectionHelp([
            'Business Overview' => 'Tell us what the business does, who owns it, and how it operates.',
            'Financial Position' => 'The key numbers and documents that show how the business is performing.',
            'People and HR' => 'Who works in the business, how work is covered, and where people risk sits.',
        ]);

        $this->updateQuestion(
            oldPrompt: 'System handoffs and duplicate entry.',
            newPrompt: 'Where do you enter the same information more than once?',
            newHelpText: 'List the systems or spreadsheets involved, how often it happens, who does it, and whether it is copy/paste, upload/download, or automatic.',
        );

        $this->updateQuestion(
            oldPrompt: 'Search and AI discoverability evidence.',
            newPrompt: 'How do customers find you online?',
            newHelpText: 'Mention Google search, your website pages, FAQs, reviews, or any AI/search tools that bring enquiries. It is fine to write "not sure".',
        );
    }

    public function down(): void
    {
        $this->updateSectionHelp([
            'Business Overview' => 'Foundational context for the entity, ownership, and operating model.',
            'Financial Position' => 'High-level financial signals and the evidence-attachment path.',
            'People and HR' => 'Team size, people risks, and HR maturity.',
        ]);

        $this->updateQuestion(
            oldPrompt: 'Where do you enter the same information more than once?',
            newPrompt: 'System handoffs and duplicate entry.',
            newHelpText: 'List source system, destination system, data moved, frequency, owner, and whether the handoff is copy/paste, import/export, spreadsheet, or API.',
        );

        $this->updateQuestion(
            oldPrompt: 'How do customers find you online?',
            newPrompt: 'Search and AI discoverability evidence.',
            newHelpText: 'Share known SEO, local search, structured data, FAQ, answer-engine, AI Overview, GEO, AEO, or AIO issues/opportunities.',
        );
    }

    /**
     * @param  array<string, string>  $helpTextByTitle
     */
    private function updateSectionHelp(array $helpTextByTitle): void
    {
        $questionnaireIds = $this->standardAdvisoryQuestionnaireIds();

        if ($questionnaireIds === []) {
            return;
        }

        foreach ($helpTextByTitle as $title => $helpText) {
            DB::table('questionnaire_sections')
                ->whereIn('questionnaire_id', $questionnaireIds)
                ->where('title', $title)
                ->update([
                    'help_text' => $helpText,
                    'updated_at' => now(),
                ]);
        }
    }

    private function updateQuestion(string $oldPrompt, string $newPrompt, string $newHelpText): void
    {
        $sectionIds = $this->standardAdvisorySectionIds();

        if ($sectionIds === []) {
            return;
        }

        DB::table('questionnaire_questions')
            ->whereIn('questionnaire_section_id', $sectionIds)
            ->where('prompt', $oldPrompt)
            ->update([
                'prompt' => $newPrompt,
                'help_text' => $newHelpText,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function standardAdvisoryQuestionnaireIds(): array
    {
        return DB::table('questionnaires')
            ->where('set', 'standard_advisory')
            ->where('version', '2')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function standardAdvisorySectionIds(): array
    {
        $questionnaireIds = $this->standardAdvisoryQuestionnaireIds();

        if ($questionnaireIds === []) {
            return [];
        }

        return DB::table('questionnaire_sections')
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }
};
