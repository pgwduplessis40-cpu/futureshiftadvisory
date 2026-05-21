<?php

declare(strict_types=1);

namespace Tests\Feature\DataQuality;

use App\Enums\EngagementType;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Jobs\RecomputeDataQualityScore;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Ai\Verification\DocumentVerifier;
use App\Services\DataQuality\DataQualityInsufficientException;
use App\Services\DataQuality\DataQualityScorer;
use App\Services\DataQuality\Gate as DataQualityGate;
use App\Services\Questionnaires\QuestionnaireResponseRecorder;
use App\Services\Storage\SecureFileWriter;
use App\Support\RequestContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DataQualityGateTest extends TestCase
{
    use DatabaseMigrations;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/data-quality');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');

        $this->seed(RoleSeeder::class);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_questionnaire_submission_queues_recompute_and_job_updates_the_client_level(): void
    {
        Queue::fake([RecomputeDataQualityScore::class]);
        [$user, $client] = $this->clientUserWithClient();
        [$questionnaire, $question] = $this->questionnaireWithQuestions(['What is the current revenue?']);

        app(QuestionnaireResponseRecorder::class)->record($client, $user, $questionnaire, [
            'answers' => [
                $question->id => [
                    'value' => 'Revenue is 100.',
                    'attached_document_ids' => [],
                ],
            ],
        ]);

        Queue::assertPushed(RecomputeDataQualityScore::class, fn (RecomputeDataQualityScore $job): bool => $job->clientId === $client->id);

        $this->runRecompute($client);

        $this->assertSame(Client::DATA_QUALITY_LOW, $client->refresh()->data_quality);

        $payload = app(DataQualityScorer::class)->score($client)->toPayload();
        $this->assertSame(Client::DATA_QUALITY_LOW, $payload['level']);
        $this->assertCount(4, $payload['components']);
        $this->assertSame('Questionnaire completeness', $payload['components'][0]['label']);
    }

    public function test_document_verification_queues_recompute_and_can_lift_quality_to_high(): void
    {
        Queue::fake([RecomputeDataQualityScore::class]);
        [$user, $client] = $this->clientUserWithClient();
        [$questionnaire, $question] = $this->questionnaireWithQuestions(['What revenue should this document support?']);
        $document = $this->documentFor($client, $user, 'Revenue is 100.');

        $response = QuestionnaireResponse::query()->create([
            'client_id' => $client->id,
            'questionnaire_id' => $questionnaire->id,
            'submitted_at' => now(),
            'submitted_by_user_id' => $user->getKey(),
        ]);
        $answer = $response->answers()->create([
            'question_id' => $question->id,
            'value' => 'Revenue is 100.',
            'attached_document_ids' => [$document->id],
        ]);

        $verification = app(DocumentVerifier::class)->verify($document, [
            'source' => 'questionnaire_answer',
            'questionnaire_response_id' => $response->id,
            'questionnaire_answer_id' => $answer->id,
            'questionnaire_question_id' => $question->id,
            'question_prompt' => $question->prompt,
            'claim' => 'Revenue is 100.',
        ]);

        $this->assertSame(DocumentVerification::OUTCOME_VERIFIED, $verification->outcome);
        Queue::assertPushed(RecomputeDataQualityScore::class, fn (RecomputeDataQualityScore $job): bool => $job->clientId === $client->id);

        $this->runRecompute($client);

        $this->assertSame(Client::DATA_QUALITY_HIGH, $client->refresh()->data_quality);
    }

    public function test_gate_blocks_insufficient_quality_and_profile_payload_explains_the_fix(): void
    {
        $advisor = $this->advisor();
        $client = $this->clientFor($advisor, User::TYPE_ADVISOR, 'lead_advisor');

        $this->actingAsMfa($advisor)
            ->get(route('advisor.clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('advisor/clients/Show')
                ->where('client.data_quality_summary.level', Client::DATA_QUALITY_INSUFFICIENT)
                ->where('client.data_quality_summary.message', 'Improve data first: complete the questionnaire and verify supporting documents before analysis runs.')
                ->has('client.data_quality_summary.components', 4));

        try {
            app(DataQualityGate::class)->assertSufficient($client);
            $this->fail('Expected insufficient data quality to block the gate.');
        } catch (DataQualityInsufficientException $e) {
            $this->assertStringStartsWith('Improve data first:', $e->getMessage());
        }
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function clientUserWithClient(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        $client = $this->clientFor($user, User::TYPE_CLIENT_PRIMARY, 'primary_contact');

        return [$user, $client];
    }

    private function advisor(): User
    {
        $advisor = User::factory()->withTwoFactor()->create([
            'user_type' => User::TYPE_ADVISOR,
            'primary_role' => User::TYPE_ADVISOR,
        ]);
        $advisor->assignRole(User::TYPE_ADVISOR);

        return $advisor;
    }

    private function clientFor(User $user, string $role, string $teamRole): Client
    {
        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::STANDARD_ADVISORY,
            'nzbn' => '9429000000199',
            'legal_name' => 'Data Quality Test Limited',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $role === User::TYPE_CLIENT_PRIMARY ? $user->getKey() : null,
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => $teamRole,
            'granted_modules' => [EngagementType::STANDARD_ADVISORY->value],
        ]);

        return $client;
    }

    /**
     * @param  array<int, string>  $prompts
     * @return array{0: Questionnaire, 1: QuestionnaireQuestion}
     */
    private function questionnaireWithQuestions(array $prompts): array
    {
        $questionnaire = Questionnaire::query()->create([
            'set' => QuestionnaireSet::STANDARD_ADVISORY,
            'version' => 'wo19-'.count($prompts).'-'.str_replace('.', '', (string) microtime(true)),
            'title' => 'WO-19 Data Quality Questionnaire',
            'published_at' => now(),
        ]);

        $section = $questionnaire->sections()->create([
            'order' => 1,
            'title' => 'Trading',
        ]);

        $first = null;
        foreach ($prompts as $index => $prompt) {
            $question = $section->questions()->create([
                'order' => $index + 1,
                'type' => QuestionnaireQuestionType::TEXT,
                'prompt' => $prompt,
                'required' => true,
            ]);

            $first ??= $question;
        }

        $this->assertInstanceOf(QuestionnaireQuestion::class, $first);

        return [$questionnaire, $first];
    }

    private function documentFor(Client $client, User $user, string $content): Document
    {
        return app(SecureFileWriter::class)->write(
            uploadedFile: UploadedFile::fake()->createWithContent('support.txt', $content),
            owner: $user,
            category: Document::CATEGORY_FINANCIAL_STATEMENT,
            clientId: (string) $client->getKey(),
        );
    }

    private function runRecompute(Client $client): void
    {
        (new RecomputeDataQualityScore((string) $client->getKey()))->handle(
            app(DataQualityScorer::class),
            app(RequestContext::class),
        );
    }
}
