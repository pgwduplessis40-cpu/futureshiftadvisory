<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Version 2 of the buying-a-business due diligence questions. The wording is
 * intentionally plain because many buyers are not finance or M&A specialists.
 */
final class DdSpecificQuestionnaireV2Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->updateOrCreate(
                [
                    'set' => QuestionnaireSet::DUE_DILIGENCE->value,
                    'version' => '2',
                ],
                [
                    'title' => 'Buying a Business Questions',
                    'published_at' => now(),
                ],
            );

            $sections = [
                [
                    'title' => 'The deal basics',
                    'help_text' => 'The basic facts about what you may buy, how much it may cost, and when it may happen.',
                    'questions' => [
                        $this->q('Are you buying the business assets, the company shares, or are you not sure yet?', QuestionnaireQuestionType::SINGLE_SELECT, 'This changes what old debts, contracts, staff obligations, and problems may come with the purchase.', options: $this->options(['Assets only', 'Company shares', 'Not sure yet'])),
                        $this->q('What price is being discussed, and how would it be paid?', QuestionnaireQuestionType::LONG_TEXT, 'This helps us see how much cash you need, whether the seller is lending money, and whether the payment terms protect you.'),
                        $this->q('When do you hope to decide, sign, and take over?', QuestionnaireQuestionType::LONG_TEXT, 'This helps us plan what must be checked first and what could delay the purchase.'),
                    ],
                ],
                [
                    'title' => 'Sales, costs, and profit',
                    'help_text' => 'The money records help us see whether the business really earns what the seller says it earns.',
                    'questions' => [
                        $this->q('Upload any sales, cost, profit, cash, stock, money owed, or money owing reports you have.', QuestionnaireQuestionType::FILE_ATTACH, 'These reports help us check trends, busy seasons, slow seasons, and whether profit is steady or unusual.', required: false),
                        $this->q('Are there any costs, income, owner perks, or one-off items that may not continue after you buy?', QuestionnaireQuestionType::LONG_TEXT, 'This helps us work out what the business may earn for you after the current owner leaves.'),
                        $this->q('What money would need to stay in the business on takeover day?', QuestionnaireQuestionType::LONG_TEXT, 'This helps avoid surprises around stock, unpaid customer invoices, supplier bills, deposits, or cash needed to keep trading.'),
                    ],
                ],
                [
                    'title' => 'Debts, tax, and other amounts owed',
                    'help_text' => 'Old debts or unpaid tax can change the price or create risk for you.',
                    'questions' => [
                        $this->q('Upload any loan, guarantee, security, or debt documents you have.', QuestionnaireQuestionType::FILE_ATTACH, 'These documents help us check whether the business has debts or promises that could follow the sale.', required: false),
                        $this->q('Upload any recent tax, GST, or tax dispute information you have.', QuestionnaireQuestionType::FILE_ATTACH, 'Tax problems can reduce value or create costs after you buy.', required: false),
                    ],
                ],
                [
                    'title' => 'Customers, suppliers, and key agreements',
                    'help_text' => 'A business can lose value if important customers, suppliers, or agreements do not continue after the sale.',
                    'questions' => [
                        $this->q('Who are the most important customers, and how much of the sales come from them?', QuestionnaireQuestionType::LONG_TEXT, 'If too much income comes from a few customers, losing one customer could hurt the business.'),
                        $this->q('Which supplier, lease, customer, or other agreements are important to keep?', QuestionnaireQuestionType::LONG_TEXT, 'Some agreements need permission before they can move to a new owner, and that can delay or change the deal.'),
                    ],
                ],
                [
                    'title' => 'Staff and key people',
                    'help_text' => 'The people in the business often carry the customer knowledge and day-to-day know-how.',
                    'questions' => [
                        $this->q('Who works in the business, who is essential, and what staff costs or issues should we know about?', QuestionnaireQuestionType::LONG_TEXT, 'This helps us check whether the business can keep running smoothly after you take over.'),
                    ],
                ],
                [
                    'title' => 'Premises, rent, and leases',
                    'help_text' => 'If the business relies on a location, the lease or landlord approval can be critical.',
                    'questions' => [
                        $this->q('Upload lease, rent review, landlord, or premises documents you have.', QuestionnaireQuestionType::FILE_ATTACH, 'We need to check whether you can keep using the premises after the sale and what rent changes may happen.', required: false),
                    ],
                ],
                [
                    'title' => 'Names, systems, software, and customer data',
                    'help_text' => 'The business name, website, software, and customer data may not automatically transfer to you.',
                    'questions' => [
                        $this->q('What names, websites, software, systems, licences, or online accounts does the business use?', QuestionnaireQuestionType::LONG_TEXT, 'This helps us check what you actually get and what may need a new account, licence, or owner transfer.'),
                        $this->q('Does the business hold customer or staff information, and has there been any data or privacy problem?', QuestionnaireQuestionType::LONG_TEXT, 'Customer and staff information must be handled carefully, and past problems can create cost or trust issues.'),
                    ],
                ],
                [
                    'title' => 'Rules, insurance, and claims',
                    'help_text' => 'Some businesses need licences, permits, insurance, or special approvals to keep operating.',
                    'questions' => [
                        $this->q('What licences, permits, insurance policies, claims, or legal issues should we know about?', QuestionnaireQuestionType::LONG_TEXT, 'This helps us check whether the business can legally keep operating and whether there are hidden costs.'),
                    ],
                ],
                [
                    'title' => 'Taking over after purchase',
                    'help_text' => 'Buying the business is only the first step. You also need to know what it will take to run it after takeover.',
                    'questions' => [
                        $this->q('What will need to change, move, be set up, or be taught to you after settlement?', QuestionnaireQuestionType::LONG_TEXT, 'This helps us estimate takeover costs, training needs, system changes, and how long the seller may need to help.'),
                    ],
                ],
            ];

            $this->syncSections($questionnaire, $sections);

            // v2 is the sole published base; unpublish prior versions so the
            // latest-published resolver is deterministic.
            Questionnaire::query()
                ->where('set', QuestionnaireSet::DUE_DILIGENCE->value)
                ->whereKeyNot($questionnaire->getKey())
                ->whereNotNull('published_at')
                ->update(['published_at' => null]);
        });
    }

    /**
     * @param  array<int, array{title:string, help_text:string, questions:array<int, array<string, mixed>>}>  $sections
     */
    private function syncSections(Questionnaire $questionnaire, array $sections): void
    {
        foreach ($sections as $sectionIndex => $sectionData) {
            $section = $questionnaire->sections()
                ->where('order', $sectionIndex + 1)
                ->first();

            if ($section === null) {
                $section = $questionnaire->sections()->create([
                    'order' => $sectionIndex + 1,
                    'title' => $sectionData['title'],
                    'help_text' => $sectionData['help_text'],
                ]);
            } else {
                $section->forceFill([
                    'title' => $sectionData['title'],
                    'help_text' => $sectionData['help_text'],
                ])->save();
            }

            foreach ($sectionData['questions'] as $questionIndex => $questionData) {
                $question = $section->questions()
                    ->where('order', $questionIndex + 1)
                    ->first();

                if ($question === null) {
                    $section->questions()->create([
                        ...$questionData,
                        'order' => $questionIndex + 1,
                    ]);

                    continue;
                }

                $question->forceFill([
                    ...Arr::except($questionData, ['id']),
                    'order' => $questionIndex + 1,
                ])->save();
            }
        }
    }

    /**
     * @param  array<int, array{value:string, label:string}>  $options
     * @return array<string, mixed>
     */
    private function q(
        string $prompt,
        QuestionnaireQuestionType $type,
        ?string $helpText = null,
        array $options = [],
        bool $required = true,
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type->value,
            'prompt' => $prompt,
            'help_text' => $helpText,
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
}
