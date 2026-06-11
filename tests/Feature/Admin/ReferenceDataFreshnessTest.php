<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\EconomicIndicator;
use App\Models\LearningUpdate;
use App\Models\ReferenceDataEntry;
use App\Models\User;
use App\Services\Learning\ApprovalFlow;
use App\Services\ReferenceData\ReferenceDataFreshness;
use App\Services\ReferenceData\ReferenceDataSubmission;
use App\Support\RequestContext;
use Carbon\CarbonInterface;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ReferenceDataFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_approved_but_not_implemented_reference_data_does_not_clear_dashboard_task(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();
        $entry = $this->submitEconomic($admin, EconomicIndicator::CPI_ANNUAL, now());
        $update = $entry->learningUpdate()->firstOrFail();

        $update->forceFill([
            'status' => LearningUpdate::STATUS_APPROVED,
            'effective_date' => now()->subMinute(),
        ])->save();

        $dashboard = app(ReferenceDataFreshness::class)->dashboard(now());
        $task = collect($dashboard['items'])->firstWhere('key', 'economic_indicator:cpi_annual');

        $this->assertIsArray($task);
        $this->assertSame(ReferenceDataFreshness::STATUS_MISSING, $task['status']);

        app(ApprovalFlow::class)->implementDue(now(), $admin);
        $dashboard = app(ReferenceDataFreshness::class)->dashboard(now());

        $this->assertNull(collect($dashboard['items'])->firstWhere('key', 'economic_indicator:cpi_annual'));
    }

    public function test_implemented_reference_data_becomes_overdue_after_declared_cadence(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();
        $entry = $this->submitEconomic($admin, EconomicIndicator::OCR, Carbon::parse('2026-01-01'));

        $this->approveAndImplement($entry->learningUpdate()->firstOrFail(), $admin);

        $dashboard = app(ReferenceDataFreshness::class)->dashboard(now());
        $task = collect($dashboard['items'])->firstWhere('key', 'economic_indicator:ocr');

        $this->assertIsArray($task);
        $this->assertSame(ReferenceDataFreshness::STATUS_OVERDUE, $task['status']);
        $this->assertSame('2026-02-15', $task['due_at']);
        $this->assertStringContainsString('target=economic_indicator%3Aocr', $task['action_url']);
    }

    public function test_record_targets_expose_each_dashboard_series(): void
    {
        $targets = app(ReferenceDataFreshness::class)->recordTargets();

        $this->assertCount(7, $targets);
        $this->assertSame([
            'economic_indicator:ocr',
            'economic_indicator:cpi_annual',
            'economic_indicator:gdp_quarterly',
            'economic_indicator:unemployment_rate',
            'valuation_multiple',
            'industry_wacc',
            'cpb_benchmark',
        ], collect($targets)->pluck('key')->all());

        $gdp = collect($targets)->firstWhere('key', 'economic_indicator:gdp_quarterly');

        $this->assertSame(ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR, $gdp['dataset'] ?? null);
        $this->assertSame(EconomicIndicator::GDP_QUARTERLY, $gdp['indicator'] ?? null);
        $this->assertSame('GDP quarterly', $gdp['label'] ?? null);
    }

    public function test_freshness_command_sends_due_notifications_and_clears_resolved_task(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();

        $this->artisan('reference-data:check-freshness')
            ->assertExitCode(0);

        $notification = $admin->notifications()
            ->where('type', 'reference_data.stale')
            ->get()
            ->first(fn ($notification): bool => data_get($notification->data, 'dataset_key') === 'economic_indicator:ocr');

        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);

        $entry = $this->submitEconomic($admin, EconomicIndicator::OCR, now());
        $this->approveAndImplement($entry->learningUpdate()->firstOrFail(), $admin);

        $this->artisan('reference-data:check-freshness')
            ->assertExitCode(0);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    private function submitEconomic(User $admin, string $indicator, CarbonInterface $asAt): ReferenceDataEntry
    {
        return app(ReferenceDataSubmission::class)->submit(
            dataset: ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
            payload: [
                'indicator' => $indicator,
                'label' => str_replace('_', ' ', $indicator),
                'value' => 3.4,
                'unit' => 'percent',
                'period_date' => $asAt->toDateString(),
            ],
            asAt: $asAt,
            source: 'manual-test',
            actor: $admin,
        );
    }

    private function approveAndImplement(LearningUpdate $update, User $admin): void
    {
        $update->forceFill([
            'status' => LearningUpdate::STATUS_APPROVED,
            'effective_date' => now()->subMinute(),
            'review_due_at' => now()->addDays(30),
        ])->save();

        app(ApprovalFlow::class)->implementDue(now(), $admin);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }
}
