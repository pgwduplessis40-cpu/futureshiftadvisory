<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Enums\AnalysisLens;
use App\Enums\AnalysisModule as AnalysisModuleEnum;
use App\Enums\EngagementType;
use App\Enums\FindingSeverity;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\CommunicationPreference;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\RedFlag;
use App\Models\User;
use App\Services\Ai\Contracts\AiClient;
use App\Services\Ai\Contracts\AiResponse;
use App\Services\Ai\Contracts\PromptEnvelope;
use App\Services\Ai\Contracts\Uncertainty;
use App\Services\Ai\Prompts\PromptRegistry;
use App\Services\Analysis\AnalysisFindingData;
use App\Services\Analysis\AnalysisRunner;
use App\Services\Analysis\Contracts\AnalysisModule;
use App\Services\Analysis\RedFlagPromoter;
use App\Services\DataQuality\DataQualityScore;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RedFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);

        $registry = app(PromptRegistry::class);
        $registry->register(
            id: CriticalFindingModule::PROMPT_ID,
            version: '2026-05-wo34-test',
            body: 'Produce a governed critical finding from the supplied client facts.',
            task: 'analyse',
        );
        $this->app->instance(PromptRegistry::class, $registry);
    }

    public function test_critical_finding_promotes_to_red_flag_and_urgent_notification_without_duplicates(): void
    {
        Mail::fake();

        $superAdmin = $this->superAdmin();
        $advisor = $this->advisor('red-flag-advisor@example.test');
        $advisor->communicationPreference()->create([
            'channel' => CommunicationPreference::CHANNEL_IN_PLATFORM_ONLY,
            'frequency' => CommunicationPreference::FREQUENCY_WEEKLY,
            'timezone' => 'Pacific/Auckland',
        ]);
        $client = $this->clientWithQuestionnaireResponse($advisor);
        $this->app->instance(AiClient::class, new RedFlagAnalysisAiClient);

        $run = app(AnalysisRunner::class)->run($client, new CriticalFindingModule);
        $finding = $run->findings()->firstOrFail();
        $redFlag = RedFlag::query()->firstOrFail();

        $this->assertSame(FindingSeverity::Critical, $finding->severity);
        $this->assertSame($finding->id, $redFlag->analysis_finding_id);
        $this->assertSame($client->id, $redFlag->client_id);
        $this->assertSame(RedFlag::CATEGORY_FINANCIAL, $redFlag->category);
        $this->assertSame(FindingSeverity::Critical->value, $redFlag->severity);

        $advisorNotification = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $advisor->id)
            ->first();
        $this->assertNotNull($advisorNotification);
        $this->assertSame('urgent', $advisorNotification->urgency);

        $decision = $this->decision($advisorNotification->channel_decision);
        $this->assertTrue($decision['bypassed_preference']);
        $this->assertTrue($decision['mail_now']);
        $this->assertContains('mail', $decision['channels']);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $superAdmin->id,
            'type' => 'analysis.red_flag.created',
            'urgency' => 'urgent',
        ]);

        app(RedFlagPromoter::class)->promoteFinding($finding);

        $this->assertDatabaseCount('red_flags', 1);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_red_flag_acknowledge_and_resolve_are_audited(): void
    {
        $advisor = $this->advisor('red-flag-flow@example.test');
        $client = $this->clientFor($advisor, 'Red Flag Flow Limited');
        $redFlag = RedFlag::query()->create([
            'client_id' => $client->id,
            'source_type' => 'analysis_finding',
            'source_key' => 'manual-red-flag-test',
            'category' => RedFlag::CATEGORY_VIABILITY,
            'severity' => FindingSeverity::Critical->value,
            'headline' => 'Critical viability pressure',
            'detail' => 'The finding needs immediate advisor review.',
            'surfaced_at' => now(),
        ]);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.red-flags.acknowledge', $redFlag))
            ->assertRedirect();

        $redFlag->refresh();
        $this->assertNotNull($redFlag->acknowledged_at);
        $this->assertSame($advisor->id, $redFlag->acknowledged_by_user_id);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'red_flag.acknowledged',
            'subject_type' => RedFlag::class,
            'subject_id' => $redFlag->id,
            'client_id' => $client->id,
        ]);

        $this->actingAsMfa($advisor)
            ->patch(route('advisor.red-flags.resolve', $redFlag))
            ->assertRedirect();

        $redFlag->refresh();
        $this->assertNotNull($redFlag->resolved_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'red_flag.resolved',
            'subject_type' => RedFlag::class,
            'subject_id' => $redFlag->id,
            'client_id' => $client->id,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ]);
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        return $user;
    }

    private function advisor(string $email): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'email' => $email,
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientFor(User $advisor, string $name): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $advisor->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '942900'.random_int(1000000, 9999999),
            'legal_name' => $name,
            'data_quality' => Client::DATA_QUALITY_LOW,
            'created_by_user_id' => $advisor->getKey(),
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $advisor->getKey(),
            'role' => 'lead_advisor',
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    private function clientWithQuestionnaireResponse(User $advisor): Client
    {
        $client = $this->clientFor($advisor, 'Critical Red Flag Limited');
        [$questionnaire, $question] = $this->questionnaireWithQuestion();

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $advisor->getKey(),
        ]);

        $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Cash runway is under pressure and creditors are overdue.',
            'attached_document_ids' => [],
        ]);

        return $client;
    }

    /**
     * @return array{0: Questionnaire, 1: QuestionnaireQuestion}
     */
    private function questionnaireWithQuestion(): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo34-'.Str::lower(Str::random(8)),
            'title' => 'WO-34 Analysis Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Trading',
        ]);

        $question = $section->questions()->create([
            'order' => 1,
            'type' => QuestionnaireQuestionType::TEXT,
            'prompt' => 'What is the current trading position?',
            'required' => true,
        ]);

        return [$questionnaire, $question];
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }
}

final class CriticalFindingModule implements AnalysisModule
{
    public const PROMPT_ID = 'analysis.critical-red-flag-demo';

    public function module(): AnalysisModuleEnum
    {
        return AnalysisModuleEnum::Financial;
    }

    public function promptId(): string
    {
        return self::PROMPT_ID;
    }

    public function promptInput(Client $client, DataQualityScore $score): array
    {
        return [
            'client' => [
                'id' => $client->id,
                'legal_name' => $client->legal_name,
            ],
            'data_quality_level' => $score->level,
        ];
    }

    public function sourceReferences(Client $client, DataQualityScore $score): array
    {
        return [
            'questionnaire:wo34-red-flag',
            'client:'.$client->id,
        ];
    }

    public function mapFindings(Client $client, AiResponse $response, DataQualityScore $score): array
    {
        return [
            new AnalysisFindingData(
                lens: AnalysisLens::Diagnostic,
                severity: FindingSeverity::Critical,
                title: 'Cash runway is critically constrained',
                body: $response->text,
            ),
        ];
    }
}

final class RedFlagAnalysisAiClient implements AiClient
{
    public function analyse(PromptEnvelope $prompt): AiResponse
    {
        return new AiResponse(
            text: 'Cash runway appears critically constrained and requires immediate advisor review.',
            attributions: [
                [
                    'claim' => 'Cash runway is under pressure.',
                    'source_reference' => 'questionnaire:wo34-red-flag',
                ],
            ],
            uncertainty: Uncertainty::Low,
            biasSignals: [],
            model: 'red-flag-analysis-test',
            promptVersion: $prompt->version,
            promptHash: $prompt->hash(),
            tokensIn: 20,
            tokensOut: 10,
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
