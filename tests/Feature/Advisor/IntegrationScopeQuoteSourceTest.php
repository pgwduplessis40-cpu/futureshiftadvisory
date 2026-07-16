<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Enums\EngagementType;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\FeeCalculation;
use App\Models\IntegrationFeeBand;
use App\Models\IntegrationScope;
use App\Models\QuoteSourceExtraction;
use App\Models\User;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class IntegrationScopeQuoteSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('secure_local');
        app(RequestContext::class)->apply('system', []);
    }

    public function test_advisor_uploads_an_external_plan_and_confirms_its_scope_rows_before_pricing(): void
    {
        [$advisor, $client, $scope] = $this->scopeFixture();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.integration-scopes.quote-source-extractions.store', $scope), [
                'description' => 'Use this client-supplied implementation plan with the current systems analysis.',
                'documents' => [UploadedFile::fake()->createWithContent('client-app-plan.txt', $this->planText())],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $extraction = QuoteSourceExtraction::query()->with('documents.verification')->firstOrFail();
        $this->assertSame(QuoteSourceExtraction::STATUS_EXTRACTED, $extraction->status);
        $this->assertCount(4, $extraction->extracted_rows);
        $this->assertSame('verified', $extraction->documents->firstOrFail()->verification?->outcome);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.integration-scopes.quote-source-extractions.confirm', [$scope, $extraction]), [
                'row_ids' => collect($extraction->extracted_rows)->pluck('id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $scope->refresh();
        $documentId = (string) $extraction->documents->firstOrFail()->document_id;
        $this->assertContains($documentId, $scope->source_document_ids);
        $this->assertTrue(collect($scope->systems)->contains('name', 'Field Service Board'));
        $this->assertTrue(collect($scope->tasks)->contains('description', 'Re-key completed field jobs into Xero invoices'));
        $this->assertTrue(collect($scope->connections)->contains(fn (array $connection): bool => $connection['from_system'] === 'field-service-board' && $connection['to_system'] === 'xero'));
        $this->assertContains($documentId, $scope->computed['source_document_ids']);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.integration-scopes.fee-calculations.store', $scope))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $calculation = FeeCalculation::query()->firstOrFail();
        $this->assertSame([$documentId], $calculation->inputs['quote_source_document_ids']);
        $this->assertSame([$documentId], $calculation->justification['source_document_ids']);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.integration-scopes.show', $scope))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope.quote_source_extractions.0.status', 'extracted')
                ->where('scope.quote_source_extractions.0.rows.0.review_status', 'confirmed'));
    }

    public function test_unreviewed_external_plan_blocks_a_fee_calculation(): void
    {
        [$advisor, , $scope] = $this->scopeFixture();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.integration-scopes.quote-source-extractions.store', $scope), [
                'documents' => [UploadedFile::fake()->createWithContent('client-app-plan.txt', $this->planText())],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.integration-scopes.fee-calculations.store', $scope))
            ->assertRedirect()
            ->assertSessionHasErrors('fee_calculation');

        $this->assertDatabaseCount('fee_calculations', 0);
    }

    public function test_discrepant_external_plan_cannot_be_used_for_a_quote_even_after_upload(): void
    {
        [$advisor, , $scope] = $this->scopeFixture();

        $this->actingAsMfa($advisor)
            ->post(route('advisor.integration-scopes.quote-source-extractions.store', $scope), [
                'documents' => [UploadedFile::fake()->createWithContent(
                    'discrepant-app-plan.txt',
                    "accuracy discrepancy\n".$this->planText(),
                )],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $extraction = QuoteSourceExtraction::query()->firstOrFail();
        $this->assertSame(QuoteSourceExtraction::STATUS_BLOCKED, $extraction->status);
        $this->assertStringContainsString('re-verified', (string) $extraction->blocked_reason);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.integration-scopes.fee-calculations.store', $scope))
            ->assertRedirect()
            ->assertSessionHasErrors('fee_calculation');
    }

    public function test_advisor_can_enable_fsa_hosting_with_a_cost_plus_markup_charge(): void
    {
        [$advisor, , $scope] = $this->scopeFixture();

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.integration-scopes.update', $scope), [
                'fsa_hosting_enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $scope->refresh();

        $this->assertTrue($scope->fsa_hosting_enabled);
        $this->assertSame(20.66, $scope->computed['hosting']['monthly_cost']);
        $this->assertEquals(100.0, $scope->computed['hosting']['markup_percent']);
        $this->assertSame(41.32, $scope->computed['hosting']['monthly_fee']);
        $this->assertSame(495.84, $scope->computed['hosting']['annual_fee']);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.integration-scopes.show', $scope))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope.fsa_hosting_enabled', true)
                ->where('scope.computed.hosting.monthly_fee', 41.32)
                ->missing('scope.computed.hosting.monthly_cost')
                ->missing('scope.computed.hosting.markup_percent'));
    }

    /** @return array{0:User,1:Client,2:IntegrationScope} */
    private function scopeFixture(): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000001',
            'legal_name' => 'External Plan Systems Limited',
            'data_quality' => Client::DATA_QUALITY_MEDIUM,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        foreach ([IntegrationFeeBand::BAND_S, IntegrationFeeBand::BAND_M, IntegrationFeeBand::BAND_L, IntegrationFeeBand::BAND_XL] as $band) {
            IntegrationFeeBand::query()->create([
                'complexity_band' => $band,
                'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
                'fee_low' => 8_000,
                'fee_mid' => 10_000,
                'fee_high' => 12_000,
                'currency' => 'NZD',
                'is_active' => true,
                'updated_by_user_id' => $advisor->getKey(),
            ]);
        }

        $scope = IntegrationScope::query()->create([
            'client_id' => $client->getKey(),
            'status' => IntegrationScope::STATUS_COMPLETE,
            'systems' => [
                ['id' => 'xero', 'name' => 'Xero', 'vendor' => 'Xero', 'role' => 'Accounting', 'api_quality' => 'rest_public', 'auth' => 'oauth', 'monthly_records' => 2_800, 'confidence' => 'known', 'source' => 'manual'],
            ],
            'tasks' => [
                ['id' => 'invoices', 'description' => 'Re-key invoices', 'minutes_per_occurrence' => 10, 'occurrences_per' => 'week', 'people_count' => 1, 'hourly_cost' => 50, 'confidence' => 'known', 'source' => 'manual'],
            ],
            'connections' => [
                ['id' => 'xero-export', 'from_system' => 'xero', 'to_system' => 'xero', 'direction' => 'one_way', 'transform_complexity' => 'low', 'confidence' => 'known', 'source' => 'manual'],
            ],
            'delivery_mode' => IntegrationScope::DELIVERY_INHOUSE,
            'capture_percent' => 80,
            'savings_horizon_years' => 3,
            'discount_rate_percent' => 12,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        return [$advisor, $client, $scope];
    }

    private function planText(): string
    {
        return implode("\n", [
            'System: Field Service Board; API: none; auth: none; monthly records: 12500',
            'System: Client CRM; API: rest_partner; auth: oauth; monthly records: 8400',
            'Task: Re-key completed field jobs into Xero invoices; 14 minutes; 2 people; day; $52/hour',
            'Connection: Field Service Board -> Xero; one way; med',
        ]);
    }
}
