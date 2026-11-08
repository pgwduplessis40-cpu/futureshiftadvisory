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
        self::assertFalse($sections['streams']['editable']);
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
}
