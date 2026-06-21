<?php

declare(strict_types=1);

namespace Tests\Feature\Proposals;

use App\Enums\DiscountMethod;
use App\Enums\EngagementType;
use App\Enums\FeeMethod;
use App\Enums\ProposalStatus;
use App\Enums\PvType;
use App\Models\BusinessValuation;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Consent;
use App\Models\FeeCalculation;
use App\Models\Proposal;
use App\Models\PvCalculation;
use App\Models\Template;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Proposals\ProposalBuilder;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

final class ProposalBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const RLS_APP_ROLE = 'fsa_proposals_rls_app';

    private object $renderer;

    private bool $connectionBypassesRls = false;

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

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->connectionBypassesRls = $this->currentRoleBypassesRls();

            if ($this->connectionBypassesRls) {
                $this->createNonBypassRole();
            }
        }
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('RESET ROLE');

            if ($this->connectionBypassesRls) {
                DB::statement('REVOKE SELECT ON proposals, consents FROM '.self::RLS_APP_ROLE);
                DB::statement('REVOKE USAGE ON SCHEMA public FROM '.self::RLS_APP_ROLE);
                DB::statement('DROP ROLE IF EXISTS '.self::RLS_APP_ROLE);
            }
        }

        parent::tearDown();
    }

    public function test_advisor_generates_branded_proposal_pdf_consents_and_payload(): void
    {
        [$advisor, $client] = $this->clientWithTeam();
        $this->businessValuation($client, 450000);
        $calculation = $this->feeCalculation($client, 12000, 3.5);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.clients.proposals.store', $client), [
                'fee_calculation_id' => $calculation->id,
                'scope_summary' => 'Staged advisory roadmap for the next quarter.',
                'insurance_consent' => Consent::ELECTION_OPT_IN,
                'coach_consent' => Consent::ELECTION_OPT_OUT,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false));

        $proposal = Proposal::query()->with('consents')->firstOrFail();

        $this->assertSame(ProposalStatus::Draft, $proposal->status);
        $this->assertSame('Staged advisory roadmap for the next quarter.', $proposal->scope['summary']);
        $this->assertEquals(450000.0, $proposal->pv_summary['current_pv']);
        $this->assertSame(3.5, $proposal->roi_ratio);
        $this->assertSame(2, $proposal->consents->count());
        $this->assertDatabaseHas('consents', [
            'proposal_id' => $proposal->id,
            'type' => Consent::TYPE_INSURANCE_REFERRAL,
            'election' => Consent::ELECTION_OPT_IN,
        ]);
        $this->assertNull($proposal->pdf_path);
        $this->assertNull($proposal->pdf_byte_size);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.generated',
            'client_id' => $client->id,
        ]);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.proposal_store_url', route('advisor.clients.proposals.store', $client, absolute: false))
                ->where('client.proposals.0.status', ProposalStatus::Draft->value)
                ->where('client.proposals.0.can_release', true)
                ->where('client.proposals.0.view_url', route('advisor.proposals.show', $proposal, absolute: false))
                ->where('client.proposals.0.download_url', route('advisor.proposals.download', $proposal, absolute: false))
                ->has('client.fee_calculations', 1)
                ->has('client.proposals', 1));

        $this->actingAsMfa($advisor)
            ->get(route('advisor.proposals.show', $proposal))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('Future Shift Advisory', false)
            ->assertSee('Proposal Client Limited', false);

        $this->actingAsMfa($advisor)
            ->get(route('advisor.proposals.download', $proposal))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $proposal->refresh();
        Storage::disk('secure_local')->assertExists($proposal->pdf_path);
        $this->assertGreaterThan(100, $proposal->pdf_byte_size);
        $this->assertStringContainsString('Future Shift Advisory', $this->renderer->html);
        $this->assertStringContainsString('ROI ratio', $this->renderer->html);
    }

    public function test_proposal_generation_uses_active_uploaded_proposal_template(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required to build the uploaded proposal template fixture.');
        }

        [$advisor, $client] = $this->clientWithTeam('proposal-template-advisor@example.test');
        $calculation = $this->feeCalculation($client, 15000, 4.2);
        $path = 'templates/proposal-template.docx';

        Storage::disk('secure_local')->put($path, $this->minimalProposalTemplateDocx());

        Template::query()->create([
            'category' => Template::CATEGORY_PROPOSAL,
            'title' => 'FSA Uploaded Proposal Template',
            'body' => '',
            'structure' => [
                'source_kind' => 'uploaded_file',
                'uploaded_file' => [
                    'stored_path' => $path,
                    'extension' => 'docx',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'original_name' => 'FSA_Report_Proposal_Template.docx',
                    'sha256' => hash('sha256', Storage::disk('secure_local')->get($path) ?: ''),
                ],
            ],
            'source_reference' => 'test:proposal-template',
            'status' => Template::STATUS_ACTIVE,
            'version' => 2,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        $proposal = app(ProposalBuilder::class)->generate($client, $calculation, [
            'scope' => ['summary' => 'Template-driven proposal scope.'],
        ], [
            'created_by_user_id' => $advisor->getKey(),
        ]);

        app(ProposalBuilder::class)->rerenderPdf($proposal);

        $this->assertStringContainsString('UPLOADED PROPOSAL TEMPLATE', $this->renderer->html);
        $this->assertStringContainsString('Proposal Client Limited', $this->renderer->html);
        $this->assertStringContainsString('Template-driven proposal scope.', $this->renderer->html);
        $this->assertStringContainsString('Validity period starts on release', $this->renderer->html);
        $this->assertStringContainsString('$2,500 per month - 6-month engagement', $this->renderer->html);
        $this->assertStringContainsString('Estimated ROI: 4.20x return', $this->renderer->html);
        $this->assertStringContainsString('PV of $63,000', $this->renderer->html);
        $this->assertStringContainsString('<h2>Scope</h2>', $this->renderer->html);
        $this->assertStringNotContainsString('[Expiry Date]', $this->renderer->html);
        $this->assertStringNotContainsString('[X]× return', $this->renderer->html);
        $this->assertStringNotContainsString('[X,XXX]', $this->renderer->html);
        $this->assertStringNotContainsString('[XXX,XXX]', $this->renderer->html);
        $this->assertStringNotContainsString('Body text - Arial 9.5pt', $this->renderer->html);
        $this->assertStringNotContainsString('Financial Health Assessment', $this->renderer->html);
    }

    public function test_generating_proposal_recalls_current_unsigned_client_proposals(): void
    {
        [$advisor, $client] = $this->clientWithTeam('proposal-recall-advisor@example.test');
        $draft = $this->storedProposal($client);
        $released = $this->storedProposal($client);
        $renewed = $this->storedProposal($client);
        $awaitingSignature = $this->storedProposal($client);
        $signed = $this->storedProposal($client);

        $released->forceFill([
            'status' => ProposalStatus::Released,
            'released_at' => now(),
            'released_by_user_id' => $advisor->getKey(),
            'expires_at' => now()->addDays(30),
        ])->save();
        $renewed->forceFill(['status' => ProposalStatus::Renewed])->save();
        $awaitingSignature->forceFill([
            'status' => ProposalStatus::Released,
            'released_at' => now(),
            'released_by_user_id' => $advisor->getKey(),
            'expires_at' => now()->addDays(30),
        ])->save();
        Proposal::allowSignoffStatusTransition(fn () => $awaitingSignature->forceFill([
            'status' => ProposalStatus::AwaitingSignature,
            'awaiting_signature_at' => now(),
        ])->save());
        $signed->forceFill([
            'status' => ProposalStatus::Released,
            'released_at' => now(),
            'released_by_user_id' => $advisor->getKey(),
            'expires_at' => now()->addDays(30),
        ])->save();
        Proposal::allowSignoffStatusTransition(function () use ($signed): void {
            $signed->forceFill([
                'status' => ProposalStatus::AwaitingSignature,
                'awaiting_signature_at' => now(),
            ])->save();
            $signed->forceFill([
                'status' => ProposalStatus::Signed,
                'signed_at' => now(),
            ])->save();
        });

        $newProposal = app(ProposalBuilder::class)->generate($client, $this->feeCalculation($client, 18000, 4), [], [
            'created_by_user_id' => $advisor->getKey(),
        ]);

        foreach ([$draft, $released, $renewed, $awaitingSignature] as $proposal) {
            $proposal->refresh();
            $this->assertSame(ProposalStatus::Recalled, $proposal->status);
            $this->assertSame($advisor->getKey(), $proposal->recalled_by_user_id);
            $this->assertNull($proposal->expires_at);
        }

        $this->assertSame(ProposalStatus::Signed, $signed->refresh()->status);
        $this->assertSame(ProposalStatus::Draft, $newProposal->refresh()->status);
        $this->assertDatabaseCount('proposals', 6);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.auto_recalled',
            'subject_id' => $draft->id,
        ]);
    }

    public function test_recalled_proposals_are_not_releaseable(): void
    {
        [$advisor, $client] = $this->clientWithTeam('proposal-recalled-advisor@example.test');
        $proposal = $this->storedProposal($client);
        $proposal->forceFill([
            'status' => ProposalStatus::Recalled,
            'recalled_at' => now(),
            'recalled_by_user_id' => $advisor->getKey(),
        ])->save();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.proposals.0.status', ProposalStatus::Recalled->value)
                ->where('client.proposals.0.can_release', false)
                ->where('client.proposals.0.can_recall', false)
                ->where('client.proposals.0.can_renew', false));

        $this->actingAsMfa($advisor)
            ->from(route('advisor.clients.show', $client, absolute: false))
            ->patch(route('advisor.proposals.release', $proposal), [
                'expiry_days' => 30,
            ])
            ->assertRedirect(route('advisor.clients.show', $client, absolute: false))
            ->assertSessionHasErrors(['proposal' => 'Only draft or renewed proposals can be released.']);

        $this->assertSame(ProposalStatus::Recalled, $proposal->refresh()->status);
    }

    public function test_release_recall_expiry_command_and_renewal_flow(): void
    {
        Config::set('proposals.expiry_days', 12);
        $this->travelTo(now()->setMicrosecond(0));
        [$advisor, $client] = $this->clientWithTeam('proposal-flow@example.test');
        $calculation = $this->feeCalculation($client, 9000, 2.25);
        $builder = app(ProposalBuilder::class);

        $proposal = $builder->generate($client, $calculation, [], [
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $released = $builder->release($proposal, $advisor);

        $this->assertSame(ProposalStatus::Released, $released->status);
        $this->assertSame(now()->addDays(12)->toDateString(), $released->expires_at?->toDateString());
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.released',
            'subject_id' => $released->id,
        ]);

        $recalled = $builder->recall($released, $advisor);

        $this->assertSame(ProposalStatus::Recalled, $recalled->status);
        $this->assertNull($recalled->expires_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.recalled',
            'subject_id' => $released->id,
        ]);

        try {
            $builder->release($recalled, $advisor, 1);
            $this->fail('Recalled proposals must not be releaseable.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Only draft or renewed proposals can be released.', $exception->getMessage());
        }

        $activeProposal = $builder->generate($client, $this->feeCalculation($client, 9000, 2.25), [], [
            'created_by_user_id' => $advisor->getKey(),
        ]);
        $expiring = $builder->release($activeProposal, $advisor, 1);
        $this->travelTo(now()->addDays(2));

        $this->artisan('proposals:expire')
            ->expectsOutput('1 proposal expired.')
            ->assertSuccessful();

        $expired = $expiring->refresh();
        $this->assertSame(ProposalStatus::Expired, $expired->status);
        $this->assertNotNull($expired->expired_at);

        $renewed = $builder->renew($expired, $advisor);

        $this->assertSame(ProposalStatus::Renewed, $renewed->status);
        $this->assertSame(2, $renewed->version);
        $this->assertSame($expired->id, $renewed->renewed_from_proposal_id);
        $this->assertNull($renewed->pdf_path);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'proposal.renewed',
            'subject_id' => $renewed->id,
        ]);

        $releasedRenewal = $builder->release($renewed, $advisor, 30);
        $this->assertSame(ProposalStatus::Released, $releasedRenewal->status);
    }

    public function test_signature_statuses_are_blocked_outside_signoff_flow(): void
    {
        $client = $this->client('Reserved Proposal Limited');
        $calculation = $this->feeCalculation($client, 5000, 1.5);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('sign-off flow');

        Proposal::query()->create([
            'client_id' => $client->id,
            'fee_calculation_id' => $calculation->id,
            'status' => ProposalStatus::AwaitingSignature,
            'version' => 1,
            'scope' => ['summary' => 'Reserved status probe.'],
            'services' => [],
            'pv_summary' => [],
            'roi_ratio' => 0,
            'acceptance_terms' => [],
        ]);
    }

    public function test_proposals_and_consents_are_isolated_by_client_rls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Proposal RLS assertions require Postgres.');
        }

        $clientA = $this->client('Proposal A Limited');
        $clientB = $this->client('Proposal B Limited');
        $proposalA = $this->storedProposal($clientA);
        $proposalB = $this->storedProposal($clientB);

        app(RequestContext::class)->apply('advisor', [(string) $clientA->getKey()]);

        $visibleProposalIds = $this->withRlsRole(fn (): array => DB::table('proposals')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
        $visibleConsentIds = $this->withRlsRole(fn (): array => DB::table('consents')
            ->pluck('proposal_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());

        $this->assertContains($proposalA->id, $visibleProposalIds);
        $this->assertNotContains($proposalB->id, $visibleProposalIds);
        $this->assertContains($proposalA->id, $visibleConsentIds);
        $this->assertNotContains($proposalB->id, $visibleConsentIds);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientWithTeam(string $advisorEmail = 'proposal-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = $this->client('Proposal Client Limited', $advisor);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return [$advisor, $client];
    }

    private function client(string $name, ?User $createdBy = null): Client
    {
        app(RequestContext::class)->apply('system', []);

        return Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => fake()->numerify('9429#########'),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $createdBy?->getKey(),
        ]);
    }

    private function feeCalculation(Client $client, float $mid, float $roi): FeeCalculation
    {
        return FeeCalculation::query()->create([
            'client_id' => $client->getKey(),
            'method' => FeeMethod::OutcomeBased,
            'inputs' => ['fixture' => true],
            'suggested_low' => round($mid * 0.8, 2),
            'suggested_mid' => $mid,
            'suggested_high' => round($mid * 1.2, 2),
            'improvement_pv_total' => round($mid * $roi, 2),
            'risk_cost_pv_total' => 2500,
            'roi_ratio' => $roi,
            'justification' => [
                'services' => [
                    ['name' => 'Advisor roadmap', 'line_total' => $mid],
                ],
            ],
        ]);
    }

    private function businessValuation(Client $client, float $mid): BusinessValuation
    {
        $calculation = PvCalculation::query()->create([
            'client_id' => $client->getKey(),
            'type' => PvType::BusinessValuation,
            'discount_method' => DiscountMethod::AdvisorConfigured,
            'discount_rate' => 0.12,
            'discount_rate_rationale' => 'Fixture valuation rate.',
            'inputs' => ['fixture' => true],
            'result' => ['present_value' => $mid],
            'as_at' => now(),
            'source_attributions' => [
                ['claim' => 'Fixture valuation', 'source_reference' => 'test:valuation'],
            ],
        ]);

        return BusinessValuation::query()->create([
            'client_id' => $client->getKey(),
            'pv_calculation_id' => $calculation->getKey(),
            'sde_value' => ['mid' => $mid],
            'ebitda_value' => ['mid' => $mid],
            'dcf_value' => ['mid' => $mid],
            'reconciled_low' => $mid * 0.9,
            'reconciled_mid' => $mid,
            'reconciled_high' => $mid * 1.1,
            'adjustments' => [],
            'source_attributions' => [
                ['claim' => 'Fixture valuation', 'source_reference' => 'test:valuation'],
            ],
            'as_at' => now(),
        ]);
    }

    private function storedProposal(Client $client): Proposal
    {
        $calculation = $this->feeCalculation($client, 7500, 2);

        $proposal = Proposal::query()->create([
            'client_id' => $client->id,
            'fee_calculation_id' => $calculation->id,
            'status' => ProposalStatus::Draft,
            'version' => 1,
            'scope' => ['summary' => 'RLS fixture proposal.'],
            'services' => [['name' => 'RLS service']],
            'pv_summary' => ['roi_ratio' => 2],
            'roi_ratio' => 2,
            'acceptance_terms' => ['phase' => 'phase_2_release_only'],
        ]);

        Consent::query()->create([
            'client_id' => $client->id,
            'proposal_id' => $proposal->id,
            'type' => Consent::TYPE_INSURANCE_REFERRAL,
            'election' => Consent::ELECTION_UNDECIDED,
            'evidence' => ['fixture' => true],
            'captured_at' => now(),
        ]);

        return $proposal;
    }

    private function minimalProposalTemplateDocx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fsa-proposal-template-');
        $this->assertIsString($path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path, \ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>UPLOADED PROPOSAL TEMPLATE [Business Name] [Date]</w:t></w:r></w:p>
    <w:p><w:r><w:t>Valid until [Expiry Date]</w:t></w:r></w:p>
    <w:p><w:r><w:t>$[X,XXX] per month - [X]-month engagement</w:t></w:r></w:p>
    <w:p><w:r><w:t>Estimated ROI: [X]× return on advisory investment in year 1</w:t></w:r></w:p>
    <w:p><w:r><w:t>Based on total identified improvement opportunity PV of $[XXX,XXX]</w:t></w:r></w:p>
    <w:p><w:r><w:t>Prepared for [Client Name]</w:t></w:r></w:p>
    <w:p><w:r><w:t>1. Financial Health Assessment</w:t></w:r></w:p>
    <w:p><w:r><w:t>[Body text - Arial 9.5pt, Dark Grey. State the finding directly in the first sentence. Evidence follows. Every claim is referenced to the source data.]</w:t></w:r></w:p>
    <w:p><w:r><w:t>Metric [X]% [Y]%</w:t></w:r></w:p>
  </w:body>
</w:document>
XML);
        $this->assertTrue($zip->close());

        $contents = file_get_contents($path);
        @unlink($path);

        $this->assertIsString($contents);

        return $contents;
    }

    private function currentRoleBypassesRls(): bool
    {
        $role = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        return (bool) ($role->rolsuper ?? false) || (bool) ($role->rolbypassrls ?? false);
    }

    private function createNonBypassRole(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '%1$s') THEN
                    CREATE ROLE %1$s NOLOGIN NOBYPASSRLS;
                END IF;
            END
            $$;

            GRANT USAGE ON SCHEMA public TO %1$s;
            GRANT SELECT ON proposals, consents TO %1$s;
        SQL, self::RLS_APP_ROLE));
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    private function withRlsRole(callable $callback): mixed
    {
        if (! $this->connectionBypassesRls) {
            return $callback();
        }

        DB::statement('SET ROLE '.self::RLS_APP_ROLE);
        $usesSavepoint = DB::transactionLevel() > 0;

        if ($usesSavepoint) {
            DB::statement('SAVEPOINT proposals_rls_probe');
        }

        try {
            $result = $callback();

            if ($usesSavepoint) {
                DB::statement('RELEASE SAVEPOINT proposals_rls_probe');
            }

            return $result;
        } catch (\Throwable $e) {
            if ($usesSavepoint) {
                DB::statement('ROLLBACK TO SAVEPOINT proposals_rls_probe');
            }

            throw $e;
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
