<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\GovernanceReviewFinding;
use App\Models\NpoComplianceAlert;
use App\Models\NpoEngagement;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Analysis\Modules\ComplianceChecker;
use App\Services\Npo\GovernanceReviewAnalyzer;
use App\Services\Npo\NpoComplianceLookup;
use App\Support\RequestContext;
use Database\Seeders\GovernanceReviewQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class GovernanceReviewAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private CapturingGovernanceAiClient $ai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, GovernanceReviewQuestionnaireSeeder::class]);
        app(RequestContext::class)->apply('system', []);
        $this->ai = new CapturingGovernanceAiClient;
        $this->app->instance(AiClient::class, $this->ai);
    }

    public function test_generates_source_attributed_pending_findings_and_requires_advisor_review(): void
    {
        [$engagement, $advisor] = $this->engagement(NpoLegalStructure::RegisteredCharity);
        $this->governanceResponse($engagement, $advisor, paidStaff: true);

        $findings = app(GovernanceReviewAnalyzer::class)->run($engagement, $advisor);

        $this->assertSame(1, $this->ai->analyseCalls);
        $this->assertSame(GovernanceReviewAnalyzer::PROMPT_ID, $this->ai->lastPrompt?->id);
        $this->assertGreaterThanOrEqual(7, $findings->count());
        $this->assertTrue($findings->every(
            fn (GovernanceReviewFinding $finding): bool => $finding->npo_engagement_id === $engagement->id
                && $finding->client_id === $engagement->client_id
                && $finding->status === GovernanceReviewFinding::STATUS_PENDING_ADVISOR_REVIEW
                && $finding->attributions !== []
                && $finding->uncertainty instanceof Uncertainty
                && ($finding->ai_payload['prompt_hash'] ?? null) === $this->ai->lastPrompt?->hash(),
        ));
        $this->assertSame([], app(GovernanceReviewAnalyzer::class)->clientFacingFindings($engagement)->all());

        $paidStaffFinding = $findings->firstWhere('finding_key', 'paid_staff_holidays_act');
        $this->assertInstanceOf(GovernanceReviewFinding::class, $paidStaffFinding);
        $this->assertStringContainsString(strtolower(ComplianceChecker::HOLIDAYS_ACT), $this->criteriaText($paidStaffFinding));
        $this->assertStringContainsString(ComplianceChecker::PROMPT_ID, $this->criteriaText($paidStaffFinding));

        $reviewed = app(GovernanceReviewAnalyzer::class)->review($findings->first(), $advisor, 'Advisor checked source pack.');

        $this->assertTrue($reviewed->isReviewed());
        $this->assertCount(1, app(GovernanceReviewAnalyzer::class)->clientFacingFindings($engagement));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.governance_review_analysis_generated',
            'subject_id' => $engagement->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'npo.governance_review_finding_reviewed',
            'subject_id' => $reviewed->id,
            'actor_role' => User::TYPE_ADVISOR,
        ]);
    }

    public function test_flags_evidence_thin_when_questionnaire_response_is_missing(): void
    {
        [$engagement, $advisor] = $this->engagement(NpoLegalStructure::RegisteredCharity);

        $findings = app(GovernanceReviewAnalyzer::class)->run($engagement, $advisor);

        $evidenceDepth = $findings->firstWhere('finding_key', 'evidence_depth');

        $this->assertInstanceOf(GovernanceReviewFinding::class, $evidenceDepth);
        $this->assertSame('high', $evidenceDepth->severity->value);
        $this->assertStringContainsString('No submitted governance review questionnaire response', $evidenceDepth->body);
        $this->assertNotEmpty($evidenceDepth->attributions);
    }

    public function test_advisor_routes_run_and_review_governance_findings_from_client_page(): void
    {
        [$engagement, $advisor] = $this->engagement(NpoLegalStructure::RegisteredCharity);
        $this->governanceResponse($engagement, $advisor);

        $this->actingAsMfa($advisor)
            ->post(route('advisor.npo-engagements.governance-review.analysis', $engagement))
            ->assertRedirect();

        $finding = GovernanceReviewFinding::query()
            ->where('npo_engagement_id', $engagement->id)
            ->where('status', GovernanceReviewFinding::STATUS_PENDING_ADVISOR_REVIEW)
            ->firstOrFail();

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $engagement->client_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('client.npo_governance_review.id', $engagement->id)
                ->where(
                    'client.npo_governance_review.pending_review_count',
                    GovernanceReviewFinding::query()->where('npo_engagement_id', $engagement->id)->count(),
                )
                ->where('client.npo_governance_review.findings.0.review_url', route('advisor.governance-review-findings.review', $finding, absolute: false)));

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.governance-review-findings.review', $finding), [
                'advisor_notes' => 'Reviewed against the governance evidence pack.',
            ])
            ->assertRedirect();

        $this->assertTrue($finding->refresh()->isReviewed());
        $this->assertSame('Reviewed against the governance evidence pack.', $finding->advisor_notes);
    }

    public function test_selects_legal_structure_specific_criteria(): void
    {
        [$charityEngagement, $advisor] = $this->engagement(NpoLegalStructure::RegisteredCharity);
        $this->governanceResponse($charityEngagement, $advisor);

        $charityFindings = app(GovernanceReviewAnalyzer::class)->run($charityEngagement, $advisor);
        $charityCriteria = $this->criteriaText($charityFindings->firstWhere('finding_key', 'legal_structure_compliance'));

        $this->assertStringContainsString('s.42g', $charityCriteria);
        $this->assertStringContainsString('charities amendment act 2023', $charityCriteria);

        [$societyEngagement, $societyAdvisor] = $this->engagement(NpoLegalStructure::IncorporatedSociety, isa2022Reregistered: true);
        $this->governanceResponse($societyEngagement, $societyAdvisor);

        $societyFindings = app(GovernanceReviewAnalyzer::class)->run($societyEngagement, $societyAdvisor);
        $societyCriteria = $this->criteriaText($societyFindings->firstWhere('finding_key', 'legal_structure_compliance'));

        $this->assertStringContainsString('incorporated societies act 2022', $societyCriteria);
    }

    public function test_unacknowledged_compliance_alert_blocks_until_advisor_acknowledgement(): void
    {
        [$engagement, $advisor] = $this->engagement(NpoLegalStructure::IncorporatedSociety, isa2022Reregistered: false);
        $this->governanceResponse($engagement, $advisor);
        $alert = NpoComplianceAlert::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'type' => NpoComplianceAlert::TYPE_ISA_2022_REREGISTRATION_MISSING,
            'severity' => NpoComplianceAlert::SEVERITY_CRITICAL,
            'message' => 'ISA 2022 re-registration is missing.',
            'source' => 'test',
            'metadata' => ['society_number' => '500099'],
            'triggered_at' => now(),
        ]);

        try {
            app(GovernanceReviewAnalyzer::class)->run($engagement, $advisor);
            $this->fail('Governance analysis should be blocked while the compliance alert is unacknowledged.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('NPO compliance alerts must be acknowledged', $exception->getMessage());
        }
        $this->assertSame(0, $this->ai->analyseCalls);

        app(NpoComplianceLookup::class)->acknowledge($alert, $advisor);

        $findings = app(GovernanceReviewAnalyzer::class)->run($engagement, $advisor);
        $legalFinding = $findings->firstWhere('finding_key', 'legal_structure_compliance');

        $this->assertInstanceOf(GovernanceReviewFinding::class, $legalFinding);
        $this->assertSame('critical', $legalFinding->severity->value);
        $this->assertSame(1, $this->ai->analyseCalls);
    }

    /**
     * @return array{0: NpoEngagement, 1: User}
     */
    private function engagement(NpoLegalStructure $structure, ?bool $isa2022Reregistered = null): array
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => fake()->company(),
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->getKey(),
            'sub_type' => NpoEngagementSubType::GovernanceReview,
            'legal_structure' => $structure,
            'isa_2022_reregistered' => $isa2022Reregistered,
        ]);

        return [$engagement, $advisor];
    }

    private function governanceResponse(NpoEngagement $engagement, User $advisor, bool $paidStaff = false): QuestionnaireResponse
    {
        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::GOVERNANCE_REVIEW)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $engagement->client_id,
            'npo_engagement_id' => $engagement->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $advisor->getKey(),
        ]);

        foreach ($questionnaire->sections as $section) {
            foreach ($section->questions as $question) {
                $response->answers()->create([
                    'question_id' => $question->id,
                    'value' => $this->answerValue($question, $paidStaff),
                    'attached_document_ids' => $question->type === QuestionnaireQuestionType::FILE_ATTACH
                        ? [(string) Str::uuid()]
                        : [],
                ]);
            }
        }

        return $response->refresh()->load('answers.question.section');
    }

    private function answerValue(QuestionnaireQuestion $question, bool $paidStaff): mixed
    {
        $prompt = strtolower($question->prompt);

        if (str_contains($prompt, 'employ paid staff')) {
            return $paidStaff ? 'yes' : 'no';
        }

        if (str_contains($prompt, 'paid-staff oversight')) {
            return $paidStaff ? 'Payroll is reviewed monthly with no known remediation issue.' : null;
        }

        return match ($question->type) {
            QuestionnaireQuestionType::TEXT,
            QuestionnaireQuestionType::LONG_TEXT => 'Governance evidence is available for advisor review.',
            QuestionnaireQuestionType::NUMBER,
            QuestionnaireQuestionType::CURRENCY => 4,
            QuestionnaireQuestionType::DATE => now()->subMonths(6)->toDateString(),
            QuestionnaireQuestionType::SINGLE_SELECT,
            QuestionnaireQuestionType::LIKERT => (string) ($question->options[0]['value'] ?? ''),
            QuestionnaireQuestionType::MULTI_SELECT => [(string) ($question->options[0]['value'] ?? '')],
            QuestionnaireQuestionType::FILE_ATTACH => null,
        };
    }

    private function criteriaText(?GovernanceReviewFinding $finding): string
    {
        $this->assertInstanceOf(GovernanceReviewFinding::class, $finding);

        return strtolower(json_encode($finding->criteria, JSON_THROW_ON_ERROR));
    }
}

final class CapturingGovernanceAiClient implements AiClient
{
    public int $analyseCalls = 0;

    public ?PromptEnvelope $lastPrompt = null;

    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        $this->analyseCalls++;
        $this->lastPrompt = $prompt;

        return new AiResponse(
            text: 'Governance evidence includes board, constitution, COI, and financial oversight signals for advisor review.',
            attributions: [
                [
                    'claim' => 'Governance evidence is supplied for advisor review.',
                    'source_reference' => $prompt->sourceReferences[0] ?? 'npo_engagement:test',
                ],
            ],
            uncertainty: Uncertainty::Medium,
            biasSignals: [],
            model: 'governance-test-ai',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 42,
            tokensOut: 18,
            metadata: ['response_id' => 'wo-n04-test'],
        );
    }

    public function verifyDocument(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function scoreCriterion(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function summarise(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }

    public function redFlag(PromptEnvelope $prompt): AiResponse
    {
        return $this->analyse($prompt);
    }
}
