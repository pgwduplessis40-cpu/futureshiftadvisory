<?php

declare(strict_types=1);

namespace Tests\Feature\Entrepreneurs;

use App\Enums\EntrepreneurStage;
use App\Models\AuditEvent;
use App\Models\BusinessPlan;
use App\Models\EntrepreneurForecastModel;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Services\Entrepreneurs\BusinessPlanSnapshot;
use App\Services\Entrepreneurs\Forecast\CashOpening;
use App\Services\Entrepreneurs\Forecast\ForecastCalendar;
use App\Services\Entrepreneurs\Forecast\FounderMonth;
use App\Services\Entrepreneurs\Forecast\ReservePolicy;
use App\Services\Entrepreneurs\Forecast\RevenueStream;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastEngineVersion;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelInputs;
use App\Services\Entrepreneurs\Forecast\Storage\ForecastModelStore;
use App\Services\Entrepreneurs\Forecast\StreamMonth;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ForecastModelStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('security.mfa_required', false);
        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_saving_records_a_first_revision_and_an_audit_event(): void
    {
        [$advisor, $plan] = $this->plan();

        $model = app(ForecastModelStore::class)->save($plan, $this->inputs(), $advisor);

        self::assertSame(1, $model->revision);
        self::assertSame(ForecastEngineVersion::CURRENT, $model->engine_version);
        self::assertFalse($model->isSelected(), 'A saved model must not select itself.');

        $event = AuditEvent::query()->where('action', 'entrepreneur.forecast_model_saved')->sole();
        self::assertSame(['drivers', 'actuals', 'reserve_policy'], data_get($event->after, 'changed_sections'));
        self::assertSame($plan->getKey(), data_get($event->after, 'business_plan_id'));
    }

    public function test_resaving_the_same_inputs_neither_bumps_the_revision_nor_writes_an_audit_event(): void
    {
        [$advisor, $plan] = $this->plan();
        $store = app(ForecastModelStore::class);

        $store->save($plan, $this->inputs(), $advisor);
        $second = $store->save($plan, $this->inputs(), $advisor);

        self::assertSame(1, $second->revision, 'An unchanged save is not an edit.');
        self::assertSame(1, AuditEvent::query()->where('action', 'entrepreneur.forecast_model_saved')->count());
    }

    public function test_a_changed_driver_bumps_the_revision_and_names_the_changed_section(): void
    {
        [$advisor, $plan] = $this->plan();
        $store = app(ForecastModelStore::class);
        $store->save($plan, $this->inputs(), $advisor);

        $model = $store->save($plan, $this->inputs(price: '1400'), $advisor);

        self::assertSame(2, $model->revision);
        // Audit ids are uuids, so the event is found by the revision it
        // records rather than by row order.
        $event = AuditEvent::query()
            ->where('action', 'entrepreneur.forecast_model_saved')
            ->get()
            ->sole(fn (AuditEvent $candidate): bool => data_get($candidate->after, 'revision') === 2);
        self::assertSame(['drivers'], data_get($event->after, 'changed_sections'));
        self::assertSame(['actuals', 'reserve_policy'], array_values(array_diff(
            ['drivers', 'actuals', 'reserve_policy'],
            (array) data_get($event->after, 'changed_sections'),
        )), 'Sections that did not move must not be reported as edited.');
    }

    public function test_a_stale_expected_revision_is_rejected_rather_than_overwriting_newer_figures(): void
    {
        [$advisor, $plan] = $this->plan();
        $store = app(ForecastModelStore::class);
        $store->save($plan, $this->inputs(), $advisor);

        $this->expectException(ValidationException::class);
        $store->save($plan, $this->inputs(price: '1400'), $advisor, expectedRevision: 0);
    }

    public function test_selection_and_deselection_are_separately_recorded(): void
    {
        [$advisor, $plan] = $this->plan();
        $store = app(ForecastModelStore::class);
        $model = $store->save($plan, $this->inputs(), $advisor);

        self::assertNull($store->selectedForPlan($plan), 'A saved model is not a selected model.');

        $selected = $store->select($model, $advisor);
        self::assertTrue($selected->isSelected());
        self::assertSame($advisor->getKey(), $selected->selected_by_user_id);
        self::assertNotNull($store->selectedForPlan($plan));
        self::assertSame(1, AuditEvent::query()->where('action', 'entrepreneur.forecast_model_selected')->count());

        $store->deselect($selected->refresh(), $advisor);
        self::assertNull($store->selectedForPlan($plan));
        self::assertSame(1, AuditEvent::query()->where('action', 'entrepreneur.forecast_model_deselected')->count());
    }

    public function test_an_unselected_model_leaves_the_plan_snapshot_byte_identical(): void
    {
        Carbon::setTestNow('2026-11-07T09:00:00+13:00');
        [$advisor, $plan] = $this->plan();
        $before = app(BusinessPlanSnapshot::class)->captureInputs($plan);

        app(ForecastModelStore::class)->save($plan, $this->inputs(), $advisor);

        $after = app(BusinessPlanSnapshot::class)->captureInputs($plan->refresh());
        self::assertSame(json_encode($before), json_encode($after), 'Opting in is selection, not saving.');
        self::assertArrayNotHasKey('forecast_model', $after);
    }

    public function test_a_selected_model_names_its_version_in_the_snapshot(): void
    {
        [$advisor, $plan] = $this->plan();
        $store = app(ForecastModelStore::class);
        $store->select($store->save($plan, $this->inputs(), $advisor), $advisor);

        $snapshot = app(BusinessPlanSnapshot::class)->captureInputs($plan->refresh());

        self::assertSame(ForecastEngineVersion::CURRENT, data_get($snapshot, 'forecast_model.engine_version'));
        self::assertSame(1, data_get($snapshot, 'forecast_model.revision'));
        // The snapshot carries the version, never the release evaluation.
        self::assertArrayNotHasKey('issue_readiness', $snapshot);
    }

    public function test_a_stored_model_loads_back_into_engine_value_objects(): void
    {
        [$advisor, $plan] = $this->plan();
        $store = app(ForecastModelStore::class);
        $store->save($plan, $this->inputs(), $advisor);

        $stored = $store->forPlan($plan);
        self::assertInstanceOf(EntrepreneurForecastModel::class, $stored);
        $restored = $store->inputs($stored);

        self::assertSame('2026-01', $restored->calendar->startMonth);
        self::assertSame('1200', $restored->streams[0]->price);
        self::assertNull($restored->streams[0]->schedule['2026-01']->providerUnitCost);
        self::assertSame('3350.00', $restored->reservePolicy?->target()?->toScale(2)->__toString());
    }

    public function test_the_table_enforces_row_level_security(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::markTestSkipped('Row-level security requires Postgres (see docs/dev-setup.md).');
        }

        $flags = DB::selectOne(
            'SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = ?',
            ['entrepreneur_forecast_models'],
        );
        self::assertTrue((bool) $flags?->relrowsecurity, 'Row-level security must be enabled.');
        self::assertTrue((bool) $flags?->relforcerowsecurity, 'Row-level security must be forced for the table owner.');

        $policy = DB::selectOne(
            'SELECT qual, with_check FROM pg_policies WHERE tablename = ? AND policyname = ?',
            ['entrepreneur_forecast_models', 'entrepreneur_forecast_models_scope'],
        );
        self::assertNotNull($policy, 'The scope policy must exist.');
        // The read scope admits the assigned advisor and the founder; the
        // write scope admits the advisor, because the reconstruction is an
        // advisor decision rather than a founder entry.
        self::assertStringContainsString('assigned_advisor_id', (string) $policy->qual);
        self::assertStringContainsString('user_id', (string) $policy->qual);
        self::assertStringContainsString('assigned_advisor_id', (string) $policy->with_check);
    }

    private function inputs(string $price = '1200'): ForecastModelInputs
    {
        return new ForecastModelInputs(
            calendar: new ForecastCalendar('2026-01', 24),
            streams: [new RevenueStream('intensive', 'Intensive day', 'day', $price, '2', '6', true, 'own_record', [
                '2026-01' => new StreamMonth('4', '4', '4', '24', '0'),
            ])],
            founderMonths: ['2026-01' => new FounderMonth('80', '16', '8')],
            cashOpening: new CashOpening('100', 'bank statement 2025-12-31', true),
            reservePolicy: new ReservePolicy('1000', 3, true, '500', '0.10', 'monthly fixed costs including owner pay'),
        );
    }

    /** @return array{0:User,1:BusinessPlan} */
    private function plan(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        $entrepreneur = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ENTREPRENEUR,
            'primary_role' => User::TYPE_ENTREPRENEUR,
        ]);
        $entrepreneur->assignRole(User::TYPE_ENTREPRENEUR);
        $profile = EntrepreneurProfile::query()->create([
            'user_id' => $entrepreneur->id,
            'assigned_advisor_id' => $advisor->id,
            'name' => 'Forecast Founder',
            'email' => $entrepreneur->email,
            'stage' => EntrepreneurStage::BUILDING_PHASE_1,
            'concept_summary' => 'Forecast concept.',
        ]);
        $plan = BusinessPlan::query()->create([
            'entrepreneur_profile_id' => $profile->id,
            'title' => 'Forecast test plan',
            'source_type' => BusinessPlan::SOURCE_ENTREPRENEUR,
            'status' => BusinessPlan::STATUS_BUILDING,
            'current_phase' => 1,
            'created_by_user_id' => $entrepreneur->id,
        ]);

        return [$advisor, $plan];
    }
}
