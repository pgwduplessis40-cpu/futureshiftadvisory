<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Questionnaire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Version 2 of the Standard Advisory (new client) questionnaire — the full,
 * researched base content. Each question carries the "why we need this" as
 * help_text. Published after v1 (later published_at), so the resolver
 * (forSet()->published()->orderByDesc('published_at')) makes this the active set.
 * v1 is left intact for history / any existing responses.
 */
final class StandardAdvisoryQuestionnaireV2Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::STANDARD_ADVISORY->value,
                    'version' => '2',
                ],
                [
                    'title' => 'Standard Advisory Questionnaire',
                    'published_at' => now(),
                ],
            );

            if ($questionnaire->sections()->exists()) {
                return;
            }

            $legalDisputeId = (string) Str::uuid();
            $legalDisputeDetailId = (string) Str::uuid();

            $sections = [
                [
                    'title' => 'Business Overview',
                    'help_text' => 'Foundational context for the entity, ownership, and operating model.',
                    'questions' => [
                        $this->q('Describe the business in plain English.', QuestionnaireQuestionType::LONG_TEXT, 'Tell us what your business does and who it helps in one or two sentences.'),
                        $this->q('Legal structure of the business.', QuestionnaireQuestionType::SINGLE_SELECT, 'What legal form is the business? This affects tax and liability.', options: $this->options(['Sole trader', 'Partnership', 'Company', 'Trust', 'Other'])),
                        $this->q('Owners and ownership split.', QuestionnaireQuestionType::LONG_TEXT, 'List each owner and the percentage they own.'),
                        $this->q('Shareholder or owners’ agreement.', QuestionnaireQuestionType::FILE_ATTACH, 'Do you have one? If yes, upload it. It explains how owners make decisions and transfer ownership.', required: false),
                        $this->q('Planned ownership or management changes in the next 12 months?', QuestionnaireQuestionType::SINGLE_SELECT, 'Are you planning to sell, bring in a partner, or change management?', options: $this->yesNo()),
                        $this->q('Why was the business started?', QuestionnaireQuestionType::TEXT, 'In one sentence, why did you start this business?'),
                        $this->q('Top three business risks you worry about.', QuestionnaireQuestionType::LONG_TEXT, 'List the biggest things that could go wrong (cash, customers, suppliers, people).'),
                    ],
                ],
                [
                    'title' => 'Products and Services',
                    'help_text' => 'What the business sells, at what margin, and how concentrated revenue is.',
                    'questions' => [
                        $this->q('Core products or services and usual selling price.', QuestionnaireQuestionType::LONG_TEXT, 'List each core product/service and the typical price you charge.'),
                        $this->q('Gross profit or margin per product/service.', QuestionnaireQuestionType::LONG_TEXT, 'Gross profit = price minus direct cost. If you don’t know, write an estimate.'),
                        $this->q('Units or orders sold per month (typical).', QuestionnaireQuestionType::LONG_TEXT, 'How many of each product or service do you sell in a normal month?'),
                        $this->q('Product lifecycle stage.', QuestionnaireQuestionType::LONG_TEXT, 'Is each product in launch, growth, mature, or decline?'),
                        $this->q('IP, patents, trademarks, or licences.', QuestionnaireQuestionType::LONG_TEXT, 'Do any products rely on special rights or licences?', required: false),
                        $this->q('Delivery terms.', QuestionnaireQuestionType::MULTI_SELECT, 'How and when do customers receive products or services?', options: $this->options(['Immediate', 'Scheduled', 'On credit'])),
                    ],
                ],
                [
                    'title' => 'Market and Outcomes',
                    'help_text' => 'Customer segments, competition, and demand signals.',
                    'questions' => [
                        $this->q('Primary customer (describe simply).', QuestionnaireQuestionType::TEXT, 'Who buys from you? (e.g., homeowners, small cafes, midsized retailers, age group, location.)'),
                        $this->q('Active sales channels.', QuestionnaireQuestionType::MULTI_SELECT, 'Which channels are currently active?', options: $this->options(['Website', 'Social', 'Referrals', 'Marketplace', 'Wholesale', 'Other'])),
                        $this->q('Top competitors (names and websites).', QuestionnaireQuestionType::LONG_TEXT, 'Who else do customers buy from? List names and websites if known.', required: false),
                        $this->q('What competitors do better than you.', QuestionnaireQuestionType::LONG_TEXT, 'Short note on their advantage (price, brand, delivery, features).', required: false),
                        $this->q('% of revenue from top product or top customer.', QuestionnaireQuestionType::NUMBER, 'If one product or customer makes most income, tell us the approximate %.', required: false),
                        $this->q('Demand trend over the last 12 months.', QuestionnaireQuestionType::SINGLE_SELECT, 'Is demand growing, steady, or falling?', options: $this->options(['Growing', 'Steady', 'Falling'])),
                    ],
                ],
                [
                    'title' => 'Financial Position',
                    'help_text' => 'High-level financial signals and the evidence-attachment path.',
                    'questions' => [
                        $this->q('Approximate annual revenue for the last completed year.', QuestionnaireQuestionType::CURRENCY, 'Give the total sales for the last full year.'),
                        $this->q('Latest financial statements.', QuestionnaireQuestionType::FILE_ATTACH, 'Upload profit & loss and balance sheet if available.', required: false),
                        $this->q('Prior-year financial statements.', QuestionnaireQuestionType::FILE_ATTACH, 'If you have them, upload them.', required: false),
                        $this->q('Current cash balance and runway (months).', QuestionnaireQuestionType::LONG_TEXT, 'How much cash do you have and how many months can you operate without new income?'),
                        $this->q('Outstanding loans and repayment terms.', QuestionnaireQuestionType::LONG_TEXT, 'List lenders, balances and monthly repayments.', required: false),
                        $this->q('Receivables and payables ageing.', QuestionnaireQuestionType::LONG_TEXT, 'Do customers pay on time? How old are unpaid invoices?', required: false),
                        $this->q('Any one-off items last year.', QuestionnaireQuestionType::LONG_TEXT, 'Large sale, legal cost, or other unusual items we should know about.', required: false),
                    ],
                ],
                [
                    'title' => 'People and HR',
                    'help_text' => 'Team size, people risks, and HR maturity.',
                    'questions' => [
                        $this->q('Number of staff and contractors.', QuestionnaireQuestionType::LONG_TEXT, 'List full-time, part-time and contractors separately.'),
                        $this->q('Do you have written employment contracts and contractor agreements?', QuestionnaireQuestionType::SINGLE_SELECT, 'Written agreements reduce employment risk. Upload if available.', options: $this->yesNo()),
                        $this->q('Org chart or list of key roles.', QuestionnaireQuestionType::LONG_TEXT, 'Who does what and who covers critical roles?', required: false),
                        $this->q('Key person dependency.', QuestionnaireQuestionType::LONG_TEXT, 'Who could stop the business if they left?'),
                        $this->q('Payroll cost and benefits as % of revenue.', QuestionnaireQuestionType::NUMBER, 'If known, give a rough percentage.', required: false),
                        $this->q('When did staff last have performance reviews or training?', QuestionnaireQuestionType::TEXT, 'Month/year or “Not recently.”', required: false),
                    ],
                ],
                [
                    'title' => 'Operations',
                    'help_text' => 'Operational systems, dependencies, and delivery risk.',
                    'questions' => [
                        $this->q('Core systems used day to day.', QuestionnaireQuestionType::LONG_TEXT, 'List accounting software, CRM, inventory system, POS, or spreadsheets.'),
                        $this->q('Supplier concentration (% from top three suppliers).', QuestionnaireQuestionType::NUMBER, 'What % of purchases come from your top three suppliers?', required: false),
                        $this->q('Do you have standard operating procedures (SOPs)?', QuestionnaireQuestionType::SINGLE_SELECT, 'Written procedures for core tasks reduce key-person risk. Upload if available.', options: $this->yesNo()),
                        $this->q('Do you have a business continuity or disaster recovery plan?', QuestionnaireQuestionType::SINGLE_SELECT, 'A simple plan for major disruptions.', options: $this->yesNo()),
                        $this->q('Manual tasks that take a lot of time each week.', QuestionnaireQuestionType::LONG_TEXT, 'List tasks that feel repetitive or slow.', required: false),
                    ],
                ],
                [
                    'title' => 'Sales and Marketing',
                    'help_text' => 'Demand generation, sales process, and growth constraints.',
                    'questions' => [
                        $this->q('Which channels generate paying customers?', QuestionnaireQuestionType::MULTI_SELECT, 'List the channels that actually produce sales (not just website visits).', options: $this->options(['Website', 'Social', 'Referrals', 'Marketplace', 'Wholesale', 'Email', 'Paid ads', 'Events', 'Other'])),
                        $this->q('Website URL and main product/service pages.', QuestionnaireQuestionType::LONG_TEXT, 'List the homepage and the pages that explain what you sell, including product, service, pricing, booking, or enquiry pages.', required: false),
                        $this->q('How accurately does the website describe what you sell?', QuestionnaireQuestionType::LONG_TEXT, 'Note any products/services, prices, locations, customer segments, proof points, or offers that are missing, outdated, or unclear online.', required: false),
                        $this->q('Search and AI discoverability evidence.', QuestionnaireQuestionType::LONG_TEXT, 'Share known SEO, local search, structured data, FAQ, answer-engine, AI Overview, GEO, AEO, or AIO issues/opportunities.', required: false),
                        $this->q('Leads per month and conversion to customers.', QuestionnaireQuestionType::LONG_TEXT, 'How many enquiries, and how many become paying customers?', required: false),
                        $this->q('Average sales cycle length.', QuestionnaireQuestionType::TEXT, 'From first contact to payment — days or weeks.', required: false),
                        $this->q('Monthly marketing spend and best-performing channels.', QuestionnaireQuestionType::LONG_TEXT, 'How much you spend and which channels give the best results.', required: false),
                        $this->q('Biggest barrier to more sales.', QuestionnaireQuestionType::SINGLE_SELECT, 'What most holds back sales?', options: $this->options(['Price', 'Awareness', 'Trust', 'Delivery', 'Product fit', 'Other'])),
                    ],
                ],
                [
                    'title' => 'Strategy and Goals',
                    'help_text' => 'Near-term ambition, plans, and what to prioritise.',
                    'questions' => [
                        $this->q('Top three business goals for the next 12 months.', QuestionnaireQuestionType::LONG_TEXT, 'List measurable goals (e.g., increase revenue 20%, hire operations manager).'),
                        $this->q('Written business or strategic plan.', QuestionnaireQuestionType::FILE_ATTACH, 'If you have one, upload it.', required: false),
                        $this->q('Planned capital projects or M&A targets.', QuestionnaireQuestionType::LONG_TEXT, 'Any planned investments, equipment purchases, or acquisitions?', required: false),
                        $this->q('Exit plan or preferred timeline for sale/handover.', QuestionnaireQuestionType::LONG_TEXT, 'Do you plan to sell or hand over the business? When?', required: false),
                        $this->q('Single most important thing an advisor should fix first.', QuestionnaireQuestionType::LONG_TEXT, 'Short answer to help us prioritise.'),
                    ],
                ],
                [
                    'title' => 'Compliance and Risk',
                    'help_text' => 'Regulatory exposure, insurance, and open issues.',
                    'questions' => [
                        $this->q('Licences and renewal dates.', QuestionnaireQuestionType::LONG_TEXT, 'Any licences, registrations or permits needed to trade?', required: false),
                        $this->q('Insurance coverage and renewal dates.', QuestionnaireQuestionType::LONG_TEXT, 'Type of insurance and when it renews.', required: false),
                        $this->q('Any current or potential legal disputes?', QuestionnaireQuestionType::SINGLE_SELECT, 'Disputes can affect value and risk.', id: $legalDisputeId, options: $this->yesNo()),
                        $this->q('Describe the legal dispute(s).', QuestionnaireQuestionType::LONG_TEXT, 'Brief description if yes.', id: $legalDisputeDetailId, required: false, conditional: [
                            'when' => $legalDisputeId,
                            'equals' => 'yes',
                            'show' => $legalDisputeDetailId,
                        ]),
                        $this->q('When was the last risk review or audit?', QuestionnaireQuestionType::TEXT, 'Month/year or “Not done.”', required: false),
                    ],
                ],
                [
                    'title' => 'Owner and Leadership',
                    'help_text' => 'Owner involvement, time, reward, and succession.',
                    'questions' => [
                        $this->q('Owner involvement in daily operations.', QuestionnaireQuestionType::LIKERT, 'How hands-on is the owner day to day?', options: $this->likert()),
                        $this->q('Owner working hours per week and desired hours.', QuestionnaireQuestionType::LONG_TEXT, 'How many hours you work now and how many you want to work.'),
                        $this->q('Current owner salary/draw and desired salary.', QuestionnaireQuestionType::LONG_TEXT, 'What you pay yourself now and what you’d like to receive.', required: false),
                        $this->q('Succession or leadership cover plan.', QuestionnaireQuestionType::LONG_TEXT, 'Describe briefly who would run the business if you stepped back.'),
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

            // v2 is the sole published base; unpublish prior versions so the
            // "latest published" resolvers (and tests) are deterministic even
            // when the clock is frozen and published_at would otherwise tie.
            Questionnaire::query()
                ->where('set', QuestionnaireSet::STANDARD_ADVISORY->value)
                ->whereKeyNot($questionnaire->getKey())
                ->whereNotNull('published_at')
                ->update(['published_at' => null]);
        });
    }

    /**
     * @param  array<int, array{value:string, label:string}>  $options
     * @param  array<string, mixed>|null  $conditional
     * @return array<string, mixed>
     */
    private function q(
        string $prompt,
        QuestionnaireQuestionType $type,
        ?string $helpText = null,
        ?string $id = null,
        array $options = [],
        ?array $conditional = null,
        bool $required = true,
    ): array {
        return [
            'id' => $id ?? (string) Str::uuid(),
            'type' => $type->value,
            'prompt' => $prompt,
            'help_text' => $helpText,
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
