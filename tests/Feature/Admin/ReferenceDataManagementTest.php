<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\EconomicIndicator;
use App\Models\IndustryWaccData;
use App\Models\LearningUpdate;
use App\Models\LearningUpdateImplementation;
use App\Models\NpoEngagement;
use App\Models\ReferenceDataEntry;
use App\Models\User;
use App\Models\ValuationMultiple;
use App\Services\Learning\ApprovalFlow;
use App\Services\Npo\NpoValueCalculator;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ReferenceDataManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);

        $this->secureRoot = storage_path('framework/testing/reference-data-secure-storage');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_economic_indicator_is_ledgered_then_projected_only_on_implementation(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();

        $this->submitJson($admin, ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR, [
            'indicator' => 'cpi_annual',
            'label' => 'CPI annual',
            'value' => 3.4,
            'unit' => 'percent',
            'period_date' => '2026-06-01',
        ], source: 'stats-manual');

        $entry = ReferenceDataEntry::query()->firstOrFail();
        $update = $entry->learningUpdate()->firstOrFail();

        $this->assertSame(ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR, $entry->dataset);
        $this->assertSame('2026-06-01', $entry->as_at?->toDateString());
        $this->assertSame('stats-manual', $entry->source);
        $this->assertSame(LearningUpdate::STATUS_DETECTED, $update->status);
        $this->assertDatabaseCount('economic_indicators', 0);

        $implementation = $this->approveAndImplement($update, $admin);
        $indicator = EconomicIndicator::query()->firstOrFail();

        $this->assertSame(LearningUpdate::STATUS_IMPLEMENTED, $update->refresh()->status);
        $this->assertSame(EconomicIndicator::class, $implementation->target_type);
        $this->assertSame($indicator->id, $implementation->target_id);
        $this->assertSame('manual_admin', $indicator->source_badge);
        $this->assertSame('stats-manual', $indicator->source);
        $this->assertEqualsWithDelta(3.4, $indicator->value, 0.001);
        $this->assertSame($entry->id, $indicator->payload['reference_data_entry_id']);
        $this->assertSame(ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR, $implementation->after_state['projection']['dataset']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'reference_data.submitted',
            'subject_id' => $entry->id,
        ]);
    }

    public function test_reference_data_page_exposes_dashboard_record_targets(): void
    {
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->get(route('admin.reference-data.index', ['target' => 'economic_indicator:gdp_quarterly']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/reference-data/Index')
                ->where('recordTargets.0.key', 'economic_indicator:ocr')
                ->where('recordTargets.2.key', 'economic_indicator:gdp_quarterly')
                ->where('recordTargets.2.dataset', ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR)
                ->where('recordTargets.2.indicator', EconomicIndicator::GDP_QUARTERLY)
                ->where('recordTargets.4.key', ReferenceDataEntry::DATASET_VALUATION_MULTIPLE)
                ->where('datasets.0', ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR));
    }

    public function test_reference_data_page_marks_selected_gdp_as_pending_review(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.reference-data.store'), [
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'source' => 'manual_admin',
                'as_at' => '2026-06-15',
                'payload_json' => json_encode([
                    'indicator' => EconomicIndicator::GDP_QUARTERLY,
                    'label' => 'GDP quarterly',
                    'value' => 0.2,
                    'unit' => 'percent_quarterly_change',
                    'period_date' => '2026-06-15',
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('admin.reference-data.index', ['target' => 'economic_indicator:gdp_quarterly'], absolute: false))
            ->assertSessionHas('status', 'reference-data-submitted');

        $this->actingAsMfa($admin)
            ->get(route('admin.reference-data.index', ['target' => 'economic_indicator:gdp_quarterly']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/reference-data/Index')
                ->where('pendingReviews.0.target_key', 'economic_indicator:gdp_quarterly')
                ->where('pendingReviews.0.label', 'GDP quarterly')
                ->where('pendingReviews.0.value', '0.2 percent_quarterly_change')
                ->where('pendingReviews.0.as_at', '2026-06-15')
                ->where('pendingReviews.0.status', LearningUpdate::STATUS_DETECTED)
                ->where('entries.0.learning_update_status', LearningUpdate::STATUS_DETECTED));
    }

    public function test_duplicate_pending_reference_data_submission_reuses_existing_review(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $admin = $this->superAdmin();
        $payload = [
            'indicator' => EconomicIndicator::GDP_QUARTERLY,
            'label' => 'GDP quarterly',
            'value' => 0.2,
            'unit' => 'percent_quarterly_change',
            'period_date' => '2026-06-15',
        ];

        $this->actingAsMfa($admin)
            ->post(route('admin.reference-data.store'), [
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'source' => 'manual_admin',
                'as_at' => '2026-06-15',
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('admin.reference-data.index', ['target' => 'economic_indicator:gdp_quarterly'], absolute: false))
            ->assertSessionHas('status', 'reference-data-submitted');

        $this->actingAsMfa($admin)
            ->post(route('admin.reference-data.store'), [
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'source' => 'manual_admin',
                'as_at' => '2026-06-15',
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('admin.reference-data.index', ['target' => 'economic_indicator:gdp_quarterly'], absolute: false))
            ->assertSessionHas('status', 'reference-data-pending-review');

        $this->assertDatabaseCount('reference_data_entries', 1);
        $this->assertDatabaseCount('learning_updates', 1);
    }

    public function test_reference_data_submission_accepts_screenshot_evidence(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();
        $screenshot = UploadedFile::fake()->create('rbnz-ocr-screenshot.png', 8, 'image/png');

        $this->actingAsMfa($admin)
            ->post(route('admin.reference-data.store'), [
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'source' => 'rbnz-screenshot',
                'as_at' => '2026-05-29',
                'payload_json' => json_encode([
                    'indicator' => EconomicIndicator::OCR,
                    'label' => 'OCR reference rate',
                    'value' => 2.25,
                    'unit' => 'percent',
                    'period_date' => '2026-05-27',
                ], JSON_THROW_ON_ERROR),
                'evidence_upload' => $screenshot,
            ])
            ->assertRedirect(route('admin.reference-data.index', ['target' => 'economic_indicator:ocr'], absolute: false));

        $entry = ReferenceDataEntry::query()->firstOrFail();
        $document = Document::query()->firstOrFail();
        $update = $entry->learningUpdate()->firstOrFail();

        $this->assertSame($document->id, $entry->evidence_document_id);
        $this->assertSame(Document::CATEGORY_REFERENCE_DATA_EVIDENCE, $document->category);
        $this->assertSame('rbnz-ocr-screenshot.png', $document->original_filename);
        $this->assertSame($document->id, data_get($update->evidence, 'evidence_document.id'));

        $this->actingAsMfa($admin)
            ->get(route('admin.reference-data.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/reference-data/Index')
                ->where('entries.0.evidence.id', $document->id)
                ->where('entries.0.evidence.filename', 'rbnz-ocr-screenshot.png')
                ->where('entries.0.evidence.url', route('admin.reference-data.evidence', $document, absolute: false)));

        $this->actingAsMfa($admin)
            ->get(route('admin.reference-data.evidence', $document))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_rejected_reference_data_never_projects(): void
    {
        $admin = $this->superAdmin();

        $this->submitJson($admin, ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR, [
            'indicator' => 'unemployment_rate',
            'value' => 4.8,
            'unit' => 'percent',
        ]);

        $update = ReferenceDataEntry::query()->firstOrFail()->learningUpdate()->firstOrFail();
        $update->forceFill(['status' => LearningUpdate::STATUS_REJECTED])->save();

        $this->assertSame(0, app(ApprovalFlow::class)->implementDue(now(), $admin)->count());
        $this->assertDatabaseCount('economic_indicators', 0);
    }

    public function test_valuation_multiple_projection_supersedes_prior_active_row(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();
        $basePayload = [
            'industry_code' => 'M6962',
            'industry_label' => 'Management advice',
            'metric' => 'ebitda',
            'multiple_low' => 2.4,
            'multiple_mid' => 3.1,
            'multiple_high' => 3.8,
            'quarter' => '2026Q2',
        ];

        $this->submitJson($admin, ReferenceDataEntry::DATASET_VALUATION_MULTIPLE, $basePayload, source: 'broker-survey');
        $firstEntry = ReferenceDataEntry::query()->firstOrFail();
        $this->approveAndImplement($firstEntry->learningUpdate()->firstOrFail(), $admin);
        $first = ValuationMultiple::query()->firstOrFail();

        $this->submitJson($admin, ReferenceDataEntry::DATASET_VALUATION_MULTIPLE, [
            ...$basePayload,
            'multiple_low' => 2.8,
            'multiple_mid' => 3.4,
            'multiple_high' => 4.0,
        ], source: 'broker-survey');
        // Select the just-submitted entry deterministically: the frozen test clock makes
        // both ledger rows share created_at, so latest() ordering is ambiguous (UUID PK).
        $secondEntry = ReferenceDataEntry::query()->whereKeyNot($firstEntry->getKey())->firstOrFail();
        $this->approveAndImplement($secondEntry->learningUpdate()->firstOrFail(), $admin);

        $active = ValuationMultiple::query()->whereNull('superseded_at')->firstOrFail();

        $this->assertNotNull($first->refresh()->superseded_at);
        $this->assertSame('manual_admin', $active->source_badge);
        $this->assertEqualsWithDelta(3.4, $active->multiple_mid, 0.01);
        $this->assertSame(2, ValuationMultiple::query()->count());
    }

    public function test_industry_wacc_projects_to_consumer_table_on_implementation(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();

        $this->submitJson($admin, ReferenceDataEntry::DATASET_INDUSTRY_WACC, [
            'industry_code' => 'M6962',
            'industry_label' => 'Management advice',
            'wacc_rate' => 0.1125,
            'cost_of_equity' => 0.14,
            'cost_of_debt' => 0.075,
            'equity_weight' => 0.7,
            'debt_weight' => 0.3,
            'quarter' => '2026Q2',
        ], source: 'manual-wacc');

        $this->approveAndImplement(ReferenceDataEntry::query()->firstOrFail()->learningUpdate()->firstOrFail(), $admin);
        $wacc = IndustryWaccData::query()->firstOrFail();

        $this->assertSame('M6962', $wacc->industry_code);
        $this->assertSame('manual_admin', $wacc->source_badge);
        $this->assertEqualsWithDelta(0.1125, $wacc->wacc_rate, 0.000001);
        $this->assertSame('2026Q2', $wacc->quarter);
    }

    public function test_cpb_csv_upload_is_scanned_grouped_and_effective_only_after_implementation(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $admin = $this->superAdmin();
        $engagement = $this->npoEngagement();

        $csv = implode("\n", [
            'programme_type,size_band,cost_per_beneficiary',
            'community_services,medium,500',
            'food_rescue,small,300',
        ]);

        $this->actingAsMfa($admin)
            ->post(route('admin.reference-data.store'), [
                'dataset' => ReferenceDataEntry::DATASET_CPB_BENCHMARK,
                'source' => 'cpb-template',
                'as_at' => '2026-06-01',
                'upload' => UploadedFile::fake()->createWithContent('cpb.csv', $csv),
            ])
            ->assertRedirect(route('admin.reference-data.index', ['target' => ReferenceDataEntry::DATASET_CPB_BENCHMARK], absolute: false));

        $entry = ReferenceDataEntry::query()->firstOrFail();
        $update = $entry->learningUpdate()->firstOrFail();

        $this->assertSame(Document::SCANNER_CLEAN, Document::query()->firstOrFail()->scanner_result);
        $this->assertCount(2, $entry->payload['benchmarks']);
        $this->assertCount(2, $update->proposed_change['benchmarks']);

        $update->forceFill([
            'status' => LearningUpdate::STATUS_APPROVED,
            'effective_date' => now()->addDay(),
        ])->save();

        $approvedOnly = app(NpoValueCalculator::class)->calculateCostPerBeneficiary($engagement, [
            'programme_type' => 'community_services',
            'size_band' => 'medium',
            'programme_expenditure' => 95000,
            'beneficiary_count' => 100,
        ]);

        $this->assertEqualsWithDelta(950.0, $approvedOnly->result['benchmark_cost_per_beneficiary'], 0.01);

        $update->forceFill(['effective_date' => now()->subMinute()])->save();
        $this->assertSame(1, app(ApprovalFlow::class)->implementDue(now(), $admin)->count());

        $implemented = app(NpoValueCalculator::class)->calculateCostPerBeneficiary($engagement, [
            'programme_type' => 'community_services',
            'size_band' => 'medium',
            'programme_expenditure' => 95000,
            'beneficiary_count' => 100,
        ]);

        $this->assertEqualsWithDelta(500.0, $implemented->result['benchmark_cost_per_beneficiary'], 0.01);
        $this->assertSame($update->id, $implemented->benchmark_config['learning_update_id']);
    }

    public function test_non_super_admin_cannot_submit_reference_data(): void
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $this->actingAsMfa($advisor)
            ->post(route('admin.reference-data.store'), [
                'dataset' => ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR,
                'source' => 'manual',
                'as_at' => '2026-06-01',
                'payload_json' => json_encode([
                    'indicator' => 'cpi_annual',
                    'value' => 3.4,
                    'unit' => 'percent',
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertForbidden();
    }

    public function test_reference_data_entries_are_append_only_on_postgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Append-only trigger is enforced by Postgres.');
        }

        $admin = $this->superAdmin();
        $this->submitJson($admin, ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR, [
            'indicator' => 'ocr',
            'value' => 5.25,
            'unit' => 'percent',
        ]);
        $entry = ReferenceDataEntry::query()->firstOrFail();

        $this->expectException(QueryException::class);

        $entry->forceFill(['source' => 'changed'])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submitJson(
        User $admin,
        string $dataset,
        array $payload,
        string $source = 'manual-admin',
    ): void {
        $this->actingAsMfa($admin)
            ->post(route('admin.reference-data.store'), [
                'dataset' => $dataset,
                'source' => $source,
                'as_at' => '2026-06-01',
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('admin.reference-data.index', ['target' => $this->targetForPayload($dataset, $payload)], absolute: false));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function targetForPayload(string $dataset, array $payload): string
    {
        if ($dataset === ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR) {
            return ReferenceDataEntry::DATASET_ECONOMIC_INDICATOR.':'.(string) $payload['indicator'];
        }

        return $dataset;
    }

    private function approveAndImplement(LearningUpdate $update, User $admin): LearningUpdateImplementation
    {
        $update->forceFill([
            'status' => LearningUpdate::STATUS_APPROVED,
            'effective_date' => now()->subMinute(),
            'review_due_at' => now()->addDays(30),
        ])->save();

        return app(ApprovalFlow::class)->implementDue(now(), $admin)->firstOrFail();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    private function npoEngagement(): NpoEngagement
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Reference Data NPO Trust',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        return NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::StandardNpo,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);
    }
}
