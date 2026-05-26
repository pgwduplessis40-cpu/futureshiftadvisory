<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GovernanceReviewQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::GOVERNANCE_REVIEW->value,
                    'version' => '1',
                ],
                [
                    'title' => 'Governance Review Questionnaire',
                    'published_at' => now(),
                ],
            );

            if ($questionnaire->sections()->exists()) {
                return;
            }

            $hasPaidStaffId = (string) Str::uuid();
            $paidStaffNotesId = (string) Str::uuid();
            $constitutionOutOfDateId = (string) Str::uuid();
            $constitutionActionId = (string) Str::uuid();
            $charityRegisteredId = (string) Str::uuid();
            $charityNumberId = (string) Str::uuid();

            $sections = [
                [
                    'title' => 'Organisation Context',
                    'help_text' => 'Mission, structure, registration, and beneficiary context for a focused governance review.',
                    'questions' => [
                        $this->question('Describe the organisation mission and the community or beneficiaries served.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Confirm the current legal structure.', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'Registered Charity',
                            'Incorporated Society',
                            'Both Registered Charity and Incorporated Society',
                            'Charitable Trust Board',
                            'Community Trust or Foundation',
                            'Social Enterprise',
                            'Unincorporated Community Organisation',
                        ])),
                        $this->question('Is the organisation registered with Charities Services?', QuestionnaireQuestionType::SINGLE_SELECT, id: $charityRegisteredId, options: $this->yesNo()),
                        $this->question('Enter the charities registration number.', QuestionnaireQuestionType::TEXT, id: $charityNumberId, conditional: [
                            'when' => $charityRegisteredId,
                            'equals' => 'yes',
                            'show' => $charityNumberId,
                        ]),
                        $this->question('Does the organisation employ paid staff or contractors?', QuestionnaireQuestionType::SINGLE_SELECT, id: $hasPaidStaffId, options: $this->yesNo()),
                        $this->question('Summarise paid-staff oversight, payroll, and key HR risks.', QuestionnaireQuestionType::LONG_TEXT, id: $paidStaffNotesId, conditional: [
                            'when' => $hasPaidStaffId,
                            'equals' => 'yes',
                            'show' => $paidStaffNotesId,
                        ]),
                    ],
                ],
                [
                    'title' => 'Board Composition and Skills',
                    'help_text' => 'Board capability, succession, diversity of perspective, and meeting discipline.',
                    'questions' => [
                        $this->question('How many current voting board or committee members are active?', QuestionnaireQuestionType::NUMBER),
                        $this->question('Which roles are formally filled?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Chair',
                            'Treasurer',
                            'Secretary',
                            'Deputy chair',
                            'Independent trustee',
                            'Community representative',
                        ])),
                        $this->question('Rate the current board skills coverage.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Describe the most important board skills gaps or succession risks.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('How often does the board or committee meet?', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'Monthly',
                            'Every two months',
                            'Quarterly',
                            'Ad hoc',
                            'Not currently meeting',
                        ])),
                        $this->question('How is community, beneficiary, or tangata whenua voice included in governance decisions?', QuestionnaireQuestionType::LONG_TEXT),
                    ],
                ],
                [
                    'title' => 'Constitution and Compliance',
                    'help_text' => 'Constitution currency, statutory obligations, and governance compliance signals.',
                    'questions' => [
                        $this->question('Attach the constitution, rules, or trust deed.', QuestionnaireQuestionType::FILE_ATTACH),
                        $this->question('When was the constitution, rules, or trust deed last reviewed?', QuestionnaireQuestionType::DATE, required: false),
                        $this->question('Does the governing document need updates for current law or practice?', QuestionnaireQuestionType::SINGLE_SELECT, id: $constitutionOutOfDateId, options: $this->yesNo()),
                        $this->question('Describe the planned constitution or rules update.', QuestionnaireQuestionType::LONG_TEXT, id: $constitutionActionId, conditional: [
                            'when' => $constitutionOutOfDateId,
                            'equals' => 'yes',
                            'show' => $constitutionActionId,
                        ]),
                        $this->question('Confirm Incorporated Societies Act 2022 re-registration status.', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'Not applicable',
                            'Already re-registered',
                            'In progress',
                            'Not started',
                            'Unsure',
                        ])),
                        $this->question('Rate confidence that annual returns and statutory filings are current.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Summarise any known Charities Act, s.42G, Incorporated Societies Act, or trust-law concerns.', QuestionnaireQuestionType::LONG_TEXT, required: false),
                    ],
                ],
                [
                    'title' => 'Financial Oversight',
                    'help_text' => 'Board-level financial controls, reporting, reserves, and delegated authority.',
                    'questions' => [
                        $this->question('Attach the latest financial statements.', QuestionnaireQuestionType::FILE_ATTACH),
                        $this->question('How frequently does the board receive financial reporting?', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'Every meeting',
                            'Monthly',
                            'Quarterly',
                            'Annually',
                            'Only on request',
                            'Not currently provided',
                        ])),
                        $this->question('Rate board confidence in financial sustainability and reserves.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Approximate unrestricted reserves or operating runway in months.', QuestionnaireQuestionType::NUMBER, required: false),
                        $this->question('Describe the approval limits, delegated authorities, and two-person controls currently used.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('List any material funding conditions, reporting deadlines, or funder risks the board monitors.', QuestionnaireQuestionType::LONG_TEXT, required: false),
                    ],
                ],
                [
                    'title' => 'Governance Evidence Pack',
                    'help_text' => 'Core governance evidence needed before the advisor can complete the review.',
                    'questions' => [
                        $this->question('Attach the current board register.', QuestionnaireQuestionType::FILE_ATTACH),
                        $this->question('Attach the conflicts of interest register.', QuestionnaireQuestionType::FILE_ATTACH),
                        $this->question('How often are interests declared and minuted?', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'At every meeting',
                            'At least annually',
                            'When a conflict arises',
                            'Informally',
                            'Not currently recorded',
                        ])),
                        $this->question('Describe the process for identifying and managing conflicts of interest.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Summarise the top three governance improvements the board already knows it wants to make.', QuestionnaireQuestionType::LONG_TEXT),
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
        ?string $id = null,
        array $options = [],
        ?array $conditional = null,
        bool $required = true,
    ): array {
        return [
            'id' => $id ?? (string) Str::uuid(),
            'type' => $type->value,
            'prompt' => $prompt,
            'help_text' => null,
            'options' => $options,
            'conditional_logic' => $conditional,
            'required' => $required,
        ];
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    private function yesNo(): array
    {
        return [
            ['value' => 'yes', 'label' => 'Yes'],
            ['value' => 'no', 'label' => 'No'],
        ];
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, array{value:string, label:string}>
     */
    private function options(array $labels): array
    {
        return array_map(
            static fn (string $label): array => [
                'value' => Str::slug($label, '_'),
                'label' => $label,
            ],
            $labels,
        );
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    private function likert(): array
    {
        return [
            ['value' => '1', 'label' => 'Very low'],
            ['value' => '2', 'label' => 'Low'],
            ['value' => '3', 'label' => 'Moderate'],
            ['value' => '4', 'label' => 'High'],
            ['value' => '5', 'label' => 'Very high'],
        ];
    }
}
