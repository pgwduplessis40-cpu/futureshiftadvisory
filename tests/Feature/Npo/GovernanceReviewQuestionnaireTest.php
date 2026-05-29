<?php

declare(strict_types=1);

namespace Tests\Feature\Npo;

use App\Enums\EngagementType;
use App\Enums\NpoEngagementSubType;
use App\Enums\NpoLegalStructure;
use App\Enums\QuestionnaireQuestionType;
use App\Enums\QuestionnaireSet;
use App\Jobs\VerifyDocumentJob;
use App\Models\Client;
use App\Models\ClientTeamMember;
use App\Models\Document;
use App\Models\DocumentVerification;
use App\Models\NpoEngagement;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\TermsVersion;
use App\Models\User;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\ScanResult;
use App\Services\Portal\OnboardingWizard;
use App\Services\Questionnaires\QuestionnairePayload;
use App\Support\RequestContext;
use Database\Seeders\GovernanceReviewQuestionnaireSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GovernanceReviewQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private string $secureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secureRoot = storage_path('framework/testing/npo-governance-review');
        File::deleteDirectory($this->secureRoot);
        Config::set('filesystems.disks.secure_local.root', $this->secureRoot);
        Storage::forgetDisk('secure_local');

        $this->seed([RoleSeeder::class, GovernanceReviewQuestionnaireSeeder::class]);
        app(RequestContext::class)->apply('system', []);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('secure_local');
        File::deleteDirectory($this->secureRoot);

        parent::tearDown();
    }

    public function test_governance_review_set_is_seeded_admin_managed_and_versioned(): void
    {
        $questionnaire = Questionnaire::query()
            ->forSet(QuestionnaireSet::GOVERNANCE_REVIEW)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();

        $requiredUploads = $questionnaire->sections
            ->flatMap->questions
            ->filter(fn (QuestionnaireQuestion $question): bool => $question->type === QuestionnaireQuestionType::FILE_ATTACH
                && $question->required)
            ->values();

        $this->assertSame('Governance Review Questionnaire', $questionnaire->title);
        $this->assertCount(5, $questionnaire->sections);
        $this->assertCount(4, $requiredUploads);
        $this->assertSame([
            'Attach the constitution, rules, or trust deed.',
            'Attach the latest financial statements.',
            'Attach the current board register.',
            'Attach the conflicts of interest register.',
        ], $requiredUploads->pluck('prompt')->all());

        $admin = $this->superAdmin();

        $this->actingAsMfa($admin)
            ->post(route('admin.questionnaires.store'), ['set' => QuestionnaireSet::GOVERNANCE_REVIEW->value])
            ->assertRedirect();

        $draft = Questionnaire::query()
            ->where('set', QuestionnaireSet::GOVERNANCE_REVIEW->value)
            ->where('version', '2')
            ->whereNull('published_at')
            ->with('sections.questions')
            ->firstOrFail();

        $payload = app(QuestionnairePayload::class)->schema($draft);
        $sections = $payload['sections'];
        $sections[0]['title'] = 'Updated Organisation Context';

        $this->actingAsMfa($admin)
            ->put(route('admin.questionnaires.update', $draft), [
                'set' => $payload['set'],
                'version' => '2',
                'title' => 'Updated Governance Review',
                'sections' => $sections,
            ])
            ->assertRedirect(route('admin.questionnaires.edit', $draft, absolute: false));

        $this->assertSame('Updated Governance Review', $draft->refresh()->title);
        $this->assertSame('Updated Organisation Context', $draft->sections()->orderBy('order')->firstOrFail()->title);
    }

    public function test_portal_completion_stamps_response_with_governance_engagement(): void
    {
        Queue::fake();

        [$user, $client, $engagement] = $this->npoClientUser();
        $questionnaire = $this->governanceQuestionnaire();

        $this->actingAsMfa($user)
            ->post(route('portal.onboarding.store', ['step' => OnboardingWizard::STEP_QUESTIONNAIRE]), [
                'answers' => $this->answersFor($questionnaire),
            ])
            ->assertRedirect(route('portal.onboarding.step', [
                'step' => OnboardingWizard::STEP_DOCUMENTS,
            ], absolute: false));

        $response = $questionnaire->responses()->firstOrFail();
        $state = $client->refresh()->onboarding_wizard_state;

        $this->assertSame($client->id, $response->client_id);
        $this->assertSame($engagement->id, $response->npo_engagement_id);
        $this->assertSame($engagement->id, $state['steps'][OnboardingWizard::STEP_QUESTIONNAIRE]['npo_engagement_id']);
        $this->assertContains(OnboardingWizard::STEP_QUESTIONNAIRE, $state['completed_steps']);
        Queue::assertPushed(VerifyDocumentJob::class);
    }

    public function test_governance_uploads_are_scanned_persisted_and_verified(): void
    {
        $scanner = new class implements FileScanner
        {
            public int $calls = 0;

            public function scan(mixed $stream): ScanResult
            {
                $this->calls++;

                return ScanResult::clean(['engine' => 'npo-governance-test-scanner']);
            }
        };
        $this->app->instance(FileScanner::class, $scanner);

        [$user, $client] = $this->npoClientUser();
        $question = $this->governanceQuestionnaire()
            ->sections
            ->flatMap->questions
            ->first(fn (QuestionnaireQuestion $question): bool => $question->type === QuestionnaireQuestionType::FILE_ATTACH);

        $this->assertInstanceOf(QuestionnaireQuestion::class, $question);

        $response = $this->actingAsMfa($user)
            ->withHeader('Accept', 'application/json')
            ->post(route('portal.documents.store'), [
                'file' => UploadedFile::fake()->createWithContent('constitution.pdf', "%PDF-1.4\nThe board meets monthly and records conflicts of interest."),
                'category' => Document::CATEGORY_COMPLIANCE_DOC,
                'question_id' => $question->id,
                'question_prompt' => $question->prompt,
                'claim_value' => 'The board meets monthly and records conflicts of interest.',
            ]);

        $response->assertCreated();

        $documentId = $response->json('document.id');
        $this->assertSame(1, $scanner->calls);
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'client_id' => $client->id,
            'category' => Document::CATEGORY_COMPLIANCE_DOC,
            'scanner_result' => Document::SCANNER_CLEAN,
        ]);
        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $documentId,
            'questionnaire_question_id' => $question->id,
            'outcome' => DocumentVerification::OUTCOME_VERIFIED,
        ]);
    }

    private function governanceQuestionnaire(): Questionnaire
    {
        return Questionnaire::query()
            ->forSet(QuestionnaireSet::GOVERNANCE_REVIEW)
            ->published()
            ->with('sections.questions')
            ->firstOrFail();
    }

    /**
     * @return array{0: User, 1: Client, 2: NpoEngagement}
     */
    private function npoClientUser(): array
    {
        $user = User::factory()->withTwoFactor()->create([
            'name' => 'NPO Client Owner',
            'email' => fake()->unique()->safeEmail(),
            'user_type' => User::TYPE_CLIENT_PRIMARY,
            'primary_role' => User::TYPE_CLIENT_PRIMARY,
        ]);
        $user->assignRole(User::TYPE_CLIENT_PRIMARY);

        app(RequestContext::class)->apply('system', [], (string) $user->getKey());

        $client = Client::query()->create([
            'engagement_type' => EngagementType::NPO,
            'nzbn' => fake()->unique()->numerify('9429#########'),
            'legal_name' => 'Community Governance Trust',
            'entity_type' => 'Charitable Trust Board',
            'data_quality' => Client::DATA_QUALITY_INSUFFICIENT,
            'primary_contact_user_id' => $user->getKey(),
            'onboarding_wizard_state' => [
                'current_step' => 5,
                'completed_steps' => [
                    OnboardingWizard::STEP_WELCOME,
                    OnboardingWizard::STEP_IDENTITY,
                    OnboardingWizard::STEP_BUSINESS_SNAPSHOT,
                    OnboardingWizard::STEP_GOALS,
                ],
                'steps' => [],
            ],
        ]);

        ClientTeamMember::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->getKey(),
            'role' => 'primary_contact',
            'granted_modules' => [EngagementType::NPO->value],
        ]);

        $engagement = NpoEngagement::query()->create([
            'client_id' => $client->getKey(),
            'sub_type' => NpoEngagementSubType::GovernanceReview,
            'legal_structure' => NpoLegalStructure::RegisteredCharity,
        ]);

        return [$user, $client, $engagement];
    }

    /**
     * @return array<string, array{value:mixed, attached_document_ids:array<int, string>}>
     */
    private function answersFor(Questionnaire $questionnaire): array
    {
        $answers = [];

        foreach ($questionnaire->sections as $section) {
            foreach ($section->questions as $question) {
                $answers[$question->id] = match ($question->type) {
                    QuestionnaireQuestionType::TEXT,
                    QuestionnaireQuestionType::LONG_TEXT => [
                        'value' => 'Governance review answer for '.$section->title,
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::NUMBER,
                    QuestionnaireQuestionType::CURRENCY => [
                        'value' => 3,
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::DATE => [
                        'value' => now()->toDateString(),
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::SINGLE_SELECT,
                    QuestionnaireQuestionType::LIKERT => [
                        'value' => (string) ($question->options[0]['value'] ?? ''),
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::MULTI_SELECT => [
                        'value' => [(string) ($question->options[0]['value'] ?? '')],
                        'attached_document_ids' => [],
                    ],
                    QuestionnaireQuestionType::FILE_ATTACH => [
                        'value' => null,
                        'attached_document_ids' => [(string) Str::uuid()],
                    ],
                };
            }
        }

        return $answers;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->withTwoFactor()->create();
        $user->assignRole(User::TYPE_SUPER_ADMIN);

        TermsVersion::query()->delete();

        return $user;
    }
}
