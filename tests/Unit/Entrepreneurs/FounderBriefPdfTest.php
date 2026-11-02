<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\PlanSection;
use App\Services\Entrepreneurs\BudgetCalculator;
use App\Services\Entrepreneurs\BusinessPlanExecutiveSummary;
use App\Services\Entrepreneurs\FounderBriefPages;
use App\Services\Entrepreneurs\FunderReadyBriefBuilder;
use App\Services\Pdf\BrowsershotRenderer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

final class FounderBriefPdfTest extends TestCase
{
    public function test_brief_includes_saved_financials_and_keeps_one_off_payments_separate(): void
    {
        [$profile, $plan, $status] = $this->fixture();
        $pages = app(FounderBriefPages::class)->build($profile, $plan, $status, true);
        $this->assertCount(11, $pages);
        $this->assertSame('Three-year financial outlook', $pages[4]['title']);
        $this->assertSame('Year 3', $pages[4]['tables'][0]['headers'][3]);
        $this->assertCount(12, $pages[5]['tables'][0]['rows']);
        $this->assertSame(['Trademark registration', '$200.00', 'Month 4 - once'], $pages[8]['tables'][0]['rows'][0]);
        $this->assertStringNotContainsString('Trademark', json_encode($pages[7]));
        $this->assertSame('Month 12', $pages[5]['tables'][0]['rows'][11][0]);
        $this->assertSame(
            FounderBriefPages::money(data_get($plan->budgetRunway->computed, 'annual_totals.0.ending_cash')),
            $pages[4]['tables'][0]['rows'][8][1],
        );
    }

    public function test_missing_summary_and_budget_are_honestly_labelled_in_review_copy(): void
    {
        [$profile, $plan, $status] = $this->fixture();
        $plan->setRelation('budgetRunway', null);
        $status['executive_summary']['usable'] = false;
        $pages = app(FounderBriefPages::class)->build($profile, $plan, $status, true);
        $this->assertStringContainsString('not available', $pages[1]['paragraphs'][0]);
        $this->assertStringNotContainsString('The illustrative business', $pages[1]['paragraphs'][0]);
        $this->assertSame('No saved figures available', $pages[4]['tables'][0]['rows'][0][0]);
        $this->assertStringContainsString('Opening cash: Not available', $pages[5]['paragraphs'][1]);
    }

    public function test_normal_and_dense_fallback_pdfs_stay_within_eight_to_twelve_pages(): void
    {
        Schema::shouldReceive('hasTable')->with('templates')->andReturn(false);
        $builder = app(FunderReadyBriefBuilder::class);
        foreach ([false, true] as $dense) {
            [$profile, $plan, $status] = $this->fixture($dense);
            $pages = app(FounderBriefPages::class)->build($profile, $plan, $status, true);
            $pdf = (new ReflectionMethod($builder, 'fallback'))->invoke($builder, 'Illustrative Studio', $pages, 'ADVISOR DRAFT - not approved for external issue');
            preg_match('/\/Count (\d+)/', $pdf, $matches);
            $count = (int) ($matches[1] ?? 0);
            $this->assertGreaterThanOrEqual(8, $count);
            $this->assertLessThanOrEqual(12, $count, 'Dense fallback must retain a readable brief within the page budget.');
            $this->assertStringContainsString('Trademark registration', $pdf);
            $this->assertStringContainsString('ADVISOR DRAFT', $pdf);
            // Optional local visual QA artifacts; no client records or network calls.
            if (getenv('FOUNDER_BRIEF_QA_DIR')) {
                $dir = (string) getenv('FOUNDER_BRIEF_QA_DIR');
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $name = $dense ? 'dense' : 'sample';
                file_put_contents($dir.'/'.$name.'-fallback.pdf', $pdf);
                $html = (new ReflectionMethod($builder, 'html'))->invoke($builder, 'Illustrative Studio', $pages, $status, 'ADVISOR DRAFT - not approved for external issue', 'ADVISOR DRAFT - not approved for external issue');
                file_put_contents($dir.'/'.$name.'.html', $html);
                file_put_contents($dir.'/'.$name.'.pdf', app(BrowsershotRenderer::class)->render($html));
            }
        }
    }

    private function fixture(bool $dense = false): array
    {
        $profile = new EntrepreneurProfile(['name' => 'Illustrative founder']);
        $sections = [];
        $copy = 'The illustrative business provides practical workshops for small service businesses. Customers pay per workshop and can choose continuing support. Pilot delivery informs pricing and capacity. The founder reviews customer demand and cash reserves before increasing commitments.';
        foreach ([
            'business-type-location', 'mission-vision', 'industry-context', 'differentiation',
            'revenue-model', 'organisation-management', 'goals-objectives', 'risk-register',
        ] as $key) {
            $sections[] = new PlanSection(['key' => 'founder-'.$key, 'title' => $key, 'metadata' => ['requirement_key' => $key], 'body' => $dense ? str_repeat($copy, 15) : $copy, 'attached_document_ids' => []]);
        }
        $sections[] = new PlanSection(['key' => BusinessPlanExecutiveSummary::SECTION_KEY, 'body' => str_repeat($copy.' ', $dense ? 30 : 4)]);
        $launch = [['label' => 'Trademark registration', 'amount' => 100, 'quantity' => 2, 'month' => 4]];
        $fixed = [['label' => 'Workspace', 'amount' => 900, 'quantity' => 1, 'cadence' => 'monthly', 'cadence_confirmed' => true]];
        $future = [['label' => 'Equipment renewal', 'amount' => 3000, 'quantity' => 1, 'year' => 3, 'recurring' => false]];
        if ($dense) {
            foreach (range(1, 25) as $index) {
                $label = 'Scheduled business cost '.$index.' '.str_repeat('with a detailed description ', 5);
                $launch[] = ['label' => $label, 'amount' => 20, 'quantity' => 2, 'month' => 6];
                $fixed[] = ['label' => $label, 'amount' => 12, 'quantity' => 1, 'cadence' => 'annual', 'cadence_confirmed' => true];
                $future[] = ['label' => $label, 'amount' => 50, 'quantity' => 1, 'year' => 2, 'recurring' => true];
            }
        }
        $revenue = [['label' => 'Workshops', 'amount' => 300, 'quantity' => 20, 'cadence' => 'monthly', 'gross_profit_percent' => 70]];
        $computed = app(BudgetCalculator::class)->compute($launch, $fixed, $revenue, [], 2, assumptions: ['opening_cash_balance' => 10000], futureCosts: $future, companyTaxRatePercent: 28);
        $budget = new EntrepreneurBudget(['launch_costs' => $launch, 'monthly_fixed_costs' => $fixed, 'revenue_forecast' => $revenue, 'future_costs' => $future, 'expected_runway_months' => 2, 'forecast_years' => 3, 'computed' => $computed]);
        $plan = (new BusinessPlan(['title' => 'Illustrative Studio']))->setRelation('sections', new Collection($sections))->setRelation('budgetRunway', $budget);
        $status = [
            'active' => false, 'label' => 'Review required', 'reasons' => ['Review source evidence.'],
            'executive_summary' => ['usable' => true, 'context_hash' => 'sample'], 'assessment_id' => null,
            'approval_token' => str_repeat('a', 64),
            'review' => ['can_approve' => true, 'approved' => false, 'note' => null, 'approved_at' => null, 'findings' => $dense ? array_fill(0, 15, str_repeat('Review the source evidence before external issue. ', 10)) : ['Review source evidence.']],
            'summary_recovery' => ['can_retry' => false, 'state' => 'complete', 'message' => 'Current'],
        ];

        return [$profile, $plan, $status];
    }
}
