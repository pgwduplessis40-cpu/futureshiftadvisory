<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurBudget;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Entrepreneurs\BudgetCalculator;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelStore;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\ForecastModelFacts;
use App\Services\Entrepreneurs\ReleaseGate\GateContextFactory;
use App\Services\Entrepreneurs\ReleaseGate\GateReport;
use App\Services\Entrepreneurs\ReleaseGate\ReleaseGate;
use App\Services\Entrepreneurs\ReleaseGate\RuleResult;
use App\Services\Entrepreneurs\ReleaseGate\Subcheck;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ForecastModelInputsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every rule a selected reconstruction can legitimately move.
     *
     * The six phase-2 rules read it by design. MI-01 reads its cash ledger,
     * MI-02 its funding inventory, PM-12 the funding checks it copies from
     * MI-02, and SS-01 loses the budget scenarios built off the cash curve
     * the reconstruction replaced. Every other rule must be byte-identical
     * across a selection, which is what the three canaries below prove.
     *
     * MI-03 is deliberately not here. It moves only when a fixed-cost
     * schedule has been recorded, and these canaries select a reconstruction
     * that carries none, so listing it would excuse it from the comparison
     * rather than describe it. A reconstruction that does carry one is held
     * to its own list, beside the costed canary below.
     *
     * @var list<string>
     */
    private const FED_BY_THE_RECONSTRUCTION = ['PM-06', 'PM-11', 'MI-07', 'MI-08', 'SS-02', 'SS-03',
        'MI-01', 'MI-02', 'PM-12', 'SS-01'];

    private BusinessPlan $plan;

    private EntrepreneurProfile $profile;

    private User $advisor;

    private User $otherAdvisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('security.mfa_required', false);
        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        $this->advisor = $this->advisor('advisor');
        $this->otherAdvisor = $this->advisor('other');
        $owner = User::factory()->create(['user_type' => 'entrepreneur', 'primary_role' => 'entrepreneur']);
        $this->profile = EntrepreneurProfile::query()->create(['name' => 'Synthetic Founder', 'email' => $owner->email,
            'user_id' => $owner->id, 'assigned_advisor_id' => $this->advisor->id, 'stage' => 'idea_validation']);
        $this->plan = BusinessPlan::query()->create(['entrepreneur_profile_id' => $this->profile->id,
            'title' => 'Synthetic plan', 'source_type' => 'entrepreneur']);
    }

    public function test_a_plan_with_no_reconstruction_says_so_and_names_the_current_engine(): void
    {
        $page = $this->actingAsMfa($this->advisor)->get($this->url())->assertOk()->viewData('page');

        self::assertSame('advisor/entrepreneurs/ForecastModel', data_get($page, 'component'));
        self::assertNull(data_get($page, 'props.model'));
        self::assertSame('fsa-forecast.2d.1', data_get($page, 'props.engine.engine_version'));
        self::assertTrue(data_get($page, 'props.can_edit'));
    }

    public function test_saving_records_a_revision_and_reports_the_sections_it_did_not_capture(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect()->assertSessionHasNoErrors();

        $page = $this->get($this->url())->viewData('page');
        self::assertSame(1, data_get($page, 'props.model.revision'));
        self::assertFalse(data_get($page, 'props.model.selected'));
        self::assertTrue(data_get($page, 'props.model.readable'));
        // base 1000*3 = 3000; contingency (3000+500)*0.10 = 350.
        self::assertSame('3350.00', data_get($page, 'props.model.reserve.target'));
        self::assertSame([], data_get($page, 'props.model.reserve.missing'));
        $sections = collect(data_get($page, 'props.model.sections'))->keyBy('key');
        self::assertTrue($sections['calendar']['captured']);
        self::assertTrue($sections['actuals']['captured']);
        self::assertFalse($sections['streams']['captured'], 'An unentered section is reported as missing, not implied.');
        self::assertFalse($sections['bank_opening']['editable'], 'The bank reconciliation opening is still captured on a later screen.');
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.forecast_model_saved']);
    }

    public function test_saving_through_this_screen_leaves_sections_it_cannot_edit_untouched(): void
    {
        $store = app(ForecastModelStore::class);
        $store->save($this->plan, new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 12),
            streams: [new RevenueStream('intensive', 'Intensive day', 'day', '1200', '2', '6', true, 'own_record', [
                '2026-01' => new StreamMonth('4', '4', '4', '24', '0'),
            ])],
        ), $this->advisor);

        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect()->assertSessionHasNoErrors();

        $model = $store->forPlan($this->plan);
        self::assertNotNull($model);
        $inputs = $store->inputs($model);
        self::assertCount(1, $inputs->streams, 'A stream this screen never showed must survive a save from it.');
        self::assertSame('1200', $inputs->streams[0]->price);
        self::assertSame(24, $inputs->calendar->months, 'The edited calendar is still applied.');
    }

    public function test_selecting_a_reconstruction_moves_only_the_rules_that_read_it(): void
    {
        $gate = app(ReleaseGate::class);
        $before = $gate->evaluate($this->plan->refresh(), 'plan');
        self::assertCount(11, $before->deferred(), 'Six of the nine phase-2 rules are registered.');

        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/selection', ['selected' => true])->assertRedirect()->assertSessionHasNoErrors();

        $after = $gate->evaluate($this->plan->refresh(), 'plan');
        // The six phase-2 rules read the selected model, so their results are
        // expected to move. Every rule enabled before them must be
        // byte-identical: the producer withholds the keys those rules already
        // read, and this is the assertion that proves it.
        self::assertSame(
            CanonicalJson::encode($this->rulesEnabledBeforePhaseTwo($before)),
            CanonicalJson::encode($this->rulesEnabledBeforePhaseTwo($after)),
            'Selecting a reconstruction must move no rule that was enabled before it could be read.',
        );
        self::assertCount(11, $after->deferred());
    }

    public function test_the_elapsed_actuals_rule_reports_its_missing_evidence_before_a_model_supplies_it(): void
    {
        $gate = app(ReleaseGate::class);
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        // Saved but not selected, so nothing supplies the snapshot date or
        // the model start. The rule says what it is missing rather than
        // treating an absent record as a month with nothing to report.
        $unselected = $this->subchecks($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-07');
        self::assertCount(1, $unselected);
        self::assertSame('cannot_verify', $unselected[0]->status->value);
        self::assertNotSame([], $unselected[0]->missingInputs, 'Cannot verify has to name what is missing.');

        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();

        // Selected: the producer supplies the snapshot date and the model
        // start, so the rule reaches the elapsed months themselves.
        $selected = $this->subchecks($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-07');
        self::assertNotSame(
            CanonicalJson::encode($unselected),
            CanonicalJson::encode($selected),
            'A selected reconstruction is the evidence this rule was waiting for.',
        );
    }

    public function test_selection_and_deselection_are_recorded_and_shown(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->post($this->url().'/selection', ['selected' => true])->assertRedirect()->assertSessionHasNoErrors();
        $page = $this->get($this->url())->viewData('page');
        self::assertTrue(data_get($page, 'props.model.selected'));
        self::assertSame($this->advisor->name, data_get($page, 'props.model.selected_by'));
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.forecast_model_selected']);

        $this->post($this->url().'/selection', ['selected' => false])->assertRedirect()->assertSessionHasNoErrors();
        self::assertFalse(data_get($this->get($this->url())->viewData('page'), 'props.model.selected'));
        $this->assertDatabaseHas('audit_events', ['action' => 'entrepreneur.forecast_model_deselected']);
    }

    public function test_selection_without_a_saved_reconstruction_is_refused(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url().'/selection', ['selected' => true])->assertSessionHasErrors('selected');
        $this->assertDatabaseMissing('audit_events', ['action' => 'entrepreneur.forecast_model_selected']);
    }

    public function test_a_stale_revision_and_a_repeated_month_are_both_refused(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->post($this->url(), [...$this->payload(), 'expected_revision' => 0, 'months' => 36])->assertSessionHasErrors('revision');
        $repeated = $this->payload();
        $repeated['actuals'][] = $repeated['actuals'][0];
        $this->post($this->url(), $repeated)->assertSessionHasErrors('actuals');

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame(1, $model->revision, 'A refused save leaves the stored revision alone.');
    }

    public function test_consecutive_saves_each_advance_the_revision_they_were_loaded_against(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        // The page reloads at revision 1, so the next save holds revision 1.
        // The form is keyed on the revision for exactly this reason.
        $this->post($this->url(), [...$this->payload(), 'expected_revision' => 1, 'months' => 36])->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame(2, $model->revision);
        self::assertSame(36, app(ForecastModelStore::class)->inputs($model)->calendar->months);
    }

    public function test_a_value_the_engine_refuses_is_a_form_error_rather_than_a_failure(): void
    {
        $this->actingAsMfa($this->advisor)
            ->post($this->url(), [...$this->payload(), 'reserve_monthly_fixed_costs' => '-400'])
            ->assertSessionHasErrors('model');

        self::assertNull(app(ForecastModelStore::class)->forPlan($this->plan));
    }

    public function test_an_unassigned_advisor_can_neither_open_save_nor_select(): void
    {
        $this->actingAsMfa($this->otherAdvisor)->get($this->url())->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->post($this->url().'/selection', ['selected' => true])->assertForbidden();

        self::assertNull(app(ForecastModelStore::class)->forPlan($this->plan));
    }

    public function test_streams_and_founder_capacity_are_recorded_against_the_stored_calendar(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->post($this->url().'/drivers', $this->drivers())->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $inputs = app(ForecastModelStore::class)->inputs($model);
        self::assertCount(1, $inputs->streams);
        self::assertSame('workshops', $inputs->streams[0]->id);
        self::assertTrue($inputs->streams[0]->inBaseCase);
        self::assertSame('450', $inputs->streams[0]->price);
        self::assertCount(1, $inputs->streams[0]->schedule);
        self::assertSame('2', $inputs->streams[0]->schedule['2026-03']->bookedUnits);
        self::assertSame('120', $inputs->founderMonths['2026-03']->availableHours);
        self::assertSame(2, $model->revision, 'The driver save is a revision of the same reconstruction.');
    }

    public function test_an_unanswered_base_case_question_is_stored_as_absent_rather_than_as_exclusion(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $drivers = $this->drivers();
        // The select posts an empty string, not a null, when nobody has
        // answered. The unanswered state has to survive the wire as itself.
        $drivers['streams'][0]['in_base_case'] = '';
        $drivers['streams'][0]['evidence_status'] = '';

        $this->post($this->url().'/drivers', $drivers)->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $stream = app(ForecastModelStore::class)->inputs($model)->streams[0];
        self::assertNull($stream->inBaseCase, 'An unanswered flag must not become a decision to exclude the stream.');
        self::assertNull($stream->evidenceStatus, 'An unstated evidence status stays unstated rather than becoming a claim.');
        $page = $this->get($this->url())->viewData('page');
        self::assertNull(data_get($page, 'props.model.streams.0.in_base_case'));
        $sections = collect(data_get($page, 'props.model.sections'))->keyBy('key');
        self::assertStringContainsString('1 of them has no base-case flag', $sections['streams']['detail']);
    }

    public function test_recording_drivers_leaves_the_calendar_reserve_and_actuals_untouched(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->post($this->url().'/drivers', $this->drivers())->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $inputs = app(ForecastModelStore::class)->inputs($model);
        self::assertSame(24, $inputs->calendar->months);
        self::assertSame('1200', $inputs->cashOpening?->amount);
        self::assertSame('1000', $inputs->reservePolicy?->monthlyFixedCosts);
        self::assertCount(1, $inputs->actualMonths, 'A reviewed month must survive a save of the streams beside it.');
    }

    public function test_recording_drivers_changes_no_release_verdict(): void
    {
        $gate = app(ReleaseGate::class);
        $before = $gate->evaluate($this->plan->refresh(), 'plan');
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->post($this->url().'/drivers', $this->drivers())->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();

        $after = $gate->evaluate($this->plan->refresh(), 'plan');
        self::assertSame(
            CanonicalJson::encode($this->rulesEnabledBeforePhaseTwo($before)),
            CanonicalJson::encode($this->rulesEnabledBeforePhaseTwo($after)),
            'Recording streams must move no rule that was enabled before the model could be read.',
        );
        self::assertCount(11, $after->deferred(), 'Three phase-2 rules stay deferred until their facts can produce the specified Fail.');
    }

    public function test_drivers_are_refused_before_a_calendar_exists_to_hold_them(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url().'/drivers', $this->drivers())->assertSessionHasErrors('drivers');

        self::assertNull(app(ForecastModelStore::class)->forPlan($this->plan),
            'A schedule of months must not create a reconstruction with no horizon.');
    }

    public function test_duplicate_references_months_and_engine_refusals_are_reported_against_the_form(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $shared = $this->drivers();
        $shared['streams'][] = $shared['streams'][0];
        $this->post($this->url().'/drivers', $shared)->assertSessionHasErrors('streams');

        $repeated = $this->drivers();
        $repeated['streams'][0]['schedule'][] = $repeated['streams'][0]['schedule'][0];
        $this->post($this->url().'/drivers', $repeated)->assertSessionHasErrors('streams');

        $twiceCounted = $this->drivers();
        $twiceCounted['founder_months'][] = $twiceCounted['founder_months'][0];
        $this->post($this->url().'/drivers', $twiceCounted)->assertSessionHasErrors('founder_months');

        $negative = $this->drivers();
        $negative['streams'][0]['price'] = '-450';
        $this->post($this->url().'/drivers', $negative)->assertSessionHasErrors('drivers');

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame(1, $model->revision, 'Every refused driver save leaves the stored revision alone.');
    }

    public function test_a_schedule_month_outside_the_calendar_is_refused_rather_than_stored(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $beyond = $this->drivers();
        // The calendar runs 2026-01 for 24 months. The revenue projection
        // refuses a schedule that reaches past it rather than discarding the
        // month, so storing one would store a model that cannot project.
        $beyond['streams'][0]['schedule'][0]['period'] = '2029-05';

        $this->post($this->url().'/drivers', $beyond)->assertSessionHasErrors('streams');

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame([], app(ForecastModelStore::class)->inputs($model)->streams);

        // Reported against the collection that holds the month, so the
        // advisor is sent to the one they have to change.
        $capacity = $this->drivers();
        $capacity['founder_months'][0]['period'] = '2029-05';
        $this->post($this->url().'/drivers', $capacity)->assertSessionHasErrors('founder_months');
    }

    public function test_shortening_the_calendar_under_recorded_months_is_refused(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/drivers', $this->drivers())->assertSessionHasNoErrors();

        // The same unprojectable state, reached from the other form: the
        // months stay where they are and the horizon moves out from under
        // them.
        $shortened = $this->payload();
        $shortened['expected_revision'] = 2;
        $shortened['months'] = 2;
        $this->post($this->url(), $shortened)->assertSessionHasErrors('model');

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame(24, app(ForecastModelStore::class)->inputs($model)->calendar->months,
            'A refused calendar change leaves the stored horizon alone.');
    }

    public function test_the_engine_verdict_is_reported_beside_the_recorded_sections(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $complete = $this->drivers();
        // The shared payload leaves extra capacity unstated, which is enough
        // on its own to stop a month pricing. Stating it gives this test one
        // month the engine can carry all the way to a revenue figure.
        $complete['streams'][0]['schedule'][0]['additional_capacity_units'] = '0';
        $this->post($this->url().'/drivers', $complete)->assertSessionHasNoErrors();

        $page = $this->get($this->url())->viewData('page');
        $projection = data_get($page, 'props.model.projection');
        self::assertSame('cannot_verify', data_get($projection, 'status'),
            'One month of capacity across a 24 month horizon is readable but not complete.');
        self::assertSame(24, data_get($projection, 'months_projected'));
        // Two units at 450, from twenty founder hours at ten hours a unit:
        // the engine computed that from the stored reconstruction.
        self::assertSame(1, data_get($projection, 'base_revenue_months'));
        self::assertNull(data_get($projection, 'refusal'));
        $reasons = array_column((array) data_get($projection, 'missing'), 'months', 'reason');
        self::assertArrayHasKey('founder.available_hours', $reasons);
        self::assertSame(23, $reasons['founder.available_hours'],
            'A gap is counted once with its reach, not repeated for every month it touches.');
    }

    public function test_an_unassigned_advisor_cannot_record_drivers(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->actingAsMfa($this->otherAdvisor)->post($this->url().'/drivers', $this->drivers())->assertForbidden();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame([], app(ForecastModelStore::class)->inputs($model)->streams);
    }

    public function test_trade_pay_asset_and_tax_terms_are_recorded_against_the_stored_calendar(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->post($this->url().'/terms', $this->terms())->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $inputs = app(ForecastModelStore::class)->inputs($model);
        self::assertSame('invoice-104', $inputs->tradeItems[0]->id);
        self::assertSame('2026-07-01', $inputs->ownerPaySteps[0]->effectiveOn);
        self::assertSame('12000', $inputs->ownerPaySteps[0]->threshold);
        self::assertSame('laptop-2026', $inputs->assets[0]->id);
        // A whole number typed into a text box has to arrive as an integer.
        self::assertSame(36, $inputs->assets[0]->lifeMonths);
        self::assertSame('28', $inputs->taxPolicy?->ratePercent);
        self::assertTrue($inputs->taxPolicy->lossesEligible);
        // A period that made a loss keeps its sign.
        self::assertSame('-4500', $inputs->taxPeriods[0]->accountingProfit);
        self::assertSame(2, $model->revision, 'The terms save is a revision of the same reconstruction.');
    }

    public function test_a_settlement_takes_its_direction_and_its_order_from_the_item_it_settles(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $terms = $this->terms();
        // Two items settling on one day. The ledger positions a movement by
        // its date and sequence together and refuses two in one position, so
        // numbering each item's settlements from zero would make a stored
        // model the ledger cannot read.
        $terms['trade_items'][] = [
            'id' => 'bill-77', 'source' => 'Supplier statement', 'kind' => 'payable',
            'raised_on' => '2026-03-04', 'amount' => '300',
            'settlements' => [[
                'id' => 'bill-77.payment', 'source' => 'Bank statement April 2026', 'date' => '2026-04-01',
                'amount' => '300', 'category' => 'operating_payment', 'verified' => 'no',
            ]],
        ];

        $this->post($this->url().'/terms', $terms)->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $items = app(ForecastModelStore::class)->inputs($model)->tradeItems;
        $receipt = $items[0]->settlements[0] ?? null;
        $payment = $items[1]->settlements[0] ?? null;
        self::assertNotNull($receipt);
        self::assertNotNull($payment);
        self::assertSame('in', $receipt->direction, 'A receivable brings cash in; the direction is never typed.');
        self::assertSame('out', $payment->direction);
        self::assertSame($receipt->date, $payment->date, 'This case only means something while both settle on one day.');
        self::assertNotSame($receipt->sequence, $payment->sequence,
            'Two settlements on one day must hold different positions, or the ledger refuses the pair.');
        self::assertFalse($payment->verified, 'An explicit "not verified" is not the same as saying nothing.');
    }

    public function test_an_unanswered_asset_classification_is_stored_as_absent_rather_than_as_expense(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $terms = $this->terms();
        // The selects post empty strings, not nulls, when nobody has answered.
        $terms['assets'][0]['classification'] = '';
        $terms['assets'][0]['reviewed'] = '';
        $terms['assets'][0]['method'] = '';

        $this->post($this->url().'/terms', $terms)->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $asset = app(ForecastModelStore::class)->inputs($model)->assets[0];
        self::assertNull($asset->classification, 'An unanswered classification must not become a decision to expense.');
        self::assertNull($asset->reviewed, 'An unstated review stays unstated rather than becoming a denial.');
        $sections = collect(data_get($this->get($this->url())->viewData('page'), 'props.model.sections'))->keyBy('key');
        self::assertStringContainsString('1 at or above 1000 NZD has no reviewed classification', $sections['assets']['detail']);
    }

    public function test_an_item_with_no_settlements_recorded_keeps_its_terms_absent(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $terms = $this->terms();
        $terms['trade_items'][0]['settlements'] = [];

        $this->post($this->url().'/terms', $terms)->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertNull(app(ForecastModelStore::class)->inputs($model)->tradeItems[0]->settlements,
            'No recorded terms is not the same claim as terms that settle nothing.');
        $sections = collect(data_get($this->get($this->url())->viewData('page'), 'props.model.sections'))->keyBy('key');
        self::assertStringContainsString('1 of them has no settlement terms', $sections['trade_items']['detail']);
    }

    public function test_a_settlement_dated_before_the_forecast_opens_is_reported_rather_than_hidden(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $terms = $this->terms();
        // The ledger refuses cash dated before the forecast opens, because
        // that cash is already in the opening balance. Recording it is not
        // blocked here, since the calendar can still move; it is reported.
        $terms['trade_items'][0]['settlements'][0]['date'] = '2025-12-20';

        $this->post($this->url().'/terms', $terms)->assertRedirect()->assertSessionHasNoErrors();

        $sections = collect(data_get($this->get($this->url())->viewData('page'), 'props.model.sections'))->keyBy('key');
        self::assertStringContainsString('1 settlement is dated before the forecast opens', $sections['trade_items']['detail']);
        self::assertStringContainsString('belongs in the opening balance', $sections['trade_items']['detail']);
    }

    public function test_a_threshold_is_dropped_from_an_owner_pay_step_that_starts_on_its_date(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $terms = $this->terms();
        // A trigger changed back to its date leaves the threshold behind in
        // the form. The engine refuses that pair outright, so the value the
        // advisor never restated is dropped rather than reported as an error.
        $terms['owner_pay_steps'][0]['trigger'] = 'base';

        $this->post($this->url().'/terms', $terms)->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $step = app(ForecastModelStore::class)->inputs($model)->ownerPaySteps[0];
        self::assertSame('base', $step->trigger);
        self::assertNull($step->threshold);
    }

    public function test_saving_terms_with_nothing_said_about_tax_leaves_tax_uncaptured(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $terms = $this->terms();
        foreach (['tax_entity_basis', 'tax_rate_percent', 'tax_losses_eligible', 'tax_policy_source',
            'tax_eligibility_source', 'tax_version'] as $key) {
            $terms[$key] = '';
        }
        $terms['tax_periods'] = [];

        $this->post($this->url().'/terms', $terms)->assertRedirect()->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertNull(app(ForecastModelStore::class)->inputs($model)->taxPolicy,
            'Saving the trade terms beside it must not turn an unstated tax policy into an empty one.');
        $sections = collect(data_get($this->get($this->url())->viewData('page'), 'props.model.sections'))->keyBy('key');
        self::assertFalse($sections['tax']['captured']);
        self::assertTrue($sections['trade_items']['captured'], 'The sections that were recorded are still recorded.');
    }

    public function test_recording_terms_leaves_the_calendar_streams_and_actuals_untouched(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/drivers', $this->drivers())->assertSessionHasNoErrors();

        $terms = $this->terms();
        $terms['expected_revision'] = 2;
        $this->post($this->url().'/terms', $terms)->assertSessionHasNoErrors();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        $inputs = app(ForecastModelStore::class)->inputs($model);
        self::assertSame(24, $inputs->calendar->months);
        self::assertSame('1200', $inputs->cashOpening?->amount);
        self::assertSame('1000', $inputs->reservePolicy?->monthlyFixedCosts);
        self::assertCount(1, $inputs->streams, 'A stream must survive a save of the terms beside it.');
        self::assertCount(1, $inputs->actualMonths);
        self::assertSame('120', $inputs->founderMonths['2026-03']->availableHours);
    }

    public function test_recording_terms_changes_no_release_verdict(): void
    {
        $gate = app(ReleaseGate::class);
        $before = $gate->evaluate($this->plan->refresh(), 'plan');
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->post($this->url().'/terms', $this->terms())->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();

        $after = $gate->evaluate($this->plan->refresh(), 'plan');
        self::assertSame(
            CanonicalJson::encode($this->rulesEnabledBeforePhaseTwo($before)),
            CanonicalJson::encode($this->rulesEnabledBeforePhaseTwo($after)),
            'Recording terms must move no rule that was enabled before the model could be read.',
        );
        self::assertCount(11, $after->deferred(), 'Three phase-2 rules stay deferred until their facts can produce the specified Fail.');
    }

    public function test_terms_are_refused_before_a_calendar_exists_to_hold_them(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url().'/terms', $this->terms())->assertSessionHasErrors('terms');

        self::assertNull(app(ForecastModelStore::class)->forPlan($this->plan),
            'Dated terms must not create a reconstruction with no horizon to project across.');
    }

    public function test_repeated_references_unsuitable_categories_and_tax_gaps_are_reported_against_the_form(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $shared = $this->terms();
        $shared['trade_items'][] = $shared['trade_items'][0];
        $this->post($this->url().'/terms', $shared)->assertSessionHasErrors('trade_items');

        // Same cash allocated to two different items would count it twice.
        $twiceAllocated = $this->terms();
        $twiceAllocated['trade_items'][] = array_merge($twiceAllocated['trade_items'][0], ['id' => 'invoice-105']);
        $this->post($this->url().'/terms', $twiceAllocated)->assertSessionHasErrors('trade_items');

        $backwards = $this->terms();
        $backwards['trade_items'][0]['settlements'][0]['category'] = 'operating_payment';
        $this->post($this->url().'/terms', $backwards)->assertSessionHasErrors('trade_items');

        $unchosen = $this->terms();
        $unchosen['trade_items'][0]['settlements'][0]['category'] = '';
        $this->post($this->url().'/terms', $unchosen)->assertSessionHasErrors('trade_items');

        $repeatedAsset = $this->terms();
        $repeatedAsset['assets'][] = $repeatedAsset['assets'][0];
        $this->post($this->url().'/terms', $repeatedAsset)->assertSessionHasErrors('assets');

        // Losses carry from one period into the next, so a gap between them
        // is a reconstruction the engine would refuse to project.
        $gapped = $this->terms();
        $gapped['tax_periods'][] = ['start_on' => '2027-05-01', 'end_on' => '2028-03-31',
            'accounting_profit' => '1000', 'tax_adjustments' => '0'];
        $this->post($this->url().'/terms', $gapped)->assertSessionHasErrors('tax_periods');

        $negative = $this->terms();
        $negative['assets'][0]['cost'] = '-2400';
        $this->post($this->url().'/terms', $negative)->assertSessionHasErrors('terms');

        // Each group posts the revision it loaded with, so a terms form held
        // open while somebody else saved cannot overwrite their work.
        $stale = $this->terms();
        $stale['expected_revision'] = 0;
        $this->post($this->url().'/terms', $stale)->assertSessionHasErrors('revision');

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame(1, $model->revision, 'Every refused terms save leaves the stored revision alone.');
    }

    public function test_an_unassigned_advisor_cannot_record_terms(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        $this->actingAsMfa($this->otherAdvisor)->post($this->url().'/terms', $this->terms())->assertForbidden();

        $model = app(ForecastModelStore::class)->forPlan($this->plan);
        self::assertNotNull($model);
        self::assertSame([], app(ForecastModelStore::class)->inputs($model)->tradeItems);
    }

    /**
     * The rules that were enabled before the phase-2 rules were registered.
     *
     * Selecting a reconstruction is meant to move the rules that read it and
     * nothing else. Comparing this set across a selection proves the fact
     * producer withholds the keys the older rules already read, rather than
     * taking it on trust.
     *
     * @return list<RuleResult>
     */
    private function rulesEnabledBeforePhaseTwo(GateReport $report): array
    {
        return array_values(array_filter($report->results,
            fn (RuleResult $result): bool => ! in_array($result->definition->id, self::FED_BY_THE_RECONSTRUCTION, true)));
    }

    /** @return list<Subcheck> */
    private function subchecks(GateReport $report, string $ruleId): array
    {
        return $this->ruleResult($report, $ruleId)->subchecks;
    }

    private function ruleResult(GateReport $report, string $ruleId): RuleResult
    {
        foreach ($report->results as $result) {
            if ($result->definition->id === $ruleId) {
                return $result;
            }
        }

        throw new \RuntimeException($ruleId.' is not in the catalogue this report was built from.');
    }

    /**
     * One stored budget, so the plan has a declared forecast horizon.
     *
     * The cash-ledger rule reads `model.horizon` before anything else, and
     * only the budget states it: a plan with no budget has no stated horizon
     * to measure a reconstruction against, and the rule saying so is right.
     * These assertions need the horizon to exist.
     */
    private function budget(int $years = 1): void
    {
        EntrepreneurBudget::query()->create(['business_plan_id' => $this->plan->id, 'forecast_years' => $years,
            'computed' => app(BudgetCalculator::class)->compute([], [], [], [], null, forecastYears: $years), 'status' => 'partial']);
    }

    /**
     * A reconstruction whose every month closes, inside a one-year horizon.
     *
     * The pay step applies from its own date rather than waiting on trailing
     * revenue, because an unknown weekly rate withholds every balance from
     * the first payment onward and this is the case where the ledger closes.
     */
    private function twelveMonthsOfCash(): void
    {
        $calendar = $this->payload();
        $calendar['months'] = 12;
        $this->actingAsMfa($this->advisor)->post($this->url(), $calendar)->assertSessionHasNoErrors();
        $terms = $this->terms();
        $terms['owner_pay_steps'] = [['effective_on' => '2026-07-01', 'weekly_amount' => '800', 'trigger' => 'base', 'threshold' => null]];
        $this->post($this->url().'/terms', $terms)->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();
    }

    /**
     * Two recurring overheads, both invoiced monthly and paid inside the
     * month they are invoiced in.
     *
     * The fixed-cost rule wants dated payments against every cost in every
     * month, so this is the shape in which it can reconcile rather than
     * report. A cost billed annually is recorded the same way and is covered
     * separately, where what the rule says about it is the point.
     *
     * @return list<array<string,string|null>>
     */
    private function fixedCosts(): array
    {
        return [
            ['id' => 'lease', 'source' => 'Signed lease to December 2026', 'rate' => '900', 'quantity' => '1',
                'frequency' => 'monthly', 'first_invoice_on' => '2026-01-01', 'last_invoice_on' => '2026-12-01',
                'creditor_days' => '20', 'reviewed' => 'yes'],
            ['id' => 'software', 'source' => 'Subscription invoices 2026', 'rate' => '50', 'quantity' => '3',
                'frequency' => 'monthly', 'first_invoice_on' => '2026-01-05', 'last_invoice_on' => '2026-12-05',
                'creditor_days' => '10', 'reviewed' => 'yes'],
        ];
    }

    /**
     * The terms, with a tax period covering the months being reviewed.
     *
     * The shared fixture's period opens in April, which leaves the first
     * quarter inside no declared period and therefore carrying no stated
     * adjustment at all. That is correct behaviour and is worth its own
     * coverage, but it is not the case these tax assertions are about.
     *
     * @return array<string,mixed>
     */
    private function taxTerms(): array
    {
        $terms = $this->terms();
        $terms['tax_periods'] = [['start_on' => '2026-01-01', 'end_on' => '2026-12-31',
            'accounting_profit' => '7000', 'tax_adjustments' => '500']];

        return $terms;
    }

    /**
     * The tax terms, with a schedule paying March's tax in March.
     *
     * @return array<string,mixed>
     */
    private function taxCashTerms(): array
    {
        $terms = $this->taxTerms();
        $terms['tax_cash_state'] = 'scheduled';
        $terms['gst_cash_state'] = 'not_applicable';
        $terms['tax_cash_source'] = 'IRD income tax instalment notice';
        $terms['gst_cash_source'] = 'Not GST registered';
        $terms['tax_payments'] = [[
            'id' => 'provisional-1', 'source' => 'IRD statement of account', 'date' => '2026-03-28',
            'amount' => '700', 'category' => 'tax', 'direction' => 'out', 'verified' => 'yes',
        ]];

        return $terms;
    }

    /** A selected reconstruction whose first three months are reviewed and carry profit. */
    private function threeReviewedMonths(): void
    {
        $calendar = $this->payload();
        $calendar['months'] = 12;
        // A loss, a month absorbed entirely by the carried balance, and a
        // month large enough to exhaust it and pay tax.
        $calendar['actuals'] = [$this->actual('2026-01', '-2000'), $this->actual('2026-02', '1000'),
            $this->actual('2026-03', '8000')];
        $this->actingAsMfa($this->advisor)->post($this->url(), $calendar)->assertSessionHasNoErrors();
        $this->post($this->url().'/terms', $this->taxTerms())->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();
    }

    /** A selected twelve-month reconstruction carrying a recorded fixed-cost schedule. */
    private function twelveMonthsOfCostedCash(): void
    {
        $calendar = $this->payload();
        $calendar['months'] = 12;
        $this->actingAsMfa($this->advisor)->post($this->url(), $calendar)->assertSessionHasNoErrors();
        $terms = $this->terms();
        $terms['owner_pay_steps'] = [['effective_on' => '2026-07-01', 'weekly_amount' => '800', 'trigger' => 'base', 'threshold' => null]];
        $terms['fixed_costs'] = $this->fixedCosts();
        $terms['fixed_cost_schedule_complete'] = 'yes';
        $this->post($this->url().'/terms', $terms)->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();
    }

    private function advisor(string $name): User
    {
        $user = User::factory()->withTwoFactor()->create(['name' => $name, 'user_type' => 'advisor', 'primary_role' => 'advisor']);
        $user->assignRole('advisor');

        return $user;
    }

    private function url(): string
    {
        return route('advisor.entrepreneurs.plans.forecast-model.show', [$this->profile, $this->plan]);
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'expected_revision' => null,
            'start_month' => '2026-01',
            'months' => 24,
            'opening_amount' => '1200',
            'opening_source' => 'Bank statement to 31 December 2025',
            'opening_verified' => true,
            'reserve_monthly_fixed_costs' => '1000',
            'reserve_months_covered' => 3,
            'reserve_includes_owner_pay' => true,
            'reserve_one_off_cash_costs' => '500',
            'reserve_contingency_rate' => '0.10',
            'reserve_basis' => 'Monthly fixed costs including owner pay',
            'actuals' => [$this->actual('2026-01')],
        ];
    }

    /**
     * One reviewed month as the form sends it.
     *
     * The profit and loss is left unstated unless a test states it. A
     * reviewed month carrying profit is what the tax chain reads, so putting
     * one in the shared fixture would move the tax rule in every test on this
     * form rather than in the ones that are about it.
     *
     * @return array<string,string|bool|null>
     */
    private function actual(string $period, ?string $profitBeforeTax = null): array
    {
        return [
            'period' => $period, 'state' => 'trading', 'reconciled' => true, 'closing_bank_cash' => '5000',
            'reviewed_by' => 'A. Advisor', 'reviewed_on' => '2026-02-05', 'pre_trading_evidence' => null,
            'revenue' => null, 'operating_expenses' => null, 'depreciation' => null, 'interest' => null,
            'other_income' => null, 'profit_before_tax' => $profitBeforeTax, 'income_tax_expense' => null,
        ];
    }

    /** @return array<string,mixed> */
    private function drivers(): array
    {
        return [
            'expected_revision' => 1,
            'streams' => [[
                'id' => 'workshops', 'name' => 'Weekend workshops', 'unit' => 'workshop', 'price' => '450',
                'preparation_hours_per_unit' => '4', 'delivery_hours_per_unit' => '6',
                'in_base_case' => 'base', 'evidence_status' => 'own_record',
                'schedule' => [[
                    'period' => '2026-03', 'booked_units' => '2', 'delivery_units' => '2', 'invoiced_units' => '2',
                    'founder_hours' => '20', 'additional_capacity_units' => null, 'provider' => null,
                    'provider_unit_cost' => null, 'no_cost_basis' => null,
                ]],
            ]],
            'founder_months' => [[
                'period' => '2026-03', 'available_hours' => '120',
                'administration_hours' => '20', 'business_development_hours' => '15',
            ]],
        ];
    }

    /**
     * The terms form as a browser sends it: every figure a string, every
     * yes/no question one of '', 'yes' or 'no'.
     *
     * @return array<string,mixed>
     */
    private function terms(): array
    {
        return [
            'expected_revision' => 1,
            'funding_sources' => [[
                'id' => 'term-loan-2026', 'source' => 'Signed facility letter', 'identity' => 'Westpac',
                'type' => 'Term loan', 'amount' => '25000', 'commitment' => 'committed',
                'available_from' => '2026-02-01', 'drawn_on' => '2026-02-15',
                'restriction' => 'unrestricted', 'verified' => 'yes',
            ]],
            'funding_inventory_complete' => 'yes',
            'trade_items' => [[
                'id' => 'invoice-104', 'source' => 'Sales ledger export', 'kind' => 'receivable',
                'raised_on' => '2026-03-02', 'amount' => '900',
                'settlements' => [[
                    'id' => 'invoice-104.payment', 'source' => 'Bank statement April 2026', 'date' => '2026-04-01',
                    'amount' => '900', 'category' => 'operating_receipt', 'verified' => 'yes',
                ]],
            ]],
            'owner_pay_steps' => [[
                'effective_on' => '2026-07-01', 'weekly_amount' => '800',
                'trigger' => 'trailing_three_month_average_revenue', 'threshold' => '12000',
            ]],
            'owner_pay_source' => 'Signed owner drawings policy',
            'owner_pay_first_payment_on' => '2026-07-03',
            'opening_eligible_losses' => '4500',
            'assets' => [[
                'id' => 'laptop-2026', 'source' => 'Supplier invoice 8831', 'cost' => '2400',
                'classification' => 'capital', 'policy_source' => 'Capitalisation policy v2', 'reviewed' => 'yes',
                'paid_on' => '2026-02-10', 'recognition_start_on' => '2026-02-01', 'life_months' => '36',
                'residual' => '200', 'method' => 'straight_line_full_start_month', 'opening_depreciation' => '0',
            ]],
            'tax_entity_basis' => 'Company',
            'tax_rate_percent' => '28',
            'tax_losses_eligible' => 'yes',
            'tax_policy_source' => 'IR4 guidance',
            'tax_eligibility_source' => 'Shareholder continuity note',
            'tax_version' => '2026.1',
            'tax_periods' => [[
                'start_on' => '2026-04-01', 'end_on' => '2027-03-31',
                'accounting_profit' => '-4500', 'tax_adjustments' => '500',
            ]],
            // Left empty in the shared fixture on purpose. Recurring overheads
            // are real cash leaving the business, so putting them here would
            // move every cash balance in every other test on this form, and a
            // fixture change is a poor way to discover that.
            'fixed_costs' => [],
            'fixed_cost_schedule_complete' => '',
            // Left unstated in the shared fixture for the same reason the
            // fixed costs are: a tax cash schedule puts payments in the
            // ledger and would move every cash balance in every other test.
            'tax_cash_state' => '',
            'gst_cash_state' => '',
            'tax_cash_source' => '',
            'gst_cash_source' => '',
            'tax_payments' => [],
        ];
    }

    public function test_the_cash_inputs_the_engine_needs_are_recorded_and_survive_the_other_forms(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/terms', $this->terms())->assertSessionHasNoErrors();

        $store = app(ForecastModelStore::class);
        $model = $store->forPlan($this->plan);
        self::assertNotNull($model);
        $inputs = $store->inputs($model);
        self::assertSame('Signed owner drawings policy', $inputs->ownerPaySource);
        self::assertSame('2026-07-03', $inputs->ownerPayFirstPaymentOn);
        self::assertSame('4500', $inputs->openingEligibleLosses);

        // Every other form rebuilds the whole reconstruction, so a field one
        // of them does not show is a field it can silently drop.
        $drivers = $this->drivers();
        $drivers['expected_revision'] = 2;
        $this->post($this->url().'/drivers', $drivers)->assertSessionHasNoErrors();
        $calendar = $this->payload();
        $calendar['expected_revision'] = 3;
        $this->post($this->url(), $calendar)->assertSessionHasNoErrors();

        $carried = $store->inputs($store->forPlan($this->plan));
        self::assertSame('Signed owner drawings policy', $carried->ownerPaySource,
            'A pay arrangement recorded on the terms form must survive a save from either other form.');
        self::assertSame('2026-07-03', $carried->ownerPayFirstPaymentOn);
        self::assertSame('4500', $carried->openingEligibleLosses);
    }

    public function test_a_reconstruction_reaches_the_gate_as_evidenced_facts_only_once_selected(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/drivers', $this->drivers())->assertSessionHasNoErrors();

        // Saving is not selecting. A draft nobody has chosen must not enter
        // the snapshot, or every revision of it would invalidate decisions
        // taken against a model it has no bearing on.
        $factory = app(GateContextFactory::class);
        $before = $factory->current($this->plan->refresh(), 'plan');
        self::assertSame([], $before->facts->all('model.start_period'));

        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();

        $after = $factory->current($this->plan->refresh(), 'plan');
        $start = $after->facts->one('model.start_period');
        self::assertNotNull($start, 'A selected reconstruction is the plan financial model and must be evidence.');
        self::assertSame('2026-01', $start->value);
        self::assertNotSame([], $start->sources, 'A fact the gate reads must cite the captured record behind it.');
        self::assertSame('captured_record', $start->sources[0]->kind);

        // The keys the enabled rules already read stay withheld, so a
        // selected model adds evidence beside the stored budget rather than
        // competing with it. The two keys it also takes off the budget are
        // left out here: those are asserted where a budget exists to lose
        // them, which this plan does not have.
        foreach (array_diff(ForecastModelFacts::WITHHELD, ForecastModelFacts::SUPERSEDES) as $withheld) {
            self::assertCount(
                count($before->facts->all($withheld)),
                $after->facts->all($withheld),
                $withheld.' must not gain a competing record from the reconstruction.',
            );
        }
    }

    public function test_a_selected_reconstruction_becomes_the_cash_ledger_the_integrity_rule_reads(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);

        // Before: nothing on this platform had ever produced a ledger entry,
        // so the rule reported every month's ledger as missing however much
        // the stored budget said about the balances. Cannot verify was as far
        // as it could reach, on any plan.
        $before = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-01');
        self::assertSame('cannot_verify', $before->status?->value);
        self::assertContains('ledger:month:1', array_column($before->subchecks, 'id'));

        $this->twelveMonthsOfCash();

        $after = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-01');
        self::assertSame('pass', $after->status?->value,
            'A dated, classified and sourced ledger is the evidence this rule was waiting for.');
        $ids = array_column($after->subchecks, 'id');
        foreach (['ledger:month:2', 'annual:1', 'opening-basis', 'continuity:2'] as $check) {
            self::assertContains($check, $ids, $check.' is a reconciliation the ledger now makes possible.');
        }
        // The balances come off the reconstruction, not the stored budget:
        // 1200 opening, less 2400 for the laptop and plus the 25000 term loan
        // drawn in February. The draw reaching the curve is the funding
        // inventory and the cash ledger meeting in one figure.
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;
        self::assertSame('1200.00', $facts->one('cash.opening', period: 'month:1')?->value);
        self::assertSame('23800.00', $facts->one('cash.closing', period: 'month:2')?->value);
    }

    public function test_a_reconstruction_reaching_past_the_plan_horizon_reports_the_cash_outside_it(): void
    {
        // One forecast year is declared, and the reconstruction runs for two.
        $this->budget();
        $calendar = $this->payload();
        $this->actingAsMfa($this->advisor)->post($this->url(), $calendar)->assertSessionHasNoErrors();
        $terms = $this->terms();
        $terms['owner_pay_steps'] = [['effective_on' => '2026-07-01', 'weekly_amount' => '800', 'trigger' => 'base', 'threshold' => null]];
        $this->post($this->url().'/terms', $terms)->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();

        // A plan that declares one forecast year and selects a two-year model
        // has cash movements outside the horizon being evaluated. The rule
        // demonstrates it rather than reporting it unverifiable, and the
        // advisor resolves it by aligning the two.
        $result = $this->ruleResult(app(ReleaseGate::class)->evaluate($this->plan->refresh(), 'plan'), 'MI-01');
        $outside = array_values(array_filter($result->subchecks, fn (Subcheck $check): bool => str_starts_with($check->id, 'ledger-horizon:')
            && $check->status->unresolved()));
        self::assertNotSame([], $outside, 'Cash dated past the evaluated horizon has to be named, not absorbed.');
        self::assertSame('fail', $result->status?->value);
    }

    public function test_the_budget_scenarios_report_as_unestablished_rather_than_start_from_another_model(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);
        $before = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'SS-01');
        self::assertNotContains('scenarios', array_column($before->subchecks, 'id'),
            'The stored budget does supply a scenario to reconcile before any reconstruction is selected.');

        $this->twelveMonthsOfCash();

        // A budget scenario's cash curve is built off the base curve the
        // reconstruction replaced, and the rule checks each scenario's first
        // month against the model's own opening cash, which is one figure with
        // no scenario of its own. Left in place, every scenario would start
        // from a balance this plan's financial model does not use, and the
        // rule would demonstrate that as a mismatch. The reconstruction states
        // no scenarios, so the rule says so.
        $after = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'SS-01');
        self::assertSame(['scenarios'], array_column($after->subchecks, 'id'));
        self::assertSame('cannot_verify', $after->status?->value, 'An honest gap, not a mismatch nobody demonstrated.');
    }

    public function test_with_a_budget_in_place_only_the_cash_ledger_rule_changes_its_verdict(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);
        $before = $gate->evaluate($this->plan->refresh(), 'plan');
        $this->twelveMonthsOfCash();
        $after = $gate->evaluate($this->plan->refresh(), 'plan');

        // MI-01 is the rule this slice moves, beside the six registered in
        // the slice before it. Every other verdict has to hold, including the
        // three rules whose evidence changes: MI-02 and PM-12 lose an
        // available-funds figure that was a mixture of two models, and SS-01
        // loses the extremum taken off the cash curve the reconstruction
        // replaced. Losing a stale figure must not move a verdict.
        $verdicts = fn (GateReport $report): array => array_combine(
            array_map(fn (RuleResult $result): string => $result->definition->id, $report->results),
            array_map(fn (RuleResult $result): ?string => $result->status?->value, $report->results));
        $moved = ['PM-06', 'PM-11', 'MI-07', 'MI-08', 'SS-02', 'SS-03', 'MI-01'];
        self::assertSame(
            array_diff_key($verdicts($before), array_flip($moved)),
            array_diff_key($verdicts($after), array_flip($moved)),
            'Only the cash-ledger rule and the six registered before it may change verdict on a selection.',
        );

        $evidence = fn (GateReport $report): array => array_values(array_filter($report->results,
            fn (RuleResult $result): bool => ! in_array($result->definition->id, self::FED_BY_THE_RECONSTRUCTION, true)));
        self::assertSame(CanonicalJson::encode($evidence($before)), CanonicalJson::encode($evidence($after)),
            'A rule that reads neither the cash curve nor what came off it must be byte-identical.');
    }

    public function test_funding_sources_are_recorded_and_reach_the_ledger_as_dated_financing(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/terms', $this->terms())->assertSessionHasNoErrors();

        $store = app(ForecastModelStore::class);
        $inputs = $store->inputs($store->forPlan($this->plan));
        self::assertCount(1, $inputs->fundingSources);
        self::assertSame(['Westpac', 'committed', 'unrestricted', true], [$inputs->fundingSources[0]->identity,
            $inputs->fundingSources[0]->commitment, $inputs->fundingSources[0]->restriction, $inputs->fundingSources[0]->verified]);
        self::assertTrue($inputs->fundingInventoryComplete);

        // Every other form rebuilds the whole reconstruction, so a section one
        // of them does not show is a section it can silently drop.
        $drivers = $this->drivers();
        $drivers['expected_revision'] = 2;
        $this->post($this->url().'/drivers', $drivers)->assertSessionHasNoErrors();
        $carried = $store->inputs($store->forPlan($this->plan));
        self::assertCount(1, $carried->fundingSources, 'Recording drivers must not discard the funding inventory.');
        self::assertTrue($carried->fundingInventoryComplete);

        // The draw is cash arriving, so it belongs in the dated ledger.
        $cash = data_get($this->get($this->url())->viewData('page'), 'props.model.cash');
        self::assertNull(data_get($cash, 'refusal'), 'A funding draw must hold its own position in the ledger.');
        self::assertSame(0, data_get($cash, 'settled_before_opening'));
    }

    public function test_a_draw_outside_the_forecast_calendar_is_refused_in_the_direction_it_is_wrong(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        // Before the forecast opens: the money is already inside the opening
        // balance, so counting it as funding too would count it twice.
        $early = $this->terms();
        $early['funding_sources'][0]['available_from'] = '2025-11-01';
        $early['funding_sources'][0]['drawn_on'] = '2025-12-20';
        $this->post($this->url().'/terms', $early)->assertSessionHasErrors('funding_sources');
        self::assertStringContainsString('counted twice',
            (string) session('errors')?->first('funding_sources'));

        // After it ends: the money never arrives inside the horizon, so it is
        // not funding this forecast can spend.
        $late = $this->terms();
        $late['funding_sources'][0]['drawn_on'] = '2028-06-01';
        $this->post($this->url().'/terms', $late)->assertSessionHasErrors('funding_sources');
        self::assertStringContainsString('not available to this forecast',
            (string) session('errors')?->first('funding_sources'));

        self::assertNull(app(ForecastModelStore::class)->forPlan($this->plan)?->drivers['funding_sources'][0] ?? null,
            'A refused save must leave nothing behind.');
    }

    public function test_a_recorded_fixed_cost_schedule_is_what_the_fixed_cost_rule_was_missing(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);
        $before = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-03');
        // Nothing on the platform produced a fixed-cost rate before this, so
        // the rule could only report its whole schedule missing.
        self::assertSame('cannot_verify', $before->status?->value);
        self::assertSame(['exact-schedule'], array_column($before->subchecks, 'id'));

        $this->twelveMonthsOfCostedCash();
        $after = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-03');
        $unresolved = array_column(array_values(array_filter($after->subchecks,
            fn (Subcheck $check): bool => $check->status->unresolved())), 'id');

        self::assertSame([], $unresolved, 'A recorded schedule leaves the fixed-cost rule nothing to report.');
        self::assertSame('pass', $after->status?->value);
    }

    public function test_the_fixed_cost_schedule_recognises_the_annualised_rate_unrounded(): void
    {
        $this->budget();
        $this->twelveMonthsOfCostedCash();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // 900 a month and 50 times three a month. Both are stated against
        // every month, because the rule reads a rate parallel to the expense
        // of the month it belongs to.
        self::assertSame('900.00000000', $facts->one('cost.expense', 'lease', 'month:1')?->value);
        self::assertSame('150.00000000', $facts->one('cost.expense', 'software', 'month:7')?->value);
        self::assertSame('1050.00000000', $facts->one('model.fixed_cost', period: 'month:7')?->value);
        self::assertSame('12600.00000000', $facts->one('cost.annual_total', period: 'year:1')?->value);

        // The lease is invoiced on the first and paid twenty days later, so
        // the cash and the expense land in the same month and the accrual it
        // carries at month end is nothing.
        self::assertSame('900.00', $facts->one('cost.payment', 'lease', 'month:3')?->value);
        self::assertSame('2026-03-21', $facts->one('cost.payment', 'lease', 'month:3')?->asOf);
        self::assertSame('0.00000000', $facts->one('cost.accrual_movement', 'lease', 'month:3')?->value);
    }

    public function test_an_annually_billed_cost_is_spread_and_carries_the_prepayment_it_creates(): void
    {
        $this->budget();
        $calendar = $this->payload();
        $calendar['months'] = 12;
        $this->actingAsMfa($this->advisor)->post($this->url(), $calendar)->assertSessionHasNoErrors();
        $terms = $this->terms();
        // A premium that does not divide evenly into twelve months of cents,
        // invoiced once in February and paid in March.
        $terms['fixed_costs'] = [['id' => 'insurance', 'source' => 'Policy schedule 2026', 'rate' => '1000',
            'quantity' => '1', 'frequency' => 'annual', 'first_invoice_on' => '2026-02-15',
            'last_invoice_on' => '2026-02-15', 'creditor_days' => '30', 'reviewed' => 'yes']];
        $terms['fixed_cost_schedule_complete'] = 'yes';
        $this->post($this->url().'/terms', $terms)->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // A twelfth of the premium every month, held unrounded. Rounding it to
        // cents here would force the residual into one month, and a schedule
        // with a residual month is not the straight line the rate describes.
        self::assertSame('83.33333333', $facts->one('cost.expense', 'insurance', 'month:1')?->value);
        self::assertSame('83.33333333', $facts->one('cost.expense', 'insurance', 'month:12')?->value);

        // January accrues the month's share with no cash behind it yet; March
        // pays the whole year and turns the position into a prepayment.
        self::assertSame('83.33333333', $facts->one('cost.accrual_movement', 'insurance', 'month:1')?->value);
        self::assertSame('-916.66666667', $facts->one('cost.accrual_movement', 'insurance', 'month:3')?->value);
        self::assertSame('1000.00', $facts->one('cost.payment', 'insurance', 'month:3')?->value);

        // The rule wants dated payments against every cost in every month, and
        // an annual premium has cash in one of them. The eleven months it does
        // not are reported rather than treated as nothing owed, which is the
        // rule's own bar and not something this producer can answer for it.
        $result = $this->ruleResult(app(ReleaseGate::class)->evaluate($this->plan->refresh(), 'plan'), 'MI-03');
        $unresolved = array_column(array_values(array_filter($result->subchecks,
            fn (Subcheck $check): bool => $check->status->unresolved())), 'id');
        self::assertCount(11, $unresolved, 'Eleven months of an annual premium have no cash against them.');
        self::assertSame([], array_values(array_filter($unresolved,
            fn (string $id): bool => ! str_starts_with($id, 'payments:'))), 'Nothing but the payment months is unresolved.');
    }

    public function test_no_fixed_cost_total_until_the_schedule_is_finished_and_every_cost_priced(): void
    {
        $this->budget();
        $calendar = $this->payload();
        $calendar['months'] = 12;
        $this->actingAsMfa($this->advisor)->post($this->url(), $calendar)->assertSessionHasNoErrors();
        $terms = $this->terms();
        $terms['fixed_costs'] = $this->fixedCosts();

        // Nobody has said the schedule is finished. The rows are still stated,
        // because a recorded cost is evidence whether or not the list is
        // closed, but the rule's coverage check reads the declaration.
        $terms['fixed_cost_schedule_complete'] = '';
        $this->post($this->url().'/terms', $terms)->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;
        self::assertNull($facts->one('cost.schedule_complete')?->value);
        self::assertSame('1050.00000000', $facts->one('model.fixed_cost', period: 'month:1')?->value);
        self::assertContains('schedule-coverage', array_column(array_values(array_filter(
            $this->ruleResult(app(ReleaseGate::class)->evaluate($this->plan->refresh(), 'plan'), 'MI-03')->subchecks,
            fn (Subcheck $check): bool => $check->status->unresolved())), 'id'));

        // One cost nobody has priced withholds the month's total rather than
        // understating it as the sum of the costs that happen to be known.
        $unpriced = $terms;
        $unpriced['expected_revision'] = null;
        $unpriced['fixed_costs'][1]['rate'] = null;
        $unpriced['fixed_cost_schedule_complete'] = 'yes';
        $this->post($this->url().'/terms', $unpriced)->assertSessionHasNoErrors();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;
        self::assertNull($facts->one('model.fixed_cost', period: 'month:1')?->value);
        self::assertNull($facts->one('cost.annual_total', period: 'year:1'));
    }

    public function test_a_fixed_cost_invoiced_outside_the_calendar_is_refused_at_the_form(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();

        // Charges raised before the forecast opens are already in the opening
        // position; recognising them again would double the expense.
        $early = $this->terms();
        $early['fixed_costs'] = [[...$this->fixedCosts()[0], 'first_invoice_on' => '2025-11-01']];
        $this->post($this->url().'/terms', $early)->assertSessionHasErrors('fixed_costs');
        self::assertStringContainsString('already in the opening position',
            (string) session('errors')?->first('fixed_costs'));

        // A charge raised after the forecast ends is not an expense it carries.
        $late = $this->terms();
        $late['fixed_costs'] = [[...$this->fixedCosts()[0], 'last_invoice_on' => '2028-06-01']];
        $this->post($this->url().'/terms', $late)->assertSessionHasErrors('fixed_costs');
        self::assertStringContainsString('not an expense this forecast carries',
            (string) session('errors')?->first('fixed_costs'));

        self::assertSame([], app(ForecastModelStore::class)->forPlan($this->plan)?->drivers['fixed_costs'] ?? [],
            'A refused save must leave nothing behind.');
    }

    public function test_fixed_cost_invoices_reach_the_cash_ledger_as_dated_payments(): void
    {
        $this->budget();
        $this->twelveMonthsOfCostedCash();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // The overheads are real cash leaving the business, so they belong in
        // the ledger beside the trade payables rather than only in the expense
        // schedule. January pays the lease on the 21st and the software on the
        // 15th: 1050 out, against an opening balance of 1200.
        self::assertSame('1200.00', $facts->one('cash.opening', period: 'month:1')?->value);
        self::assertSame('-1050.00', $facts->one('cash.movement', period: 'month:1')?->value);
        self::assertSame('150.00', $facts->one('cash.closing', period: 'month:1')?->value);
        self::assertSame('operating_payments', $facts->one('ledger.category', 'lease.0.payment', 'month:1')?->value);
    }

    public function test_the_tax_rule_reads_a_monthly_chain_off_the_reviewed_months(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);
        $before = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-04');
        // Nothing stated a tax policy or a monthly profit, so the rule could
        // report one lump gap and nothing more precise.
        self::assertContains('tax-policy', array_column($before->subchecks, 'id'));

        $this->threeReviewedMonths();
        $after = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-04');
        $ids = array_column($after->subchecks, 'id');

        // The policy precondition is satisfied, so the rule gets past it and
        // reconciles each reviewed month's chain instead.
        self::assertNotContains('tax-policy', $ids);
        foreach (['expense:0', 'expense:1', 'expense:2'] as $check) {
            self::assertContains($check, $ids, $check.' is a month the chain can now reconcile.');
        }
        $reconciled = array_values(array_filter($after->subchecks,
            fn (Subcheck $check): bool => str_starts_with($check->id, 'expense:')));
        self::assertSame(['pass', 'pass', 'pass'], array_map(fn (Subcheck $c): ?string => $c->status->value, $reconciled));
    }

    public function test_a_loss_raises_the_carried_balance_and_never_refunds_tax(): void
    {
        $this->budget();
        $this->threeReviewedMonths();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // Opening eligible losses are 4,500 and the rate is 28 per cent.
        // January loses 2,000, which is carried rather than refunded; February
        // earns 1,000, absorbed entirely by the balance; March earns 8,000,
        // of which 5,500 is absorbed and 2,500 is taxed.
        self::assertSame('0.00', $facts->one('tax.expense', period: 'month:1')?->value);
        self::assertSame('6500.00', $facts->one('tax.closing_losses', period: 'month:1')?->value);
        self::assertSame('0.00', $facts->one('tax.expense', period: 'month:2')?->value);
        self::assertSame('5500.00', $facts->one('tax.closing_losses', period: 'month:2')?->value);
        self::assertSame('700.00', $facts->one('tax.expense', period: 'month:3')?->value);
        self::assertSame('0.00', $facts->one('tax.closing_losses', period: 'month:3')?->value);

        self::assertSame('loss_carry_forward', $facts->one('tax.policy')?->value);
        self::assertSame('4500', $facts->one('tax.opening_losses')?->value);
    }

    public function test_the_chain_stops_where_the_reviewed_months_stop(): void
    {
        $this->budget();
        $this->threeReviewedMonths();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // A forecast month's profit is recorded nowhere, and deriving one
        // would mean deciding whether a receivable is the same revenue a
        // stream already counted. The chain states nothing for month four
        // rather than carrying a balance off a figure nobody stated.
        self::assertNull($facts->one('tax.profit', period: 'month:4'));
        self::assertNull($facts->one('tax.expense', period: 'month:4'));

        // So the rule reports the horizon as uncovered, which is the point:
        // three reviewed months are not a twelve-month tax schedule.
        $result = $this->ruleResult(app(ReleaseGate::class)->evaluate($this->plan->refresh(), 'plan'), 'MI-04');
        self::assertContains('coverage', array_column(array_values(array_filter($result->subchecks,
            fn (Subcheck $check): bool => $check->status->unresolved())), 'id'));
    }

    public function test_an_unreviewed_month_states_no_profit_however_its_accounts_read(): void
    {
        $this->budget();
        $calendar = $this->payload();
        $calendar['months'] = 12;
        // Reconciled is the review. A month carrying a profit figure nobody
        // reconciled must not head a chain every later month depends on.
        $calendar['actuals'] = [[...$this->actual('2026-01', '9000'), 'reconciled' => false]];
        $this->actingAsMfa($this->advisor)->post($this->url(), $calendar)->assertSessionHasNoErrors();
        $this->post($this->url().'/terms', $this->taxTerms())->assertSessionHasNoErrors();
        $this->post($this->url().'/selection', ['selected' => true])->assertSessionHasNoErrors();

        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;
        self::assertNull($facts->one('tax.profit', period: 'month:1'));
        self::assertSame('loss_carry_forward', $facts->one('tax.policy')?->value,
            'The policy is still recorded; it is the month that is unreviewed.');

        // And the screen says so, rather than leaving an advisor to discover
        // from a release verdict that the tax schedule starts nowhere.
        $sections = data_get($this->get($this->url())->viewData('page'), 'props.model.sections');
        $actuals = collect($sections)->firstWhere('key', 'actuals');
        self::assertStringContainsString('0 of them are reconciled and state a profit before tax',
            (string) data_get($actuals, 'detail'));
    }

    public function test_a_period_adjustment_lands_in_the_month_that_period_ends(): void
    {
        $this->budget();
        $this->threeReviewedMonths();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // The adjustment is determined when the return is prepared, so the
        // months before the period ends carry none. The amount is the
        // advisor's; only the timing is the convention.
        self::assertSame('0.00', $facts->one('tax.adjustment', period: 'month:1')?->value);
        self::assertSame('0.00', $facts->one('tax.adjustment', period: 'month:3')?->value);
    }

    public function test_a_recorded_tax_cash_schedule_is_what_the_cash_tax_checks_were_missing(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);
        $this->threeReviewedMonths();
        $before = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-04');
        // The chain reconciles, but nothing says when the tax is paid.
        foreach (['cash-tax:0', 'cash-tax:1', 'cash-tax:2'] as $check) {
            self::assertContains($check, array_column(array_values(array_filter($before->subchecks,
                fn (Subcheck $c): bool => $c->status->unresolved())), 'id'));
        }

        $this->post($this->url().'/terms', [...$this->taxCashTerms(), 'expected_revision' => null])
            ->assertSessionHasNoErrors();
        $after = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-04');
        $unresolved = array_column(array_values(array_filter($after->subchecks,
            fn (Subcheck $c): bool => $c->status->unresolved())), 'id');

        // Every reviewed month now reconciles its cash as well as its chain:
        // the payment, the tax payable it moves, and the cash-tax lines in
        // the ledger behind it. What is left is the horizon, which three
        // reviewed months cannot cover.
        self::assertSame(['coverage'], $unresolved);
    }

    public function test_tax_recognised_and_not_yet_paid_is_carried_as_payable(): void
    {
        $this->budget();
        $this->threeReviewedMonths();
        $this->post($this->url().'/terms', [...$this->taxCashTerms(), 'expected_revision' => null])
            ->assertSessionHasNoErrors();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // January and February recognise no tax and pay none. March
        // recognises 700 and pays it in the same month, so nothing is left
        // outstanding at the end of it.
        self::assertSame('0.00', $facts->one('tax.payment', period: 'month:1')?->value);
        self::assertSame('0.00', $facts->one('tax.payable_movement', period: 'month:1')?->value);
        self::assertSame('700.00', $facts->one('tax.payment', period: 'month:3')?->value);
        self::assertSame('2026-03-28', $facts->one('tax.payment', period: 'month:3')?->asOf);
        self::assertSame('0.00', $facts->one('tax.payable_movement', period: 'month:3')?->value);

        // And the payment reached the cash ledger as a classified tax line.
        self::assertSame('cash_tax', $facts->one('ledger.category', 'provisional-1', 'month:3')?->value);
        self::assertSame('700', $facts->one('ledger.amount', 'provisional-1', 'month:3')?->value);
    }

    public function test_a_schedule_that_contradicts_its_own_scope_is_refused(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $contradictory = $this->taxCashTerms();
        $contradictory['tax_cash_state'] = 'none_due';

        $this->post($this->url().'/terms', $contradictory)->assertSessionHasErrors('tax_payments');
        self::assertStringContainsString('cannot hold both',
            (string) session('errors')?->first('tax_payments'));
    }

    public function test_tax_paid_before_the_forecast_opens_is_refused_rather_than_counted_twice(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $early = $this->taxCashTerms();
        $early['tax_payments'][0]['date'] = '2025-11-20';

        $this->post($this->url().'/terms', $early)->assertSessionHasErrors('tax_payments');
        self::assertStringContainsString('in the opening balance, not in the schedule',
            (string) session('errors')?->first('tax_payments'));
    }

    public function test_a_month_carrying_a_refund_states_no_payment_rather_than_a_net_figure(): void
    {
        $this->budget();
        $this->threeReviewedMonths();
        $refunded = $this->taxCashTerms();
        $refunded['expected_revision'] = null;
        $refunded['tax_payments'][] = ['id' => 'refund-1', 'source' => 'IRD refund advice', 'date' => '2026-03-10',
            'amount' => '200', 'category' => 'tax', 'direction' => 'in', 'verified' => 'yes'];
        $this->post($this->url().'/terms', $refunded)->assertSessionHasNoErrors();
        $facts = app(GateContextFactory::class)->current($this->plan->refresh(), 'plan')->facts;

        // The ledger gives a tax refund no category, because the rule's
        // inflow categories do not describe one. A net payment figure could
        // therefore never agree with the ledger it is checked against, so the
        // month states none and the rule reports it.
        self::assertNull($facts->one('tax.payment', period: 'month:3'));
        self::assertNull($facts->one('ledger.category', 'refund-1', 'month:3'));
    }

    public function test_a_recorded_fixed_cost_schedule_moves_only_the_rules_that_read_it(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);
        $verdicts = fn (GateReport $report): array => array_combine(
            array_map(fn (RuleResult $result): string => $result->definition->id, $report->results),
            array_map(fn (RuleResult $result): ?string => $result->status?->value, $report->results));

        $untouched = $gate->evaluate($this->plan->refresh(), 'plan');
        $before = $verdicts($untouched);
        $this->twelveMonthsOfCostedCash();
        $costed = $gate->evaluate($this->plan->refresh(), 'plan');
        $after = $verdicts($costed);

        // Three verdicts change. The fixed-cost rule reaches a finding for the
        // first time, and the two that read the cash curve move because the
        // overheads put dated payments into it. The rest of the rules fed by
        // the reconstruction keep the verdicts they had: a cost schedule does
        // not resolve what was already unestablished about funding or
        // scenarios, and reporting that it had would be the defect.
        $moved = array_keys(array_filter($after, fn (?string $status, string $id): bool => $status !== $before[$id],
            ARRAY_FILTER_USE_BOTH));
        sort($moved);
        self::assertSame(['MI-01', 'MI-03', 'MI-07'], $moved);

        // Verdicts alone would not catch a rule quietly reading a cost fact
        // without changing its mind, so every rule outside the set this
        // reconstruction may touch is compared whole.
        $reads = [...self::FED_BY_THE_RECONSTRUCTION, 'MI-03'];
        $evidence = fn (GateReport $report): array => array_values(array_filter($report->results,
            fn (RuleResult $result): bool => ! in_array($result->definition->id, $reads, true)));
        self::assertSame(CanonicalJson::encode($evidence($untouched)), CanonicalJson::encode($evidence($costed)),
            'A cost schedule is not evidence about revenue, capacity, documents or governance.');
    }

    public function test_two_sources_under_one_name_are_refused_before_a_total_counts_them_twice(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $repeated = $this->terms();
        $repeated['funding_sources'][] = [...$repeated['funding_sources'][0], 'id' => 'term-loan-second'];

        $this->post($this->url().'/terms', $repeated)->assertSessionHasErrors('funding_sources');
        self::assertStringContainsString('named the same', (string) session('errors')?->first('funding_sources'));
    }

    public function test_a_recorded_funding_inventory_is_what_the_funding_rule_was_missing(): void
    {
        $this->budget();
        $gate = app(ReleaseGate::class);
        $before = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-02');
        self::assertSame('cannot_verify', $before->status?->value);

        $this->twelveMonthsOfCash();
        $after = $this->ruleResult($gate->evaluate($this->plan->refresh(), 'plan'), 'MI-02');
        $unresolved = array_column(array_values(array_filter($after->subchecks,
            fn (Subcheck $check): bool => $check->status->unresolved())), 'id');

        // Everything the funding section establishes now reconciles: the named
        // source, its dates, its commitment, its restriction, and the draw
        // matching it in the financing ledger. What is left is one gap, and it
        // is not a funding gap: the opening balance has no account and
        // restriction scope, which the reconstruction holds in its bank
        // reconciliation opening and no screen captures yet.
        self::assertSame(['opening-provenance'], $unresolved,
            'A recorded inventory should leave exactly the gap that is not about funding.');
        self::assertSame('cannot_verify', $after->status?->value);
    }

    public function test_the_dated_cash_run_is_reported_beside_the_projection(): void
    {
        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/terms', $this->terms())->assertSessionHasNoErrors();

        $cash = data_get($this->get($this->url())->viewData('page'), 'props.model.cash');
        self::assertNull(data_get($cash, 'refusal'), 'The settlement and the asset purchase must not collide in the ledger.');
        // The receivable settles in April, the laptop is paid in February,
        // and the weekly owner pay series runs from July to the end of the
        // horizon.
        self::assertGreaterThan(2, data_get($cash, 'movements_counted'));
        self::assertSame(0, data_get($cash, 'settled_before_opening'));
        // The only pay step waits on trailing revenue with no base step
        // beneath it, so the weekly rate is unknown on every payment date.
        // The engine withholds the funding measures as a set rather than
        // publishing figures drawn from a ledger with holes in it, and a
        // withheld figure is the honest answer here.
        foreach (['lowest_cash', 'cash_deficit', 'reserve_target', 'reserve_shortfall'] as $measure) {
            self::assertNull(data_get($cash, $measure), $measure.' must not be published from an incomplete ledger.');
        }
        $gaps = array_column((array) data_get($cash, 'missing'), 'months', 'reason');
        self::assertArrayHasKey('owner_pay.base_policy', $gaps);
        self::assertGreaterThan(50, $gaps['owner_pay.base_policy'],
            'One unknown rate across a weekly series is one gap with its reach, not one gap per payment date.');
    }
}
