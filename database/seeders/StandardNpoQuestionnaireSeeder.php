<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StandardNpoQuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::STANDARD_NPO->value,
                    'version' => '1',
                ],
                [
                    'title' => 'Standard NPO Health Questionnaire',
                    'published_at' => now(),
                ],
            );

            if ($questionnaire->sections()->exists()) {
                return;
            }

            $sections = [
                [
                    'title' => 'Organisation Profile',
                    'help_text' => 'Core profile information that frames the NPO health assessment.',
                    'questions' => [
                        $this->question('Describe the organisation purpose, legal form, and primary communities served.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Confirm the current legal structure.', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'Registered Charity',
                            'Incorporated Society',
                            'Charitable Trust Board',
                            'Company with charitable purposes',
                            'Social Enterprise',
                            'Unincorporated community organisation',
                        ])),
                        $this->question('How many paid staff, contractors, and active volunteers support the organisation?', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Attach the current constitution, rules, trust deed, or governing document.', QuestionnaireQuestionType::FILE_ATTACH),
                    ],
                ],
                [
                    'title' => 'Strategic Direction',
                    'help_text' => 'Strategic priorities, change context, and planning horizon.',
                    'questions' => [
                        $this->question('Summarise the current strategic plan or main strategic direction.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('What timeframe does the current strategy cover?', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'Less than 12 months',
                            'One to two years',
                            'Three to five years',
                            'More than five years',
                            'No current strategy',
                        ])),
                        $this->question('Rate confidence that the board and leadership team are aligned on strategic priorities.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('List the top three strategic risks or opportunities the organisation is navigating.', QuestionnaireQuestionType::LONG_TEXT),
                    ],
                ],
                [
                    'title' => 'Mission and Strategy',
                    'help_text' => 'Dimension 1: mission clarity, strategic coherence, and beneficiary alignment.',
                    'questions' => [
                        $this->question('How clearly are mission, vision, and values documented and used in decisions?', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Describe how programmes or services are checked against the mission.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Which groups have meaningful input into strategy?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Board or trustees',
                            'Senior leaders',
                            'Staff',
                            'Volunteers',
                            'Beneficiaries',
                            'Community partners',
                            'Mana whenua or iwi representatives',
                            'Funders',
                        ])),
                        $this->question('Attach the current strategic plan or planning summary.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
                    ],
                ],
                [
                    'title' => 'Service Delivery and Operations',
                    'help_text' => 'Dimension 2: delivery model, service reliability, and operational controls.',
                    'questions' => [
                        $this->question('Describe the main services, programmes, or products delivered.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Rate the reliability of core service delivery processes.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('How are operational risks, incidents, or complaints recorded and escalated?', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Which operating constraints are currently material?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Capacity',
                            'Facilities',
                            'Digital systems',
                            'Volunteer availability',
                            'Supplier or partner dependency',
                            'Regulatory requirements',
                            'Funding restrictions',
                        ])),
                    ],
                ],
                [
                    'title' => 'Governance and Compliance',
                    'help_text' => 'Dimension 3: governance discipline, compliance obligations, and board assurance.',
                    'questions' => [
                        $this->question('Rate board confidence that legal, charity, and reporting obligations are current.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Confirm the current statutory or registration status.', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'All current',
                            'Minor updates needed',
                            'Material updates underway',
                            'Overdue or uncertain',
                            'Not applicable',
                        ])),
                        $this->question('Describe how conflicts, delegations, and board decisions are recorded.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Attach the latest board pack, minutes, compliance register, or governance dashboard.', QuestionnaireQuestionType::FILE_ATTACH),
                    ],
                ],
                [
                    'title' => 'Financial Sustainability',
                    'help_text' => 'Dimension 4: financial stewardship, reserves, controls, and reporting cadence.',
                    'questions' => [
                        $this->question('Attach the latest financial statements or management accounts.', QuestionnaireQuestionType::FILE_ATTACH),
                        $this->question('How many months of unrestricted operating reserves are available?', QuestionnaireQuestionType::NUMBER, required: false),
                        $this->question('Rate confidence in financial sustainability over the next 12 months.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Describe budget controls, delegated approvals, and reporting to the board.', QuestionnaireQuestionType::LONG_TEXT),
                    ],
                ],
                [
                    'title' => 'People and Capability',
                    'help_text' => 'Dimension 5: staffing, volunteers, capability, safety, and succession.',
                    'questions' => [
                        $this->question('Describe the staff, contractor, and volunteer capability needed to deliver the plan.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Rate confidence in succession coverage for key roles.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Which people systems are currently documented?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Employment agreements',
                            'Volunteer agreements',
                            'Health and safety process',
                            'Performance conversations',
                            'Safeguarding process',
                            'Training records',
                            'Succession plan',
                        ])),
                        $this->question('Attach a people, volunteer, safeguarding, or health and safety policy if available.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
                    ],
                ],
                [
                    'title' => 'Impact Measurement',
                    'help_text' => 'Dimension 6: outcomes, learning loops, and evidence of impact.',
                    'questions' => [
                        $this->question('Describe the outcomes the organisation is trying to achieve.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('How often are impact measures reviewed by leadership or the board?', QuestionnaireQuestionType::SINGLE_SELECT, options: $this->options([
                            'Monthly',
                            'Quarterly',
                            'Twice yearly',
                            'Annually',
                            'Ad hoc',
                            'Not currently measured',
                        ])),
                        $this->question('Rate confidence that impact data is reliable enough for decisions and funder reporting.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Attach the latest impact report, evaluation, dashboard, or outcomes summary.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
                    ],
                ],
                [
                    'title' => 'Te Tiriti',
                    'help_text' => 'Dimension 8 in Mode A: standalone Te Tiriti obligations, partnerships, and outcomes evidence.',
                    'questions' => [
                        $this->question('Describe how Te Tiriti obligations, commitments, or expectations apply to the organisation.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Which Te Tiriti practices are active today?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Board-level accountability',
                            'Mana whenua relationship',
                            'Maori outcomes measures',
                            'Cultural safety practice',
                            'Partnership agreements',
                            'Funder-specific obligations',
                            'Not yet established',
                        ])),
                        $this->question('Rate confidence that Te Tiriti commitments are visible in governance, delivery, and impact reporting.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Attach relevant Te Tiriti, partnership, equity, or cultural safety documentation.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
                    ],
                ],
                [
                    'title' => 'Funding Resilience',
                    'help_text' => 'Dimension 7: revenue mix, funder concentration, pipeline, and restricted funding risk.',
                    'questions' => [
                        $this->question('List the main current funding sources and approximate share of annual revenue.', QuestionnaireQuestionType::LONG_TEXT),
                        $this->question('Rate confidence that funding is resilient for the next 12 to 24 months.', QuestionnaireQuestionType::LIKERT, options: $this->likert()),
                        $this->question('Which funding risks are material?', QuestionnaireQuestionType::MULTI_SELECT, options: $this->options([
                            'Single-funder concentration',
                            'Short grant cycles',
                            'Restricted funding',
                            'Late reporting',
                            'Contract renewal risk',
                            'Donation volatility',
                            'Earned-income volatility',
                        ])),
                        $this->question('Attach key funding agreements, grant conditions, or pipeline documentation.', QuestionnaireQuestionType::FILE_ATTACH, required: false),
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
        array $options = [],
        bool $required = true,
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type->value,
            'prompt' => $prompt,
            'help_text' => null,
            'options' => $options,
            'conditional_logic' => null,
            'required' => $required,
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
