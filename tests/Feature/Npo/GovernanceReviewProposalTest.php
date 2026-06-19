<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\NpoEngagement;
use App\Models\PaymentAuthority;
use App\Models\Proposal;
use App\Models\ProposalSignoffStep;
use App\Models\User;
use App\Services\Fees\FeeCalculator;
use App\Services\Pdf\PdfRenderer;
use App\Services\Proposals\ProposalBuilder;
use App\Services\Proposals\SignoffFlow;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class GovernanceReviewProposalTest extends TestCase
{
    use RefreshDatabase;

    private object $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
        Storage::fake('secure_local');

        $this->renderer = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html): string
            {
                $this->html = $html;

                return "%PDF-1.4\n".strip_tags($html);
            }
        };

        $this->app->instance(PdfRenderer::class, $this->renderer);
    }

    public function test_governance_review_fixed_fee_proposal_releases_signs_and_authorises_payment(): void
    {
        [$advisor, $client, $clientUser, $engagement] = $this->npoClient();

        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::GovernanceReview, [
            'size_band' => 'small',
            'annual_operating_budget' => 350000,
        ], [
            'npo_engagement_id' => $engagement->id,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $this->assertSame($engagement->id, $calculation->npo_engagement_id);
        $this->assertSame(FeeMethod::GovernanceReview, $calculation->method);
        $this->assertSame(1500.0, $calculation->suggested_mid);
        $this->assertEquals(750.0, $calculation->justification['conversion_credit']['amount_mid']);
        $this->assertTrue($calculation->justification['conversion_credit']['advisor_discretion']);
        $this->assertNull($calculation->justification['retainer_structure']);

        $builder = app(ProposalBuilder::class);
        $proposal = $builder->generate($client, $calculation, [
            'consents' => [
                Consent::TYPE_INSURANCE_REFERRAL => Consent::ELECTION_UNDECIDED,
                Consent::TYPE_COACH_REFERRAL => Consent::ELECTION_UNDECIDED,
            ],
        ], [
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $this->assertSame($engagement->id, $proposal->npo_engagement_id);
        $this->assertSame(FeeMethod::GovernanceReview->value, $proposal->scope['proposal_variant']);
        $this->assertTrue($proposal->acceptance_terms['fixed_fee']);
        $this->assertTrue($proposal->acceptance_terms['no_retainer_structure']);
        $this->assertEquals(750.0, $proposal->acceptance_terms['conversion_credit']['amount_mid']);
        $this->assertSame($engagement->id, $proposal->pv_summary['npo_engagement_id']);

        $proposal = $builder->rerenderPdf($proposal);

        $this->assertStringContainsString('Conversion credit', $this->renderer->html);
        Storage::disk('secure_local')->assertExists($proposal->pdf_path);

        $proposal = $builder->release($proposal, $advisor);
        $flow = app(SignoffFlow::class);

        $flow->complete($proposal, ProposalSignoffStep::STEP_REVIEW, [], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_INSURANCE_CONSENT, [
            'election' => Consent::ELECTION_OPT_IN,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_COACH_CONSENT, [
            'election' => Consent::ELECTION_OPT_OUT,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_PAYMENT_METHOD, [
            'type' => PaymentAuthority::TYPE_CARD,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_AUTHORITY, [
            'fixture_token' => 'governance-review-authority',
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_SIGNATURE, [
            'signature_name' => 'Governance Signer',
            'accepted' => true,
            'ip' => '203.0.113.52',
            'user_agent' => 'Governance review test',
        ], $clientUser);
        $flow->complete($proposal, ProposalSignoffStep::STEP_CONFIRMATION, [], $clientUser);

        $proposal = $proposal->refresh();
        $this->assertSame(ProposalStatus::Signed, $proposal->status);
        $this->assertSame(7, $proposal->signoffSteps()->count());
        $this->assertDatabaseHas('payment_authorities', [
            'proposal_id' => $proposal->id,
            'status' => PaymentAuthority::STATUS_ACTIVE,
            'gateway' => PaymentAuthority::GATEWAY_STRIPE,
        ]);
        $this->assertEqualsCanonicalizing([
            Consent::TYPE_COACH_REFERRAL,
            Consent::TYPE_INSURANCE_REFERRAL,
        ], Consent::query()->where('proposal_id', $proposal->id)->pluck('type')->all());
    }

    public function test_governance_review_fee_bands_are_applied(): void
    {
        [, $client, , $engagement] = $this->npoClient('governance-band-advisor@example.test');
        $calculator = app(FeeCalculator::class);
        $options = ['npo_engagement_id' => $engagement->id];

        $small = $calculator->calculate($client, FeeMethod::GovernanceReview, ['size_band' => 'small'], $options);
        $medium = $calculator->calculate($client, FeeMethod::GovernanceReview, ['size_band' => 'medium'], $options);
        $large = $calculator->calculate($client, FeeMethod::GovernanceReview, ['annual_operating_budget' => 2500000], $options);

        $this->assertSame([1500.0, 1500.0, 1500.0], [
            $small->suggested_low,
            $small->suggested_mid,
            $small->suggested_high,
        ]);
        $this->assertSame([1800.0, 2000.0, 2200.0], [
            $medium->suggested_low,
            $medium->suggested_mid,
            $medium->suggested_high,
        ]);
        $this->assertSame([2200.0, 2350.0, 2500.0], [
            $large->suggested_low,
            $large->suggested_mid,
            $large->suggested_high,
        ]);
        $this->assertSame('large', $large->justification['size_band']);
        $this->assertEquals(1175.0, $large->justification['conversion_credit']['amount_mid']);
    }

    public function test_proposal_builder_rejects_mismatched_governance_review_engagement_pairing(): void
    {
        [$advisor, $client, , $engagementA] = $this->npoClient('governance-mismatch-advisor@example.test');
        $engagementB = $this->governanceEngagement($client);
        $calculation = app(FeeCalculator::class)->calculate($client, FeeMethod::GovernanceReview, [
            'size_band' => 'medium',
        ], [
            'npo_engagement_id' => $engagementA->id,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Proposal NPO engagement must match');

        app(ProposalBuilder::class)->generate($client, $calculation, [
            'npo_engagement_id' => $engagementB->id,
        ], [
            'created_by_user_id' => $advisor->getKey(),
        ]);
    }

    public function test_composite_foreign_keys_reject_different_client_npo_engagements(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Composite FK enforcement assertions require Postgres.');
        }

        [, $clientA, , $engagementA] = $this->npoClient('governance-fk-a@example.test', 'Governance FK A Trust');
        [, , , $engagementB] = $this->npoClient('governance-fk-b@example.test', 'Governance FK B Trust');

        $this->assertCompositeFkRejects(fn (): FeeCalculation => FeeCalculation::query()->create([
            'client_id' => $clientA->getKey(),
            'npo_engagement_id' => $engagementB->getKey(),
            'method' => FeeMethod::GovernanceReview,
            'inputs' => ['fixture' => true],
            'suggested_low' => 1500,
            'suggested_mid' => 1500,
            'suggested_high' => 1500,
            'improvement_pv_total' => 0,
            'risk_cost_pv_total' => 0,
            'roi_ratio' => 0,
            'justification' => ['fixture' => true],
        ]));

        $calculation = FeeCalculation::query()->create([
            'client_id' => $clientA->getKey(),
            'npo_engagement_id' => $engagementA->getKey(),
            'method' => FeeMethod::GovernanceReview,
            'inputs' => ['fixture' => true],
            'suggested_low' => 1500,
            'suggested_mid' => 1500,
            'suggested_high' => 1500,
            'improvement_pv_total' => 0,
            'risk_cost_pv_total' => 0,
            'roi_ratio' => 0,
            'justification' => ['fixture' => true],
        ]);

        $this->assertCompositeFkRejects(fn (): Proposal => Proposal::query()->create([
            'client_id' => $clientA->getKey(),
            'npo_engagement_id' => $engagementB->getKey(),
            'fee_calculation_id' => $calculation->getKey(),
            'status' => ProposalStatus::Draft,
            'version' => 1,
            'scope' => ['summary' => 'Cross-client FK probe.'],
            'services' => [],
            'pv_summary' => [],
            'roi_ratio' => 0,
            'acceptance_terms' => [],
        ]));
    }

    /**
     * @return array{0: User, 1: Client, 2: User, 3: NpoEngagement}
     */
    private function npoClient(
        string $advisorEmail = 'governance-proposal-advisor@example.test',
        string $clientName = 'Community Governance Proposal Trust',
    ): array {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $clientUser = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $clientUser->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => $clientName,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
            'primary_contact_user_id' => $clientUser->getKey(),
        ]);

        foreach ([[$advisor, 'lead_advisor'], [$clientUser, 'primary_contact']] as [$user, $role]) {
            ClientTeamMember::query()->create([
                'client_id' => $client->id,
                'user_id' => $user->getKey(),
                'role' => $role,
                'granted_modules' => [EngagementType::NPO->value],
            ]);
        }

        return [$advisor, $client, $clientUser, $this->governanceEngagement($client)];
    }

    private function governanceEngagement(Client $client): NpoEngagement
    {
        return NpoEngagement::query()->create([
            'client_id' => $client->id,
            'sub_type' => NpoEngagementSubType::GovernanceReview,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
            'isa_2022_reregistered' => null,
        ]);
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function assertCompositeFkRejects(callable $callback): void
    {
        DB::statement('SAVEPOINT governance_review_fk_probe');

        try {
            $callback();
            DB::statement('RELEASE SAVEPOINT governance_review_fk_probe');
            $this->fail('The composite NPO engagement/client foreign key should reject this row.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT governance_review_fk_probe');
            $this->addToAssertionCount(1);
        }
    }
}
