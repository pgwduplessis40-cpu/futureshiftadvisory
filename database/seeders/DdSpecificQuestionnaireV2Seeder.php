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
 * Version 2 of the Due Diligence (dd_specific) questionnaire — the full,
 * researched base content, completed on the target/vendor side. Each question
 * carries the "why we need this" as help_text. Published after v1, so the
 * resolver makes this the active set; v1 is retained for history.
 *
 * Per scope decision: the addendum's "Clarifying questions for you" (asset vs
 * share, regulated, cross-border) are acquirer/engagement-setup questions, not
 * target questions, and the "Red Flags Checklist" is advisor-facing / auto-
 * detected — both are intentionally excluded from this target questionnaire.
 */
final class DdSpecificQuestionnaireV2Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                [
                    'set' => QuestionnaireSet::DUE_DILIGENCE->value,
                    'version' => '2',
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
                    'title' => 'Deal Summary and Structure',
                    'help_text' => 'How the transaction is framed — what transfers, how, and on what timetable.',
                    'questions' => [
                        $this->q('Transaction type — asset sale or share sale?', QuestionnaireQuestionType::SINGLE_SELECT, 'Decides which liabilities and contracts transfer to the buyer.', options: $this->options(['Asset sale', 'Share sale', 'Undecided'])),
                        $this->q('Proposed price and payment mix.', QuestionnaireQuestionType::LONG_TEXT, 'Cash, vendor finance, earnout, or combination? Shows how risk and seller incentives are shared.'),
                        $this->q('Target completion date and key milestones.', QuestionnaireQuestionType::LONG_TEXT, 'Dates for exclusivity, DD, signing, and settlement. Sets the timetable and conditionality.'),
                    ],
                ],
                [
                    'title' => 'Financial Quality and Earnings',
                    'help_text' => 'Verifying true, recurring earnings and the working-capital position.',
                    'questions' => [
                        $this->q('3–5 years P&L, balance sheet, cashflow, and last 24 months monthly management accounts.', QuestionnaireQuestionType::FILE_ATTACH, 'Verifies trends, seasonality and normalised earnings.', required: false),
                        $this->q('Owner discretionary adjustments and one-off items.', QuestionnaireQuestionType::LONG_TEXT, 'Describe and quantify, so we can calculate true recurring EBITDA.'),
                        $this->q('Working capital detail.', QuestionnaireQuestionType::LONG_TEXT, 'Aged receivables/payables, inventory, customer prepayments. Sets the working-capital target at close.'),
                    ],
                ],
                [
                    'title' => 'Liabilities, Debt and Tax',
                    'help_text' => 'Hidden obligations that change price or structure.',
                    'questions' => [
                        $this->q('All loan agreements, guarantees, security interests, and covenant history.', QuestionnaireQuestionType::FILE_ATTACH, 'Hidden debt or guarantees change price and structure.', required: false),
                        $this->q('Recent tax returns, GST filings, and any tax audits or disputes.', QuestionnaireQuestionType::FILE_ATTACH, 'Tax exposures differ by asset vs share sale.', required: false),
                    ],
                ],
                [
                    'title' => 'Contracts, Customers and Suppliers',
                    'help_text' => 'Revenue concentration and change-of-control risk in key relationships.',
                    'questions' => [
                        $this->q('Top 20 customers and % revenue, contract terms, expiry, and auto-renewal clauses.', QuestionnaireQuestionType::LONG_TEXT, 'Customer concentration is a major value and risk driver.'),
                        $this->q('Material supplier contracts, exclusivity, and change-of-control consent requirements.', QuestionnaireQuestionType::LONG_TEXT, 'Supplier consent or exclusivity can block or reprice the deal.'),
                    ],
                ],
                [
                    'title' => 'People and HR',
                    'help_text' => 'Staff transfer rules, retention risk, and payroll liabilities.',
                    'questions' => [
                        $this->q('Employees, contractors, key persons, employment contracts, non-competes, and payroll liabilities.', QuestionnaireQuestionType::LONG_TEXT, 'Staff transfer rules and retention risk affect continuity and cost.'),
                    ],
                ],
                [
                    'title' => 'Property, Leases and Premises',
                    'help_text' => 'Premises continuity and landlord consent.',
                    'questions' => [
                        $this->q('Leases, rent reviews, assignment consent clauses, and landlord contacts.', QuestionnaireQuestionType::FILE_ATTACH, 'Lease consent can be a deal breaker in asset purchases.', required: false),
                    ],
                ],
                [
                    'title' => 'IP, IT and Data Privacy',
                    'help_text' => 'What intellectual property actually transfers, and data-handling exposure.',
                    'questions' => [
                        $this->q('Ownership of trademarks, domain names, source code, licences, and software subscriptions.', QuestionnaireQuestionType::LONG_TEXT, 'Confirms what IP actually transfers and any licence restrictions.'),
                        $this->q('Data privacy compliance and history of breaches.', QuestionnaireQuestionType::LONG_TEXT, 'Privacy exposure and breach history are post-close liabilities.'),
                    ],
                ],
                [
                    'title' => 'Regulatory, Insurance and Environmental',
                    'help_text' => 'Sector-specific obligations and claims exposure.',
                    'questions' => [
                        $this->q('Licences, permits, renewal dates, insurance policies, and claims history.', QuestionnaireQuestionType::LONG_TEXT, 'Regulated sectors need early checks to avoid post-close surprises.'),
                    ],
                ],
                [
                    'title' => 'Integration, Synergies and Post-Close Costs',
                    'help_text' => 'The true cost and timing of capturing the deal’s value.',
                    'questions' => [
                        $this->q('Systems to migrate, estimated one-time integration costs, and required vendor handover period.', QuestionnaireQuestionType::LONG_TEXT, 'Models true cost of capture and timing of synergies.'),
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
                ->where('set', QuestionnaireSet::DUE_DILIGENCE->value)
                ->whereKeyNot($questionnaire->getKey())
                ->whereNotNull('published_at')
                ->update(['published_at' => null]);
        });
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
