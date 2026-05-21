<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StandardAdvisoryQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::STANDARD_ADVISORY->value,
                    'version' => '1',
                ],
                [
                    'title' => 'Standard Advisory Questionnaire',
                    'published_at' => now(),
                ],
            );

            if ($questionnaire->sections()->exists()) {
                return;
            }

            $ownershipChangeId = (string) Str::uuid();
            $ownershipSummaryId = (string) Str::uuid();
            $hasEmployeesId = (string) Str::uuid();
            $employeeCountId = (string) Str::uuid();
            $regulatedActivitiesId = (string) Str::uuid();
            $complianceNotesId = (string) Str::uuid();

            $sections = [
                [
                    'title' => 'Business Overview',
                    'help_text' => 'Foundational context for the entity, ownership, and operating model.',
                    'questions' => [
                        $this->question('Describe the business in plain English.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Is ownership expected to change in the next 12 months?', QuestionnaireQuestionType::SINGLE_SELECT, id: $ownershipChangeId, options: $this->yesNo()),
                        $this->question('Summarise the expected ownership change.', QuestionnaireQuestionType::LONG_TEXT, id: $ownershipSummaryId, conditional: [
                            'when' => $ownershipChangeId,
                            'equals' => 'yes',
                            'show' => $ownershipSummaryId,
                        ]),
                    ],
                ],
                [
                    'title' => 'Products and Services',
                    'help_text' => 'What the business sells and how concentrated revenue is across offerings.',
                    'questions' => [
                        $this->question('List the core products or services.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('What percentage of revenue comes from the top product or service?', QuestionnaireQuestionType::NUMBER),
                    ],
                ],
                [
                    'title' => 'Market and Customers',
                    'help_text' => 'Customer segments, demand signals, and channel concentration.',
                    'questions' => [
                        $this->question('Who is the primary customer segment?', QuestionnaireQuestionType::TEXT),
                        $this->question('Which sales channels are currently active?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Direct',
                            'Online',
                            'Referral',
                            'Wholesale',
                            'Marketplace',
                            'Other',
                        ])),
                    ],
                ],
                [
                    'title' => 'Financial Position',
                    'help_text' => 'High-level financial signals and the first evidence attachment path.',
                    'questions' => [
                        $this->question('Approximate annual revenue for the last completed year.', QuestionnaireQuestionType::CURRENCY),
                        $this->question('Revenue trend over the last 12 months.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Attach the latest financial statements when uploads are enabled.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
                    ],
                ],
                [
                    'title' => 'People and HR',
                    'help_text' => 'Team size, people risks, and HR maturity.',
                    'questions' => [
                        $this->question('Does the business employ staff or contractors?', QuestionnaireQuestionType::SINGLE_SELECT, id: $hasEmployeesId, options: $this->yesNo()),
                        $this->question('How many staff or contractors are currently active?', QuestionnaireQuestionType::NUMBER, id: $employeeCountId, conditional: [
                            'when' => $hasEmployeesId,
                            'equals' => 'yes',
                            'show' => $employeeCountId,
                        ]),
                    ],
                ],
                [
                    'title' => 'Operations',
                    'help_text' => 'Operational systems, dependencies, and delivery risk.',
                    'questions' => [
                        $this->question('Which systems run day-to-day operations?', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Supplier or key-person dependency level.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                    ],
                ],
                [
                    'title' => 'Sales and Marketing',
                    'help_text' => 'Demand generation, sales process, and growth constraints.',
                    'questions' => [
                        $this->question('Which marketing channels generate qualified enquiries?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Website',
                            'Email',
                            'Social',
                            'Paid ads',
                            'Events',
                            'Partners',
                            'Word of mouth',
                        ])),
                        $this->question('What is the main sales or conversion challenge?', QuestionnaireQuestionType::LONG_TEXT),
                    ],
                ],
                [
                    'title' => 'Strategy and Goals',
                    'help_text' => 'Near-term ambition, deadlines, and success measures.',
                    'questions' => [
                        $this->question('Target date for the main advisory outcome.', QuestionnaireQuestionType::DATE),
                        $this->question('What strategic priority should the advisor understand first?', QuestionnaireQuestionType::LONG_TEXT),
                    ],
                ],
                [
                    'title' => 'Compliance and Risk',
                    'help_text' => 'Regulatory exposure, open issues, and material risk flags.',
                    'questions' => [
                        $this->question('Does the business have regulated activities or licences?', QuestionnaireQuestionType::SINGLE_SELECT, id: $regulatedActivitiesId, options: $this->yesNo()),
                        $this->question('Describe the licences, obligations, or open compliance issues.', QuestionnaireQuestionType::LONG_TEXT, id: $complianceNotesId, conditional: [
                            'when' => $regulatedActivitiesId,
                            'equals' => 'yes',
                            'show' => $complianceNotesId,
                        ]),
                    ],
                ],
                [
                    'title' => 'Owner and Leadership',
                    'help_text' => 'Owner involvement, succession planning, and leadership resilience.',
                    'questions' => [
                        $this->question('Owner involvement in daily operations.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Describe the current succession or leadership cover plan.', QuestionnaireQuestionType::LONG_TEXT),
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
