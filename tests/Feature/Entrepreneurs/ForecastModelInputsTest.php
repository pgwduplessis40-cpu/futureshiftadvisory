<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Models\BusinessPlan;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelStore;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Services\Entrepreneurs\ReleaseGate\CanonicalJson;
use App\Services\Entrepreneurs\ReleaseGate\ReleaseGate;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ForecastModelInputsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_saving_and_selecting_change_no_release_verdict(): void
    {
        $gate = app(ReleaseGate::class);
        $before = $gate->evaluate($this->plan->refresh(), 'plan');

        $this->actingAsMfa($this->advisor)->post($this->url(), $this->payload())->assertRedirect();
        $this->post($this->url().'/selection', ['selected' => true])->assertRedirect()->assertSessionHasNoErrors();

        $after = $gate->evaluate($this->plan->refresh(), 'plan');
        self::assertSame(
            CanonicalJson::encode($before->results),
            CanonicalJson::encode($after->results),
            'Selecting a reconstruction must not move a verdict until the rules are switched on.',
        );
        self::assertCount(17, $after->deferred(), 'The nine phase-2 rules stay unimplemented in this slice.');
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
            CanonicalJson::encode($before->results),
            CanonicalJson::encode($after->results),
            'Recording streams must not move a verdict until the rules are switched on.',
        );
        self::assertCount(17, $after->deferred(), 'The nine phase-2 rules stay unimplemented in this slice.');
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
            CanonicalJson::encode($before->results),
            CanonicalJson::encode($after->results),
            'Recording terms must not move a verdict until the rules are switched on.',
        );
        self::assertCount(17, $after->deferred(), 'The nine phase-2 rules stay unimplemented in this slice.');
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
            'actuals' => [[
                'period' => '2026-01', 'state' => 'trading', 'reconciled' => true, 'closing_bank_cash' => '5000',
                'reviewed_by' => 'A. Advisor', 'reviewed_on' => '2026-02-05', 'pre_trading_evidence' => null,
            ]],
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
