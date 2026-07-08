<?php

declare(strict_types=1);

namespace Tests\Feature\Dd;

use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\EngagementType;
use App\Enums\QuestionnaireSet;
use App\Models\AnalysisFinding;
use App\Models\AnalysisRun;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\DdDataRoomItem;
use App\Models\DdEngagement;
use App\Models\DdWorkstream;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Conflicts\ConflictDeclarer;
use App\Services\Dd\DataRoom;
use App\Services\Dd\DdOnboarding;
use App\Services\Dd\Workstreams\DdWorkstreamRunner;
use App\Services\Integration\Iponz\Contracts\IponzClient;
use App\Services\Integration\Linz\Contracts\LinzClient;
use App\Services\Integration\Ppsr\Contracts\PpsrClient;
use App\Support\RequestContext;
use Database\Seeders\DdSpecificQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DdWorkstreamRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(DdSpecificQuestionnaireSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    public function test_each_dd_workstream_runs_on_the_analysis_spine_with_double_weighted_document_support(): void
    {
        [$advisor, $engagement] = $this->ddEngagement();
        $this->questionnaireResponse($engagement);

        foreach (array_keys(DataRoom::WORKSTREAMS) as $workstream) {
            $this->dataRoomItem($engagement, $workstream, DocumentVerification::OUTCOME_VERIFIED);
        }

        $records = app(DdWorkstreamRunner::class)->runAll($engagement, $advisor);

        $this->assertCount(8, $records);
        $this->assertTrue($records->every(fn (DdWorkstream $record): bool => $record->status === DdWorkstream::STATUS_COMPLETED));
        $this->assertDatabaseCount('analysis_runs', 8);

        foreach ($records as $record) {
            $this->assertSame(2, $record->verification_weight);
            $this->assertSame(AnalysisRun::STATUS_COMPLETED, $record->analysisRun?->status);
            $this->assertSame(AnalysisModuleEnum::DdWorkstream, $record->analysisRun?->module);

            $finding = $record->analysisRun?->findings()->first();
            $this->assertInstanceOf(AnalysisFinding::class, $finding);
            $this->assertSame(AnalysisFinding::DOCUMENT_SUPPORT_VERIFIED, $finding->document_support);
            $this->assertNotSame([], $finding->attributions);
            $this->assertStringContainsString('double-weighted', $finding->body);
        }
    }

    public function test_nz_specific_checks_are_run_and_persisted_for_relevant_workstreams(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('nz-check-dd-advisor@example.test');
        $this->questionnaireResponse($engagement);
        $this->dataRoomItem($engagement, 'legal', DocumentVerification::OUTCOME_VERIFIED);
        $this->dataRoomItem($engagement, 'tax', DocumentVerification::OUTCOME_VERIFIED);
        $this->dataRoomItem($engagement, 'hr_people', DocumentVerification::OUTCOME_VERIFIED);
        $this->dataRoomItem($engagement, 'operational', DocumentVerification::OUTCOME_VERIFIED);

        $legal = app(DdWorkstreamRunner::class)->run($engagement, 'legal', $advisor);
        $tax = app(DdWorkstreamRunner::class)->run($engagement, 'tax', $advisor);
        $hr = app(DdWorkstreamRunner::class)->run($engagement, 'hr_people', $advisor);
        $operational = app(DdWorkstreamRunner::class)->run($engagement, 'operational', $advisor);

        $this->assertArrayHasKey('ppsr', $legal->nz_checks);
        $this->assertArrayHasKey('linz', $legal->nz_checks);
        $this->assertArrayHasKey('iponz', $legal->nz_checks);
        $this->assertArrayHasKey('ird_gst', $tax->nz_checks);
        $this->assertArrayHasKey('holidays_act', $hr->nz_checks);
        $this->assertArrayHasKey('owner_dependency', $operational->nz_checks);
        $this->assertSame('declined_current_gateway_pending_data_consumer', $tax->nz_checks['ird_gst']['status']);
        $this->assertSame('ird:gateway:regulatory-deferred', $tax->nz_checks['ird_gst']['source_reference']);
        $this->assertStringContainsString('cannot be independently verified', $tax->nz_checks['ird_gst']['finding']);
        $this->assertStringContainsString('reapply for IRD Data Consumer access', $tax->nz_checks['ird_gst']['required_action']);

        $this->assertNotSame([], app(PpsrClient::class)->securityInterests('9429000000999'));
        $this->assertNotSame([], app(LinzClient::class)->titleInterests('9429000000999'));
        $this->assertNotSame([], app(IponzClient::class)->intellectualProperty('Target Supplies Limited', '9429000000999'));
    }

    public function test_accuracy_discrepancy_pauses_only_the_affected_workstream(): void
    {
        [$advisor, $engagement] = $this->ddEngagement('pause-dd-advisor@example.test');
        $this->questionnaireResponse($engagement);
        $this->dataRoomItem($engagement, 'legal', DocumentVerification::OUTCOME_ACCURACY_DISCREPANCY);
        $this->dataRoomItem($engagement, 'financial', DocumentVerification::OUTCOME_VERIFIED);

        $legal = app(DdWorkstreamRunner::class)->run($engagement, 'legal', $advisor);
        $financial = app(DdWorkstreamRunner::class)->run($engagement, 'financial', $advisor);

        $this->assertSame(DdWorkstream::STATUS_PAUSED, $legal->status);
        $this->assertSame(DdWorkstream::PAUSED_ACCURACY_DISCREPANCY, $legal->paused_reason);
        $this->assertNull($legal->analysis_run_id);
        $this->assertSame(DdWorkstream::STATUS_COMPLETED, $financial->status);
        $this->assertNotNull($financial->analysis_run_id);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'dd.workstream_paused',
            'subject_id' => $legal->id,
        ]);
    }

    /**
     * @return array{0: User, 1: DdEngagement}
     */
    private function ddEngagement(string $advisorEmail = 'workstream-dd-advisor@example.test'): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $advisorEmail,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::DUE_DILIGENCE,
            'nzbn' => '9429000000111',
            'legal_name' => 'Buyer Holdings Limited',
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);
        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::DUE_DILIGENCE->value],
        ]);

        $conflict = app(ConflictDeclarer::class)->declare(
            advisor: $advisor,
            client: $client,
            referralType: ConflictDeclarer::DUE_DILIGENCE,
            existingRelationship: false,
        );

        $engagement = app(DdOnboarding::class)->start(
            buyer: $client,
            advisor: $advisor,
            conflict: $conflict,
            targetName: 'Target Supplies Limited',
            targetDetails: [
                'nzbn' => '9429000000999',
                'industry' => 'Distribution',
                'notes' => 'Owner dependent warehouse operation with key person risk.',
            ],
        );

        return [$advisor, $engagement];
    }

    private function questionnaireResponse(DdEngagement $engagement): void
    {
        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::DUE_DILIGENCE)
            ->published()
            ->firstOrFail();
        $question = $questionnaire->sections()->with('questions')->firstOrFail()->questions->first();
        $this->assertNotNull($question);

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $engagement->client_id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
        ]);

        $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Target has trading, legal, tax, HR, operational, valuation, market, and regulatory evidence in the data room.',
            'attached_document_ids' => [],
        ]);
    }

    private function dataRoomItem(DdEngagement $engagement, string $workstream, string $verificationOutcome): DdDataRoomItem
    {
        $document = Document::query()->create([
            'client_id' => $engagement->client_id,
            'category' => Document::CATEGORY_DD_ARTIFACT,
            'original_filename' => "{$workstream}.txt",
            'stored_path' => 'dd-workstreams/'.Str::uuid().".{$workstream}.txt",
            'byte_size' => 128,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', $workstream.$verificationOutcome),
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);

        DocumentVerification::query()->create([
            'document_id' => $document->id,
            'client_id' => $engagement->client_id,
            'claim_source' => 'dd_workstream_fixture',
            'context_hash' => hash('sha256', $document->id.$verificationOutcome),
            'claim_text' => "The {$workstream} evidence supports the DD workstream.",
            'outcome' => $verificationOutcome,
            'confidence' => 0.9300,
            'verified_at' => now(),
        ]);

        return DdDataRoomItem::query()->create([
            'client_id' => $engagement->client_id,
            'dd_engagement_id' => $engagement->id,
            'document_id' => $document->id,
            'workstream' => $workstream,
            'folder' => 'general',
            'artifact_type' => DdDataRoomItem::ARTIFACT_TYPE,
            'source' => DdDataRoomItem::SOURCE_GUEST_UPLOAD,
        ]);
    }
}
